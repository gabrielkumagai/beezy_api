<?php
use Workerman\Worker;
use Workerman\Connection\TcpConnection;

require_once __DIR__ . '/vendor/autoload.php';

$ws = new Worker("websocket://0.0.0.0:8081");

$chatConnections = [];

$ws->onConnect = function(TcpConnection $connection) {
    echo "🟢 Nova conexão: {$connection->id}\n";
};

$ws->onMessage = function(TcpConnection $connection, $data) use (&$chatConnections, $ws) {
    echo "📩 Mensagem recebida: $data\n";

    $payload = json_decode($data, true);
    if (!$payload) {
        echo "❌ JSON inválido\n";
        return;
    }

    if ($payload['type'] === 'join') {
        $chatId = $payload['chatId'];
        $chatConnections[$connection->id] = $chatId;
        echo "👥 Conexão {$connection->id} entrou no chat $chatId\n";
        return;
    }

    if ($payload['type'] === 'message') {
        $chatId = $payload['chatId'];

        $message = [
            'chatId' => $chatId,
            'sender' => $payload['sender'],
            'senderId' => $payload['senderId'],
            'content' => $payload['content'],
            'timestamp' => date('Y-m-d H:i:s'),
            'messageId' => rand(1, 999999),
        ];

        echo "💬 Nova mensagem de {$payload['sender']} (chat {$chatId}): {$payload['content']}\n";

        foreach ($ws->connections as $client) {
            $clientId = $client->id;
            if (isset($chatConnections[$clientId]) && $chatConnections[$clientId] === $chatId) {
                $client->send(json_encode($message));
            }
        }
    }
};

$ws->onClose = function(TcpConnection $connection) use (&$chatConnections) {
    echo "🔴 Conexão encerrada: {$connection->id}\n";
    unset($chatConnections[$connection->id]);
};

Worker::runAll();
