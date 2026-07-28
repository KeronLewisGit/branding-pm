<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Floor identifier — printed on badges, used for kiosk login. Not the PK.
            $table->string('employee_number', 32)->unique();
            $table->string('full_name', 160);
            // Nullable: floor operators may have no company email. Unique when present.
            $table->string('email', 190)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            // Nullable: PIN-only operators have no password.
            $table->string('password')->nullable();
            // Hash::make() of a 4-6 digit PIN; hidden on the model.
            $table->string('pin')->nullable();
            $table->timestamp('pin_set_at')->nullable();
            $table->boolean('is_active')->default(true);
            // References sites.id, but sites is created later — the foreign key
            // constraint is added in 2025_01_01_000200_create_sites_table.php.
            $table->foreignId('default_site_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
