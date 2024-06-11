<?php
namespace MyApp;

require_once '../vendor/autoload.php';
require_once '../database/db_connection.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Chat implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Received message: $msg\n";
        
        $data = json_decode($msg, true);
        $senderId = $data['sender_id'];
        $receiverId = $data['receiver_id'];
        $content = $data['content'];
        
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send($msg);
                echo "Message sent to client ({$client->resourceId})\n";
            }
        }

        $this->saveMessageToDatabase($senderId, $receiverId, $content);
    }

    protected function saveMessageToDatabase($senderId, $receiverId, $content) {
        global $conn;

        $query = "INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $senderId, $receiverId, $content);
        $stmt->execute();
        $stmt->close();
        echo "Message saved to database\n";
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}
?>
