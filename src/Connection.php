<?php

declare(strict_types=1);

namespace ws;

use pocketmine\utils\Binary;
use ws\ipc\Protocol;
use ws\ipc\Sender;
use ws\utils\InternetAddress;
use function chr;

class Connection{
	/** @var int */
	private $id;
	/** @var InternetAddress */
	private $address;
	/** @var Sender */
	private $sender;

	public function __construct(int $id, InternetAddress $address, Sender $sender){
		$this->id = $id;
		$this->address = $address;
		$this->sender = $sender;
	}

	public function getAddress() : InternetAddress{
		return $this->address;
	}

	public function send(string $message, bool $broadcast = false) : void{
		$this->sender->send(chr(Protocol::MESSAGE) . Binary::writeLInt($this->id) . chr($broadcast ? 1 : 0) . $message);
	}

	public function close(string $reason = "connection closed") : void{
		$this->sender->send(chr(Protocol::DISCONNECT) . Binary::writeLInt($this->id) . $reason);
	}
}
