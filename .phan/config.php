<?php

declare(strict_types=1);

return [
    'directory_list' => [
        'src',
        'vendor',
    ],
    'exclude_file_regex' => '@^vendor/.*/(tests?|Tests?)/@',
    'exclude_analysis_directory_list' => [
        'vendor/',
    ],
];
