<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\Chat;

require dirname(__DIR__) . '/vendor/autoload.php';
require 'chat.php';

echo "Starting server...\n";

try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new Chat()
            )
        ),
        8080
    );

    echo "Server started on port 8080\n";
    $server->run();
} catch (Exception $e) {
    echo "Error starting server: " . $e->getMessage() . "\n";
}
?>
