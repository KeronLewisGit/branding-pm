<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail relay settings, editable in the application.
 *
 * This project removed a generic `settings` table on purpose — "a row somebody
 * can edit into an invalid state is a worse home for configuration than a file
 * under version control" — and that reasoning still holds for anything the
 * scheduler or queue has to read. Mail is the exception worth making: the
 * relay is the one setting that changes for reasons outside the plant (an
 * expired API key, a switched provider), and needing SSH to fix a password
 * reset is how a locked-out supervisor stays locked out for a day.
 *
 * The original objection is answered by shape rather than by ignoring it:
 * typed columns instead of free-text key/value rows, validation on the way in,
 * and a test-send on the screen so a wrong value fails while somebody is
 * looking at it rather than at the next password reset.
 *
 * ONE row, id 1. Not a key/value store: there is exactly one relay, and a
 * table that can hold two is a table that will eventually disagree with
 * itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table): void {
            $table->id();

            $table->string('host', 190);
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username', 190)->nullable();

            // Encrypted at rest via the model's `encrypted` cast. A SendGrid
            // API key is a send-as-you credential, and this column is in every
            // nightly dump.
            $table->text('password')->nullable();

            // 'tls' | 'ssl' | null. Named for the config key it feeds
            // (config/mail.php reads MAIL_ENCRYPTION), NOT for MAIL_SCHEME,
            // which nothing in this application reads.
            $table->string('encryption', 10)->nullable()->default('tls');

            $table->string('from_address', 190);
            $table->string('from_name', 190);

            // Off until somebody has proved it works with the test button.
            // Until then the .env values stay in force, so switching this on
            // is a decision rather than a side effect of saving a half-filled
            // form.
            $table->boolean('is_active')->default(false);

            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 500)->nullable();

            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
