<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ChecklistRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Signature capture (milestone 5) — the single place a canvas data URL is
 * validated, stored and removed.
 *
 * The client sends `data:image/png;base64,…`. That string is untrusted input:
 * it arrives as a Livewire action argument, so nothing about it — the MIME
 * label, the size, the fact it is even an image — may be believed. Every
 * check below is performed on the DECODED bytes, never on the header the
 * client wrote.
 *
 * Stored under `config('checklists.signature_path')` on
 * `config('checklists.signature_disk')`, one folder per run:
 *
 *     signatures/runs/128/operator-9f2c….png
 *
 * The filename is random, never the user id — a signature image is a
 * biometric-ish record and its path ends up in a PDF footer in milestone 7.
 */
final class SignatureImage
{
    /** Roughly a full-width 3× pad of dense ink; a genuine signature is far smaller. */
    public const MAX_BYTES = 512 * 1024;

    public const MAX_DIMENSION = 4000;

    private const PREFIX = 'data:image/png;base64,';

    /**
     * True when the value really is a small PNG image, decoded and inspected.
     */
    public static function isValid(mixed $dataUrl): bool
    {
        return is_string($dataUrl) && self::decode($dataUrl) !== null;
    }

    /**
     * Raw PNG bytes, or null if the value is not a PNG data URL within limits.
     */
    public static function decode(string $dataUrl): ?string
    {
        if (! str_starts_with($dataUrl, self::PREFIX)) {
            return null;
        }

        // Length check BEFORE decoding: a 100 MB string must not be expanded
        // into memory just to be rejected. base64 is 4 bytes per 3 decoded.
        if (strlen($dataUrl) > (int) ceil(self::MAX_BYTES * 4 / 3) + strlen(self::PREFIX)) {
            return null;
        }

        // Strict mode: any character outside the base64 alphabet fails
        // instead of being silently skipped.
        $binary = base64_decode(substr($dataUrl, strlen(self::PREFIX)), true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        // The decisive check — the file's own header, not the client's label.
        $size = @getimagesizefromstring($binary);

        if ($size === false || ($size[2] ?? null) !== IMAGETYPE_PNG) {
            return null;
        }

        if ($size[0] < 1 || $size[1] < 1
            || $size[0] > self::MAX_DIMENSION
            || $size[1] > self::MAX_DIMENSION) {
            return null;
        }

        return $binary;
    }

    /**
     * Store a validated signature and return its path on the signature disk.
     *
     * @param  'operator'|'supervisor'  $role
     *
     * @throws \InvalidArgumentException when the data URL did not validate —
     *                                   callers validate first, so reaching this is a programming error.
     */
    public static function store(string $dataUrl, ChecklistRun $run, string $role): string
    {
        $binary = self::decode($dataUrl);

        if ($binary === null) {
            throw new \InvalidArgumentException('Signature is not a valid PNG data URL.');
        }

        $path = sprintf(
            '%s/runs/%d/%s-%s.png',
            trim((string) config('checklists.signature_path', 'signatures'), '/'),
            $run->id,
            $role,
            Str::random(24),
        );

        Storage::disk(self::disk())->put($path, $binary);

        return $path;
    }

    /**
     * Remove a superseded signature file. Used when a rejected run is signed
     * and resubmitted — the old image is no longer the record and would
     * otherwise be orphaned on disk forever.
     */
    public static function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::disk())->delete($path);
    }

    /**
     * Display URL for a run's signature.
     *
     * Points at MediaController, which re-checks the run's own view policy
     * before streaming a byte. It used to return a plain public-disk URL that
     * anybody could fetch unauthenticated (seed-notes §D11) — a signature is
     * the nearest thing this system holds to a biometric.
     */
    public static function url(ChecklistRun $run, string $role): string
    {
        return route('media.signature', ['run' => $run, 'role' => $role]);
    }

    private static function disk(): string
    {
        return self::diskName();
    }

    /**
     * The configured signature disk.
     *
     * Public so MediaController can stream from the same place this class
     * writes to — there must be exactly one answer to "where do signatures
     * live", or a hardened URL would serve from a different folder than the
     * one the signing code fills.
     */
    public static function diskName(): string
    {
        return (string) config('checklists.signature_disk', 'local');
    }
}
