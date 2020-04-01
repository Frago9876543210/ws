<?php

declare(strict_types=1);

namespace ws\utils;

use InvalidArgumentException;
use pocketmine\utils\Limits;

class InternetAddress{
	/** @var string */
	private $address;
	/** @var int */
	private $port;

	public function __construct(string $address, int $port){
		$this->address = $address;
		if($port < 0 or $port > Limits::UINT16_MAX){
			throw new InvalidArgumentException("Invalid port range");
		}
		$this->port = $port;
	}

	public function getAddress() : string{
		return $this->address;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function __toString() : string{
		return $this->address . " " . $this->port;
	}
}
