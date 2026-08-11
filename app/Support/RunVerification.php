<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;

/**
 * The verification hash printed in the footer of every run PDF
 * (SPEC §"PDF Export").
 *
 * What it is for: somebody holding a printed sheet can ask the system whether
 * that sheet still matches the record. If an approved run were ever altered,
 * the hash regenerated from the database would no longer match the one on the
 * paper, and the discrepancy is visible without trusting either copy.
 *
 * What it is NOT: a signature. It is a digest over the record, salted with
 * the application key so it cannot be recomputed by someone who only has the
 * printout. Anyone with the key can regenerate it — that is enough for
 * "has this sheet been altered?", and no more is claimed.
 *
 * The inputs are only the fields that constitute the record: change any item
 * status, signature or timestamp and the hash changes.
 *
 * Part quantities were an input until the parts module was removed. Dropping
 * them changes the hash of every run computed from here on, so a verification
 * code printed before that change will not match one printed after it. That
 * was accepted knowingly: the runs carrying part rows were DemoSeeder
 * fabrication, no real sheet had yet been filed, and freezing a dead field
 * into the audit hash forever to avoid the break would have been worse.
 */
final class RunVerification
{
    public static function hash(ChecklistRun $run): string
    {
        $run->loadMissing(['items']);

        $payload = [
            'run' => $run->id,
            'template' => $run->checklist_template_id.'@'.$run->template_version,
            'machine' => $run->machine_id,
            'scheduled_for' => $run->scheduled_for?->toDateString(),
            'shift' => $run->shift->value,
            'status' => $run->status->value,
            'submitted_at' => $run->submitted_at?->toIso8601String(),
            'operator' => $run->operator_id.'|'.$run->operator_signed_at?->toIso8601String(),
            'supervisor' => $run->supervisor_id.'|'.$run->supervisor_signed_at?->toIso8601String(),
            'items' => $run->items
                ->sortBy('sort_order')
                ->map(fn (ChecklistRunItem $item): string => implode(':', [
                    $item->sort_order,
                    $item->status->value,
                    (string) $item->value_numeric,
                    (string) $item->value_text,
                    (string) $item->fail_reason,
                ]))
                ->implode(';'),
            'notes' => (string) $run->notes,
        ];

        $digest = hash_hmac(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );

        // 16 hex characters, grouped — long enough that a collision is not a
        // practical concern, short enough to be read off paper and typed.
        return strtoupper(implode('-', str_split(substr($digest, 0, 16), 4)));
    }

    /**
     * True when a hash read off a printed sheet still matches the record.
     * Used by the verification check in a later milestone; kept beside the
     * generator so the two cannot drift.
     */
    public static function matches(ChecklistRun $run, string $candidate): bool
    {
        return hash_equals(
            self::hash($run),
            strtoupper(trim($candidate)),
        );
    }
}
