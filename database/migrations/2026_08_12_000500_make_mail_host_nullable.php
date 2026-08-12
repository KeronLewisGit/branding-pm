<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An API relay has no host and no port, so stop requiring one.
 *
 * These were NOT NULL because the table was written for SMTP, where both are
 * essential. When the SendGrid API transport arrived it had neither, and the
 * gap was filled by storing the literal string `api.sendgrid.com` and port 587
 * — values that look like configuration, describe nothing, and are not what the
 * API uses. It was a placeholder wearing the costume of a setting.
 *
 * That mattered beyond tidiness. The fallback for a missing SendGrid package
 * read those columns back and tried to open an SMTP session against them,
 * producing an authentication failure for a problem that had nothing to do
 * with authentication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table): void {
            $table->string('host', 190)->nullable()->change();
            $table->unsignedSmallInteger('port')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table): void {
            $table->string('host', 190)->nullable(false)->change();
            $table->unsignedSmallInteger('port')->nullable(false)->default(587)->change();
        });
    }
};
