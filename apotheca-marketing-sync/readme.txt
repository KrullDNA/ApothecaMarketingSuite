=== Apotheca Marketing Sync ===
Contributors: apotheca
Tags: woocommerce, sync, marketing, webhook, sso
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pushes WooCommerce events to the Apotheca Marketing Suite on a marketing subdomain. Companion plugin for multi-site setups.

== Description ==

Apotheca Marketing Sync is a lightweight companion plugin installed on your main WooCommerce store. It captures customer events and dispatches them to Apotheca Marketing Suite running on a dedicated marketing subdomain.

**Features:**

* **6 Event Types** — Customer registered, order placed, order status changed, cart updated, checkout started, product viewed.
* **HMAC-SHA256 Authentication** — All dispatched events are cryptographically signed.
* **Exponential Backoff Retries** — Failed dispatches retry at 5min, 15min, and 45min intervals.
* **Product View Beacon** — Ultra-lightweight inline script (~380 bytes) on single product pages only.
* **SSO Integration** — One-click admin toolbar link to the marketing subdomain dashboard.
* **Health Dashboard** — Queued events, send counts, and recent error log at Tools > Marketing Sync.

**Minimal Footprint:**

* Zero CSS or JS files shipped — all output is inline
* Zero assets loaded on non-product front-end pages
* Admin page uses native WordPress form markup (no React/external JS)
* Single database table (ams_sync_log)

== Installation ==

1. Upload the `apotheca-marketing-sync` folder to `/wp-content/plugins/` on your **main WooCommerce store**.
2. Activate the plugin through the 'Plugins' menu.
3. Navigate to Tools > Marketing Sync to configure the endpoint URL and shared secret.
4. The shared secret must match the one configured in Apotheca Marketing Suite on the subdomain.

See SETUP_GUIDE.md in the main plugin for detailed multi-site setup instructions.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
