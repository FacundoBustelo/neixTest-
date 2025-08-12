<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Repository\InstrumentRepository;
use App\Domain\Repository\UserInstrumentConfigRepository;
use InvalidArgumentException;

final class ConfigService
{
    public function __construct(
        private InstrumentRepository $instrumentRepo,
        private UserInstrumentConfigRepository $userCfgRepo
    ) {}

    /**
     * Valida y normaliza la entrada de una config:
     * - symbol: string no vacío
     * - side: 'buy' | 'sell'
     * - target_price: numérico > 0 (redondea a 6 decimales)
     * - quantity:     numérico > 0 (redondea a 4 decimales)
     *
     * @return array{symbol:string,target:float,qty:float,side:string}
     * @throws InvalidArgumentException si algo no cumple
     */
    public function validateOne(array $input): array
    {
        $symbol    = trim((string)($input['symbol'] ?? ''));
        $side      = trim((string)($input['side'] ?? ''));
        $targetRaw = isset($input['target_price']) ? trim((string)$input['target_price']) : '';
        $qtyRaw    = isset($input['quantity'])     ? trim((string)$input['quantity'])     : '';

        $targetRaw = str_replace(',', '.', $targetRaw);
        $qtyRaw    = str_replace(',', '.', $qtyRaw);

        $missing = [];
        if ($symbol === '')    $missing[] = "El campo 'symbol' es obligatorio.";
        if ($targetRaw === '') $missing[] = "El campo 'target_price' es obligatorio.";
        if ($qtyRaw === '')    $missing[] = "El campo 'quantity' es obligatorio.";
        if ($side === '')      $missing[] = "El campo 'side' es obligatorio.";
        if ($missing) {
            throw new InvalidArgumentException(implode(' ', $missing));
        }

        $fmtErr = [];
        if (!in_array($side, ['buy','sell'], true))             $fmtErr[] = "El campo 'side' debe ser 'buy' o 'sell'.";
        if (!is_numeric($targetRaw) || (float)$targetRaw <= 0)  $fmtErr[] = "El 'target_price' debe ser numérico y mayor a 0.";
        if (!is_numeric($qtyRaw)    || (float)$qtyRaw <= 0)     $fmtErr[] = "La 'quantity' debe ser numérica y mayor a 0.";
        if ($fmtErr) {
            throw new InvalidArgumentException(implode(' ', $fmtErr));
        }

        $target = round((float)$targetRaw, 6);
        $qty    = round((float)$qtyRaw, 4);

        return [
            'symbol' => $symbol,
            'target' => $target,
            'qty'    => $qty,
            'side'   => $side,
        ];
    }

    /**
     * Recibe datos YA validados (validateOne) y persiste.
     *
     * @throws InvalidArgumentException si el símbolo no existe
     */
    public function saveOneValidated(
        int $userId,
        string $symbol,
        float $targetPrice,
        float $quantity,
        string $side
    ): void {
        $iid = $this->instrumentRepo->idBySymbol($symbol);
        if ($iid === null) {
            throw new InvalidArgumentException("Símbolo desconocido: {$symbol}");
        }
        $this->userCfgRepo->upsert($userId, $iid, $targetPrice, $quantity, $side);
    }
}
