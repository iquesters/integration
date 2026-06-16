# Integration Module — Developer Reference

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/integration/list` | List all configured integrations |
| `GET` | `/api/integration/show/{uid}` | Show a specific integration |
| `POST` | `/api/integration/store` | Add a new integration |
| `PUT` | `/api/integration/update/{uid}` | Update an integration configuration |
| `DELETE` | `/api/integration/delete/{uid}` | Remove an integration |

## Data Structure

| Field | Type | Description |
|-------|------|-------------|
| `uid` | string | Unique identifier |
| `name` | string | Integration display name |
| `provider` | string | Third-party provider slug |
| `status` | string | `active` or `inactive` |
| `config` | json | Provider-specific configuration keys (encrypted at rest) |
| `created_at` | timestamp | Creation date |
| `updated_at` | timestamp | Last updated date |

## Provider Configuration Keys

### Zoho Books
| Key | Description |
|-----|-------------|
| `client_id` | OAuth client ID |
| `client_secret` | OAuth client secret |
| `redirect_uri` | OAuth callback URL |

### WooCommerce
| Key | Description |
|-----|-------------|
| `store_url` | WooCommerce store base URL |
| `consumer_key` | REST API consumer key |
| `consumer_secret` | REST API consumer secret |

### Gautam's Bot
| Key | Description |
|-----|-------------|
| `bot_token` | Bot authentication token |
| `webhook_url` | Endpoint to receive bot events |

### Entity
| Key | Description |
|-----|-------------|
| `api_url` | Entity service API base URL |
| `api_key` | Entity service authentication key |

## Example: Store Request
```json
{
    "name": "Zoho Books",
    "provider": "zoho-books",
    "status": "active",
    "config": {
        "client_id": "your-client-id",
        "client_secret": "your-client-secret",
        "redirect_uri": "https://yourdomain.com/callback"
    }
}
```

## Standard Response Format
```json
{
    "status": 200,
    "message": "Request successful",
    "response_schema": {
        "data": []
    }
}
```

## Error Handling

| Code | Meaning |
|------|---------|
| `400` | Bad Request — invalid input data |
| `404` | Not Found — integration does not exist |
| `409` | Conflict — duplicate integration entry |
| `422` | Unprocessable Entity — validation or config error |

## Folder Structure
```
integration/
  resources/views/integrations/
    entity/             # Entity integration views
    gautams_bot/        # Gautam's Bot integration views
    woocommerces/       # WooCommerce integration views
    zoho_books/         # Zoho Books integration views
    form.blade.php      # Shared create/edit form
    index.blade.php     # Listing with group filter
    knob.blade.php      # Configuration knob UI
    show.blade.php      # Detail and knob viewer
  src/                  # Core package logic
  config/               # Package configuration
  database/             # Migrations and seeders
  routes/               # API and web route definitions
```