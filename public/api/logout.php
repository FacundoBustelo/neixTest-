
<?php
require __DIR__ . '/../../vendor/autoload.php';
use App\Infrastructure\Auth;
header('Content-Type: application/json');
Auth::logout();
echo json_encode(['ok'=>true]);
