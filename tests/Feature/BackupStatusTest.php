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

    config([
        'backups.path' => $this->backupPath,
        'backups.max_age_hours' => 36,
        // Off-site defaults to unconfigured, so the existing cases test the
        // local backups alone. The off-site cases opt in.
        'backups.offsite.share' => '',
        'backups.offsite.max_age_hours' => 36,
    ]);
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

/*
|--------------------------------------------------------------------------
| Off-site copies
|--------------------------------------------------------------------------
| The copier writes a status file the application reads. Freshness of that
| FILE is the signal, never its contents: an unreachable share stops the
| container mounting, so it never runs to record its own failure and the last
| good status sits there saying "ok".
*/

function offsiteStatus(string $path, int $hoursOld, int $failed = 0, int $held = 3): void
{
    $file = $path.DIRECTORY_SEPARATOR.'.offsite-status.json';

    File::put($file, json_encode([
        'ran_at' => now()->subHours($hoursOld)->toIso8601String(),
        'destination' => '//fileserver/backups',
        'copied' => 1,
        'verified' => 1,
        'failed' => $failed,
        'held_offsite' => $held,
        'message' => $failed > 0 ? 'share not reachable or not writable' : 'ok',
    ], JSON_PRETTY_PRINT));

    touch($file, now()->subHours($hoursOld)->getTimestamp());
}

it('says nothing is wrong when no share is configured', function (): void {
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);

    $this->artisan('backup:status')
        ->expectsOutputToContain('not configured')
        ->assertSuccessful();
});

it('fails when a share is configured but nothing has ever been copied', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);

    $this->artisan('backup:status')
        ->expectsOutputToContain('nothing has been copied')
        ->assertFailed();
});

it('passes when copies are reaching the share', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    offsiteStatus($this->backupPath, hoursOld: 1);

    $this->artisan('backup:status')->assertSuccessful();
});

it('fails on a stale status file even though it still says ok', function (): void {
    // THE case this is for. An unreachable share means the container cannot
    // mount and never starts, so nothing rewrites this file. Reading its
    // message would report success while backups sat on one disk for days.
    config(['backups.offsite.share' => '//fileserver/backups']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    offsiteStatus($this->backupPath, hoursOld: 72, failed: 0);

    $this->artisan('backup:status')
        ->expectsOutputToContain('nothing has run for 72h')
        ->assertFailed();
});

it('fails when the last run reported a failure', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    offsiteStatus($this->backupPath, hoursOld: 1, failed: 2);

    $this->artisan('backup:status')
        ->expectsOutputToContain('share not reachable')
        ->assertFailed();
});

it('fails on a status file it cannot parse', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    File::put($this->backupPath.'/.offsite-status.json', 'not json');

    $this->artisan('backup:status')->assertFailed();
});

it('ships a copier that verifies from the share, not from the source', function (): void {
    // A network copy that half-succeeds leaves a file with the right name and
    // the wrong contents. Checksumming the source against itself would pass.
    $script = file_get_contents(base_path('docker/backup/offsite.sh'));

    expect($script)
        ->toContain('sha256sum "${dest}.partial"')   // read back FROM the share
        ->toContain('.partial')                      // never occupy the real name mid-transfer
        ->toContain('write_status')                  // so the app can see the result
        ->toContain('touch "${OFFSITE_DIR}/.write-probe"');   // a dropped mount still looks like a directory
});
