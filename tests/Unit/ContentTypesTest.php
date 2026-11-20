<?php

namespace Tests\Unit;

use App\Support\ContentTypes;
use Tests\TestCase;

class ContentTypesTest extends TestCase
{
    public function test_every_type_in_the_config_is_complete_and_sensible(): void
    {
        $keys = [];

        foreach (config('content_types.platforms') as $platform => $definition) {
            $this->assertNotEmpty($definition['label'], "platform $platform needs a label");
            $this->assertNotEmpty($definition['types'], "platform $platform needs types");

            foreach ($definition['types'] as $key => $type) {
                $this->assertStringStartsWith($platform.'_', $key, "key $key should start with its platform");
                $this->assertNotContains($key, $keys, "duplicate key $key");
                $keys[] = $key;

                $this->assertContains($type['icon'], ['image', 'carousel', 'reel', 'story', 'feed', 'video', 'flyer', 'kit'], "$key icon");
                $this->assertContains($type['preview'], ['feed', 'vertical', 'story', 'document'], "$key preview");
                [$min, $max] = $type['media'];
                $this->assertTrue($min >= 1 && $min <= $max, "$key media range");
                $this->assertIsBool($type['caption']['required']);
                $this->assertGreaterThan(0, $type['caption']['limit']);
            }
        }

        $this->assertSame($keys, ContentTypes::keys());
    }

    public function test_the_tree_groups_types_under_their_platform_in_config_order(): void
    {
        $tree = ContentTypes::tree();

        $this->assertSame(['instagram', 'facebook', 'tiktok', 'other'], array_column($tree, 'key'));
        $this->assertSame(
            ['instagram_single', 'instagram_carousel', 'instagram_reel', 'instagram_story'],
            array_column($tree[0]['types'], 'key'),
        );
        $this->assertSame(['facebook_feed', 'facebook_reel', 'facebook_story'], array_column($tree[1]['types'], 'key'));
        $this->assertSame(['tiktok_video', 'tiktok_carousel'], array_column($tree[2]['types'], 'key'));
        $this->assertSame(['other_flyer', 'other_sales_kit'], array_column($tree[3]['types'], 'key'));
    }

    public function test_labels_and_rules(): void
    {
        $this->assertSame('Instagram Single Post', ContentTypes::label('instagram_single'));
        $this->assertSame('TikTok Video', ContentTypes::label('tiktok_video'));
        $this->assertSame('Flyer', ContentTypes::label('other_flyer')); // "Other" adds no prefix
        $this->assertSame('mystery', ContentTypes::label('mystery'));

        $carousel = ContentTypes::find('instagram_carousel');
        $this->assertSame([2, 20, true], [$carousel['min_media'], $carousel['max_media'], $carousel['multiple']]);
        $this->assertFalse(ContentTypes::find('instagram_story')['caption_required']);
        $this->assertTrue(ContentTypes::find('instagram_reel')['caption_required']);
        $this->assertNull(ContentTypes::find('instagram_feed')); // renamed to instagram_single
        $this->assertNull(ContentTypes::find(null));
    }

    public function test_order_follows_the_config_and_ignores_unknown_keys(): void
    {
        $this->assertSame(
            ['instagram_story', 'facebook_feed', 'other_flyer'],
            ContentTypes::order(['other_flyer', 'nope', 'facebook_feed', 'instagram_story']),
        );
    }
}
