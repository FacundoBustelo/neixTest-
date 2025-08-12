<?php
// app/Infrastructure/auth.php
namespace App\Infrastructure;

class Auth {
  public static function start(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_set_cookie_params([ 'httponly' => true, 'samesite' => 'Lax' ]);
      session_name('neixsid');
      session_start();
    }
  }
  public static function requireUser(): array {
    self::start();
    if (!isset($_SESSION['user'])) {
      http_response_code(401);
      header('Content-Type: application/json');
      echo json_encode(['error' => 'unauthorized']);
      exit;
    }
    return $_SESSION['user'];
  }
  public static function login(array $user): void {
    self::start();
    $_SESSION['user'] = $user;
  }
  public static function logout(): void {
    self::start();
    session_destroy();
  }
}
