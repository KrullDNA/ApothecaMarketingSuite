=== Apotheca Marketing Suite ===
Contributors: apotheca
Tags: email marketing, sms, automation, flows, standalone
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium email and SMS marketing automation suite with flows, segmentation, RFM scoring, pop-up forms, AI features, and analytics. Runs standalone or alongside WooCommerce.

== Description ==

Apotheca Marketing Suite is a self-hosted marketing automation platform. It can run on its own dedicated WordPress site with no WooCommerce required — customer and order data is pushed from your main WooCommerce store via the companion **Apotheca Marketing Sync** plugin.

= Linking Your Two Sites =

1. Install **Apotheca Marketing Suite** on your marketing subdomain and activate it.
2. Install **Apotheca Marketing Sync** on your main WooCommerce store and activate it.
3. Generate a shared secret (any strong random string, 32+ characters).
4. On the marketing subdomain, go to **Apotheca Marketing > Sync**, enter your Store URL and the shared secret, then save. Copy the **Ingest Endpoint URL**.
5. On the main store, go to **Tools > Marketing Sync**, paste the Ingest Endpoint URL and the same shared secret, then save.
6. Click **Test Connection** on both sites to confirm the link.

That's it. All customer, order, cart, and product view events now sync automatically. See SETUP_GUIDE.md for the full walkthrough.

= Key Features =

* **Standalone Deployment** — Runs on its own WordPress site without WooCommerce. Data arrives via HMAC-authenticated REST endpoint from the companion sync plugin.
* **Automated Flows** — 8 trigger types including welcome series, abandoned cart recovery, post-purchase, win-back, browse abandonment, birthday, RFM segment changes, and custom events.
* **Smart Segmentation** — 25+ condition types with nested AND/OR logic, automatic recalculation every 6 hours.
* **RFM Scoring** — Nightly recency/frequency/monetary analysis with 8 named segments.
* **Pop-Up Forms** — 6 form types: modal, flyout, embedded, full-page, sticky bar, and spin-to-win with coupon generation.
* **SMS Integration** — Twilio-powered SMS campaigns with TCPA compliance, keyword opt-out, and delivery tracking.
* **Analytics Dashboard** — Revenue attribution, email/SMS performance, RFM heatmap, flow funnels, and CSV export.
* **AI Features** — OpenAI-powered subject line generation, email body drafting, send-time optimisation, product recommendations, and segment suggestions.
* **Visual Email Editor** — Drag-and-drop block editor with 12 block types, live preview, and Outlook compatibility.
* **Elementor Widgets** — 4 widgets: opt-in form, subscriber count badge, campaign archive, and preference centre.
* **Reviews Integration** — WooCommerce + Judge.me review imports, review gating, social proof email blocks.
* **SSO** — One-click login from the main store admin toolbar to the marketing dashboard.
* **Subdomain Sync** — HMAC-authenticated ingest endpoint receives events from the main store. Sync log with status tracking.

= Architecture Principles =

* Zero external CDN dependencies — all assets bundled
* Zero front-end JavaScript site-wide — inline scripts only where needed
* Conditional asset loading — admin JS only on AMS pages
* All background jobs via Action Scheduler (bundled when WooCommerce is absent)
* AES-256-CBC encryption for all API credentials
* GDPR-compliant with double opt-in and tokenised unsubscribe

== Installation ==

= Standalone deployment (recommended) =

1. Upload the `apotheca-marketing-suite` folder to `wp-content/plugins/` on your marketing subdomain (a WordPress site without WooCommerce).
2. Activate the plugin through the 'Plugins' menu.
3. Go to **Apotheca Marketing > Sync** to link your main WooCommerce store (see "Linking Your Two Sites" above).
4. Navigate to Apotheca Marketing in the admin menu to begin setup.

= Co-located with WooCommerce =

1. Upload the `apotheca-marketing-suite` folder to `wp-content/plugins/` on your WooCommerce store.
2. Activate the plugin through the 'Plugins' menu.
3. The plugin detects WooCommerce and uses local hooks directly — no sync configuration needed.

For the full multi-site setup guide, see SETUP_GUIDE.md.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. Apotheca Marketing Suite runs in standalone mode without WooCommerce. Customer and order data is received via the ingest endpoint from the companion Apotheca Marketing Sync plugin installed on your WooCommerce store. If WooCommerce is present on the same site, the plugin uses local WC hooks alongside the ingest data.

= How do I link the marketing subdomain to my main store? =

Install the Apotheca Marketing Sync plugin on the main store, enter the same shared secret on both sites, and paste the Ingest Endpoint URL from the marketing subdomain into the sync plugin. Click Test Connection to verify. See SETUP_GUIDE.md for a step-by-step guide.

= Does this send emails directly? =

The plugin uses WordPress's wp_mail() function. For production use, configure a transactional email service (e.g., Amazon SES, Mailgun, Postmark) via an SMTP plugin.

= Is this GDPR compliant? =

Yes. The plugin includes double opt-in, consent tracking with timestamps, tokenised one-click unsubscribe, and a preference centre.

= What is standalone mode? =

When activated on a WordPress site without WooCommerce, the plugin shows an info notice and operates in standalone mode. All admin features work. All WooCommerce-specific hooks are silently skipped. Data arrives via the ingest endpoint. Action Scheduler is bundled inside the plugin.

== Changelog ==

= 1.0.0 =
* Initial release with all session features complete.
* Standalone deployment support — no WooCommerce required on the marketing site.
* Bundled Action Scheduler for standalone environments.
* HMAC-authenticated ingest endpoint with replay protection.
* SSO receiver with login error notice on expired tokens.
* Sync settings tab with Store URL, Shared Secret, Ingest Endpoint URL, Test Connection, and Sync Log.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
