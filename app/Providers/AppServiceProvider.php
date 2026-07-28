<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\ChecklistRun;
use App\Models\ChecklistTemplate;
use App\Models\Issue;
use App\Models\Machine;
use App\Models\User;
use App\Policies\ChecklistRunPolicy;
use App\Policies\ChecklistTemplatePolicy;
use App\Policies\IssuePolicy;
use App\Policies\MachinePolicy;
use App\Policies\UserPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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

        // Surface N+1 queries everywhere except production. Mass assignment
        // stays guarded — every model declares an explicit $fillable.
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
