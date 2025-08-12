<?php
declare(strict_types=1);

namespace App\Domain\Repository;

interface UserRepository {
    public function idByUsername(string $username): ?int;
}
