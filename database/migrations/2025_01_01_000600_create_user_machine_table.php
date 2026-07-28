<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pivot: which machines an operator is assigned. An operator with no rows here
// falls back to all machines at their default_site_id (see MachineScope).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_machine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_machine');
    }
};
