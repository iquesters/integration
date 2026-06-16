<?php

return [
    'chatbot_util' => [
        'api_url' => env('CHATBOT_UTIL_API_URL', ''),
        'timeout' => (int) env('CHATBOT_UTIL_TIMEOUT', 20),
    ],
];
