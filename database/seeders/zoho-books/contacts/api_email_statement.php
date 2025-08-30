<?php

return [
    [
        'key' => 'api_email_statement',
        'value' => '
            {
                "method": "POST",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}/statements/email",
                "query_params": {
                "organization_id": "string"
                },
                "headers": {
                "Authorization": "Zoho-oauthtoken {access_token}",
                "Content-Type": "application/json"
                },
                "body_schema": {
                "send_from_org_email_id": "boolean",
                "to_mail_ids": ["string"],
                "cc_mail_ids": ["string"],
                "subject": "string",
                "body": "string"
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