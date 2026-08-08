<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When this person was last shown the first-run walkthrough.
 *
 * On the **user**, not in the browser's localStorage, because the shop-floor
 * tablet is shared: local storage would show the walkthrough to whoever
 * happened to use that tablet first and to nobody afterwards, while showing
 * it again to the same person on the next tablet along. Exactly backwards.
 *
 * Nullable, and not backfilled: every existing account is treated as never
 * having seen it, which for a first release is the correct answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('walkthrough_seen_at')->nullable()->after('pin_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('walkthrough_seen_at');
        });
    }
};
