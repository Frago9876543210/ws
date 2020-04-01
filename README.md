# ws
[![Build Status](https://travis-ci.org/Frago9876543210/ws.svg?branch=master)](https://travis-ci.org/Frago9876543210/ws)

Lightweight multithreaded websocket server written in PHP

## Requirements
- PHP 7.3+
- [ext-parallel](https://github.com/krakjoe/parallel)

## Example
Project tree
```
├── composer.json
└── src
    ├── MyListener.php
    └── run.php
```

### `composer.json`:
```json
{
  "require": {
    "frago9876543210/ws": "dev-master",
    "ext-json": "*"
  },
  "autoload": {
    "psr-4": {
      "app\\": "src/"
    }
  },
  "minimum-stability": "dev"
}
```

### `src/run.php`:
```php
<?php

declare(strict_types=1);

use app\MyListener;
use ws\utils\InternetAddress;
use ws\WebSocket;

require_once "vendor/autoload.php";

new WebSocket(new InternetAddress("0.0.0.0", 8080), "/ws", MyListener::class);
```

### `src/MyListener.php`:
```php
<?php

declare(strict_types=1);

namespace app;

use JsonException;
use Particle\Validator\Validator;
use ws\Connection;
use ws\EventListener;
use function json_decode;
use const JSON_THROW_ON_ERROR;

class MyListener implements EventListener{

	public function onConnect(Connection $connection) : void{
		echo "+ {$connection->getAddress()}\n";
	}

	public function onMessage(Connection $connection, string $message) : void{
		try{
			$data = json_decode($message, true, 2, JSON_THROW_ON_ERROR);

			$validator = new Validator();
			$validator->required("username")->string();
			$validator->required("message")->string();

			if($validator->validate($data)->isValid()){
				$connection->send($message, true);
				echo "{$data["username"]} ({$connection->getAddress()}): {$data["message"]}\n";
			}else{
				$connection->close("Invalid data provided");
			}
		}catch(JsonException $e){
			$connection->close("Failed to parse json: " . $e->getMessage());
		}
	}

	public function onDisconnect(Connection $connection) : void{
		echo "- {$connection->getAddress()}\n";
	}
}
```
