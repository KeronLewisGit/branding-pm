<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The first migration since the original 22.
 *
 * A kiosk device was implicitly a tablet: nothing recorded what the hardware
 * actually was, so the admin screen could only ever offer one way to enrol it
 * (carry it over and scan a QR) — useless for a laptop or a panel PC, which
 * cannot scan a code displayed on their own screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table): void {
            // Declared by an administrator. Existing rows were all tablets by
            // assumption, and the default keeps that true.
            $table->enum('kind', ['tablet', 'laptop', 'desktop', 'phone', 'other'])
                ->default('tablet')
                ->after('name');

            // What actually enrolled / last used the device, so the fleet list
            // can flag a "tablet" being driven from a laptop. Recorded for
            // display only — it is a client-supplied string and never gates
            // anything. Truncated on write; some User-Agents are enormous.
            $table->string('last_user_agent', 255)->nullable()->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'last_user_agent']);
        });
    }
};
