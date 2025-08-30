<?php

return [
    [
        'key' => 'api_delete_a_contact',
        'value' => '
            {
                "method": "DELETE",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}",
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
                "oauth_scope": "ZohoBooks.contacts.DELETE"
            }',
        'status' => 'inactive',
    ]
];