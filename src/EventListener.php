<?php

declare(strict_types=1);

namespace ws;

interface EventListener{
	public function onConnect(Connection $connection) : void;

	public function onMessage(Connection $connection, string $message) : void;

	public function onDisconnect(Connection $connection) : void;
}
