<?php

// Only the settings we override; the rest comes from the Inertia package defaults.
return [

    // Our page components live in resources/js/Pages (capital P).
    'pages' => [
        'ensure_pages_exist' => false,
        'paths' => [resource_path('js/Pages')],
        'extensions' => ['js', 'vue'],
    ],

];
