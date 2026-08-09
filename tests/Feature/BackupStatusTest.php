<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| backup:status
|--------------------------------------------------------------------------
| Backups fail quietly — a container nobody restarted, a full disk. This
| command exists to be the thing that notices, so it has to fail loudly when
| there is nothing recent, and it must not pass merely because a directory
| exists.
*/

beforeEach(function (): void {
    $this->backupPath = storage_path('framework/testing/backups');

    File::deleteDirectory($this->backupPath);
    File::makeDirectory($this->backupPath, 0755, true);

    config(['backups.path' => $this->backupPath, 'backups.max_age_hours' => 36]);
});

afterEach(function (): void {
    File::deleteDirectory($this->backupPath);
});

/**
 * A plausible dump: gzip content, a checksum beside it, and an age.
 */
function fakeBackup(string $path, string $name, int $hoursOld, bool $withChecksum = true): string
{
    $file = $path.DIRECTORY_SEPARATOR.$name;

    File::put($file, gzencode(str_repeat('-- INSERT INTO checklist_runs …', 2000)));

    if ($withChecksum) {
        File::put($file.'.sha256', hash_file('sha256', $file));
    }

    touch($file, now()->subHours($hoursOld)->getTimestamp());

    return $file;
}

it('fails when there is no backup at all', function (): void {
    $this->artisan('backup:status')
        ->expectsOutputToContain('No backups found')
        ->assertFailed();
});

it('fails when the directory does not even exist', function (): void {
    config(['backups.path' => $this->backupPath.'/nowhere']);

    $this->artisan('backup:status')->assertFailed();
});

it('passes on a recent backup', function (): void {
    fakeBackup($this->backupPath, 'branding_pm-2026-08-09_023000.sql.gz', hoursOld: 2);

    $this->artisan('backup:status')
        ->expectsOutputToContain('checksum matches')
        ->assertSuccessful();
});

it('fails once the newest backup is older than the limit', function (): void {
    // The failure this command exists for: backups stopped days ago and
    // everything still looks configured.
    fakeBackup($this->backupPath, 'branding_pm-2026-08-05_023000.sql.gz', hoursOld: 100);

    $this->artisan('backup:status')
        ->expectsOutputToContain('Backups have stopped running')
        ->assertFailed();
});

it('judges staleness on the newest, not the oldest', function (): void {
    fakeBackup($this->backupPath, 'branding_pm-2026-08-01_023000.sql.gz', hoursOld: 200);
    fakeBackup($this->backupPath, 'branding_pm-2026-08-09_023000.sql.gz', hoursOld: 3);

    $this->artisan('backup:status')->assertSuccessful();
});

it('honours an explicit --max-age', function (): void {
    fakeBackup($this->backupPath, 'branding_pm-2026-08-09_023000.sql.gz', hoursOld: 10);

    $this->artisan('backup:status', ['--max-age' => 48])->assertSuccessful();
    $this->artisan('backup:status', ['--max-age' => 4])->assertFailed();
});

it('reports a dump that has changed since it was written', function (): void {
    // Bit-rot, a truncated copy, or a file somebody edited. The row counts
    // would look fine; the restore would not.
    $file = fakeBackup($this->backupPath, 'branding_pm-2026-08-09_023000.sql.gz', hoursOld: 1);

    File::put($file, gzencode('something else entirely'));
    touch($file, now()->subHour()->getTimestamp());

    $this->artisan('backup:status')->expectsOutputToContain('CHECKSUM MISMATCH');
});

it('says so when a dump has no checksum beside it', function (): void {
    fakeBackup($this->backupPath, 'branding_pm-2026-08-09_023000.sql.gz', hoursOld: 1, withChecksum: false);

    $this->artisan('backup:status')
        ->expectsOutputToContain('no checksum recorded')
        ->assertSuccessful();
});

it('ignores files that are not dumps', function (): void {
    File::put($this->backupPath.'/notes.txt', 'not a backup');
    File::put($this->backupPath.'/.gitignore', '*');

    $this->artisan('backup:status')
        ->expectsOutputToContain('No backups found')
        ->assertFailed();
});

/*
|--------------------------------------------------------------------------
| The script that actually takes them
|--------------------------------------------------------------------------
*/

it('ships a backup script that refuses to keep a suspiciously small dump', function (): void {
    // Guarded here because it is the difference between a failed backup and a
    // good one being silently replaced by an error page.
    $script = file_get_contents(base_path('docker/backup/backup.sh'));

    expect($script)
        ->toContain('--single-transaction')      // consistent without locking the floor out
        ->toContain('gzip -t')                   // catches a dump truncated by a full disk
        ->toContain('refusing to keep it')       // the size floor
        ->toContain('.partial')                  // never mistake an interrupted dump for a good one
        ->toContain('sha256sum');
});

it('keeps the backup directory out of git', function (): void {
    // Dumps contain every operator's name, PIN hash and password hash.
    expect(file_get_contents(base_path('storage/backups/.gitignore')))
        ->toContain('*')
        ->and(trim(shell_exec('git -C '.escapeshellarg(base_path()).' check-ignore storage/backups/probe.sql.gz || echo NOT_IGNORED')))
        ->not->toBe('NOT_IGNORED');
});
