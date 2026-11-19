<?php

return [

    // Wall-clock time zone used for publish dates and deadlines in forms and tables.
    // Dates are stored in UTC and converted at the edges.
    'timezone' => env('CONTENT_TIMEZONE', 'Asia/Jakarta'),

    // "Automatic" deadline = publish date minus this many hours.
    'deadline_hours' => 24,

    'caption_limit' => 2200,
    'max_tags' => 30,
    'max_references' => 10,

    // Suggestions shown in the Content Pillar / Format fields (people may still type their own).
    'pillars' => ['Educational', 'Promotional', 'Entertaining', 'Inspirational', 'Community', 'Product'],
    'formats' => ['Photo', 'Illustration', 'Infographic', 'Quote', 'Video', 'Motion graphic', 'Testimonial', 'Tutorial', 'Behind the scenes', 'Announcement'],

];
