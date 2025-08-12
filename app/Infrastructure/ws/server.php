<?php declare(strict_types=1);// app/Infrastructure/ws/server.php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\SocketServer;
use Ramsey\Uuid\Uuid;

use App\Infrastructure\ws\PriceEngine;
use App\Application\ConfigService;
use App\Infrastructure\Repository\PdoInstrumentRepository;
use App\Infrastructure\Repository\PdoUserInstrumentConfigRepository;
use App\Infrastructure\Repository\PdoUserRepository;

final class FxServer implements MessageComponentInterface {
    private \SplObjectStorage $clients;
    private PriceEngine $engine;

    // Lazy init (se crean al primer uso)
    private ?ConfigService $configService = null;
    private ?PdoUserRepository $userRepo  = null;

    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $this->engine  = new PriceEngine();
        echo "WS started on 0.0.0.0:8080\n";
    }

    public function clientCount(): int { return count($this->clients); }

    public function onOpen(ConnectionInterface $conn): void {
        $this->clients->attach($conn);
        // snapshot inmediato
        $conn->send(json_encode([
            'type' => 'price_update',
            'timestamp' => round(microtime(true) * 1000),
            'data' => $this->engine->snapshot()
        ]));
    }

    public function onMessage(ConnectionInterface $from, $msg): void {
        $payload = json_decode($msg, true);
        if (!is_array($payload) || !isset($payload['type'])) return;

        try {
            switch ($payload['type']) {
                case 'ping':
                    $from->send(json_encode(['type'=>'pong','ts'=>round(microtime(true)*1000)]));
                    break;

                case 'force_tick':
                    $this->broadcastPrices();
                    break;

                case 'send_configs':
                    $results = $this->handleSendConfigs($payload);
                    $from->send(json_encode([
                        'type'      => 'ack_configs',
                        'id'        => Uuid::uuid4()->toString(),
                        'results'   => $results,   // [{symbol, ok, error?}]
                        'persisted' => true
                    ]));
                    break;
            }
        } catch (\Throwable $e) {
            // Nunca tirar el server; devolvé error “amigable”
            $from->send(json_encode([
                'type'   => 'error',
                'message'=> 'internal ws error'
            ]));
            error_log('WS onMessage error: '.$e->getMessage());
        }
    }

    /**
     * Valida y PERSISTE cada config usando ConfigService.
     * Retorna array por símbolo, siempre, aunque haya errores.
     */
    private function handleSendConfigs(array $payload): array {
        // Lazy init seguro (evita crashear en constructor si DB no está lista)
        if ($this->configService === null) {
            $this->configService = new ConfigService(
                new PdoInstrumentRepository(),
                new PdoUserInstrumentConfigRepository()
            );
        }
        if ($this->userRepo === null) {
            $this->userRepo = new PdoUserRepository();
        }

        $userName = trim((string)($payload['user'] ?? ''));
        $configs  = $payload['configs'] ?? [];
        $results  = [];

        // validar usuario
        $userId = ($userName !== '') ? ($this->userRepo->idByUsername($userName) ?? 0) : 0;
        if ($userId <= 0) {
            foreach ($configs as $c) {
                $sym = trim((string)($c['symbol'] ?? '*'));
                $results[] = ['symbol' => ($sym !== '' ? $sym : '*'), 'ok' => false, 'error' => 'user not found'];
            }
            return $results;
        }

        foreach ($configs as $c) {
            try {
                $v = $this->configService->validateOne($c);
                $this->configService->saveOneValidated($userId, $v['symbol'], $v['target'], $v['qty'], $v['side']);
                $results[] = ['symbol' => $v['symbol'], 'ok' => true];
            } catch (\Throwable $e) {
                $symShow = trim((string)($c['symbol'] ?? '*'));
                $results[] = [
                    'symbol' => ($symShow !== '' ? $symShow : '*'),
                    'ok'     => false,
                    'error'  => $e->getMessage()
                ];
            }
        }
        return $results;
    }

    public function onClose(ConnectionInterface $conn): void { $this->clients->detach($conn); }

    public function onError(ConnectionInterface $conn, \Exception $e): void {
        error_log($e->getMessage());
        $conn->close();
    }

    public function broadcastPrices(): void {
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

// Boot WS
$loop   = new StreamSelectLoop();
$fx     = new FxServer();
$ws     = new WsServer($fx);
$http   = new HttpServer($ws);
$socket = new SocketServer('0.0.0.0:8080', [], $loop);
$server = new IoServer($http, $socket, $loop);

// tick de precios
$loop->addPeriodicTimer(2.0, function() use ($fx) { $fx->broadcastPrices(); });

$loop->run();
