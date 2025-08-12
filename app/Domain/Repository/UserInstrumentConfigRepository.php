<?php
declare(strict_types=1);

namespace App\Domain\Repository;

interface UserInstrumentConfigRepository {
    public function upsert(
        int $userId,
        int $instrumentId,
        float $targetPrice,
        float $quantity,
        string $side
    ): void;
}
