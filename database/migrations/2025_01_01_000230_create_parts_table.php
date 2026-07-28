<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            // External part ID — a string, never an integer: one catalogue code
            // is literally "XXX". Do not cast to int anywhere.
            $table->string('part_code', 32)->unique();
            $table->string('name', 190);
            $table->string('unit', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
