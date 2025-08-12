<?php
declare(strict_types=1);

namespace App\Domain\Repository;

interface InstrumentRepository {
    public function idBySymbol(string $symbol): ?int;
}