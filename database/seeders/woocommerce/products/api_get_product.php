<?php

return [
    /**
     * ----------------------------------------
     * Get Single Product
     * ----------------------------------------
     */
    [
        'key' => 'api_get_product',
        'value' => '
        {
            "method": "GET",
            "url": "https://{store_url}/wp-json/wc/v3/products/{product_id}",
            "query_params": {},
            "headers": {
                "Authorization": "Basic {base64(consumer_key:consumer_secret)}"
            },
            "body_schema": {},
            "response_schema": {
                "id": "number",
                "name": "string",
                "slug": "string",
                "permalink": "string",
                "date_created": "string",
                "date_modified": "string",
                "type": "string",
                "status": "string",
                "featured": "boolean",
                "catalog_visibility": "string",
                "description": "string",
                "short_description": "string",
                "sku": "string",
                "price": "string",
                "regular_price": "string",
                "sale_price": "string",
                "on_sale": "boolean",
                "stock_quantity": "number",
                "stock_status": "string",
                "categories": [
                    {
                        "id": "number",
                        "name": "string",
                        "slug": "string"
                    }
                ],
                "images": [
                    {
                        "id": "number",
                        "src": "string",
                        "name": "string",
                        "alt": "string"
                    }
                ],
                "attributes": "array",
                "meta_data": "array"
            },
            "oauth_scope": "",
            "response_schema_id_key": "id",
            "req_schema_required_keys": ["product_id"]
        }',
        'status' => 'active',
    ]
];