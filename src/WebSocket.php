<?php

declare(strict_types=1);

namespace ws;

use ErrorUtils;
use parallel\Channel;
use parallel\Events;
use parallel\Runtime;
use ws\ipc\Sender;
use ws\utils\InternetAddress;

class WebSocket{
	private const COMPOSER_AUTOLOADER_PATH = "vendor/autoload.php";

	/** @var Runtime */
	private $runtime;
	/** @var Channel */
	private $status;

	/**
	 * @param InternetAddress $bindAddress
	 * @param string          $route
	 * @param string          $listenerClass
	 * @phpstan-param class-string<EventListener> $listenerClass
	 */
	public function __construct(InternetAddress $bindAddress, string $route, string $listenerClass){
		ErrorUtils::setErrorExceptionHandler();

		$this->runtime = new Runtime(self::COMPOSER_AUTOLOADER_PATH);
		$this->status = new Channel();

		$this->runtime->run(function(Channel $status) use ($bindAddress, $route, $listenerClass) : void{
			$sender = new Sender();
			$server = new WebSocketServer($bindAddress, $route, $listenerClass, $sender);

			$loop = new Events(); //used just for shutdown method
			$loop->addChannel($status);
			$loop->setBlocking(false);

			while($loop->poll() === null){
				$server->tick();
			}
		}, [$this->status]);
	}

	public function shutdown() : void{
		$this->status->send(false);
	}

	public function __destruct(){
		$this->runtime->close();
		$this->status->close();
	}
}
