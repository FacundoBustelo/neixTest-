<?php
declare(strict_types=1);

use App\Infrastructure\Auth;
use App\Application\ConfigService;
use App\Infrastructure\Repository\PdoInstrumentRepository;
use App\Infrastructure\Repository\PdoUserInstrumentConfigRepository;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require dirname(__DIR__, 2) . '/vendor/autoload.php';

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
  }

  $user = Auth::requireUser();
  $userId = (int)($user['id'] ?? 0);
  if ($userId <= 0) { throw new RuntimeException('user not found'); }

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $service = new ConfigService(
    new PdoInstrumentRepository(),
    new PdoUserInstrumentConfigRepository()
  );

  $v = $service->validateOne($data);

  $service->saveOneValidated($userId, $v['symbol'], $v['target'], $v['qty'], $v['side']);

  echo json_encode(['ok' => true, 'symbol' => $v['symbol']], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
