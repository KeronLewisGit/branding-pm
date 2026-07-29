<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use LogsActivity;
    use Notifiable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'employee_number',
        'full_name',
        'email',
        'email_verified_at',
        'password',
        'pin',
        'pin_set_at',
        'is_active',
        'default_site_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'pin_set_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('user')
            ->logOnly([
                'employee_number',
                'full_name',
                'email',
                'is_active',
                'default_site_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    // ── Relationships ────────────────────────────────────────────────

    /**
     * Machines this user is assigned to as an operator.
     */
    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'user_machine')->withTimestamps();
    }

    public function defaultSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'default_site_id');
    }

    public function runsAsOperator(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'operator_id');
    }

    public function runsAsSupervisor(): HasMany
    {
        return $this->hasMany(ChecklistRun::class, 'supervisor_id');
    }

    public function issuesRaised(): HasMany
    {
        return $this->hasMany(Issue::class, 'raised_by');
    }

    public function issuesAssigned(): HasMany
    {
        return $this->hasMany(Issue::class, 'assigned_to');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function hasPin(): bool
    {
        return $this->pin !== null;
    }

    /**
     * The login form accepts email OR employee_number as the identifier,
     * so having a password is what decides password login — not email.
     */
    public function canLoginWithPassword(): bool
    {
        return $this->password !== null;
    }
}
