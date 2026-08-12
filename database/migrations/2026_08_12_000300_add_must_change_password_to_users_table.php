<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Force a password change after an administrator issues one.
 *
 * A password somebody else chose, sent through email, is a shared secret from
 * the moment it is sent — it exists in a mailbox, in that mailbox's backups,
 * and in whatever the administrator wrote it on. Making the first sign-in
 * replace it bounds how long that matters to a single login.
 *
 * A flag rather than a `password_changed_at` timestamp. The question asked on
 * every request is "must this person change it now", and a boolean answers
 * that without the reader having to know what a null timestamp is supposed to
 * mean. When the password was last changed is already in the activity log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
