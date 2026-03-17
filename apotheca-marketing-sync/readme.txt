=== Apotheca Marketing Sync ===
Contributors: apotheca
Tags: woocommerce, sync, marketing, webhook, sso
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pushes WooCommerce events to Apotheca Marketing Suite running on a separate marketing subdomain. Companion plugin for standalone deployments.

== Description ==

Apotheca Marketing Sync is a lightweight companion plugin installed on your main WooCommerce store. It captures customer events and dispatches them to Apotheca Marketing Suite running on a dedicated marketing subdomain (which does not need WooCommerce installed).

= How to Link Your Two Sites =

1. Install this plugin on your **main WooCommerce store** and activate it.
2. Install **Apotheca Marketing Suite** on your **marketing subdomain** and activate it.
3. On the marketing subdomain, go to **Apotheca Marketing > Sync**:
   - Enter your store URL (e.g. `https://yoursite.com`)
   - Enter a shared secret (any strong random string, 32+ characters)
   - Save, then copy the **Ingest Endpoint URL** shown on the page
4. On your main store, go to **Tools > Marketing Sync**:
   - Paste the Ingest Endpoint URL into the **Marketing Subdomain URL** field
   - Enter the same shared secret
   - Save
5. Click **Test Connection** — you should see a green success notice.

Done. All events now sync automatically.

= Features =

* **7 Event Types** — Customer registered, order placed, order status changed, cart updated, checkout started, product viewed, abandoned cart.
* **HMAC-SHA256 Authentication** — All dispatched events are cryptographically signed with a shared secret.
* **Exponential Backoff Retries** — Failed dispatches retry at 5min, 15min, and 45min intervals.
* **Product View Beacon** — Ultra-lightweight inline script (~380 bytes) on single product pages only.
* **SSO Integration** — One-click admin toolbar link to the marketing subdomain dashboard.
* **Health Dashboard** — Queued events, send counts, and recent error log at Tools > Marketing Sync.

= Minimal Footprint =

* Zero CSS or JS files shipped — all output is inline
* Zero assets loaded on non-product front-end pages
* Admin page uses native WordPress form markup (no React/external JS)
* Single database table (ams_sync_log)

== Installation ==

1. Upload the `apotheca-marketing-sync` folder to `/wp-content/plugins/` on your **main WooCommerce store**.
2. Activate the plugin through the 'Plugins' menu.
3. Navigate to **Tools > Marketing Sync** to configure the endpoint URL and shared secret.
4. The shared secret must match the one configured in Apotheca Marketing Suite on the subdomain.

See SETUP_GUIDE.md in the Apotheca Marketing Suite plugin for the full two-site setup walkthrough.

== Frequently Asked Questions ==

= Where do I get the Ingest Endpoint URL? =

On the marketing subdomain, go to **Apotheca Marketing > Sync**. The Ingest Endpoint URL is displayed as a read-only field with a copy button. It looks like: `https://marketing.yoursite.com/wp-json/ams/v1/sync/ingest`

= Does the marketing subdomain need WooCommerce? =

No. Apotheca Marketing Suite runs in standalone mode without WooCommerce. All data arrives via the ingest endpoint from this sync plugin.

= What happens if the marketing subdomain is unreachable? =

Events are retried automatically with exponential backoff (5min, 15min, 45min). Failed attempts are visible in the error log at **Tools > Marketing Sync**.

= How does SSO work? =

A "Marketing Suite" link appears in the admin toolbar. Clicking it generates a one-time HMAC-signed token (60-second expiry) and redirects you to the marketing subdomain, which verifies the signature and logs you in automatically.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
