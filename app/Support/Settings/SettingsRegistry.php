<?php

namespace App\Support\Settings;

/** config/kinderdesk.php dagi 'settings' reyestri bilan ishlash. */
class SettingsRegistry
{
    /** @return array<string, array> key => definition (group bilan) */
    public static function definitions(): array
    {
        $out = [];
        foreach (config('kinderdesk.settings') as $groupKey => $group) {
            foreach ($group['items'] as $key => $def) {
                $def['group'] = $groupKey;
                $def['group_title'] = $group['title'];
                $def['group_feature'] = $group['feature'] ?? null;
                $out[$key] = $def;
            }
        }

        return $out;
    }

    public static function definition(string $key): ?array
    {
        return self::definitions()[$key] ?? null;
    }

    /** Foydalanuvchi kiritgan qiymatni sozlama turiga qarab tozalaydi. */
    public static function cast(string $key, mixed $value): mixed
    {
        $def = self::definition($key);
        if (! $def) {
            return $value;
        }

        switch ($def['type']) {
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'int':
                $n = (int) $value;
                if (isset($def['min'])) {
                    $n = max($def['min'], $n);
                }
                if (isset($def['max'])) {
                    $n = min($def['max'], $n);
                }

                return $n;
            case 'select':
                $value = (string) $value;

                return array_key_exists($value, $def['options']) ? $value : $def['default'];
            case 'multiselect':
                $value = array_values(array_filter((array) $value, fn ($v) => array_key_exists($v, $def['options'])));

                return array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $value);
            case 'list':
                if (is_string($value)) {
                    $value = preg_split('/\r\n|\r|\n/', $value);
                }
                $value = array_values(array_unique(array_filter(array_map(fn ($v) => trim((string) $v), (array) $value), fn ($v) => $v !== '')));

                return $value ?: $def['default'];
            case 'time':
                return preg_match('/^\d{2}:\d{2}$/', (string) $value) ? $value : $def['default'];
            default:
                return trim((string) $value);
        }
    }
}
