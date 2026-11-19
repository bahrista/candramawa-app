<?php

namespace App\Enums;

/** Platform + format. Values are prefixed with the platform so more can be added later. */
enum PostType: string
{
    case InstagramFeed = 'instagram_feed';
    case InstagramCarousel = 'instagram_carousel';
    case InstagramReel = 'instagram_reel';
    case InstagramStory = 'instagram_story';

    public function label(): string
    {
        return match ($this) {
            self::InstagramFeed => 'Instagram Feed',
            self::InstagramCarousel => 'Instagram Carousel',
            self::InstagramReel => 'Instagram Reel',
            self::InstagramStory => 'Instagram Story',
        };
    }

    /** Only carousels hold several media links. */
    public function multipleMedia(): bool
    {
        return $this === self::InstagramCarousel;
    }

    public function minMedia(): int
    {
        return $this->multipleMedia() ? 2 : 1;
    }

    public function maxMedia(): int
    {
        return $this->multipleMedia() ? 20 : 1;
    }

    /** Stories are usually text-free, so a caption is optional for them. */
    public function captionRequired(): bool
    {
        return $this !== self::InstagramStory;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
