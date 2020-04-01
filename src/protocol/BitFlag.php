<?php

declare(strict_types=1);

namespace ws\protocol;

final class BitFlag{

	private function __construct(){
		//NOOP
	}

	// ================================ BYTE 1 ================================ //

	/**
	 * Is fragmented
	 */
	public const FIN = 1 << 7;

	/**
	 * Reserved flags, always MUST be zero:
	 * 1 << 6
	 * 1 << 5
	 * 1 << 4
	 */

	/**
	 * @see Opcode
	 */
	public const OPCODE_MASK = 0b1111;

	// ================================ BYTE 2 ================================ //

	public const MASKED = 1 << 7;

	public const PAYLOAD_LENGTH_MASK = 0b01111111;

	public const EXTENDED_PAYLOAD_U16 = 126;
	public const EXTENDED_PAYLOAD_U64 = 127;
}
