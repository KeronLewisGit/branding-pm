<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An operator asking for the browser in front of them to become a kiosk.
 *
 * Enrolling a device is a trust decision, so `kiosk.activate` stays with
 * supervisors and up. Without this table the only options for an operator at
 * an unenrolled tablet were to find a supervisor or to be stuck, and being
 * stuck is what people work around.
 *
 * A request is not an enrolment. It records who asked, from what, and when;
 * a supervisor decides. The `claim_token` is the part that makes the whole
 * thing work: enrolment sets a cookie, and a cookie can only be set on the
 * browser making the request — a supervisor approving from their own desk
 * cannot enrol somebody else's tablet. The token is issued to the asking
 * browser and redeemed by it once approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_enrolment_requests', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('requested_by_id')->constrained('users')->cascadeOnDelete();

            // Redeemed by the asking browser after approval. Unique so a
            // redeemed or stale token can never match two requests.
            $table->string('claim_token', 64)->unique();

            // What the operator suggests calling it. A supervisor may
            // overrule it at approval — they are the one who has to find the
            // device in a fleet list afterwards.
            $table->string('suggested_name', 120);

            // Why they need it. Optional, and the only free text here.
            $table->string('note', 500)->nullable();

            // Same shape as kiosk_devices.device_info, from DeviceReport.
            // Client-supplied and forgeable: it exists so a human can tell one
            // black tablet from another, and authorises nothing.
            $table->json('device_info')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('requested_ip', 45)->nullable();

            $table->enum('status', ['pending', 'approved', 'declined', 'claimed'])
                ->default('pending')
                ->index();

            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('decline_reason', 500)->nullable();

            // Set on approval. nullOnDelete so deleting a device does not take
            // the record of who asked for it with it.
            $table->foreignId('kiosk_device_id')->nullable()->constrained('kiosk_devices')->nullOnDelete();

            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();

            // The review queue reads "pending, oldest first".
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_enrolment_requests');
    }
};
