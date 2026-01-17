<?php

return [

    /**
     * ----------------------------------------
     * List Products
     * ----------------------------------------
     */
    [
        'key' => 'api_list_products',
        'value' => '
        {
            "method": "GET",
            "url": "https://{store_url}/wp-json/wc/v3/products",
            "query_params": {
                "page": "number",
                "per_page": "number",
                "search": "string",
                "after": "string",
                "before": "string",
                "exclude": "array",
                "include": "array",
                "offset": "number",
                "order": "string",
                "orderby": "string",
                "status": "string",
                "sku": "string",
                "featured": "boolean",
                "category": "string",
                "tag": "string",
                "shipping_class": "string",
                "attribute": "string",
                "attribute_term": "string",
                "on_sale": "boolean",
                "min_price": "number",
                "max_price": "number",
                "stock_status": "string"
            },
            "headers": {
                "Authorization": "Basic {base64(consumer_key:consumer_secret)}"
            },
            "body_schema": {},
            "response_schema": [
                {
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
                    "purchasable": "boolean",
                    "total_sales": "number",
                    "virtual": "boolean",
                    "downloadable": "boolean",
                    "downloads": "array",
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
                }
            ],
            "oauth_scope": "",
            "response_schema_id_key": "id",
            "req_schema_required_keys": []
        }',
        'status' => 'active',
    ]
];