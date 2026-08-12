<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who else gets a copy when an account's sign-in details are emailed.
 *
 * A setting rather than an address in the source. The person who wants the
 * copy today is a named individual, and a name in a repository is both a
 * published personal address and a thing that becomes wrong the moment they
 * change role — quietly, since nothing fails when mail keeps going to somebody
 * who has left.
 *
 * Nullable, and nothing is copied when it is empty. Note what a copy means:
 * that mailbox accumulates the credentials of everybody ever created, which is
 * useful for a handover record and is also a single place worth compromising.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $table): void {
            $table->string('credentials_cc', 190)->nullable()->after('from_name');
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $table): void {
            $table->dropColumn('credentials_cc');
        });
    }
};
