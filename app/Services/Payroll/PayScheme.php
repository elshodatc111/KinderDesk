<?php

namespace App\Services\Payroll;

use App\Models\PayScheme as PaySchemeModel;

/**
 * Bolalar soniga (plus) bog'liq pog'onali maosh jadvali T(n).
 *
 *   b = F × p/100 (bir bolaning asos oyligi)
 *   n ≤ L yoki n M ga bo'linsa:       T(n) = n × b
 *   aks holda:                         T(n) = T(M·⌊n/M⌋) + (n − M·⌊n/M⌋) × s
 *   n > 30 va c belgilangan bo'lsa:    T(n) = T(30) + (n − 30) × c
 */
class PayScheme
{
    public function __construct(public readonly string $key, public readonly array $params)
    {
    }

    /** Sxemani bog'cha sozlamasidan (yoki boshlang'ich qiymatdan) oladi. */
    public static function for(string $key): ?self
    {
        $default = config("kinderdesk.pay_schemes.$key");
        if (! $default) {
            return null;
        }
        $row = PaySchemeModel::where('key', $key)->first();

        return new self($key, array_merge($default, $row?->params ?? []));
    }

    /** @return array<string, self> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(config('kinderdesk.pay_schemes')) as $k) {
            $out[$k] = self::for($k);
        }

        return $out;
    }

    public function name(): string
    {
        return $this->params['name'];
    }

    /** Asos narx F — guruh o'lchamidagi sozlamadan. */
    public function base(): int
    {
        return (int) tenant()->setting('groups.price_'.($this->params['size'] ?? 'large'), 0);
    }

    /** Bir bolaning asos oyligi b. */
    public function perKid(): int
    {
        return (int) round($this->base() * ($this->params['p'] ?? 0) / 100);
    }

    public function tier(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }
        $b = $this->perKid();
        $L = (int) ($this->params['L'] ?? 0);
        $M = max(1, (int) ($this->params['M'] ?? 5));
        $s = (int) ($this->params['s'] ?? 0);
        $c = (int) ($this->params['c'] ?? 0);

        if ($c > 0 && $n > 30) {
            return $this->tier(30) + ($n - 30) * $c;
        }
        if ($n <= $L || $n % $M === 0) {
            return $n * $b;
        }
        $base = intdiv($n, $M) * $M;

        return $this->tier($base) + ($n - $base) * $s;
    }

    /** Bitta plusning narxi r = T(n) / (n × W). */
    public function rate(int $n, int $workingDays): float
    {
        return $n > 0 && $workingDays > 0 ? $this->tier($n) / ($n * $workingDays) : 0.0;
    }
}
