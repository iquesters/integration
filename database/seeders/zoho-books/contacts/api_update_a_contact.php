<?php

return [
    [
        'key' => 'api_update_a_contact',
        'value' => '
            {
                "method": "PUT",
                "url": "https://www.zohoapis.com/books/v3/contacts/{contact_id}",
                "query_params": {
                "organization_id": "string"
                },
                "headers": {
                "Authorization": "Zoho-oauthtoken {access_token}",
                "Content-Type": "application/json"
                },
                "body_schema": {
                "contact_name": "string",
                "company_name": "string",
                "website": "string",
                "language_code": "string",
                "contact_type": "string",
                "customer_sub_type": "string",
                "credit_limit": "number",
                "tags": [
                    {
                    "tag_id": "number",
                    "tag_option_id": "number"
                    }
                ],
                "is_portal_enabled": "boolean",
                "currency_id": "number",
                "payment_terms": "number",
                "payment_terms_label": "string",
                "notes": "string",
                "billing_address": {
                    "attention": "string",
                    "address": "string",
                    "street2": "string",
                    "state_code": "string",
                    "city": "string",
                    "state": "string",
                    "zip": "string",
                    "country": "string",
                    "fax": "string",
                    "phone": "string"
                },
                "shipping_address": {
                    "attention": "string",
                    "address": "string",
                    "street2": "string",
                    "state_code": "string",
                    "city": "string",
                    "state": "string",
                    "zip": "string",
                    "country": "string",
                    "fax": "string",
                    "phone": "string"
                },
                "contact_persons": [
                    {
                    "contact_person_id": "number",
                    "salutation": "string",
                    "first_name": "string",
                    "last_name": "string",
                    "email": "string",
                    "phone": "string",
                    "mobile": "string",
                    "designation": "string",
                    "department": "string",
                    "skype": "string",
                    "is_primary_contact": "boolean",
                    "communication_preference": {
                        "is_whatsapp_enabled": "boolean"
                    },
                    "enable_portal": "boolean"
                    }
                ],
                "default_templates": {
                    "invoice_template_id": "number",
                    "estimate_template_id": "number",
                    "creditnote_template_id": "number",
                    "purchaseorder_template_id": "number",
                    "salesorder_template_id": "number",
                    "retainerinvoice_template_id": "number",
                    "paymentthankyou_template_id": "number",
                    "retainerinvoice_paymentthankyou_template_id": "number",
                    "invoice_email_template_id": "number",
                    "estimate_email_template_id": "number",
                    "creditnote_email_template_id": "number",
                    "purchaseorder_email_template_id": "number",
                    "salesorder_email_template_id": "number",
                    "retainerinvoice_email_template_id": "number",
                    "paymentthankyou_email_template_id": "number",
                    "retainerinvoice_paymentthankyou_email_template_id": "number"
                },
                "custom_fields": [
                    {
                    "index": "number",
                    "value": "string",
                    "label": "string"
                    }
                ],
                "opening_balances": [
                    {
                    "location_id": "string",
                    "exchange_rate": "number",
                    "opening_balance_amount": "number"
                    }
                ],
                "vat_reg_no": "string",
                "owner_id": "number",
                "tax_reg_no": "string",
                "tax_exemption_certificate_number": "string",
                "country_code": "string",
                "vat_treatment": "string",
                "tax_treatment": "string",
                "tax_regime": "string",
                "legal_name": "string",
                "is_tds_registered": "boolean",
                "place_of_contact": "string",
                "gst_no": "string",
                "gst_treatment": "string",
                "tax_authority_name": "string",
                "avatax_exempt_no": "string",
                "avatax_use_code": "string",
                "tax_exemption_id": "number",
                "tax_exemption_code": "string",
                "tax_authority_id": "number",
                "tax_id": "number",
                "tds_tax_id": "string",
                "is_taxable": "boolean",
                "facebook": "string",
                "twitter": "string",
                "track_1099": "boolean",
                "tax_id_type": "string",
                "tax_id_value": "string"
                },
                "response_schema": {
                "code": "number",
                "message": "string",
                "contact": {
                    "contact_id": "number",
                    "contact_name": "string",
                    "company_name": "string",
                    "has_transaction": "boolean",
                    "contact_type": "string",
                    "customer_sub_type": "string",
                    "credit_limit": "number",
                    "is_portal_enabled": "boolean",
                    "language_code": "string",
                    "is_taxable": "boolean",
                    "tax_id": "number",
                    "tds_tax_id": "string",
                    "tax_name": "string",
                    "tax_percentage": "number",
                    "tax_authority_id": "number",
                    "tax_exemption_id": "number",
                    "tax_authority_name": "string",
                    "tax_exemption_code": "string",
                    "place_of_contact": "string",
                    "gst_no": "string",
                    "vat_treatment": "string",
                    "tax_treatment": "string",
                    "tax_exemption_certificate_number": "string",
                    "tax_regime": "string",
                    "legal_name": "string",
                    "is_tds_registered": "boolean",
                    "gst_treatment": "string",
                    "is_linked_with_zohocrm": "boolean",
                    "website": "string",
                    "owner_id": "number",
                    "primary_contact_id": "number",
                    "payment_terms": "number",
                    "payment_terms_label": "string",
                    "currency_id": "number",
                    "currency_code": "string",
                    "currency_symbol": "string",
                    "opening_balances": [
                    {
                        "location_id": "string",
                        "exchange_rate": "number",
                        "opening_balance_amount": "number"
                    }
                    ],
                    "location_id": "string",
                    "location_name": "string",
                    "outstanding_receivable_amount": "number",
                    "outstanding_receivable_amount_bcy": "number",
                    "unused_credits_receivable_amount": "number",
                    "unused_credits_receivable_amount_bcy": "number",
                    "status": "string",
                    "payment_reminder_enabled": "boolean",
                    "custom_fields": [
                    {
                        "index": "number",
                        "value": "string",
                        "label": "string"
                    }
                    ],
                    "billing_address": {
                    "attention": "string",
                    "address": "string",
                    "street2": "string",
                    "state_code": "string",
                    "city": "string",
                    "state": "string",
                    "zip": "string",
                    "country": "string",
                    "fax": "string",
                    "phone": "string"
                    },
                    "shipping_address": {
                    "attention": "string",
                    "address": "string",
                    "street2": "string",
                    "state_code": "string",
                    "city": "string",
                    "state": "string",
                    "zip": "string",
                    "country": "string",
                    "fax": "string",
                    "phone": "string"
                    },
                    "facebook": "string",
                    "twitter": "string",
                    "contact_persons": [
                    {
                        "contact_person_id": "number",
                        "salutation": "string",
                        "first_name": "string",
                        "last_name": "string",
                        "email": "string",
                        "phone": "string",
                        "mobile": "string",
                        "designation": "string",
                        "department": "string",
                        "skype": "string",
                        "is_primary_contact": "boolean",
                        "communication_preference": {
                        "is_whatsapp_enabled": "boolean"
                        },
                        "enable_portal": "boolean"
                    }
                    ],
                    "default_templates": {
                    "invoice_template_id": "number",
                    "estimate_template_id": "number",
                    "creditnote_template_id": "number",
                    "purchaseorder_template_id": "number",
                    "salesorder_template_id": "number",
                    "retainerinvoice_template_id": "number",
                    "paymentthankyou_template_id": "number",
                    "retainerinvoice_paymentthankyou_template_id": "number",
                    "invoice_email_template_id": "number",
                    "estimate_email_template_id": "number",
                    "creditnote_email_template_id": "number",
                    "purchaseorder_email_template_id": "number",
                    "salesorder_email_template_id": "number",
                    "retainerinvoice_email_template_id": "number",
                    "paymentthankyou_email_template_id": "number",
                    "retainerinvoice_paymentthankyou_email_template_id": "number"
                    },
                    "notes": "string",
                    "created_time": "string",
                    "last_modified_time": "string"
                }
                },
                "oauth_scope": "ZohoBooks.contacts.UPDATE"
            }',
        'status' => 'inactive',
    ]
];