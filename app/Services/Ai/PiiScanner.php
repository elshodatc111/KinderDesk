<?php

namespace App\Services\Ai;

/**
 * Shaxsiy ma'lumot skaneri: telefon, hujjat raqami, karta, email.
 * Chatda foydalanuvchi matni niqoblanadi; AI'ga ketadigan har qanday paket yuborishdan oldin tekshiriladi.
 */
class PiiScanner
{
    private const PATTERNS = [
        'telefon' => '/(?<![\d])(?:\+?998[\s\-]?)?\(?\d{2}\)?[\s\-]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}(?![\d])/u',
        'karta' => '/(?<![\d])(?:\d[ \-]?){13,19}(?![\d])/u',
        'hujjat' => '/(?<![A-Za-z])(?:[A-Z]{2}\s?\d{7}|I\-?[A-Z]{2}\s?\d{6,7})(?![\d])/u',
        'email' => '/[\w.+\-]+@[\w\-]+\.[\w.\-]+/u',
    ];

    /** @return array<int, string> topilgan turlar */
    public function find(string $text): array
    {
        $found = [];
        foreach (self::PATTERNS as $kind => $re) {
            if (preg_match($re, $text)) {
                $found[] = $kind;
            }
        }

        return $found;
    }

    /** @return array{0:string, 1:bool} [niqoblangan matn, niqoblandimi] */
    public function mask(string $text): array
    {
        $masked = false;
        foreach (['email', 'hujjat', 'karta', 'telefon'] as $kind) {
            $text = preg_replace(self::PATTERNS[$kind], '['.$kind.']', $text, -1, $n);
            $masked = $masked || $n > 0;
        }

        return [$text, $masked];
    }

    /**
     * Paket ichidagi barcha MATN qiymatlari va kalitlar tekshiriladi (sonlar emas — summalar telefonga o'xshab ketmasin).
     * @throws AiException
     */
    public function assertClean(mixed $payload): void
    {
        $hits = [];
        $walk = function ($v, $key = null) use (&$walk, &$hits) {
            if (is_array($v)) {
                foreach ($v as $k => $x) {
                    $walk($x, $k);
                }

                return;
            }
            if (is_string($v)) {
                $hits = array_merge($hits, $this->find($v));
            }
            if (is_string($key)) {
                $hits = array_merge($hits, $this->find($key));
            }
        };
        $walk($payload);
        if ($hits) {
            throw new AiException("So'rovda shaxsiy ma'lumot aniqlandi (".implode(', ', array_unique($hits))."), yuborilmadi", 'pii');
        }
    }
}
