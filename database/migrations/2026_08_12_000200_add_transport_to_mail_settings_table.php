<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the relay be SendGrid's HTTP API as well as SMTP.
 *
 * Both reach the same service; they differ in how they get out of the
 * building. SMTP opens a socket on 587, which shared hosts and corporate
 * firewalls block often enough that it is the first thing to suspect when mail
 * silently stops. The API is an ordinary HTTPS request to port 443, which
 * anything that can browse the web can make.
 *
 * `api_key` is not a new column: SMTP already stores the key in `password`
 * (encrypted, hidden, kept out of the activity log), and SendGrid's SMTP
 * password IS the API key. A second encrypted secret column would be two
 * places to leak from and two to keep in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table): void {
            // 'smtp' | 'sendgrid_api'. Defaulted to smtp so an existing row
            // keeps doing exactly what it did before this migration ran.
            $table->string('transport', 20)->default('smtp')->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table): void {
            $table->dropColumn('transport');
        });
    }
};
