# Apotheca® Marketing Suite — Setup Guide

---

## Quick-Start: Link Your Two Sites in 5 Minutes

Apotheca® Marketing Suite runs on its own standalone WordPress site (no WooCommerce needed). Your main WooCommerce store pushes customer, order, and product data to it automatically via a small companion plugin called **Apotheca Marketing Sync**.

Here's how to connect them:

### What you need

| Site | URL (example) | Plugin to install |
|------|---------------|-------------------|
| Main WooCommerce store | `yoursite.com` | **Apotheca Marketing Sync** |
| Marketing subdomain | `marketing.yoursite.com` | **Apotheca® Marketing Suite** |

### Step 1 — Install both plugins

1. Upload `apotheca-marketing-suite` to `wp-content/plugins/` on your **marketing subdomain** and activate it.
2. Upload `apotheca-marketing-sync` to `wp-content/plugins/` on your **main WooCommerce store** and activate it.

Both plugins create their database tables automatically on activation.

### Step 2 — Generate a shared secret

Pick a strong random string (32+ characters). You can use a password manager or run this in a terminal:

```
openssl rand -hex 32
```

You'll paste this same string into both sites.

### Step 3 — Configure the marketing subdomain

1. Log into `marketing.yoursite.com` as an administrator.
2. Go to **Apotheca® Marketing > Sync** in the left-hand menu.
3. Fill in:
   - **Store URL** — your main store URL, e.g. `https://yoursite.com`
   - **Shared Secret Key** — paste the secret you generated
4. Click **Save Settings**.
5. Copy the **Ingest Endpoint URL** shown on this page (use the copy button). It will look like:
   `https://marketing.yoursite.com/wp-json/ams/v1/sync/ingest`

### Step 4 — Configure the main store

1. Log into `yoursite.com` as a shop manager or administrator.
2. Go to **Tools > Marketing Sync** in the left-hand menu.
3. Fill in:
   - **Marketing Subdomain URL** — paste the Ingest Endpoint URL you copied from Step 3
   - **Shared Secret Key** — paste the same secret you used on the subdomain
4. Make sure all event checkboxes are ticked (they are by default).
5. Click **Save Settings**.

### Step 5 — Test the connection

1. On the main store (**Tools > Marketing Sync**), click **Test Connection**.
2. You should see a green success notice: *"Connection OK — marketing subdomain responded with HTTP 200."*
3. On the marketing subdomain (**Apotheca® Marketing > Sync**), click **Test Connection** to run a loopback self-test and confirm the endpoint is working.
4. Check the **Sync Log** panel on both sites — you should see a `test_ping` event logged.

**That's it. Your sites are now linked.** Every customer registration, order, cart update, checkout, and product view on the main store will automatically push to the marketing subdomain within seconds.

---

## What happens after linking

Once linked, data flows automatically:

- **New customer registers** on the store → subscriber created on the marketing subdomain, welcome flow triggered.
- **Order placed** → subscriber stats updated (total orders, total spent), post-purchase flow triggered, revenue attribution checked.
- **Order status changes** → event logged, completed orders trigger post-purchase flows, refunds logged.
- **Cart updated** → abandoned cart timer reset.
- **Checkout started** → event logged for abandoned cart detection.
- **Product viewed** → browse abandonment trigger checked.

You never need to import/export CSVs or manually sync data. The marketing subdomain always has up-to-date subscriber and order data.

---

## Architecture Overview

```
┌─────────────────────────┐         HTTPS POST          ┌──────────────────────────────┐
│  Main WooCommerce Store │  ───────────────────────►   │  Marketing Subdomain          │
│  (yoursite.com)         │   HMAC-signed events        │  (marketing.yoursite.com)     │
│                         │                              │                               │
│  Plugin:                │         SSO redirect         │  Plugin:                      │
│  apotheca-marketing-    │  ◄───────────────────────   │  apotheca-marketing-suite     │
│  sync                   │   HMAC-signed token          │  (no WooCommerce needed)      │
└─────────────────────────┘                              └──────────────────────────────┘
```

**apotheca-marketing-sync** captures WooCommerce events (orders, registrations, cart activity, product views) and dispatches them to the marketing subdomain. It also provides single sign-on so store admins can access the marketing dashboard without re-authenticating.

**Apotheca® Marketing Suite** runs in standalone mode — it does not require WooCommerce. All customer and order data arrives via the authenticated ingest endpoint. If you ever co-locate WooCommerce on the same site, the plugin detects it and uses local WC hooks alongside the ingest data.

---

## Prerequisites

- Two separate WordPress installations (main store + marketing subdomain)
- WooCommerce active on the main store
- WooCommerce is **not** required on the marketing subdomain
- PHP 8.0+ on both sites
- HTTPS enabled on both sites (required for HMAC security)

---

## Standalone Mode

When Apotheca® Marketing Suite is activated on a WordPress site without WooCommerce, it runs in **standalone mode**:

- A blue info notice appears in the admin: *"Apotheca® Marketing Suite is running in standalone mode. To receive customer and order data from your WooCommerce store, enter your store URL and shared secret in Settings > Sync."*
- All admin pages, flows, segments, forms, email editor, analytics, and AI features work normally.
- All WooCommerce hooks are silently skipped (no fatal errors, no missing function calls).
- Action Scheduler is bundled inside the plugin at `lib/action-scheduler/` and loads automatically if WooCommerce is not providing it.

The notice disappears once you enter a Store URL in the Sync settings.

---

## DNS / Subdomain Setup

### Option A: Subdomain (Recommended)

Set up a subdomain like `marketing.yoursite.com` pointing to a separate WordPress installation.

```
marketing.yoursite.com  →  A record / CNAME → your server IP
```

### Option B: Separate Domain

You can also use a completely separate domain (e.g., `yoursite-marketing.com`). Just make sure:
- The endpoint URL in the sync plugin points to the correct domain.
- The allowed domain on the subdomain matches the main store's domain.

### SSL Certificates

Both sites **must** use HTTPS. The HMAC-signed payloads are transmitted via HTTP POST, and without TLS, the shared secret could be intercepted. Use Let's Encrypt or your hosting provider's SSL to secure both domains.

---

## Settings Reference

### Marketing Subdomain — Apotheca® Marketing > Sync

| Field | Description |
|-------|-------------|
| **Store URL** | Base URL of the main WooCommerce store (e.g. `https://yoursite.com`). Used for outbound API calls and product/review cache refreshes. |
| **Shared Secret Key** | HMAC signing key. Must match the secret on the main store. Stored encrypted via AES-256-CBC. Has a show/hide toggle. |
| **Allowed Source Domain** | Optional. Restricts ingest to a single domain (e.g. `yoursite.com`). Leave blank to allow any. |
| **Ingest Endpoint URL** | Read-only. The full URL to give to the sync plugin. Has a copy-to-clipboard button. |
| **Test Connection** | Sends a signed loopback ping to the ingest endpoint on this site. Confirms the endpoint is reachable and HMAC validation works. |
| **Sync Log** | Table showing the last 50 received events with event type, source, status, and timestamp. Includes a Clear Log button. |

### Main Store — Tools > Marketing Sync

| Field | Description |
|-------|-------------|
| **Marketing Subdomain URL** | The Ingest Endpoint URL copied from the marketing subdomain's Sync settings. |
| **Shared Secret Key** | HMAC signing key. Must match the secret on the subdomain. |
| **Events to Push** | Checkboxes for each event type. All enabled by default. |
| **Test Connection** | Sends a signed test ping to the marketing subdomain and displays the response. |
| **Sync Health** | Shows queued events, events sent today, and events sent this week. |
| **Recent Errors** | Failed dispatch log with retry button. |

---

## How Sync Works

1. When a tracked event occurs on the main store (e.g., a new order), the **EventCollector** captures the event data.
2. An **Action Scheduler** job fires after a 2-second delay, calling the **Dispatcher**.
3. The Dispatcher signs the raw JSON body with HMAC-SHA256 using the shared secret and sends an HTTPS POST to the ingest endpoint. Two headers are included:
   - `X-AMS-Signature` — HMAC-SHA256 hex of the raw request body
   - `X-AMS-Timestamp` — Unix timestamp of when the request was sent
4. The **SyncIngestController** on the subdomain:
   - Checks the timestamp is within 300 seconds (rejects replays).
   - Recomputes the HMAC and compares using `hash_equals()`.
   - Validates the source domain (if configured).
   - Logs the event in `ams_sync_log` with a status: `processed`, `auth_failed`, `unknown_event`, or `error`.
5. The **SyncIngestor** routes the event to the appropriate handler (create/update subscriber, log event, update stats, trigger flows).

### Event Types

| Event | What happens on the marketing subdomain |
|-------|----------------------------------------|
| `customer_registered` | Subscriber created/updated (source: `sync_registration`), welcome flow triggered |
| `order_placed` | Subscriber stats updated, `placed_order` event logged, post-purchase flow triggered, revenue attribution checked |
| `order_status_changed` | Event logged; completed → post-purchase trigger; refunded → `refund_requested` event |
| `cart_updated` | `added_to_cart` event logged, abandoned cart timer reset |
| `checkout_started` | Subscriber found/created, `started_checkout` event logged |
| `product_viewed` | `viewed_product` event logged, browse abandonment trigger checked |
| `abandoned_cart` | `abandoned_cart` event logged, abandoned cart flow triggered |
| `test_ping` | Logged and responded with HTTP 200 — no subscriber changes |
| Any unrecognised type | Logged with status `unknown_event`, HTTP 200 returned (does not break) |

### Retry Logic

If delivery fails, the Dispatcher retries with exponential backoff:
- 1st retry: 5 minutes
- 2nd retry: 15 minutes
- 3rd retry: 45 minutes

Failed attempts are logged in the sync log (visible at **Tools > Marketing Sync** on the main store).

---

## SSO (Single Sign-On)

Store administrators can access the marketing subdomain dashboard directly from the main store's admin toolbar.

### How it works:

1. A **Marketing Suite** link appears in the WordPress admin toolbar on the main store (for users with `manage_woocommerce` capability).
2. Clicking it generates a one-time SSO token containing the user's ID, email, display name, a nonce, and a 60-second expiry.
3. The token is base64-encoded and signed with HMAC-SHA256 using the shared secret.
4. The user is redirected to `https://marketing.yoursite.com/ams-sso/?token=...&sig=...`
5. The subdomain verifies the signature, checks expiry, ensures the nonce hasn't been used, and either finds or auto-creates an administrator account.
6. The user is logged in and redirected to the marketing dashboard.

### If SSO fails:

The user is redirected to `wp-login.php` with a notice:

> "Your Marketing Suite login link has expired or has already been used. Please generate a new one from your main store."

### Important notes:
- SSO tokens expire after 60 seconds and are single-use (nonce tracked via WordPress transients with 120-second TTL).
- Auto-created accounts are given the `administrator` role on the subdomain.
- The main store user ID is stored in user meta (`ams_main_site_user_id`) for reference.
- The `/ams-sso/` endpoint requires WordPress rewrite rules. After first activation, visit **Settings > Permalinks** on the subdomain and click **Save Changes** to flush rewrite rules if SSO isn't working.

---

## Security Notes

- **Shared secret storage**: On both sites, the shared secret is encrypted using AES-256-CBC with your site's `AUTH_KEY` as the encryption key. Ensure `AUTH_KEY` is set in your `wp-config.php` (WordPress generates this by default).
- **HMAC replay protection**: The ingest endpoint rejects any request with a timestamp older than 300 seconds.
- **SSO replay protection**: Each SSO token contains a unique nonce. After first use, the nonce is stored as a transient for 120 seconds, preventing reuse.
- **No WP nonces or application passwords**: The ingest endpoint is designed for server-to-server calls. Authentication is entirely via HMAC signatures.

---

## Monitoring & Troubleshooting

### On the main store (Tools > Marketing Sync):

- **Health Panel**: Shows queued events, events sent today, and events sent this week.
- **Recent Errors**: Table of failed dispatches with retry button.
- **Sync Log**: Full log of all dispatched events with status codes.

### On the subdomain (Apotheca® Marketing > Sync):

- **Ingest Status**: Shows when the last event was received.
- **Sync Log**: Last 50 received events with event type, source, status, and timestamp.
- **Clear Log**: Button to truncate the log.
- **Test Connection**: Loopback self-test to verify the endpoint works.

### Common Issues

| Problem | Solution |
|---------|----------|
| Test connection returns 401 | Shared secrets don't match. Re-enter the same secret on both sites. |
| Test connection returns 403 | Allowed domain doesn't match. Check domain setting on subdomain. |
| Events not arriving | Verify Action Scheduler is running. Check **Tools > Scheduled Actions** on the main store. |
| SSO link missing from toolbar | Ensure the sync plugin is active and the user has `manage_woocommerce` capability. |
| SSO redirects to login with error | Check shared secret, ensure rewrite rules are flushed (visit Settings > Permalinks and save), verify server time is accurate (token has 60s expiry). |
| 500 errors on ingest | Check PHP error logs on the subdomain. Ensure all database tables were created (deactivate and reactivate the plugin). |
| Standalone mode notice won't go away | Enter your Store URL in **Apotheca® Marketing > Sync** and save. |
| Action Scheduler errors on subdomain | The plugin bundles Action Scheduler in `lib/action-scheduler/`. If you see issues, ensure no conflicting Action Scheduler version is installed. |

---

## Uninstall Behaviour

### apotheca-marketing-sync (main store):
- Deactivation: unschedules all Action Scheduler jobs.
- Deletion: drops the `ams_sync_log` table and removes all `ams_sync_*` options.

### Apotheca® Marketing Suite (subdomain):
- The `ams_sync_log` table and `ams_sync_last_received` option are cleaned up when data removal is enabled in Settings and the plugin is deleted.
- The bundled Action Scheduler in `lib/action-scheduler/` is removed with the plugin files.
