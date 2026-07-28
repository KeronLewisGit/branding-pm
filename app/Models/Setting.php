<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    /**
     * Fetch a setting by key, cast according to its `type` column.
     * Supported types: string, integer, float, boolean, json.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return $setting->castValue();
    }

    /**
     * Store a setting, inferring the `type` column from the PHP type.
     */
    public static function set(string $key, mixed $value): void
    {
        [$type, $stored] = match (true) {
            is_bool($value) => ['boolean', $value ? '1' : '0'],
            is_int($value) => ['integer', (string) $value],
            is_float($value) => ['float', (string) $value],
            is_array($value) => ['json', json_encode($value)],
            $value === null => ['string', null],
            default => ['string', (string) $value],
        };

        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );
    }

    /**
     * The raw `value` column cast to the declared `type`.
     */
    public function castValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            'integer', 'int' => (int) $this->value,
            'float', 'double' => (float) $this->value,
            'boolean', 'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json', 'array' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
