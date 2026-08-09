<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Record what a kiosk device is, and who turned it into one.
 *
 * A device could previously only say when it was last seen and what
 * User-Agent it last sent. With activation now possible from the machine
 * sticker — by anybody holding `kiosk.manage`, standing anywhere — "which
 * phone is this, and who made it a kiosk?" became a question the fleet list
 * has to answer.
 *
 * On MAC addresses, since that is what gets asked for: a browser cannot
 * report one. There is no web API for it, and a MAC is a link-layer address
 * that is never carried in an HTTP request. Even server-side it is only
 * visible on the same network segment, and behind Docker's NAT the
 * application sees the gateway rather than the tablet. `device_info` holds
 * what a browser genuinely can report; the durable identity of a device is
 * its enrolment token, which — unlike a MAC — can be revoked and rotated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table): void {
            // Screen size, platform, touch support and the rest. JSON because
            // browsers keep changing what they will tell you, and this is
            // descriptive information for a human reading the fleet list —
            // nothing is ever authorised on the strength of it.
            $table->json('device_info')->nullable()->after('last_user_agent');

            $table->timestamp('enrolled_at')->nullable()->after('device_info');

            $table->foreignId('enrolled_by_id')->nullable()->after('enrolled_at')
                ->constrained('users')->nullOnDelete();

            $table->string('enrolled_ip', 45)->nullable()->after('enrolled_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('enrolled_by_id');
            $table->dropColumn(['device_info', 'enrolled_at', 'enrolled_ip']);
        });
    }
};
