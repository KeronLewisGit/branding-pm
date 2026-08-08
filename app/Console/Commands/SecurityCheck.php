<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * `php artisan security:check` — pre-deployment verification.
 *
 * Every item here is something that has to be right on the live host and
 * cannot be enforced from inside the code: a setting in `.env`, a URL scheme,
 * an account somebody forgot to remove. The deployment guide listed most of
 * them as prose, which is a checklist nobody runs.
 *
 * Exits non-zero when anything FAILS, so it can gate a deploy script.
 * Warnings do not fail the run — they are things that are legitimate in a
 * pilot and wrong in production.
 */
class SecurityCheck extends Command
{
    protected $signature = 'security:check {--strict : Treat warnings as failures}';

    protected $description = 'Verify this installation is configured safely for production';

    /** @var list<array{status: string, name: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        // config('app.env'), not app()->isProduction(): the container binds
        // `env` once at bootstrap, so isProduction() cannot see a runtime
        // change and the check would silently grade a production config
        // against pilot rules.
        $production = config('app.env') === 'production';

        $this->components->info('Security check — APP_ENV='.config('app.env'));

        $this->checkDebug($production);
        $this->checkAppKey();
        $this->checkSecureCookies($production);
        $this->checkHttps($production);
        $this->checkSignatureDisk();
        $this->checkDemoAccounts($production);
        $this->checkAdminPassword();

        $this->newLine();

        foreach ($this->results as $result) {
            match ($result['status']) {
                'pass' => $this->components->twoColumnDetail(
                    '<fg=green>PASS</>  '.$result['name'], $result['detail']
                ),
                'warn' => $this->components->twoColumnDetail(
                    '<fg=yellow>WARN</>  '.$result['name'], $result['detail']
                ),
                default => $this->components->twoColumnDetail(
                    '<fg=red>FAIL</>  '.$result['name'], $result['detail']
                ),
            };
        }

        $failures = $this->count('fail');
        $warnings = $this->count('warn');

        $this->newLine();

        if ($failures > 0) {
            $this->components->error($failures.' check(s) failed. Do not put this on a production network.');

            return self::FAILURE;
        }

        if ($warnings > 0 && $this->option('strict')) {
            $this->components->error($warnings.' warning(s), and --strict was given.');

            return self::FAILURE;
        }

        if ($warnings > 0) {
            $this->components->warn($warnings.' warning(s) — fine for a pilot, not for go-live.');

            return self::SUCCESS;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }

    /**
     * The one that leaks everything: an exception page with APP_DEBUG on
     * prints the stack trace, the query that failed and the whole `.env`,
     * including the database password and APP_KEY.
     */
    private function checkDebug(bool $production): void
    {
        if (! config('app.debug')) {
            $this->addPass('APP_DEBUG', 'off');

            return;
        }

        $production
            ? $this->addFail('APP_DEBUG', 'ON in production — an error page exposes .env, including APP_KEY and the database password')
            : $this->addWarn('APP_DEBUG', 'on (fine for '.config('app.env').', never for production)');
    }

    /**
     * APP_KEY also keys the run-sheet verification hashes, so losing or
     * changing it invalidates every printed sheet.
     */
    private function checkAppKey(): void
    {
        $key = (string) config('app.key');

        $key === ''
            ? $this->addFail('APP_KEY', 'not set — sessions, cookies and run-sheet hashes all depend on it')
            : $this->addPass('APP_KEY', 'set (back it up with the database)');
    }

    private function checkSecureCookies(bool $production): void
    {
        if (config('session.secure')) {
            $this->addPass('Secure cookies', 'session and kiosk cookies are HTTPS-only');

            return;
        }

        $production
            ? $this->addFail('Secure cookies', 'SESSION_SECURE_COOKIE is false — a session cookie sent over plain HTTP can be read off the network and replayed')
            : $this->addWarn('Secure cookies', 'off — required before go-live, and requires HTTPS to work');
    }

    private function checkHttps(bool $production): void
    {
        $url = (string) config('app.url');

        if (str_starts_with($url, 'https://')) {
            $this->addPass('APP_URL', $url);

            return;
        }

        $production
            ? $this->addFail('APP_URL', $url.' is not HTTPS — PINs and passwords cross the network in the clear')
            : $this->addWarn('APP_URL', $url.' (HTTP is fine on a closed pilot; it is not fine on go-live)');
    }

    /**
     * `public` is served directly by the web server; anything on it is a URL
     * anybody can fetch without logging in.
     */
    private function checkSignatureDisk(): void
    {
        $disk = (string) config('checklists.signature_disk');

        $disk === 'public'
            ? $this->addFail('Signature storage', 'on the public disk — signatures become guessable, unauthenticated URLs')
            : $this->addPass('Signature storage', $disk.' (served only through MediaController)');
    }

    /**
     * The demo accounts ship with the password "password" and PINs 4321 and
     * 2468, all of which are in the repository.
     */
    private function checkDemoAccounts(bool $production): void
    {
        $demo = User::query()
            ->whereIn('employee_number', ['OP-1001', 'OP-1002', 'SUP-2001', 'MM-3001', 'QA-4001'])
            ->where('is_active', true)
            ->pluck('employee_number');

        if ($demo->isEmpty()) {
            $this->addPass('Demo accounts', 'none active');

            return;
        }

        $detail = 'active: '.$demo->implode(', ').' — passwords and PINs are published in the repository';

        $production
            ? $this->addFail('Demo accounts', $detail)
            : $this->addWarn('Demo accounts', $detail);
    }

    /**
     * The old published default. Any install seeded before it was removed
     * still has it unless somebody changed the password.
     */
    private function checkAdminPassword(): void
    {
        $admin = User::query()->firstWhere('employee_number', 'ADMIN-0001');

        if ($admin === null || $admin->password === null) {
            $this->addPass('Admin password', 'no default admin account, or no password set');

            return;
        }

        Hash::check('ChangeMe!2026', $admin->password)
            ? $this->addFail('Admin password', 'still the old published default — change it now')
            : $this->addPass('Admin password', 'not the published default');
    }

    private function addPass(string $name, string $detail): void
    {
        $this->results[] = ['status' => 'pass', 'name' => $name, 'detail' => $detail];
    }

    private function addWarn(string $name, string $detail): void
    {
        $this->results[] = ['status' => 'warn', 'name' => $name, 'detail' => $detail];
    }

    private function addFail(string $name, string $detail): void
    {
        $this->results[] = ['status' => 'fail', 'name' => $name, 'detail' => $detail];
    }

    private function count(string $status): int
    {
        return count(array_filter($this->results, fn (array $r): bool => $r['status'] === $status));
    }
}
