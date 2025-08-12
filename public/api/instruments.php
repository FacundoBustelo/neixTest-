
<?php
require __DIR__ . '/../../vendor/autoload.php';
use App\Infrastructure\Auth;
use App\Infrastructure\DB;

header('Content-Type: application/json');
Auth::requireUser();

$pdo = DB::pdo();
$rows = $pdo->query("SELECT symbol, description FROM instruments ORDER BY symbol")->fetchAll();
echo json_encode($rows);
