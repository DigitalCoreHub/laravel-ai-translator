<?php

return [
    'provider' => 'openai',

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'auto_create_missing_files' => true,
];
