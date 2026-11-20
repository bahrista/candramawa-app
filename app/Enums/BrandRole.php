<?php

namespace App\Enums;

/** A person's job on one brand. The same person can hold several roles on a brand. */
enum BrandRole: string
{
    case BrandOwner = 'brand_owner';
    case ContentManager = 'content_manager';
    case MultimediaCreator = 'multimedia_creator';
    case Copywriter = 'copywriter';
    case Publisher = 'publisher';

    public function label(): string
    {
        return match ($this) {
            self::BrandOwner => 'Brand Owner',
            self::ContentManager => 'Content Manager',
            self::MultimediaCreator => 'Multimedia Creator',
            self::Copywriter => 'Copy Writer',
            self::Publisher => 'Publisher',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BrandOwner => 'Owns the brand. Manages the brand and its team, and reviews content.',
            self::ContentManager => 'Plans content and writes briefs. Manages pillars, knowledge base and products.',
            self::MultimediaCreator => 'Produces the visuals and videos.',
            self::Copywriter => 'Writes captions and copy.',
            self::Publisher => 'Publishes the finished content.',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
