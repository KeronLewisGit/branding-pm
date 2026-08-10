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
        'backups.offsite.username' => '',
        'backups.offsite.max_age_hours' => 36,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->backupPath);
});

/**
 * A plausible dump: gzip content, a checksum beside it, and an age.
 */
function fakeFileArchive(string $path, int $hoursOld): string
{
    $file = $path.DIRECTORY_SEPARATOR.'storage-fixture.tar.gz';

    File::put($file, gzencode(str_repeat('signatures/runs/1/operator.png', 200)));
    touch($file, now()->subHours($hoursOld)->getTimestamp());

    return $file;
}

function fakeBackup(string $path, string $name, int $hoursOld, bool $withChecksum = true): string
{
    // Every dump is accompanied by its night's signatures unless a test is
    // specifically about them being absent. Refreshed on each call so the
    // archive tracks the NEWEST dump, which is what staleness is judged on.
    fakeFileArchive($path, $hoursOld);

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

it('treats a share without credentials as unfinished, not broken', function (): void {
    /*
     * The share is usually chosen days before the credentials arrive and the
     * service can start. Reporting that as a failure means the check fails
     * for the whole of that gap — and a check that cries wolf is one nobody
     * reads on the day it matters.
     */
    config(['backups.offsite.share' => '//LH-ARCHIVE/Archive/branding-pm']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);

    $this->artisan('backup:status')
        ->expectsOutputToContain('awaiting credentials')
        ->assertSuccessful();
});

it('fails when a share is configured but nothing has ever been copied', function (): void {
    config([
        'backups.offsite.share' => '//fileserver/backups',
        'backups.offsite.username' => 'svc-pmbackup',
    ]);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);

    $this->artisan('backup:status')
        ->expectsOutputToContain('nothing has been copied')
        ->assertFailed();
});

it('passes when copies are reaching the share', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups', 'backups.offsite.username' => 'svc-pmbackup']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    offsiteStatus($this->backupPath, hoursOld: 1);

    $this->artisan('backup:status')->assertSuccessful();
});

it('fails on a stale status file even though it still says ok', function (): void {
    // THE case this is for. An unreachable share means the container cannot
    // mount and never starts, so nothing rewrites this file. Reading its
    // message would report success while backups sat on one disk for days.
    config(['backups.offsite.share' => '//fileserver/backups', 'backups.offsite.username' => 'svc-pmbackup']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    offsiteStatus($this->backupPath, hoursOld: 72, failed: 0);

    $this->artisan('backup:status')
        ->expectsOutputToContain('nothing has run for 72h')
        ->assertFailed();
});

it('fails when the last run reported a failure', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups', 'backups.offsite.username' => 'svc-pmbackup']);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    offsiteStatus($this->backupPath, hoursOld: 1, failed: 2);

    $this->artisan('backup:status')
        ->expectsOutputToContain('share not reachable')
        ->assertFailed();
});

it('fails on a status file it cannot parse', function (): void {
    config(['backups.offsite.share' => '//fileserver/backups', 'backups.offsite.username' => 'svc-pmbackup']);
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

/*
|--------------------------------------------------------------------------
| The signatures beside the dump
|--------------------------------------------------------------------------
| checklist_runs stores signature PATHS. Restore the database without the
| files and every approved run points at an image that is not there — the
| approval is no longer evidenced by anything.
*/

it('fails when there are dumps but no signatures archived', function (): void {
    File::put($this->backupPath.'/branding_pm-a.sql.gz', gzencode(str_repeat('x', 30000)));
    touch($this->backupPath.'/branding_pm-a.sql.gz', now()->subHour()->getTimestamp());

    $this->artisan('backup:status')
        ->expectsOutputToContain('no storage archive')
        ->assertFailed();
});

it('fails when the signatures archive has fallen behind the dumps', function (): void {
    // A fresh dump beside a week-old archive is not a usable pair: runs
    // signed in between reference files the archive does not hold.
    // The dump first, then the archive aged back — the helper refreshes it.
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);
    fakeFileArchive($this->backupPath, hoursOld: 200);

    $this->artisan('backup:status')
        ->expectsOutputToContain('the dumps have one and the files do not')
        ->assertFailed();
});

it('passes when the dump and its signatures are from the same night', function (): void {
    fakeFileArchive($this->backupPath, hoursOld: 1);
    fakeBackup($this->backupPath, 'branding_pm-a.sql.gz', hoursOld: 1);

    $this->artisan('backup:status')->assertSuccessful();
});

it('ships a backup script that archives the files after the database', function (): void {
    // Order is a correctness property, not a preference. Database first means
    // every path in the dump is already on disk when the archive is taken.
    // Files first would let a run signed in between reference a signature the
    // archive does not contain.
    $script = file_get_contents(base_path('docker/backup/backup.sh'));

    $dumpAt = strpos($script, 'mv "$tmp" "$file"');
    $filesAt = strpos($script, 'take_files ||');

    expect($filesAt)->toBeGreaterThan($dumpAt)
        ->and($script)->toContain('tar -tzf')          // read the archive back
        ->and($script)->toContain('storage-');
});

it('copies the file archives off-site too, not just the database', function (): void {
    // A share holding dumps but no signatures is an incomplete audit record
    // in the one place it most needs to be complete.
    expect(file_get_contents(base_path('docker/backup/offsite.sh')))
        ->toContain('"$LOCAL_DIR"/*.tar.gz');
});

it('keeps signature images out of git', function (): void {
    // These are the audit record — somebody's actual handwritten signature —
    // and they are operational data, not source. Laravel's stock ignore files
    // for storage/app were missing, so every signature written during the
    // pilot had been committed to the repository. They belong in the nightly
    // archive, not in a clone.
    $tracked = shell_exec('git -C '.escapeshellarg(base_path()).' ls-files storage/app');

    $images = array_values(array_filter(
        explode('
', trim((string) $tracked)),
        fn (string $line): bool => $line !== '' && ! str_ends_with($line, '.gitignore'),
    ));

    expect($images)->toBe([], 'Signature images are tracked in git: '.implode(', ', $images));
});

it('ignores newly written signatures on both disks', function (): void {
    $check = fn (string $path): string => trim((string) shell_exec(
        'git -C '.escapeshellarg(base_path()).' check-ignore '.escapeshellarg($path).' || echo NOT_IGNORED'
    ));

    expect($check('storage/app/signatures/runs/1/probe.png'))->not->toBe('NOT_IGNORED')
        ->and($check('storage/app/public/signatures/runs/1/probe.png'))->not->toBe('NOT_IGNORED');
});
