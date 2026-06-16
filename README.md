# integration
A Laravel package for managing third-party integrations (e.g. Zoho Books). It provides a standardized way to map, sync, and store external system data.

## Facebook OAuth completion

Set the Laravel package config through environment variables:

```env
CHATBOT_UTIL_API_URL=https://<chatbot-util-host>
CHATBOT_UTIL_TIMEOUT=20
```

The chatbot-util deployment must redirect completed Facebook OAuth callbacks to:

```env
FACEBOOK_CONNECT_DEFAULT_REDIRECT_TARGET=https://<laravel-host>/social/facebook/connect/complete
```
