<?php

return [
    // Default true preserves existing installations. Turn off only after B08 is deployed.
    'legacy_admin_writes_enabled' => (bool) env('KNORAI_LEGACY_INTEGRATION_WRITES_ENABLED', true),
    'chatbot_util' => [
        'api_url' => env('CHATBOT_UTIL_API_URL', ''),
        'timeout' => (int) env('CHATBOT_UTIL_TIMEOUT', 20),
        // Capture at config:cache time; request-time env() may be unavailable.
        'internal_token' => env('CHATBOT_UTIL_INTERNAL_TOKEN', ''),
    ],
];
