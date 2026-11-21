<?php

return [
    'chatbot_util' => [
        'api_url' => env('CHATBOT_UTIL_API_URL', ''),
        'timeout' => (int) env('CHATBOT_UTIL_TIMEOUT', 20),
        // Capture at config:cache time; request-time env() may be unavailable.
        'internal_token' => env('CHATBOT_UTIL_INTERNAL_TOKEN', ''),
    ],
];
