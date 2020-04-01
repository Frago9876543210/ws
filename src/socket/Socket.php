<?php

declare(strict_types=1);

namespace ws\socket;

use ws\utils\InternetAddress;
use function socket_accept;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_listen;
use function socket_read;
use function socket_select;
use function socket_set_option;
use function socket_write;
use function strlen;
use const AF_INET;
use const SO_REUSEADDR;
use const SOCK_STREAM;
use const SOL_SOCKET;
use const SOL_TCP;

class Socket{
	/** @var InternetAddress */
	private $bindAddress;
	/** @var resource */
	private $socket;

	/**
	 * @param InternetAddress $bindAddress
	 * @throws SocketException
	 */
	public function __construct(InternetAddress $bindAddress){
		$this->bindAddress = $bindAddress;

		if(($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false){
			throw SocketException::wrap("Failed to create socket");
		}
		$this->socket = $socket;

		socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

		if(!@socket_bind($this->socket, $bindAddress->getAddress(), $bindAddress->getPort())){
			throw SocketException::wrap("Failed to bind to " . $bindAddress, $this->socket);
		}

		if(!socket_listen($this->socket)){
			throw SocketException::wrap("Failed to listen socket", $this->socket);
		}
	}

	/**
	 * @return resource
	 */
	public function getSocket(){
		return $this->socket;
	}

	/**
	 * @return resource
	 * @throws SocketException
	 */
	public function accept(){
		$result = socket_accept($this->socket);
		if($result === false){
			throw SocketException::wrap("Failed to accept connection", $this->socket);
		}
		return $result;
	}

	/**
	 * @param resource $socket
	 * @param int      $length
	 * @return string|null
	 */
	public function read($socket, int $length) : ?string{
		$result = socket_read($socket, $length);
		if($result === false || strlen($result) === 0){
			return null;
		}
		return $result;
	}

	/**
	 * @param resource $socket
	 * @param string   $buffer
	 * @throws SocketException
	 */
	public function write($socket, string $buffer) : void{
		if(socket_write($socket, $buffer) === false){
			throw SocketException::wrap("Failed to write data", $this->socket);
		}
	}

	/**
	 * @param resource[] $connections
	 * @param int        $seconds
	 * @param int        $microseconds
	 * @return resource[]
	 * @throws SocketException
	 */
	public function select(array $connections, int $seconds, int $microseconds) : array{
		$r = $connections;
		$r[] = $this->socket;

		$w = null;
		$e = null;

		if(socket_select($r, $w, $e, $seconds, $microseconds) === false){
			throw SocketException::wrap("Failed to select", $this->socket);
		}

		return $r;
	}

	public function close() : void{
		socket_close($this->socket);
	}
}
