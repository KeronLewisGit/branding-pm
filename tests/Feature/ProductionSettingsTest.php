<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Production configuration
|--------------------------------------------------------------------------
| The settings that cannot be enforced from inside the code, only verified:
| a value in .env, a URL scheme, an account somebody forgot to remove.
*/

/**
 * A directory holding one backup taken a moment ago.
 *
 * The security check wants a *recent* dump, and pointing it at the real
 * storage/backups would make these tests pass or fail depending on whether
 * the machine running them happens to have taken one.
 */
function freshBackupFixture(): string
{
    $path = storage_path('framework/testing/prod-backups');

    File::deleteDirectory($path);
    File::makeDirectory($path, 0755, true);
    File::put($path.'/branding_pm-fixture.sql.gz', gzencode(str_repeat('-- dump ', 5000)));

    return $path;
}

it('defaults secure cookies to on in production and off elsewhere', function (): void {
    // config/session.php resolves this from APP_ENV, so a production host
    // gets HTTPS-only cookies without anybody remembering to set it — and a
    // plain-http:// pilot is not locked out of its own login page.
    $resolve = fn (string $appEnv, mixed $explicit = null) => $explicit
        ?? ($appEnv === 'production');

    expect($resolve('production'))->toBeTrue()
        ->and($resolve('local'))->toBeFalse()
        // An explicit false still wins — that is the pilot override.
        ->and($resolve('production', false))->toBeFalse();

    // And the shipped config expresses exactly that.
    $config = file_get_contents(config_path('session.php'));

    expect($config)->toContain("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')");
});

it('defaults APP_DEBUG to off', function (): void {
    // A missing APP_DEBUG must never mean "on".
    expect(file_get_contents(config_path('app.php')))
        ->toContain("'debug' => (bool) env('APP_DEBUG', false)");
});

it('passes its own security check when configured for production', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'app.debug' => false,
        'app.env' => 'production',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://pm.example.com',
        'session.secure' => true,
        'checklists.signature_disk' => 'local',
        'mail.default' => 'smtp',
        'mail.from.address' => 'no-reply@pm.example.com',
        'backups.path' => freshBackupFixture(),
    ]);

    $this->artisan('security:check')->assertSuccessful();
});

it('fails the security check on the things that expose a production host', function (array $overrides, string $expected): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    config(array_merge([
        'app.debug' => false,
        'app.env' => 'production',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://pm.example.com',
        'session.secure' => true,
        'checklists.signature_disk' => 'local',
        'mail.default' => 'smtp',
        'mail.from.address' => 'no-reply@pm.example.com',
        'backups.path' => freshBackupFixture(),
    ], $overrides));

    $this->artisan('security:check')
        ->expectsOutputToContain($expected)
        ->assertFailed();
})->with([
    'debug on' => [['app.debug' => true], 'APP_DEBUG'],
    'insecure cookies' => [['session.secure' => false], 'Secure cookies'],
    'plain http' => [['app.url' => 'http://pm.example.com'], 'APP_URL'],
    'signatures public' => [['checklists.signature_disk' => 'public'], 'Signature storage'],
    'no app key' => [['app.key' => ''], 'APP_KEY'],
    // A reset email written to a file leaves a locked-out user locked out.
    'mail going to a log file' => [['mail.default' => 'log'], 'Mail'],
    // The compliance record with no copy of it.
    'no backups' => [['backups.path' => '/nonexistent-backup-path'], 'Backups'],
]);

it('fails the security check while demo accounts are still active in production', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // These ship with the password "password" and PINs published in the repo.
    User::factory()->create(['employee_number' => 'OP-1001', 'is_active' => true]);

    config([
        'app.debug' => false,
        'app.env' => 'production',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://pm.example.com',
        'session.secure' => true,
        'checklists.signature_disk' => 'local',
        'mail.default' => 'smtp',
        'mail.from.address' => 'no-reply@pm.example.com',
        'backups.path' => freshBackupFixture(),
    ]);

    $this->artisan('security:check')
        ->expectsOutputToContain('Demo accounts')
        ->assertFailed();
});

it('warns rather than fails outside production', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    // A pilot on plain HTTP with debug on is a legitimate state; the command
    // says so without blocking, and --strict is how a deploy script refuses.
    config([
        'app.debug' => true,
        'app.env' => 'local',
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'http://192.168.1.10:8088',
        'session.secure' => false,
        'checklists.signature_disk' => 'local',
    ]);

    $this->artisan('security:check')->assertSuccessful();
    $this->artisan('security:check --strict')->assertFailed();
});

it('fails when signatures are still sitting on the public disk', function (): void {
    // The config saying "local" is not the same as the disk being clean.
    // Signatures written before that setting changed stay on the public disk,
    // and public/storage is a symlink — so they are fetchable with no login.
    // One was found that way: HTTP 200, and no database row referenced it.
    $this->seed(RolesAndPermissionsSeeder::class);

    $stray = storage_path('app/public/'.config('checklists.signature_path').'/runs/9999');
    File::makeDirectory($stray, 0755, true);
    File::put($stray.'/operator-leaked.png', 'not really a png');

    try {
        config([
            'app.debug' => false,
            'app.env' => 'production',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://pm.example.com',
            'session.secure' => true,
            'checklists.signature_disk' => 'local',
            'mail.default' => 'smtp',
            'mail.from.address' => 'no-reply@pm.example.com',
            'backups.path' => freshBackupFixture(),
        ]);

        $this->artisan('security:check')
            ->expectsOutputToContain('PUBLIC disk')
            ->assertFailed();
    } finally {
        File::deleteDirectory(storage_path('app/public/'.config('checklists.signature_path').'/runs/9999'));
    }
});
