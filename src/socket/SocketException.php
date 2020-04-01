<?php

declare(strict_types=1);

namespace ws\socket;

use RuntimeException;
use function socket_last_error;
use function socket_strerror;
use function trim;

class SocketException extends RuntimeException{

	/**
	 * @param string        $message
	 * @param resource|null $socket
	 * @return self
	 */
	public static function wrap(string $message, $socket = null) : self{
		$error = $socket === null ? socket_last_error() : socket_last_error($socket);
		return new self($message . ": " . trim(socket_strerror($error)));
	}
}
