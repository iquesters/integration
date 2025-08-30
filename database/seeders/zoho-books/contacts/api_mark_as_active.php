<?php

return [
    [
        'key' => 'api_mark_as_active',
        'value' => '
            {
                "method": "POST",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}/active",
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