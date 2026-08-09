<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Drop the `settings` table.
 *
 * It was a second, silent configuration surface. Ten rows were seeded — shift
 * boundaries, kiosk idle timeout, PIN attempts and lockout, grace period,
 * compliance target, display timezone — and **nothing ever read one of them**.
 * The values actually in force come from `config/checklists.php` and
 * `config('app.display_timezone')`, backed by environment variables.
 *
 * Two rows were worse than duplicates: `shift.*` and
 * `reports.compliance_target_percent` had no implementation anywhere, in
 * config or in code.
 *
 * The danger was that it read as authoritative. Somebody editing
 * `kiosk.idle_timeout_seconds` here would reasonably expect the kiosk to
 * change, and nothing would happen — and the seeder's own comment referred to
 * "the settings screen", which was never built.
 *
 * All ten rows were still at their seeded defaults when this was written, so
 * nothing configured was lost. `down()` restores the table and the seeder can
 * refill it, should a settings screen ever be built — though configuration
 * that must be readable by the scheduler and the queue belongs in config,
 * not in a row somebody can edit into an invalid state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('settings');
    }

    public function down(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });
    }
};
