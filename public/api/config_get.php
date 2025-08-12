<?php
// public/api/config_get.php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Infrastructure\Auth;
use App\Infrastructure\DB;

header('Content-Type: application/json; charset=utf-8');

try {
  $user = Auth::requireUser();

  $pdo = DB::get();
  $sql = "SELECT i.symbol, c.target_price, c.quantity, c.side
          FROM instruments i
          LEFT JOIN user_instrument_config c
            ON c.instrument_id = i.id AND c.user_id = :uid
          ORDER BY i.symbol";
  $st = $pdo->prepare($sql);
  $st->execute([':uid' => (int)$user['id']]);

  $rows = $st->fetchAll();


  foreach ($rows as &$r) {
    if ($r['target_price'] !== null) $r['target_price'] = (float)$r['target_price'];
    if ($r['quantity'] !== null)     $r['quantity']     = (float)$r['quantity'];
  }
  unset($r);

  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['error' => $e->getMessage()]);
}
