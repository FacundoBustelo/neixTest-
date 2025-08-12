<?php
// app/Infrastructure/ws/PriceEngine.php
namespace App\Infrastructure\ws;

final class PriceEngine {
    private array $symbols = ['USD','EUR','JPY','GBP'];

    private array $state = [
        'USD' => ['price' => 1.10000, 'market_qty' => 120, 'dec' => 5, 'step' => 0.00020],
        'EUR' => ['price' => 157.000, 'market_qty' => 95,  'dec' => 3, 'step' => 0.050],
        'JPY' => ['price' => 1.28000, 'market_qty' => 80,  'dec' => 5, 'step' => 0.00020],
        'GBP' => ['price' => 60000.00,'market_qty' => 3,   'dec' => 2, 'step' => 15.00],
    ];

    private function step(string $sym): void {
        $s =& $this->state[$sym];
        $s['price'] = max(0, $s['price'] + (mt_rand(-100,100) / 100.0) * $s['step']);
        $s['market_qty'] = max(1, min(9999, $s['market_qty'] + mt_rand(-15, 15)));
    }

    public function snapshot(): array {
        $out = [];
        foreach ($this->symbols as $sym) {
            $this->step($sym);
            $dec   = $this->state[$sym]['dec'];
            $price = round($this->state[$sym]['price'], $dec);
            $out[] = [
                'symbol'      => $sym,
                'price'       => $price,
                'market_qty'  => (int)$this->state[$sym]['market_qty'],
            ];
        }
        return $out;
    }
}
