<?php

declare(strict_types=1);

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Machine;
use App\Support\SqlOrder;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Ordering a column by a known list of values
|--------------------------------------------------------------------------
| Three screens each hand-wrote this, and one used MySQL's FIELD(), which
| returns 0 for a value it does not find. Matches start at 1 — so a status
| the string had not been updated for sorted FIRST, above open breakdowns,
| on the screen used to decide what to fix next.
*/

it('ranks values in the order given', function (): void {
    [$sql, $bindings] = SqlOrder::rank('status', ['open', 'closed']);

    $ranked = DB::table(DB::raw("(SELECT 'closed' AS status UNION ALL SELECT 'open') AS t"))
        ->orderByRaw($sql, $bindings)
        ->pluck('status')
        ->all();

    expect($ranked)->toBe(['open', 'closed']);
});

it('puts a value it does not know about last, not first', function (): void {
    // The whole point. `FIELD()` put it first, silently.
    [$sql, $bindings] = SqlOrder::rank('status', ['open', 'closed']);

    $ranked = DB::table(DB::raw(
        "(SELECT 'deferred' AS status UNION ALL SELECT 'closed' UNION ALL SELECT 'open') AS t"
    ))->orderByRaw($sql, $bindings)->pluck('status')->all();

    expect($ranked)->toBe(['open', 'closed', 'deferred']);
});

it('splits the listed values to the front and leaves the rest behind', function (): void {
    [$sql, $bindings] = SqlOrder::first('status', ['open']);

    $ranked = DB::table(DB::raw("(SELECT 'closed' AS status UNION ALL SELECT 'open') AS t"))
        ->orderByRaw($sql, $bindings)
        ->pluck('status')
        ->all();

    expect($ranked)->toBe(['open', 'closed']);
});

it('takes no values from the caller into the SQL string itself', function (): void {
    // The column name is ours; everything variable is bound.
    [$sql, $bindings] = SqlOrder::rank('status', ["'; DROP TABLE issues; --"]);

    expect($sql)->not->toContain('DROP')
        ->and($bindings)->toBe(["'; DROP TABLE issues; --"]);
});

it('orders the issue register the way the register claims to', function (): void {
    $machine = Machine::factory()->create();

    // Deliberately inserted worst-first so row order cannot fake the result.
    $closedBreakdown = Issue::factory()->for($machine)->create([
        'status' => IssueStatus::Closed,
        'severity' => IssueSeverity::Breakdown,
    ]);
    $openLow = Issue::factory()->for($machine)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Low,
    ]);
    $openBreakdown = Issue::factory()->for($machine)->create([
        'status' => IssueStatus::Open,
        'severity' => IssueSeverity::Breakdown,
    ]);

    $ordered = Issue::query()
        ->where('machine_id', $machine->id)
        ->orderByRaw(...SqlOrder::first('status', array_column(IssueStatus::openStatuses(), 'value')))
        ->orderByRaw(...SqlOrder::rank('severity', IssueSeverity::mostUrgentFirst()))
        ->pluck('id')
        ->all();

    // Open before closed; within open, a breakdown before anything else.
    expect($ordered)->toBe([$openBreakdown->id, $openLow->id, $closedBreakdown->id]);
});
