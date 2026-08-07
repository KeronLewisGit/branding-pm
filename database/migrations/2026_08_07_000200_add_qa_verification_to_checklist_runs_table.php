<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A third sign-off: Quality Assurance verification.
 *
 * The paper form had two signature blocks — operator and supervisor — and the
 * system mirrored that exactly. A QA officer checking that the work was
 * actually done is a separate act from the supervisor approving it, performed
 * by somebody who did neither the work nor the approval, so it needs its own
 * columns rather than overloading the supervisor ones.
 *
 * Nullable throughout: verification is a later step, and every run approved
 * before this existed is simply unverified. Nothing backfills, because
 * inventing a verification nobody performed is exactly the kind of thing this
 * column exists to make impossible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_runs', function (Blueprint $table): void {
            $table->foreignId('qa_verified_by')
                ->nullable()
                ->after('supervisor_comment')
                // nullOnDelete like the other two: people leave, and their
                // verification stays part of the record.
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('qa_verified_at')->nullable()->after('qa_verified_by');

            // A QA finding — what they noticed while verifying. Optional: most
            // verifications have nothing to say.
            $table->text('qa_comment')->nullable()->after('qa_verified_at');

            // The QA queue is "approved and not yet verified", and the
            // compliance reports filter on it.
            $table->index(['status', 'qa_verified_at'], 'runs_status_qa_verified_index');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_runs', function (Blueprint $table): void {
            $table->dropIndex('runs_status_qa_verified_index');
            $table->dropConstrainedForeignId('qa_verified_by');
            $table->dropColumn(['qa_verified_at', 'qa_comment']);
        });
    }
};
