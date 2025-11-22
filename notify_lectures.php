<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class LectureServer implements MessageComponentInterface {
    protected $clients; // All connections
    protected $rooms;   // roomName => [connId => connection]

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $this->rooms = [];
        echo "✅ Lecture WebSocket server started on port 4000...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "🔗 New connection ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data) return;

        $room = $data['room'] ?? null;

        // Handle join request
        if ($data['type'] === 'join' && $room) {
            if (!isset($this->rooms[$room])) $this->rooms[$room] = [];
            $this->rooms[$room][$from->resourceId] = $from;
            echo "📥 Connection {$from->resourceId} joined room $room\n";
            return; // no need to broadcast join message
        }

        // Only process messages if room exists
        if ($room && isset($this->rooms[$room])) {
            foreach ($this->rooms[$room] as $clientId => $clientConn) {
                if ($clientConn !== $from) {
                    $clientConn->send($msg); // send offer/answer/ice to other clients
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);

        foreach ($this->rooms as $roomName => $clients) {
            if (isset($clients[$conn->resourceId])) {
                unset($this->rooms[$roomName][$conn->resourceId]);
                echo "❌ Connection {$conn->resourceId} left room $roomName\n";
            }
        }

        echo "❌ Connection {$conn->resourceId} closed\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "⚠️ Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new LectureServer()
        )
    ),
    4000
);

$server->run();
