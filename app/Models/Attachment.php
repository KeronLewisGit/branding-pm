<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Accessors ────────────────────────────────────────────────────

    /**
     * Public URL on whichever disk the attachment was stored on.
     */
    /**
     * Display URL — the authorised media route, never a direct disk URL.
     *
     * MediaController re-checks the policy of whatever this photo hangs off
     * before streaming it. A plain public-disk URL served fault photos to
     * anybody who could guess a path (seed-notes §D11).
     */
    public function getUrlAttribute(): string
    {
        return route('media.attachment', ['attachment' => $this]);
    }
}
