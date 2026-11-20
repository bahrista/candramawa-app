<?php

/*
|--------------------------------------------------------------------------
| Content types
|--------------------------------------------------------------------------
|
| Platforms are the parents, content types the children. This list is what the
| "Content type" picker, the previews, the validation and the reports are built from.
|
| To add a type (e.g. a YouTube Short) add one entry below; no other code is needed
| unless it needs a brand new preview layout.
|
|   key      unique, never rename or remove one that is in use (existing content keeps it)
|   label    shown under its platform
|   icon     image | carousel | reel | story | feed | video | flyer | kit
|   preview  feed | vertical | story | document   (layout of the live preview)
|   media    [minimum, maximum] number of Google Drive links
|   caption  required? and the character limit
|
| A platform with 'prefix' => false shows type labels on their own ("Flyer").
|
*/

return [

    'platforms' => [

        'instagram' => [
            'label' => 'Instagram',
            'types' => [
                'instagram_single' => ['label' => 'Single Post', 'icon' => 'image', 'preview' => 'feed', 'media' => [1, 1], 'caption' => ['required' => true, 'limit' => 2200]],
                'instagram_carousel' => ['label' => 'Carousel', 'icon' => 'carousel', 'preview' => 'feed', 'media' => [2, 20], 'caption' => ['required' => true, 'limit' => 2200]],
                'instagram_reel' => ['label' => 'Reel', 'icon' => 'reel', 'preview' => 'vertical', 'media' => [1, 1], 'caption' => ['required' => true, 'limit' => 2200]],
                'instagram_story' => ['label' => 'Story', 'icon' => 'story', 'preview' => 'story', 'media' => [1, 1], 'caption' => ['required' => false, 'limit' => 2200]],
            ],
        ],

        'facebook' => [
            'label' => 'Facebook',
            'types' => [
                'facebook_feed' => ['label' => 'Feed', 'icon' => 'feed', 'preview' => 'feed', 'media' => [1, 10], 'caption' => ['required' => true, 'limit' => 5000]],
                'facebook_reel' => ['label' => 'Reel', 'icon' => 'reel', 'preview' => 'vertical', 'media' => [1, 1], 'caption' => ['required' => true, 'limit' => 5000]],
                'facebook_story' => ['label' => 'Story', 'icon' => 'story', 'preview' => 'story', 'media' => [1, 1], 'caption' => ['required' => false, 'limit' => 5000]],
            ],
        ],

        'tiktok' => [
            'label' => 'TikTok',
            'types' => [
                'tiktok_video' => ['label' => 'Video', 'icon' => 'video', 'preview' => 'vertical', 'media' => [1, 1], 'caption' => ['required' => true, 'limit' => 2200]],
                'tiktok_carousel' => ['label' => 'Carousel', 'icon' => 'carousel', 'preview' => 'vertical', 'media' => [2, 35], 'caption' => ['required' => true, 'limit' => 2200]],
            ],
        ],

        'other' => [
            'label' => 'Other',
            'prefix' => false,
            'types' => [
                'other_flyer' => ['label' => 'Flyer', 'icon' => 'flyer', 'preview' => 'document', 'media' => [1, 5], 'caption' => ['required' => false, 'limit' => 2200]],
                'other_sales_kit' => ['label' => 'Sales Kit', 'icon' => 'kit', 'preview' => 'document', 'media' => [1, 20], 'caption' => ['required' => false, 'limit' => 2200]],
            ],
        ],

    ],

];
