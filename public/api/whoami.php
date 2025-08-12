
<?php
require __DIR__ . '/../../vendor/autoload.php';
use App\Infrastructure\Auth;

header('Content-Type: application/json');
Auth::start();
echo json_encode($_SESSION['user'] ?? []);
