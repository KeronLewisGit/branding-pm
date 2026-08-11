<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the parts module.
 *
 * The department does not track part consumption against a checklist — the
 * supervisor confirmed it was never part of the paper process either — so
 * every screen asking for it was asking operators for a number nobody read.
 *
 * The four `create` migrations are deleted rather than kept, so a fresh
 * install never builds these tables at all. `dropIfExists` is what makes both
 * paths work: on the pilot database the tables exist and go; on a fresh one
 * this is a no-op rather than an error.
 *
 * Dropped children-first. `checklist_run_parts` and `checklist_template_parts`
 * both hold a foreign key to `parts`, and MySQL will refuse to drop a parent
 * while a constraint still points at it.
 *
 * `down()` is deliberately empty. Re-creating the tables would give back the
 * schema and none of the data, which is worse than an honest refusal: a
 * rollback that appears to work invites somebody to believe the history came
 * back with it. Restore from a backup instead — see docs/DEPLOYMENT.md §10.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('checklist_run_parts');
        Schema::dropIfExists('checklist_template_parts');
        Schema::dropIfExists('machine_part');
        Schema::dropIfExists('parts');
    }

    public function down(): void
    {
        // Intentionally irreversible — see the note above.
    }
};
