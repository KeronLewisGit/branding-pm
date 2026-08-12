<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPassword;
use App\Support\ViewAs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'must_change_password',
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
            'must_change_password' => 'boolean',
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

    /**
     * Every place a user id is recorded as "who did this".
     *
     * All of these are `SET NULL` on delete, which is what makes deleting a
     * user destructive in a way row-counting does not reveal: the row survives
     * and simply stops saying who was responsible. A completed checklist with
     * no operator is worse than no checklist at all, because it still looks
     * like a record.
     *
     * Kept as an explicit list rather than derived at runtime so the decision
     * is reviewable, and guarded by a test that reads the schema and fails if
     * a new foreign key to `users` appears without being considered here.
     *
     * @var array<string, list<string>>
     */
    public const HISTORY_REFERENCES = [
        'checklist_runs' => ['operator_id', 'supervisor_id', 'qa_verified_by'],
        'checklist_run_items' => ['completed_by'],
        'issues' => ['raised_by', 'assigned_to'],
        'attachments' => ['uploaded_by'],
        'kiosk_devices' => ['enrolled_by_id'],
        'kiosk_enrolment_requests' => ['reviewed_by_id'],
        'mail_settings' => ['updated_by_id'],
    ];

    /**
     * Is this person named anywhere in the maintenance record?
     *
     * The question that decides whether deleting the account removes it or
     * merely retires it. An account that has signed, completed, verified,
     * raised or enrolled anything has to be kept, because the record has to
     * keep saying who did it. An account that has done none of those things
     * protects nothing by lingering — and lingering costs something real, since
     * it holds an email address and an employee number that can then never be
     * used again.
     */
    public function hasMaintenanceHistory(): bool
    {
        foreach (self::HISTORY_REFERENCES as $table => $columns) {
            $query = DB::table($table);

            $query->where(function ($q) use ($columns): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, $this->id);
                }
            });

            if ($query->exists()) {
                return true;
            }
        }

        return false;
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

    /**
     * Can this person reset their own password by email, unaided?
     *
     * Three things have to be true, and in this plant none of them can be
     * assumed:
     *
     *   - **They have an email address.** Most floor operators do not. Theirs
     *     is a PIN on a shared tablet, and a PIN is cleared by an
     *     administrator on the users screen, not by email.
     *   - **They already sign in with a password.** A reset restores a way in
     *     that somebody had; it must not hand a password login to an account
     *     that only ever had a PIN.
     *   - **The account is active.** A deactivated account is deactivated.
     */
    public function canResetPasswordByEmail(): bool
    {
        return $this->is_active
            && $this->canLoginWithPassword()
            && $this->email !== null
            && $this->email !== '';
    }

    /**
     * Send the reset link using this application's own copy rather than
     * Laravel's stock consumer-app wording.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
