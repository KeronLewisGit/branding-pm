<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_template_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order');
            // Snapshot of the template item text at generation time — a run completed
            // in March must still show March's wording. Never read the template for display.
            $table->string('description', 500);
            $table->enum('response_type', ['check', 'pass_fail', 'numeric', 'text'])->default('check');
            $table->boolean('is_required')->default(true);
            $table->enum('status', ['pending', 'done', 'not_applicable', 'failed'])->default('pending');
            $table->decimal('value_numeric', 12, 3)->nullable();
            $table->text('value_text')->nullable();
            $table->string('fail_reason', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['checklist_run_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_run_items');
    }
};
