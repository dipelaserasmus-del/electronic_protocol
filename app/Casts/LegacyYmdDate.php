<?php

namespace App\Casts;

use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * D04, transitional: the date columns are real DATE columns now ("2026-01-05"), but the legacy controllers and views
 * (about 80 places) still expect the old integer form 20260105. This cast shows them that form and accepts it back,
 * so they keep working until the screens are rebuilt (R1–R7); new code reads Carbon dates with Protocol::dateOf().
 * Z01 replaces it with Laravel's 'date' cast.
 *
 * Reading: "2026-01-05" → 20260105. Writing: 20260105, "20260105", "2026-01-05" or a DateTime → "2026-01-05";
 * null / "" / 0 → null. Anything else (the legacy lists put display text like "05/01/2026" into these attributes
 * before rendering) is kept as it is and returned as it is; saving it would be refused by MySQL, never stored wrong.
 */
class LegacyYmdDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return (int) ($m[1] . $m[2] . $m[3]);
        }
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return self::toDateString($value);
    }

    /** The column value for a legacy or modern date input (see class comment). */
    public static function toDateString(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ((is_int($value) || is_string($value)) && preg_match('/^(\d{4})(\d{2})(\d{2})$/', (string) $value, $m)) {
            return "$m[1]-$m[2]-$m[3]";
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        return $value;
    }
}
