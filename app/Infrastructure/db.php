<?php
// app/Infrastructure/db.php
namespace App\Infrastructure;

use PDO;
use PDOException;

class DB {
  private static ?PDO $pdo = null;

  public static function pdo(): PDO {
    if (self::$pdo === null) {
      $host = getenv('DB_HOST') ?: 'db';
      $db   = getenv('DB_NAME') ?: 'neix';
      $user = getenv('DB_USER') ?: 'root';
      $pass = getenv('DB_PASS') ?: 'root';
      $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
      $opt  = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ];
      self::$pdo = new PDO($dsn, $user, $pass, $opt);
    }
    return self::$pdo;
  }

  public static function get(): PDO {
    return self::pdo();
  }
  
}
