<?php

declare(strict_types=1);

namespace ws\ipc;

use parallel\Channel;

class Sender{
	/** @var Channel */
	private $channel;

	public function __construct(){
		$this->channel = new Channel(Channel::Infinite);
	}

	public function getChannel() : Channel{
		return $this->channel;
	}

	public function send(string $value) : void{
		$this->channel->send($value);
	}
}
