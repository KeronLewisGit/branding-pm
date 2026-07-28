<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            // Nullable: an issue may be raised ad hoc, not from a run or item.
            $table->foreignId('checklist_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('checklist_run_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('severity', ['low', 'medium', 'high', 'breakdown'])->default('medium');
            $table->text('description');
            $table->enum('status', ['open', 'acknowledged', 'in_progress', 'resolved', 'closed'])
                ->default('open')
                ->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
