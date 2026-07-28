<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            // Nullable on purpose: null = holiday applies to all sites.
            $table->foreignId('site_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('name', 120);
            // Recurring = same day-of-year every year (matched on month+day).
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->unique(['site_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
