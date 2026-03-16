# Apotheca® Marketing Suite — Two-Site Sync & SSO Setup Guide

This guide explains how to connect **apotheca-marketing-sync** (installed on your main WooCommerce store) with **Apotheca® Marketing Suite** (installed on a dedicated marketing subdomain).

---

## Architecture Overview

```
┌─────────────────────────┐         HTTPS POST          ┌──────────────────────────────┐
│  Main WooCommerce Store │  ───────────────────────►   │  Marketing Subdomain          │
│  (shop.yoursite.com)    │   HMAC-signed events        │  (marketing.yoursite.com)     │
│                         │                              │                               │
│  Plugin:                │         SSO redirect         │  Plugin:                      │
│  apotheca-marketing-    │  ◄───────────────────────   │  apotheca-marketing-suite     │
│  sync                   │   HMAC-signed token          │                               │
└─────────────────────────┘                              └──────────────────────────────┘
```

**apotheca-marketing-sync** captures WooCommerce events (orders, registrations, cart activity, product views) and dispatches them to the marketing subdomain. It also provides single sign-on so store admins can jump to the marketing dashboard without re-authenticating.

---

## Prerequisites

- Two separate WordPress installations (main store + marketing subdomain)
- WooCommerce active on the main store
- WooCommerce active on the subdomain (required by Apotheca® Marketing Suite)
- PHP 8.0+ on both sites
- HTTPS enabled on both sites (required for HMAC security)

---

## Installation Order

### Step 1: Install Apotheca® Marketing Suite on the subdomain

1. Upload the `apotheca-marketing-suite` folder to `wp-content/plugins/` on the marketing subdomain.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. The plugin will create all necessary database tables automatically.

### Step 2: Install apotheca-marketing-sync on the main store

1. Upload the `apotheca-marketing-sync` folder to `wp-content/plugins/` on the main WooCommerce store.
2. Activate the plugin via **Plugins > Installed Plugins**.
3. A new `ams_sync_log` table will be created automatically.

---

## Shared Secret Configuration

Both plugins must share an identical secret key for HMAC authentication.

### On the marketing subdomain (Apotheca® Marketing Suite):

1. Navigate to **Apotheca® Marketing > Sync** in the admin menu.
2. Enter a strong shared secret in the **Shared Secret Key** field.
3. Optionally set the **Allowed Source Domain** (e.g., `shop.yoursite.com`) to restrict which domains can send sync events.
4. Click **Save Settings**.

### On the main store (apotheca-marketing-sync):

1. Navigate to **Tools > Marketing Sync** in the admin menu.
2. Enter the **Endpoint URL**: `https://marketing.yoursite.com/wp-json/ams/v1/sync/ingest`
3. Enter the exact same **Shared Secret** you configured on the subdomain.
4. Enable the events you want to sync (all are enabled by default):
   - Customer Registered
   - Order Placed
   - Order Status Changed
   - Cart Updated
   - Checkout Started
   - Product Viewed
5. Click **Save Settings**.
6. Click **Test Connection** to verify the two sites can communicate.

> **Security note:** The shared secret is stored encrypted using AES-256-CBC with your site's `AUTH_KEY` as the encryption key. Ensure `AUTH_KEY` is set in your `wp-config.php` (WordPress generates this by default).

---

## DNS / Subdomain Setup Notes

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

## How Sync Works

1. When a tracked event occurs on the main store (e.g., a new order), the **EventCollector** captures the event data.
2. An **Action Scheduler** job fires after a 2-second delay, calling the **Dispatcher**.
3. The Dispatcher signs the payload with HMAC-SHA256 using the shared secret and sends an HTTPS POST to the ingest endpoint.
4. The **SyncIngestController** on the subdomain verifies the HMAC signature, checks the timestamp (300-second window), and validates the source domain.
5. The **SyncIngestor** routes the event to the appropriate handler (create/update subscriber, log event, trigger flows).

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

### Important notes:
- SSO tokens expire after 60 seconds and are single-use (nonce tracked via WordPress transients).
- Auto-created accounts are given the `administrator` role on the subdomain.
- The `/ams-sso/` endpoint requires WordPress rewrite rules. After first activation, visit **Settings > Permalinks** on the subdomain and click **Save Changes** to flush rewrite rules.

---

## Monitoring & Troubleshooting

### On the main store (Tools > Marketing Sync):

- **Health Panel**: Shows queued events, events sent today, and events sent this week.
- **Recent Errors**: Table of failed dispatches with retry button.
- **Sync Log**: Full log of all dispatched events with status codes.

### On the subdomain (Apotheca® Marketing > Sync):

- **Ingest Status**: Shows when the last event was received.
- **Sync Log**: Last 50 received events with event type, source, HTTP status, and timestamp.
- **Clear Log**: Button to truncate the inbound log.

### Common Issues

| Problem | Solution |
|---------|----------|
| Test connection returns 401 | Shared secrets don't match. Re-enter on both sites. |
| Test connection returns 403 | Allowed domain doesn't match. Check domain setting on subdomain. |
| Events not arriving | Verify Action Scheduler is running. Check **Tools > Scheduled Actions** on the main store. |
| SSO link missing from toolbar | Ensure the sync plugin is active and the user has `manage_woocommerce` capability. |
| SSO redirects to login with error | Check shared secret, ensure rewrite rules are flushed, verify server time is accurate (token has 60s expiry). |
| 500 errors on ingest | Check PHP error logs on the subdomain. Ensure WooCommerce is active and all tables exist. |

---

## Uninstall Behaviour

### apotheca-marketing-sync (main store):
- Deactivation: unschedules all Action Scheduler jobs.
- Deletion: drops the `ams_sync_log` table and removes all `ams_sync_*` options.

### Apotheca® Marketing Suite (subdomain):
- The `ams_sync_inbound_log` table and `ams_sync_last_received` option are cleaned up when data removal is enabled in settings and the plugin is deleted.
