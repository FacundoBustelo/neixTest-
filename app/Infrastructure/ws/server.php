<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
set_error_handler(function ($no, $str) {
    // Silenciar solo deprecations
    if ($no === E_DEPRECATED || $no === E_USER_DEPRECATED) { return true; }
    return false; // otras se manejan normal
});

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\SocketServer;   
use App\Infrastructure\ws\PriceEngine;

class FxServer implements MessageComponentInterface {
    private \SplObjectStorage $clients;
    private PriceEngine $engine;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $this->engine = new PriceEngine();
        echo "WS started on 0.0.0.0:8080\n";
    }

    public function clientCount(): int {
        return count($this->clients);
    }

    public function onOpen(ConnectionInterface $conn) : void {
        $this->clients->attach($conn);
        // snapshot inmediato
        $conn->send(json_encode([
            'type' => 'price_update',
            'timestamp' => round(microtime(true) * 1000),
            'data' => $this->engine->snapshot()
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg) : void {
        $payload = json_decode($msg, true);
        if (!is_array($payload) || !isset($payload['type'])) return;

        switch ($payload['type']) {
            case 'ping':
                $from->send(json_encode(['type'=>'pong','ts'=>round(microtime(true)*1000)]));
                break;

            case 'force_tick': // prueba manual
                $this->broadcastPrices();
                break;

                case 'send_configs':
                    $results = $this->validateConfigs($payload);
                    $from->send(json_encode([
                        'type'    => 'ack_configs',
                        'results' => $results, // [{symbol, ok, error?}]
                    ]));
                break;
        }
    }

    private function validateConfigs(array $payload): array {
        $results = [];
        $configs = $payload['configs'] ?? [];
        if (!is_array($configs)) {
            return [['symbol'=>'*','ok'=>false,'error'=>'Invalid payload']];
        }
        foreach ($configs as $c) {
            $sym  = (string)($c['symbol'] ?? '');
            $tgt  = $c['target_price'] ?? null;
            $qty  = $c['quantity'] ?? null;
            $side = (string)($c['side'] ?? '');
    
            $ok = true; $err = null;
            if ($sym === '') { $ok=false; $err='symbol required'; }
            if ($side !== '' && !in_array($side, ['buy','sell'], true)) { $ok=false; $err='invalid side'; }
            if ($qty !== null && ($qty === '' || !is_numeric($qty) || (float)$qty < 0)) { $ok=false; $err='invalid qty'; }
            if ($tgt !== null && ($tgt === '' || !is_numeric($tgt) || (float)$tgt <= 0)) { $ok=false; $err='invalid target'; }
    
            $item = ['symbol'=>$sym, 'ok'=>$ok];
            if (!$ok && $err) { $item['error'] = $err; }
            $results[] = $item;
        }
        return $results;
    }

    public function onClose(ConnectionInterface $conn) : void {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) : void {
        error_log($e->getMessage());
        $conn->close();
    }

    public function broadcastPrices() : void {
        $update = [
            'type' => 'price_update',
            'timestamp' => round(microtime(true)*1000),
            'data' => $this->engine->snapshot()
        ];
        $json = json_encode($update);
        foreach ($this->clients as $client) {
            $client->send($json);
        }
    }
}


$loop = new StreamSelectLoop();
$fx   = new FxServer();
$ws   = new WsServer($fx);
$http = new HttpServer($ws);
$socket = new SocketServer('0.0.0.0:8080', [], $loop);
$server = new IoServer($http, $socket, $loop);

// Timer periódico (1s) con log
$loop->addPeriodicTimer(2.0, function() use ($fx) {
    $fx->broadcastPrices();
});

$loop->run();
