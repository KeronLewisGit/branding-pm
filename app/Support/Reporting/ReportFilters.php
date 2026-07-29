<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\User;
use App\Support\MachineScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The filter set every report accepts (milestone 7).
 *
 * Built once from either the Livewire viewer or an export request, so the
 * table on screen and the CSV/PDF that follows it cannot disagree about what
 * was asked for. The machine scope is part of the filter, not an afterthought:
 * a report a supervisor exports must cover exactly the machines they may see.
 */
final class ReportFilters
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?int $machineId,
        public readonly ?int $locationId,
        public readonly User $user,
    ) {}

    public static function make(
        User $user,
        ?string $from = null,
        ?string $to = null,
        ?int $machineId = null,
        ?int $locationId = null,
    ): self {
        $timezone = (string) config('app.display_timezone', 'UTC');

        // Default window: the last 30 days, inclusive of today.
        $end = self::parseDate($to, $timezone) ?? Carbon::today($timezone);
        $start = self::parseDate($from, $timezone) ?? $end->copy()->subDays(29);

        // A backwards range is a typo, not an intent — swap rather than
        // silently return nothing.
        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return new self($start, $end, $machineId, $locationId, $user);
    }

    public static function fromRequest(Request $request, User $user): self
    {
        return self::make(
            user: $user,
            from: $request->query('from') !== null ? (string) $request->query('from') : null,
            to: $request->query('to') !== null ? (string) $request->query('to') : null,
            machineId: $request->query('machine') !== null ? (int) $request->query('machine') : null,
            locationId: $request->query('location') !== null ? (int) $request->query('location') : null,
        );
    }

    /**
     * Machine ids in scope AND matching the filters — the subquery every
     * report joins against.
     */
    public function machineIds(): Builder
    {
        return MachineScope::for($this->user)
            ->when($this->machineId !== null, fn (Builder $q) => $q->where('machines.id', $this->machineId))
            ->when($this->locationId !== null, fn (Builder $q) => $q->where('machines.location_id', $this->locationId))
            ->select('machines.id');
    }

    public function label(): string
    {
        return $this->from->format('j M Y').' — '.$this->to->format('j M Y');
    }

    /**
     * @return array<string, string|null>
     */
    public function toQuery(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'machine' => $this->machineId !== null ? (string) $this->machineId : null,
            'location' => $this->locationId !== null ? (string) $this->locationId : null,
        ];
    }

    private static function parseDate(?string $value, string $timezone): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone)->startOfDay();
        } catch (\Throwable) {
            return null; // an unparseable date falls back to the default window
        }
    }
}
