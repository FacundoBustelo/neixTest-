<?php
// public/api/config_save.php
declare(strict_types=1);

use App\Infrastructure\DB;
use App\Infrastructure\Auth;

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

  // Sesión obligatoria
  $user = Auth::requireUser();
  $userId = (int)($user['id'] ?? 0);
  if ($userId <= 0) {
    throw new RuntimeException('user not found');
  }

  // Parseo body
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $symbol = trim((string)($data['symbol'] ?? ''));
  $side   = trim((string)($data['side'] ?? ''));
  // normalizo coma decimal -> punto
  $targetRaw = isset($data['target_price']) ? (string)$data['target_price'] : '';
  $qtyRaw    = isset($data['quantity'])     ? (string)$data['quantity']     : '';
  $targetRaw = str_replace(',', '.', trim($targetRaw));
  $qtyRaw    = str_replace(',', '.', trim($qtyRaw));

  // Reglas: todos obligatorios + mensajes descriptivos
  $missing = [];
  if ($symbol === '')   $missing[] = "El campo 'symbol' es obligatorio.";
  if ($targetRaw === '')$missing[] = "El campo 'target_price' es obligatorio.";
  if ($qtyRaw === '')   $missing[] = "El campo 'quantity' es obligatorio.";
  if ($side === '')     $missing[] = "El campo 'side' es obligatorio.";
  if ($missing) {
    throw new InvalidArgumentException(implode(' ', $missing));
  }

  $fmtErr = [];
  if (!in_array($side, ['buy','sell'], true)) {
    $fmtErr[] = "El campo 'side' debe ser 'buy' o 'sell'.";
  }
  if (!is_numeric($targetRaw) || (float)$targetRaw <= 0) {
    $fmtErr[] = "El 'target_price' debe ser numérico y mayor a 0.";
  }
  if (!is_numeric($qtyRaw) || (float)$qtyRaw <= 0) {
    $fmtErr[] = "La 'quantity' debe ser numérica y mayor a 0.";
  }
  if ($fmtErr) {
    throw new InvalidArgumentException(implode(' ', $fmtErr));
  }

  // Normalizo a precisión de la tabla
  $target = round((float)$targetRaw, 6); // DECIMAL(12,6)
  $qty    = round((float)$qtyRaw, 4);    // DECIMAL(12,4)

  // symbol -> instrument_id
  $pdo = DB::get(); // o DB::pdo() si preferís
  $stI = $pdo->prepare('SELECT id FROM instruments WHERE symbol = :s LIMIT 1');
  $stI->execute([':s' => $symbol]);
  $instrumentId = (int)($stI->fetchColumn() ?: 0);
  if ($instrumentId <= 0) {
    throw new InvalidArgumentException("Símbolo desconocido: {$symbol}");
  }

  // Upsert
  $upsert = $pdo->prepare(
    "INSERT INTO user_instrument_config
       (user_id, instrument_id, target_price, quantity, side, updated_at)
     VALUES
       (:uid, :iid, :tgt, :qty, :side, NOW())
     ON DUPLICATE KEY UPDATE
       target_price = VALUES(target_price),
       quantity     = VALUES(quantity),
       side         = VALUES(side),
       updated_at   = NOW()"
  );
  $upsert->execute([
    ':uid'  => $userId,
    ':iid'  => $instrumentId,
    ':tgt'  => $target,
    ':qty'  => $qty,
    ':side' => $side,
  ]);

  echo json_encode(['ok' => true, 'symbol' => $symbol], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
