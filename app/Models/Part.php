<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Part extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'part_code',
        'name',
        'unit',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class, 'machine_part')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistTemplate::class, 'checklist_template_parts')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
