<?php

declare(strict_types=1);

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __('app.issue_status.'.$this->value);
    }

    /**
     * @return array<string, string> value => label, for select boxes.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Statuses that still count as an open issue.
     *
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return [self::Open, self::Acknowledged, self::InProgress];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openStatuses(), true);
    }

    /**
     * Legal next statuses. The register offers exactly these and the detail
     * component refuses anything else, so a stale button in an old tab cannot
     * drive an issue backwards through the workflow.
     *
     * Resolved and Closed both reopen to `open` rather than to whatever they
     * were before: if a repair did not hold, the issue starts again.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::InProgress, self::Resolved],
            self::Acknowledged => [self::InProgress, self::Resolved],
            self::InProgress => [self::Resolved],
            self::Resolved => [self::Closed, self::Open],
            self::Closed => [self::Open],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedNext(), true);
    }

    /**
     * Tailwind token for the status badge. Paired with a text label
     * everywhere — status is never carried by colour alone (contract §8).
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'rose',
            self::Acknowledged => 'amber',
            self::InProgress => 'sky',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
        };
    }
}
