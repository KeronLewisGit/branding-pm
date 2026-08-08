<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ViewAs;
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
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;

    // `hasPermissionTo` comes from a trait, so `parent::` cannot reach it —
    // it is aliased here and called by the override below.
    use HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }
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
        'walkthrough_seen_at',
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
            'walkthrough_seen_at' => 'datetime',
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

    /**
     * True only when this user is an administrator AND is not currently
     * previewing another role.
     *
     * The policies' `before()` hooks wave admins past every check. While an
     * administrator is previewing an operator, waving them past is exactly
     * what must not happen — the preview would show an operator's menu over
     * an administrator's permissions, which answers no question at all.
     */
    public function isActingAdmin(): bool
    {
        return $this->hasRole('admin') && ! ViewAs::active();
    }

    /**
     * Permission check, narrowed by "view as" when it is on.
     *
     * Overridden here rather than through a `Gate::before` callback because
     * spatie already registers one, and whichever is registered first wins —
     * ours would never run. Every path (`can()`, `@can`, `authorize()`,
     * policies) funnels through `checkPermissionTo()` and then this method,
     * so this is the one place that cannot be bypassed.
     *
     * Only ever subtracts: a permission the previewed role lacks is refused,
     * and everything else is left to the real check below.
     *
     * @param  string|int|\BackedEnum|Permission  $permission
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if (is_string($permission) && ! ViewAs::permits($permission)) {
            return false;
        }

        return $this->spatieHasPermissionTo($permission, $guardName);
    }

    /**
     * Has this person been shown the first-run walkthrough?
     *
     * Stored on the user rather than in the browser, because the shop-floor
     * tablet is shared — see the migration for why that matters.
     */
    public function needsWalkthrough(): bool
    {
        return $this->walkthrough_seen_at === null;
    }

    public function markWalkthroughSeen(): void
    {
        // forceFill + saveQuietly: this is not a change to the maintenance
        // record and has no business in the activity log.
        $this->forceFill(['walkthrough_seen_at' => now()])->saveQuietly();
    }

    public function resetWalkthrough(): void
    {
        $this->forceFill(['walkthrough_seen_at' => null])->saveQuietly();
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
