<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * `backup:status` — is there a recent backup, and how big is it?
 *
 * Backups do not usually fail loudly. They stop happening: a container that
 * was never restarted, a full disk, a renamed volume. The dump you reach for
 * is then months old, and nothing ever said so.
 *
 * This exists to be the thing that says so. It exits **1** when the newest
 * backup is older than `backups.max_age_hours`, so it can be a monitored
 * check rather than something somebody remembers to look at.
 */
class BackupStatus extends Command
{
    protected $signature = 'backup:status {--max-age= : Hours before the newest backup counts as stale}';

    protected $description = 'Report on the database backups and fail if the newest one is stale';

    public function handle(): int
    {
        $path = (string) config('backups.path');
        $maxAge = (int) ($this->option('max-age') ?? config('backups.max_age_hours'));

        $this->components->info('Backups — '.$path);

        $backups = $this->backups($path);

        if ($backups === []) {
            $this->components->error(
                'No backups found. Is the `backup` container running? `docker compose ps backup`'
            );

            return self::FAILURE;
        }

        $newest = $backups[0];
        $age = CarbonImmutable::createFromTimestamp($newest['mtime']);
        $hours = (int) $age->diffInHours(CarbonImmutable::now());
        $stale = $hours > $maxAge;

        $this->newLine();

        $this->components->twoColumnDetail(
            ($stale ? '<fg=red>STALE</>' : '<fg=green>OK</>').'  Newest',
            basename($newest['file']).'  ('.$age->diffForHumans().')'
        );

        $this->components->twoColumnDetail('      Size', $this->humanBytes($newest['bytes']));

        $this->components->twoColumnDetail(
            '      Kept',
            count($backups).' backup(s), '.$this->humanBytes(array_sum(array_column($backups, 'bytes')))
                .' total, retention '.config('backups.retention_days').' days'
        );

        // A dump that cannot be read back is not a backup. The checksum is
        // written beside each file when it is taken; a mismatch means the file
        // has rotted or been truncated since.
        $this->components->twoColumnDetail('      Integrity', $this->verify($newest['file']));

        $offsiteFailed = $this->reportOffsite($path);

        $this->newLine();

        if ($offsiteFailed) {
            $this->components->error(
                'Backups exist but are not leaving this machine. A failed disk takes both copies.'
            );

            return self::FAILURE;
        }

        if ($stale) {
            $this->components->error(
                "The newest backup is {$hours} hours old (limit {$maxAge}). Backups have stopped running."
            );

            return self::FAILURE;
        }

        $this->components->info('Backups are current.');

        return self::SUCCESS;
    }

    /**
     * Report on the copies sent to the network share.
     *
     * Returns true when off-site is configured and something is wrong with
     * it. Judged on **when the status file was last written**, not on what it
     * says: an unreachable share stops the container mounting at all, so the
     * script never runs to record its own failure and the last good file sits
     * there reading "ok".
     */
    private function reportOffsite(string $path): bool
    {
        $configured = trim((string) config('backups.offsite.share')) !== '';
        $file = rtrim($path, '/\\').DIRECTORY_SEPARATOR.config('backups.offsite.status_file');

        if (! is_file($file)) {
            $this->components->twoColumnDetail(
                ($configured ? '<fg=red>MISSING</>' : '      ').'  Off-site',
                $configured
                    ? 'a share is configured but nothing has been copied — is `docker compose --profile offsite up -d` running?'
                    : 'not configured (set BACKUP_OFFSITE_* in .env)'
            );

            return $configured;
        }

        $status = json_decode((string) file_get_contents($file), true);
        $ranAt = CarbonImmutable::createFromTimestamp((int) filemtime($file));
        $hours = (int) $ranAt->diffInHours(CarbonImmutable::now());
        $limit = (int) config('backups.offsite.max_age_hours');

        $failed = ! is_array($status) || ($status['failed'] ?? 1) > 0;
        $stale = $hours > $limit;

        $detail = is_array($status)
            ? ($status['held_offsite'] ?? '?').' on '.($status['destination'] ?? 'the share')
                .', last run '.$ranAt->diffForHumans()
            : 'the status file could not be read';

        if ($stale) {
            // The container is not running, or cannot mount the share.
            $detail .= ' — nothing has run for '.$hours.'h (limit '.$limit.'h)';
        } elseif ($failed && is_array($status)) {
            $detail .= ' — '.($status['message'] ?? 'failed');
        }

        $this->components->twoColumnDetail(
            (($stale || $failed) ? '<fg=red>FAILING</>' : '<fg=green>OK</>').'  Off-site',
            $detail
        );

        return $stale || $failed;
    }

    /**
     * Newest first.
     *
     * @return list<array{file: string, bytes: int, mtime: int}>
     */
    private function backups(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $found = [];

        foreach (glob(rtrim($path, '/\\').DIRECTORY_SEPARATOR.'*.sql.gz') ?: [] as $file) {
            $found[] = [
                'file' => $file,
                'bytes' => (int) filesize($file),
                'mtime' => (int) filemtime($file),
            ];
        }

        usort($found, fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $found;
    }

    private function verify(string $file): string
    {
        $expected = $file.'.sha256';

        if (! is_file($expected)) {
            return 'no checksum recorded';
        }

        return hash_file('sha256', $file) === trim((string) file_get_contents($expected))
            ? 'checksum matches'
            : 'CHECKSUM MISMATCH — this file has changed since it was written';
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return round($bytes, 1).' '.$unit;
            }

            $bytes = intdiv($bytes, 1024);
        }

        return $bytes.' B';
    }
}
