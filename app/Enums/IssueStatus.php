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
}
