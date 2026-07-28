<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pivot: which parts are normally consumed on which machine, so the run form
// pre-lists them in the right order.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['machine_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_part');
    }
};
