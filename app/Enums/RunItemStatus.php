<?php

declare(strict_types=1);

namespace App\Enums;

enum RunItemStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case NotApplicable = 'not_applicable';
    case Failed = 'failed';

    public function label(): string
    {
        return __('app.item_status.'.$this->value);
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
     * Tailwind colour token for the item status indicator.
     * Never rely on this colour alone — always pair with a text label or icon.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'item-pending',
            self::Done => 'item-done',
            self::NotApplicable => 'item-not_applicable',
            self::Failed => 'item-failed',
        };
    }
}
