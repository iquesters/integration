<?php

return [
    [
        'key' => 'api_list_contacts',
        'value' => '
            {
                "method": "GET",
                "url": "https://www.zohoapis.in/books/v3/contacts",
                "query_params": {
                "organization_id": "string",
                "filter_by": "string",
                "sort_column": "string",
                "page": "number",
                "per_page": "number"
                },
                "headers": {
                "Authorization": "Zoho-oauthtoken {access_token}"
                },
                "body_schema": {},
                "response_schema": {
                "code": "number",
                "message": "string",
                "contacts": [
                    {
                    "contact_id": "number",
                    "contact_name": "string",
                    "company_name": "string",
                    "contact_type": "string",
                    "status": "string",
                    "payment_terms": "number",
                    "payment_terms_label": "string",
                    "currency_id": "number",
                    "currency_code": "string",
                    "outstanding_receivable_amount": "number",
                    "unused_credits_receivable_amount": "number",
                    "first_name": "string",
                    "last_name": "string",
                    "email": "string",
                    "phone": "string",
                    "mobile": "string",
                    "created_time": "string",
                    "last_modified_time": "string"
                    }
                ],
                "page_context": {
                    "page": "number",
                    "per_page": "number",
                    "has_more_page": "boolean",
                    "applied_filter": "string",
                    "sort_column": "string",
                    "sort_order": "string"
                }
                },
                "oauth_scope": "ZohoBooks.contacts.READ",
                "response_schema_id_key" : "contacts/contact_id",
                "req_schema_required_keys" : [""]
            }',
        'status' => 'active',
    ]
];