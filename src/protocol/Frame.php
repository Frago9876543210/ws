<?php

declare(strict_types=1);

namespace ws\protocol;

use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\Limits;
use function mt_rand;
use function pack;
use function strlen;

class Frame{
	/** @var bool */
	public $fin = true;
	/** @var int */
	public $opcode = Opcode::TEXT;
	/** @var bool */
	public $masked = false;
	/** @var string|null */
	public $maskingKey;
	/** @var string */
	public $payload;

	private function __construct(){
		//NOOP
	}

	public static function create(string $payload, int $opcode = Opcode::TEXT) : self{
		$result = new self();
		$result->payload = $payload;
		$result->opcode = $opcode;
		return $result;
	}

	public static function masked(string $payload) : self{
		$result = self::create($payload);
		$result->masked = true;
		$result->maskingKey = pack("L", mt_rand(0, Limits::UINT32_MAX));
		$result->xorEncryption();
		return $result;
	}

	private function xorEncryption() : void{
		if($this->maskingKey === null){
			return; //PHPStan doesn't understand it
		}

		for($i = 0, $payloadLength = strlen($this->payload); $i < $payloadLength; ++$i){
			$this->payload{$i} = $this->payload{$i} ^ $this->maskingKey[$i % 4];
		}
	}

	/**
	 * @param BinaryStream $stream
	 * @return self
	 * @throws BinaryDataException
	 */
	public static function read(BinaryStream $stream) : self{
		$result = new self();

		$byte1 = $stream->getByte();
		$result->fin = ($byte1 & BitFlag::FIN) !== 0;
		if((($byte1 >> 4) & 0b111) !== 0){
			throw new BinaryDataException("Reserved flags always should be zero");
		}
		$result->opcode = $byte1 & BitFlag::OPCODE_MASK;

		$byte2 = $stream->getByte();
		$result->masked = ($byte2 & BitFlag::MASKED) !== 0;
		$payloadLength = $byte2 & BitFlag::PAYLOAD_LENGTH_MASK;

		if($payloadLength === BitFlag::EXTENDED_PAYLOAD_U16){
			$payloadLength = $stream->getShort();
		}elseif($payloadLength === BitFlag::EXTENDED_PAYLOAD_U64){
			$payloadLength = $stream->getLong();
		}

		if($result->masked){
			$result->maskingKey = $stream->get(4);
		}

		$result->payload = $stream->get($payloadLength);
		if($result->masked){
			$result->xorEncryption();
		}

		return $result;
	}

	public function write(BinaryStream $stream) : string{
		$byte1 = $this->opcode;
		if($this->fin){
			$byte1 |= BitFlag::FIN;
		}
		$stream->putByte($byte1);

		$byte2 = 0;
		if($this->masked){
			$byte2 |= BitFlag::MASKED;
		}
		$payloadLength = strlen($this->payload);

		$u8 = $payloadLength < BitFlag::EXTENDED_PAYLOAD_U16;
		$u16 = !$u8 && $payloadLength <= Limits::UINT16_MAX;
		$u64 = !$u16 && $payloadLength <= PHP_INT_MAX;

		if($u8){
			$byte2 |= $payloadLength;
		}elseif($u16){
			$byte2 |= BitFlag::EXTENDED_PAYLOAD_U16;
		}elseif($u64){
			$byte2 |= BitFlag::EXTENDED_PAYLOAD_U64;
		}

		$stream->putByte($byte2);

		if($u16){
			$stream->putShort($payloadLength);
		}elseif($u64){
			$stream->putLong($payloadLength);
		}

		if($this->maskingKey !== null){
			$stream->put($this->maskingKey);
			$this->xorEncryption();
		}
		$stream->put($this->payload);

		return $stream->getBuffer();
	}
}
