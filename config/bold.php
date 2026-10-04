<?php

return [
    'identity_key' => env('BOLD_IDENTITY_KEY'),
    'integrity_key' => env('BOLD_INTEGRITY_KEY'),
    'webhook_secret' => env('BOLD_WEBHOOK_SECRET', env('BOLD_INTEGRITY_KEY')),
    'currency' => env('BOLD_CURRENCY', 'COP'),
    'button_style' => env('BOLD_BUTTON_STYLE', 'dark-L'),
    'redirection_url' => env('BOLD_REDIRECTION_URL'),
];
