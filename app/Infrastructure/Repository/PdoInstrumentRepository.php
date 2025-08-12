<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\InstrumentRepository;
use App\Infrastructure\DB;
use PDO;

final class PdoInstrumentRepository implements InstrumentRepository {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? DB::get();
    }

    public function idBySymbol(string $symbol): ?int {
        $st = $this->pdo->prepare('SELECT id FROM instruments WHERE symbol = :s LIMIT 1');
        $st->execute([':s' => $symbol]);
        $id = $st->fetchColumn();
        return $id !== false ? (int)$id : null;
    }
}
