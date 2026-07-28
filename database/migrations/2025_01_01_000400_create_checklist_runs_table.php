<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            // Snapshot of checklist_templates.version at generation time.
            $table->unsignedInteger('template_version')->default(1);
            $table->date('scheduled_for')->index();
            // 'all' means "not shift-split". NOT NULL on purpose: a nullable shift
            // would break the unique index below (NULLs never collide in MySQL),
            // letting the generator create duplicate runs.
            $table->enum('shift', ['day', 'night', 'all'])->default('all');
            $table->enum('status', ['pending', 'in_progress', 'submitted', 'approved', 'rejected', 'missed'])
                ->default('pending')
                ->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_signature_path', 255)->nullable();
            $table->timestamp('operator_signed_at')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_signature_path', 255)->nullable();
            $table->timestamp('supervisor_signed_at')->nullable();
            $table->text('supervisor_comment')->nullable();
            // The "Notes:" box on the paper sheet.
            $table->text('notes')->nullable();
            $table->unsignedInteger('downtime_minutes')->nullable();
            $table->timestamps();

            // Makes checklists:generate idempotent — one run per template/date/shift.
            $table->unique(['checklist_template_id', 'scheduled_for', 'shift'], 'runs_template_date_shift_unique');
            $table->index(['machine_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_runs');
    }
};
