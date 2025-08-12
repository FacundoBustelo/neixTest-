<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserRepository;
use App\Infrastructure\DB;
use PDO;

final class PdoUserRepository implements UserRepository {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? DB::get();
    }

    public function idByUsername(string $username): ?int {
        $st = $this->pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $st->execute([':u' => $username]);
        $id = $st->fetchColumn();
        return $id !== false ? (int)$id : null;
    }
}
