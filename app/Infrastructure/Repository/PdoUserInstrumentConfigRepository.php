<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserInstrumentConfigRepository;
use App\Infrastructure\DB;
use PDO;

final class PdoUserInstrumentConfigRepository implements UserInstrumentConfigRepository {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? DB::get();
    }

    public function upsert(
        int $userId,
        int $instrumentId,
        float $targetPrice,
        float $quantity,
        string $side
    ): void {
        $st = $this->pdo->prepare(
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
        $st->execute([
            ':uid'  => $userId,
            ':iid'  => $instrumentId,
            ':tgt'  => $targetPrice,
            ':qty'  => $quantity,
            ':side' => $side,
        ]);
    }
}
