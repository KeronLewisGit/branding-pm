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
        $hours = $age->diffInHours(CarbonImmutable::now());
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

        $this->newLine();

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
