<?php

namespace App\Enums;

enum ContentStatus: string
{
    case Draft = 'draft';
    case InProduction = 'in_production';
    case InReview = 'in_review';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProduction => 'In Production',
            self::InReview => 'In Review',
        };
    }
}
