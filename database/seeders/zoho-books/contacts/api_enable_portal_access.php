<?php

return [
    [
        'key' => 'api_enable_portal_access',
        'value' => '
            {
                "method": "POST",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}/portal/enable",
                "query_params": {
                "organization_id": "string"
                },
                "headers": {
                "Authorization": "Zoho-oauthtoken {access_token}",
                "Content-Type": "application/json"
                },
                "body_schema": {
                "contact_persons": [
                    {
                    "contact_person_id": "number"
                    }
                ]
                },
                "response_schema": {
                "code": "number",
                "message": "string"
                },
                "oauth_scope": "ZohoBooks.contacts.CREATE"
            }',
        'status' => 'inactive',
    ]
];