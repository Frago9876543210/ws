<?php

declare(strict_types=1);

namespace ws\utils;

use InvalidArgumentException;
use Particle\Validator\Validator;
use function explode;
use function implode;
use function is_string;
use function preg_match;
use function trim;

class Http{

	public static function validateRoute(string $route) : bool{
		return preg_match("/\A\/[A-Za-z0-9_]*\z/", $route) === 1;
	}

	/**
	 * @param string $data
	 * @param string $exceptedRoute
	 * @return array<string, string>
	 * @throws InvalidArgumentException
	 */
	public static function parseHeaders(string $data, string $exceptedRoute) : array{
		$headers = [];

		$lines = explode("\r\n", $data);
		if(preg_match("/\AGET (.*) HTTP\/\d.\d\z/", $lines[0], $matches) === 1){
			if($matches[1] !== $exceptedRoute){
				throw new InvalidArgumentException("Invalid route provided");
			}

			foreach($lines as $line){
				if(preg_match("/\A(\S+): (.*)\z/", trim($line), $matches) === 1){
					list(, $header, $value) = $matches;

					if(!(is_string($header) && is_string($value))){
						throw new InvalidArgumentException("Excepted headers which contains strings");
					}elseif(isset($headers[$header])){
						throw new InvalidArgumentException("Attempt to overwrite already existing header");
					}

					$headers[$header] = $value;
				}
			}
		}

		$validator = new Validator();
		$validator->required("Sec-WebSocket-Version")->string()->equals("13");
		$validator->required("Sec-WebSocket-Key");

		$result = $validator->validate($headers);
		if($result->isNotValid()){
			$messages = [];
			foreach($result->getFailures() as $f){
				$messages[] = $f->format();
			}
			throw new InvalidArgumentException("Invalid headers: " . implode(", ", $messages));
		}

		return $headers;
	}
}
