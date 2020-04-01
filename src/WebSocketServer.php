<?php

declare(strict_types=1);

namespace ws;

use ErrorUtils;
use InvalidArgumentException;
use parallel\Channel;
use parallel\Events;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use ws\ipc\Protocol;
use ws\ipc\Sender;
use ws\protocol\Frame;
use ws\protocol\Opcode;
use ws\socket\Socket;
use ws\socket\SocketException;
use ws\utils\Http;
use ws\utils\InternetAddress;
use function base64_encode;
use function microtime;
use function sha1;
use function socket_close;
use function socket_getpeername;
use function socket_set_nonblock;
use function socket_set_option;
use function socket_shutdown;
use const SO_KEEPALIVE;
use const SOL_SOCKET;

/**
 * @internal
 */
class WebSocketServer{
	private const MAGIC_GUID = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";

	/** @var Socket */
	private $socket;
	/** @var string */
	private $route;
	/** @var EventListener */
	private $listener;
	/** @var Sender */
	private $sender;

	/** @var Events<Channel> */
	private $events;

	/** @var BinaryStream */
	private $stream;

	/** @var resource[] $connections */
	private $connections = [];
	/** @var bool[] $handshakes */
	private $handshakes = [];
	/** @var float[] $timeouts */
	private $timeouts = [];

	/** @var resource[] $disconnect */
	private $disconnect = [];

	/** @var Connection[] $wsConnections */
	private $wsConnections = [];

	/** @var int $nextConnectionId */
	private $nextConnectionId = 0;

	/**
	 * @param InternetAddress $bindAddress
	 * @param string          $route
	 * @param string          $listenerClass
	 * @param Sender          $sender
	 * @phpstan-param class-string<EventListener> $listenerClass
	 */
	public function __construct(InternetAddress $bindAddress, string $route, string $listenerClass, Sender $sender){
		ErrorUtils::setErrorExceptionHandler();

		if(!Http::validateRoute($route)){
			throw new InvalidArgumentException("Invalid route provided");
		}

		$this->socket = new Socket($bindAddress);
		$this->route = $route;
		$this->listener = new $listenerClass();
		$this->sender = $sender;

		$this->events = new Events();
		$this->events->addChannel($this->sender->getChannel());
		$this->events->setBlocking(false);

		$this->stream = new BinaryStream();
	}

	public function tick() : void{
		$this->disconnect = [];

		try{
			foreach($this->socket->select($this->connections, 0, 20000) as $id => $connection){
				if($connection === $this->socket->getSocket()){
					$this->acceptIncomingConnection();
				}else{
					$this->handleExistingConnection($connection, $id);
				}
			}
		}catch(SocketException $e){
			echo $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
		}

		$this->dropInactiveConnections();
		$this->processInternalMessages();
	}

	private function acceptIncomingConnection() : void{
		$incomingConnection = $this->socket->accept();

		socket_set_nonblock($incomingConnection);
		socket_set_option($incomingConnection, SOL_SOCKET, SO_KEEPALIVE, 1);

		$connectionId = $this->nextConnectionId++;

		$this->connections[$connectionId] = $incomingConnection;
		$this->handshakes[$connectionId] = false;
		$this->timeouts[$connectionId] = microtime(true) + 5.0;
	}

	/**
	 * @param resource $connection
	 * @param int      $id
	 */
	private function handleExistingConnection($connection, int $id) : void{
		$data = $this->socket->read($connection, 1024);

		if($data === null){
			$this->disconnect[$id] = $connection;
		}elseif($this->handshakes[$id]){
			$this->processMessage($data, $connection, $id);
		}else{
			$this->processHandshake($data, $connection, $id);
		}
	}

	private function dropInactiveConnections() : void{
		foreach($this->handshakes as $id => $handshake){
			if(!$handshake && !isset($this->disconnect[$id]) && $this->timeouts[$id] < microtime(true)){
				$this->disconnect[$id] = $this->connections[$id];
			}
		}

		foreach($this->disconnect as $id => $connection){
			if(isset($this->wsConnections[$id])){
				$this->listener->onDisconnect($this->wsConnections[$id]);
			}

			@socket_shutdown($connection, 2);
			@socket_close($connection);

			unset($this->connections[$id], $this->handshakes[$id], $this->timeouts[$id], $this->wsConnections[$id]);
		}
	}

	private function processInternalMessages() : void{
		while(($event = $this->events->poll()) !== null){
			/** @var string $data */
			$data = $event->value;

			$this->stream->setBuffer($data);

			$header = $this->stream->getByte();
			$id = $this->stream->getLInt();

			$connection = $this->connections[$id];

			switch($header){
				case Protocol::MESSAGE:
					$broadcast = $this->stream->getBool();
					$message = $this->stream->getRemaining();

					$this->stream->reset();
					$buffer = Frame::create($message)->write($this->stream);

					if($broadcast){
						foreach($this->connections as $targetId => $targetConnection){
							if($targetId !== $id){
								$this->socket->write($targetConnection, $buffer);
							}
						}
					}else{
						$this->socket->write($connection, $buffer);
					}
					break;
				case Protocol::DISCONNECT:
					$reason = $this->stream->getRemaining();

					$this->stream->reset();
					$buffer = Frame::create("\x03\xe8" . $reason, Opcode::CLOSE)->write($this->stream);

					$this->socket->write($connection, $buffer);
					$this->disconnect[$id] = $connection;
					break;
			}

			$this->events->addChannel($this->sender->getChannel());
		}
	}

	/**
	 * @param string   $data
	 * @param resource $connection
	 * @param int      $id
	 */
	private function processHandshake(string $data, $connection, int $id) : void{
		try{
			$headers = Http::parseHeaders($data, $this->route);

			$clientKey = $headers["Sec-WebSocket-Key"];
			$key = base64_encode(sha1($clientKey . self::MAGIC_GUID, true));

			$this->socket->write($connection,
				"HTTP/1.1 101 Switching Protocols\r\n" .
				"Upgrade: websocket\r\n" .
				"Connection: Upgrade\r\n" .
				"Sec-WebSocket-Accept: " . $key . "\r\n\r\n"
			);

			$this->handshakes[$id] = true;

			socket_getpeername($connection, $address, $port);

			$wsConnection = new Connection($id, new InternetAddress($address, $port), $this->sender);
			$this->wsConnections[$id] = $wsConnection;

			$this->listener->onConnect($wsConnection);
		}catch(InvalidArgumentException $e){
			$this->disconnect[$id] = $connection;
		}
	}

	/**
	 * @param string   $data
	 * @param resource $connection
	 * @param int      $id
	 */
	private function processMessage(string $data, $connection, int $id) : void{
		try{
			$this->stream->setBuffer($data);
			$frame = Frame::read($this->stream);
			switch($frame->opcode){ //TODO: implement ping, pong and fragmented messages
				case Opcode::TEXT:
				case Opcode::BINARY:
					$this->listener->onMessage($this->wsConnections[$id], $frame->payload);
					break;
				case Opcode::CLOSE:
					$this->disconnect[$id] = $connection;
					break;
			}
		}catch(BinaryDataException $e){
			$this->disconnect[$id] = $connection;
		}
	}
}
