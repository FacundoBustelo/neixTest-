<?php
require __DIR__ . '/../../vendor/autoload.php';
use App\Infrastructure\DB;
use App\Infrastructure\Auth;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['error'=>'missing credentials']);
  exit;
}

$pdo = DB::pdo();
$stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

$valid = false;
if ($user) {
  $stored = $user['password_hash'];
  if (is_string($stored) && str_starts_with($stored, '$2y$')) {
    // bcrypt
    $valid = password_verify($password, $stored);
  } else {
    // fallback dev: comparar texto plano (por si el seed no trae hash)
    $valid = hash_equals((string)$stored, $password);
  }
}

if (!$valid) {
  http_response_code(401);
  echo json_encode(['error'=>'invalid user or password']);
  exit;
}

Auth::login(['id'=>$user['id'], 'username'=>$user['username']]);
echo json_encode(['ok'=>true]);
