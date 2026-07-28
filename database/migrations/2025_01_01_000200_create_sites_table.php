<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 32)->unique();
            // ISO-8601 weekday numbers, e.g. [1,2,3,4,5,6] = Mon-Sat.
            // MySQL cannot take a literal default on a JSON column, so this is
            // nullable and the model/seeder supplies the default [1,2,3,4,5,6].
            $table->json('working_days')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // users.default_site_id is declared in 0001_01_01_000000_create_users_table.php,
        // before sites exists — the constraint is added here instead.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('default_site_id')
                ->references('id')
                ->on('sites')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_site_id']);
        });

        Schema::dropIfExists('sites');
    }
};
