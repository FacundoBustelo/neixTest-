<?php
// public/api/config_bulk_save.php
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
  if ($userId <= 0) { throw new RuntimeException('user not found'); }

  // Body
  $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
  $configs = $body['configs'] ?? null;
  if (!is_array($configs)) {
    throw new InvalidArgumentException('invalid configs');
  }

  $pdo = DB::get();
  $selInstr = $pdo->prepare('SELECT id FROM instruments WHERE symbol = :s LIMIT 1');
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

  $saved = [];
  $errors = [];

  foreach ($configs as $c) {
    // --- Normalización de entrada (coma → punto, trims) ---
    $sym      = trim((string)($c['symbol'] ?? ''));
    $side     = trim((string)($c['side'] ?? ''));
    $targetRaw= isset($c['target_price']) ? trim((string)$c['target_price']) : '';
    $qtyRaw   = isset($c['quantity'])     ? trim((string)$c['quantity'])     : '';
    $targetRaw= str_replace(',', '.', $targetRaw);
    $qtyRaw   = str_replace(',', '.', $qtyRaw);

    // --- Reglas: todos obligatorios ---
    $missing = [];
    if ($sym === '')      $missing[] = "El campo 'symbol' es obligatorio.";
    if ($targetRaw === '')$missing[] = "El campo 'target_price' es obligatorio.";
    if ($qtyRaw === '')   $missing[] = "El campo 'quantity' es obligatorio.";
    if ($side === '')     $missing[] = "El campo 'side' es obligatorio.";
    if ($missing) { $errors[] = ['symbol'=>$sym ?: '*','error'=>implode(' ', $missing)]; continue; }

    // --- Formato/negocio ---
    $fmtErr = [];
    if (!in_array($side, ['buy','sell'], true))          $fmtErr[] = "El campo 'side' debe ser 'buy' o 'sell'.";
    if (!is_numeric($targetRaw) || (float)$targetRaw <= 0) $fmtErr[] = "El 'target_price' debe ser numérico y mayor a 0.";
    if (!is_numeric($qtyRaw)    || (float)$qtyRaw <= 0)    $fmtErr[] = "La 'quantity' debe ser numérica y mayor a 0.";
    if ($fmtErr) { $errors[] = ['symbol'=>$sym,'error'=>implode(' ', $fmtErr)]; continue; }

    // --- symbol → instrument_id ---
    $selInstr->execute([':s' => $sym]);
    $iid = (int)($selInstr->fetchColumn() ?: 0);
    if ($iid <= 0) { $errors[] = ['symbol'=>$sym,'error'=>"Símbolo desconocido: {$sym}"]; continue; }

    // --- Normalizar a precisión de la tabla ---
    $target = round((float)$targetRaw, 6); // DECIMAL(12,6)
    $qty    = round((float)$qtyRaw, 4);    // DECIMAL(12,4)

    // --- Upsert ---
    $upsert->execute([
      ':uid'  => $userId,
      ':iid'  => $iid,
      ':tgt'  => $target,
      ':qty'  => $qty,
      ':side' => $side,
    ]);

    $saved[] = $sym;
  }

  echo json_encode(['ok' => true, 'saved' => $saved, 'errors' => $errors], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
