# Instagram Messaging Webhook Integration

## Overview

Instagram integration follows the same architecture as Facebook Messenger and WhatsApp.
When a user sends a Direct Message to your Instagram Professional account, Meta sends a webhook
to your server. Your application receives it, saves it, forwards to the chatbot, and replies back.

```
User sends DM on Instagram
        ↓
Meta sends POST to your webhook URL
        ↓
InstagramWHController handles request (GET for verification, POST returns 200 and dispatches job)
        ↓
InstagramWHJob classifies the update
        ↓
NewInstagramMessageJob saves message to DB
        ↓
ForwardToChatbotJob (same as WhatsApp, Telegram, and Messenger — no changes needed)
        ↓
ProcessChatbotResponseJob (add instagram case)
        ↓
SendInstagramReplyJob sends reply via Graph API
        ↓
User receives reply on Instagram
```

---

## New Code Files to Create

### Controllers
**Path:** `smart-messenger/src/Http/Controllers/Webhook/InstagramWHController.php`
- Handles GET verification (same pattern as Messenger)
- Dispatches `InstagramWHJob` asynchronously on POST
- Extends `BaseWHController`

### Jobs
**Path:** `smart-messenger/src/Jobs/InstagramWHJob.php`
- Extends `WHJob`
- Classifies update type (new_message / delivery / read / story_mention / story_reply)
- Resolves and validates channel
- Dispatches `NewInstagramMessageJob`

**Path:** `smart-messenger/src/Jobs/MessageJobs/NewInstagramMessageJob.php`
- Saves incoming message to `messages` table via `SaveMessageHelper`
- Handles contact creation via `ContactService`
- Dispatches `ForwardToChatbotJob`

**Path:** `smart-messenger/src/Jobs/MessageJobs/SendInstagramReplyJob.php`
- Sends reply back via Facebook Graph API using Instagram Send API
- Saves outbound message to `messages` table

---

## Prerequisites

- Facebook Developer Account
- An Instagram Professional Account (Business or Creator — not a personal account)
- Instagram account must be linked to a Facebook Page
- Meta App created on Facebook Developer Portal
- Instagram Graph API access

---

## Step 1 — Create Facebook App

1. Go to https://developers.facebook.com
2. Click **My Apps → Create App**
3. Select **Business** as app type
4. Fill in app name and contact email
5. Click **Create App**

---

## Step 2 — Add Instagram Product and Get Page Access Token

1. In your app dashboard → click **Add Product**
2. Find **Instagram** → click **Set Up**
3. Under **API Setup with Instagram Business Login** → connect your Instagram Professional account
4. Go to **Tools → Graph API Explorer**
5. Select your app and grant `instagram_basic`, `instagram_manage_messages`, `pages_manage_metadata` permissions
6. Generate a **Page Access Token** for the Facebook Page linked to your Instagram account
7. Save this token — it is used to send Instagram DMs via API

> **Note:** While the Meta App is in development mode, the Send API can only send messages to Instagram accounts added as testers. To send to all users the app must go through Meta App Review and obtain `instagram_manage_messages` Advanced Access.

---

## Step 3 — Get Your Instagram Account ID

You need the Instagram Business Account ID (not the username):

```bash
curl -X GET \
  "https://graph.facebook.com/v25.0/me/accounts?access_token=YOUR_PAGE_ACCESS_TOKEN"
# Note the page id from the response

curl -X GET \
  "https://graph.facebook.com/v25.0/YOUR_PAGE_ID?fields=instagram_business_account&access_token=YOUR_PAGE_ACCESS_TOKEN"
# Note the instagram_business_account.id — this is your Instagram Account ID
```

---

## Step 4 — Generate Verify Token

Open tinker in the messenger project:

```bash
cd /path/to/messenger
php artisan tinker
```

Generate verify token:

```php
echo bin2hex(random_bytes(32));
// Save this output — this is your instagram_verify_token
```

---

## Step 5 — Insert Channel and Metas in DB via Tinker

First check the Instagram provider ID:

```php
DB::table('channel_providers')->get();
// Note the id of the Instagram provider
```

**Create Instagram channel:**

```php
$channel = \DB::table('channels')->insertGetId([
    'uid'                 => \Illuminate\Support\Str::ulid()->toBase32(),
    'name'                => 'My Instagram Account',
    'channel_provider_id' => 4, // Replace with actual Instagram provider ID from above
    'user_id'             => 1,
    'status'              => 'active',
    'created_at'          => now(),
    'updated_at'          => now(),
]);
echo $channel; // Note this ID
```

**Insert 4 channel metas:**

```php
\DB::table('channel_metas')->insert([
    ['ref_parent' => $channel, 'meta_key' => 'instagram_account_id',        'meta_value' => 'YOUR_INSTAGRAM_ACCOUNT_ID', 'status' => 'active', 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['ref_parent' => $channel, 'meta_key' => 'instagram_page_id',           'meta_value' => 'YOUR_LINKED_PAGE_ID',       'status' => 'active', 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['ref_parent' => $channel, 'meta_key' => 'instagram_page_access_token', 'meta_value' => 'YOUR_PAGE_ACCESS_TOKEN',    'status' => 'active', 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
    ['ref_parent' => $channel, 'meta_key' => 'instagram_verify_token',      'meta_value' => 'YOUR_VERIFY_TOKEN',         'status' => 'active', 'created_by' => 1, 'updated_by' => 1, 'created_at' => now(), 'updated_at' => now()],
]);
```

**Get channel UID for webhook URL:**

```php
\DB::select("SELECT uid FROM channels WHERE id = $channel");
// Note this UID — needed for webhook URL
```

---

## Step 6 — Start Cloudflare Tunnel (for local testing)

Since Meta requires a public HTTPS URL for the webhook callback, use Cloudflare tunnel for local testing — same as Messenger and Telegram:

```cmd
C:\Windows\System32\cloudflared.exe tunnel --url http://localhost:80
```

Wait for output like:

```
Your quick Tunnel has been created! https://abc123.trycloudflare.com
```

Note this URL — you will need it in Step 7.

> **Note:** Every time the Cloudflare URL changes, you must update the webhook callback URL in the Facebook Developer Console.

---

## Step 7 — Register Webhook with Meta

1. In your Meta App → Instagram Settings → **Webhooks → Setup Webhooks**
2. Enter:
   - **Callback URL:** `https://abc123.trycloudflare.com/webhook/instagram/{channelUid}`
   - **Verify Token:** the token you generated in Step 4
3. Click **Verify and Save**
4. After verification → click **Add Subscriptions** and select at minimum:
   - `messages`
   - `messaging_postbacks`
   - `messaging_seen` (optional — for read receipts)
5. Link your Instagram Professional Account to the webhook subscription

Meta will send a GET request to verify — your `InstagramWHController` handles this automatically.

---

## Step 8 — How Incoming Message Looks

When a user sends a DM, Meta POSTs this to your webhook:

```json
{
  "object": "instagram",
  "entry": [
    {
      "id": "INSTAGRAM_ACCOUNT_ID",
      "time": 1234567890,
      "messaging": [
        {
          "sender":    { "id": "USER_IGSID" },
          "recipient": { "id": "INSTAGRAM_ACCOUNT_ID" },
          "timestamp": 1234567890,
          "message": {
            "mid":  "aWdGFmHijkl123",
            "text": "Hello!"
          }
        }
      ]
    }
  ]
}
```

Key fields:
- `entry.0.messaging.0.sender.id` → IGSID — Instagram-scoped user ID (stored as `from` in DB)
- `entry.0.messaging.0.recipient.id` → Instagram Account ID (stored as `to` in DB)
- `entry.0.messaging.0.message.text` → message text
- `entry.0.messaging.0.message.mid` → message ID (already globally unique)

> **Note:** The top-level `object` field is `"instagram"` — not `"page"` like Messenger. Use this to distinguish platforms in shared webhook handlers if needed.

---

## Step 9 — How the Code Handles It

### InstagramWHController
Instagram uses the same GET verification pattern as Messenger:

```php
class InstagramWHController extends BaseWHController
{
    protected ?bool $async = true;

    protected function getJobClass(): string
    {
        return InstagramWHJob::class;
    }

    protected function handleVerification(Request $request, string $channelUid): mixed
    {
        if ($request->input('hub.mode') !== 'subscribe') {
            return response('Invalid hub mode', 403);
        }

        $verifyToken = $request->input('hub.verify_token');
        $challenge   = $request->input('hub.challenge');

        $channel = Channel::where('uid', $channelUid)
            ->where('status', 'active')->first();

        if (!$channel) {
            return response('Invalid channel', 403);
        }

        $meta = $channel->metas()
            ->where('meta_key', 'instagram_verify_token')
            ->where('meta_value', $verifyToken)
            ->first();

        if (!$meta) {
            return response('Invalid verify token', 403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }
}
```

### SaveMessageHelper — Add Instagram Platform

`detectPlatform()` auto-detects by message structure. Instagram and Messenger both use `mid` so distinguish by channel provider:

```php
private function detectPlatform(): string
{
    if (isset($this->message['message_id']))                  return 'telegram';
    if (isset($this->message['mid']) && $this->isInstagram()) return 'instagram'; // new
    if (isset($this->message['mid']))                         return 'messenger';
    return 'whatsapp';
}
```

Instagram-specific fields in `process()` switch case:

```php
case 'instagram':
    $messageId   = $this->message['mid'] ?? null;              // globally unique
    $from        = $this->message['sender']['id'] ?? null;     // IGSID
    $to          = $this->message['recipient']['id'] ?? null;  // Instagram Account ID
    $contactName = null;                                        // not in basic payload
    $messageType = isset($this->message['attachments']) ? 'attachment' : 'text';
    $timestamp   = isset($this->message['timestamp'])
                    ? now()->setTimestamp($this->message['timestamp'] / 1000)
                    : now();
    break;
```

### SendInstagramReplyJob — Send via Graph API

Instagram Send API uses the same Graph API endpoint as Messenger but targets the Instagram Account ID:

```php
$response = Http::withToken($channel->getMeta('instagram_page_access_token'))
    ->post('https://graph.facebook.com/v25.0/' . $channel->getMeta('instagram_account_id') . '/messages', [
        'recipient'      => ['id' => $chatId],
        'messaging_type' => 'RESPONSE',
        'message'        => ['text' => $text],
    ]);
```

### ProcessChatbotResponseJob — Add Instagram Case

```php
return match ($this->getProvider()) {
    'telegram'  => new SendTelegramReplyJob($this->inboundMessage, $payload),
    'messenger' => new SendMessengerReplyJob($this->inboundMessage, $payload),
    'instagram' => new SendInstagramReplyJob($this->inboundMessage, $payload),
    default     => new SendWhatsAppReplyJob($this->inboundMessage, $payload),
};
```

---

## Step 10 — Route Added

```php
Route::any('/webhook/instagram/{channelUid}', [InstagramWHController::class, 'handle']);
```

---

## Step 11 — UI Changes

Add Instagram form following the same pattern as Messenger and WhatsApp:

- `instagram-form.blade.php` — Step 2 fields: Instagram Account ID, Linked Page ID, Page Access Token, Verify Token
- `instagram-show.blade.php` — Show page with webhook URL, verify token, and copy buttons
- Add `@case('instagram')` in `MessagingProfileController` match cases for `create()`, `edit()`, and `show()`

---

## Step 12 — Start Queue Worker

```bash
cd /path/to/messenger
php artisan queue:work
```

---

## Step 13 — Test

**Send a message:**
1. Go to your Instagram Professional account
2. Have a test user (added as app tester in Meta Developer Console) send a DM
3. Send any message like "Hello!"

**Verify in phpMyAdmin:**
- Open `iq_messenger` database
- Open `messages` table
- You should see a new row with:
  - `from` = USER_IGSID
  - `to` = INSTAGRAM_ACCOUNT_ID
  - `message_type` = text
  - `content` = your message
  - `status` = received

**Verify in Laravel logs:**

```
storage/logs/laravel.log
```

Look for:
- `Channel validated successfully`
- `Message saved successfully`
- `Contact handled from webhook`

**Test reply via Tinker:**

```php
$message = \Iquesters\SmartMessenger\Models\Message::where('channel_id', YOUR_CHANNEL_ID)
    ->where('status', 'received')->latest()->first();

\Iquesters\SmartMessenger\Jobs\MessageJobs\SendInstagramReplyJob::dispatch(
    $message,
    ['type' => 'text', 'text' => 'Hello from SmartMessenger!']
);
```

---

## Key Differences vs Facebook Messenger

| Feature | Messenger | Instagram |
|---|---|---|
| `object` field in webhook | `page` | `instagram` |
| Sender ID type | PSID (Page-Scoped) | IGSID (Instagram-Scoped) |
| Graph API target | Page ID | Instagram Account ID |
| Account requirement | Facebook Page | Instagram Professional Account linked to a Page |
| DB meta keys | `messenger_page_id`, `messenger_page_access_token` | `instagram_account_id`, `instagram_page_id`, `instagram_page_access_token` |
| Extra setup step | None | Must fetch Instagram Account ID from linked Page |
| Story events | No | Yes — `story_mention`, `story_reply` via webhook |

---

## Notes

- For local testing use Cloudflare tunnel (same as Messenger and Telegram) — every time the URL changes update the webhook callback URL in the Meta Developer Console
- Meta verifies the webhook via GET request first — make sure your server is running before registering the webhook
- Page Access Token is used even for Instagram — Instagram API is accessed through the linked Facebook Page token
- IGSID is unique per user per Instagram account — different from PSID used in Messenger
- `message.mid` is already globally unique — no combining needed
- Instagram requires `messaging_type: RESPONSE` in the Send API payload, same as Messenger
- Instagram DM API only works with Professional accounts (Business or Creator) — personal accounts cannot receive webhook events or send API replies
- Story mentions and story replies arrive as separate webhook event types — handle them in `InstagramWHJob` if needed

---

## Official Documentation

- Instagram Messaging Overview: https://developers.facebook.com/docs/messenger-platform/instagram
- Webhook Setup for Instagram: https://developers.facebook.com/docs/messenger-platform/instagram/features/webhook
- Send API for Instagram: https://developers.facebook.com/docs/messenger-platform/instagram/features/send-message
- Instagram Graph API: https://developers.facebook.com/docs/instagram-api
- Get Instagram Account ID: https://developers.facebook.com/docs/instagram-api/getting-started
- Page Access Token: https://developers.facebook.com/docs/pages/access-tokens
- Long-lived Token Guide: https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived