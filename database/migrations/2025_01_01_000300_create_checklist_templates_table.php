<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->string('name', 190);
            $table->enum('work_category', ['daily', 'weekly', 'general']);
            $table->text('work_description');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'on_demand']);
            // true => generator creates a 'day' run and a 'night' run per due date;
            // false => one run with shift = 'all'.
            $table->boolean('per_shift')->default(false);
            // ISO weekday (1=Mon ... 7=Sun) a weekly template is due on.
            $table->unsignedTinyInteger('weekly_weekday')->default(1);
            // Day of month (1-28) a monthly template is due on.
            $table->unsignedTinyInteger('monthly_day')->default(1);
            $table->boolean('requires_supervisor_signoff')->default(true);
            // Hours after end of scheduled day before a pending run is marked missed.
            $table->unsignedSmallInteger('grace_period_hours')->default(24);
            // Incremented on item changes; snapshotted onto runs.
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['machine_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_templates');
    }
};
