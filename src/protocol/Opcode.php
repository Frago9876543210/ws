<?php

declare(strict_types=1);

namespace ws\protocol;

final class Opcode{

	private function __construct(){
		//NOOP
	}

	public const FRAGMENTED = 0x00;
	public const TEXT = 0x01;
	public const BINARY = 0x02;

	/**
	 * 0x03 - 0x07 are reserved for further non-control frames
	 */

	public const CLOSE = 0x08;
	public const PING = 0x09;
	public const PONG = 0x0a;

	/**
	 * 0x0b - 0x0f are reserved for further control frames
	 */
}
