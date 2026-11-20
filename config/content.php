<?php

return [

    // Wall-clock time zone used for publish dates and deadlines in forms and tables.
    // Dates are stored in UTC and converted at the edges.
    'timezone' => env('CONTENT_TIMEZONE', 'Asia/Jakarta'),

    // "Automatic" deadline = publish date minus this many hours.
    'deadline_hours' => 24,

    'max_tags' => 30,
    'max_references' => 15,

    // Suggestions for the "category" field of a knowledge base entry (people may type their own).
    'knowledge_categories' => ['Brand story', 'Tone of voice', 'Target audience', 'Guidelines', 'FAQ', 'Competitors', 'Other'],

];
