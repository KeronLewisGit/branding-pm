<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The "Used Parts" table printed on each paper sheet, attached per template.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_template_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['checklist_template_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_template_parts');
    }
};
