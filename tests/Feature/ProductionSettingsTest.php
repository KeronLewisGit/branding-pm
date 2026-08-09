<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
|--------------------------------------------------------------------------
| Production configuration
|--------------------------------------------------------------------------
| The settings that cannot be enforced from inside the code, only verified:
| a value in .env, a URL scheme, an account somebody forgot to remove.
*/

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
