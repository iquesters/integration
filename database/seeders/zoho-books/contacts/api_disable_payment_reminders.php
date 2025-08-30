<?php

return [
    [
        'key' => 'api_disable_payment_reminders',
        'value' => '
            {
                "method": "POST",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}/paymentreminder/disable",
                "query_params": {
                "organization_id": "string"
                },
                "headers": {
                "Authorization": "Zoho-oauthtoken {access_token}"
                },
                "body_schema": {},
                "response_schema": {
                "code": "number",
                "message": "string"
                },
                "oauth_scope": "ZohoBooks.contacts.CREATE"
            }',
        'status' => 'inactive',
    ]
];