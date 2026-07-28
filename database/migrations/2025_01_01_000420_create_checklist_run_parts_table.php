<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_run_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->nullable()->constrained()->nullOnDelete();
            // Code + name snapshotted at generation time so the record survives
            // part edits/deletions.
            $table->string('part_code_snapshot', 32);
            $table->string('part_name_snapshot', 190);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('qty_used', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_run_parts');
    }
};
