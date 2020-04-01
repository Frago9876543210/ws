<?php

declare(strict_types=1);

namespace ws\ipc;

class Protocol{

	private function __construct(){
		//NOOP
	}

	/**
	 * int32  id
	 * bool   broadcast
	 * byte[] message
	 */
	public const MESSAGE = 0;

	/**
	 * int32  id
	 * byte[] reason
	 */
	public const DISCONNECT = 1;
}
