<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\Issue;
use App\Models\Machine;
use App\Models\MailSetting;
use App\Models\User;
use App\Policies\ChecklistRunPolicy;
use App\Policies\ChecklistTemplatePolicy;
use App\Policies\IssuePolicy;
use App\Policies\MachinePolicy;
use App\Policies\MailSettingPolicy;
use App\Policies\UserPolicy;
use App\Support\MailRelay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * Laravel 11 has no AuthServiceProvider — policies are registered here.
     */
    public function boot(): void
    {
        Gate::policy(ChecklistRun::class, ChecklistRunPolicy::class);
        Gate::policy(ChecklistTemplate::class, ChecklistTemplatePolicy::class);
        Gate::policy(Machine::class, MachinePolicy::class);
        Gate::policy(Issue::class, IssuePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(MailSetting::class, MailSettingPolicy::class);

        // Surface N+1 queries everywhere except production. Mass assignment
        // stays guarded — every model declares an explicit $fillable.
        Model::preventLazyLoading(! $this->app->isProduction());

        /*
         * The relay configured in the application wins over .env, when one is
         * saved AND enabled. Deliberately here rather than in a config file:
         * config files are cached, and a cached config cannot read a database.
         *
         * It never throws — see MailRelay. A boot-time override that can fail
         * is one that can take the site down, and this application has already
         * been stranded once by exactly that shape of problem.
         */
        MailRelay::registerTransports();
        MailRelay::apply();

        $this->warnAboutUnsafeProductionSettings();
    }

    /**
     * Shout, in the log, when a production host is running with settings that
     * expose it.
     *
     * Deliberately a log line and not an exception. Refusing to boot would
     * take the checklists off the shop floor for a configuration mistake, and
     * an application that will not start gets "fixed" by whoever is on shift
     * — usually by deleting the check. `php artisan security:check` is the
     * gate; this is the thing that keeps saying so afterwards.
     */
    private function warnAboutUnsafeProductionSettings(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        if (config('app.debug')) {
            Log::critical(
                'APP_DEBUG is enabled in production. Any error page now exposes the '
                .'stack trace, the failing query and the entire .env — including '
                .'APP_KEY and the database password. Set APP_DEBUG=false.'
            );
        }

        if (! config('session.secure')) {
            Log::critical(
                'SESSION_SECURE_COOKIE is disabled in production. Session and kiosk '
                .'cookies will be sent over plain HTTP, where anyone on the same '
                .'network can read and replay them. Serve over HTTPS and set it true.'
            );
        }
    }
}
