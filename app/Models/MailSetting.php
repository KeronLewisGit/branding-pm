<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The one mail relay row. See the migration for why this is a typed single
 * row rather than the key/value settings table this project deleted.
 */
class MailSetting extends Model
{
    use LogsActivity;

    /**
     * Memoised per request, NOT put in the cache store.
     *
     * A cached Eloquent model is serialised with its attributes already cast,
     * so the decrypted API key would be written to storage/framework/cache in
     * plain text — undoing the encryption on the column it came from. One
     * indexed read of a one-row table per request is the cheaper trade.
     *
     * `false` means "looked and found none", which is different from null
     * meaning "not looked yet".
     */
    private static self|false|null $memo = null;

    protected $fillable = [
        'transport', 'host', 'port', 'username', 'password', 'encryption',
        'from_address', 'from_name', 'is_active',
        'last_tested_at', 'last_test_result', 'updated_by_id',
    ];

    /**
     * The password never appears in a payload, a log or an export.
     *
     * @var list<string>
     */
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            // Encrypted at rest: this column is in every nightly dump, and an
            // API key in one is a key somebody else can send as you with.
            'password' => 'encrypted',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('settings')
            // Everything EXCEPT the password. An activity log that records a
            // credential is a second place it leaks from.
            ->logOnly(['transport', 'host', 'port', 'username', 'encryption', 'from_address', 'from_name', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected static function booted(): void
    {
        // Saved and deleted both, so the memo cannot outlive the row.
        static::saved(fn () => self::$memo = null);
        static::deleted(fn () => self::$memo = null);
    }

    /** Drop the memo — for tests, and after a save within one request. */
    public static function forget(): void
    {
        self::$memo = null;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    /**
     * The active relay, or null when nobody has configured and enabled one —
     * in which case `.env` stays in force.
     */
    public static function active(): ?self
    {
        if (self::$memo === null) {
            self::$memo = self::query()->where('is_active', true)->first() ?? false;
        }

        return self::$memo ?: null;
    }
}
