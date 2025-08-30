<?php

return [
    [
        'key' => 'api_get_statement_mail_content',
        'value' => '
            {
                "method": "GET",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}/statements/email",
                "query_params": {
                "organization_id": "string"
                },
                "headers": {
                "Authorization": "Zoho-oauthtoken {access_token}"
                },
                "body_schema": {},
                "response_schema": {
                "code": "number",
                "message": "string",
                "data": {
                    "body": "string",
                    "subject": "string",
                    "to_contacts": [
                    {
                        "first_name": "string",
                        "selected": "boolean",
                        "phone": "string",
                        "email": "string",
                        "contact_person_id": "number",
                        "last_name": "string",
                        "salutation": "string",
                        "mobile": "string"
                    }
                    ],
                    "file_name": "string",
                    "from_emails": [
                    {
                        "user_name": "string",
                        "selected": "boolean",
                        "email": "string"
                    }
                    ],
                    "contact_id": "number"
                }
                },
                "oauth_scope": "ZohoBooks.contacts.READ"
            }',
        'status' => 'inactive',
    ]
];