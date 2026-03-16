=== Apotheca Marketing Suite ===
Contributors: apotheca
Tags: email marketing, sms, woocommerce, automation, flows
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium WooCommerce email and SMS marketing automation suite with flows, segmentation, RFM scoring, pop-up forms, AI features, and analytics.

== Description ==

Apotheca Marketing Suite is a self-hosted marketing automation platform built exclusively for WooCommerce. It replaces external email marketing services with a fully integrated, privacy-first solution that runs on your own WordPress installation.

**Key Features:**

* **Automated Flows** — 8 trigger types including welcome series, abandoned cart recovery, post-purchase, win-back, browse abandonment, birthday, RFM segment changes, and custom events.
* **Smart Segmentation** — 25+ condition types with nested AND/OR logic, automatic recalculation every 6 hours.
* **RFM Scoring** — Nightly recency/frequency/monetary analysis with 8 named segments (Champions, Big Spenders, Loyal, etc.).
* **Pop-Up Forms** — 6 form types: modal, flyout, embedded, full-page, sticky bar, and spin-to-win with WooCommerce coupon generation.
* **SMS Integration** — Twilio-powered SMS campaigns with TCPA compliance, keyword opt-out, and delivery tracking.
* **Analytics Dashboard** — Revenue attribution, email/SMS performance, RFM heatmap, flow funnels, and CSV export.
* **AI Features** — OpenAI-powered subject line generation, email body drafting, send-time optimisation, product recommendations, and segment suggestions.
* **Visual Email Editor** — Drag-and-drop block editor with 12 block types, Montserrat typography, Outlook compatibility, and live preview.
* **Elementor Widgets** — 4 widgets: opt-in form, subscriber count badge, campaign archive, and preference centre.
* **Reviews Integration** — WooCommerce + Judge.me review imports, review gating (4-5 star → public review, 1-3 → private feedback), social proof email blocks.
* **Subdomain Sync** — Receive events from a main WooCommerce store via HMAC-authenticated webhooks. SSO for seamless admin access.

**Architecture Principles:**

* Zero external CDN dependencies — all assets bundled
* Zero front-end JavaScript site-wide — inline scripts only where needed
* Conditional asset loading — admin JS only on AMS pages
* All background jobs via Action Scheduler (bundled with WooCommerce)
* AES-256-CBC encryption for all API credentials
* GDPR-compliant with double opt-in and tokenised unsubscribe

== Installation ==

1. Upload the `apotheca-marketing-suite` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. WooCommerce must be installed and active.
4. Navigate to Apotheca Marketing in the admin menu to begin setup.

For multi-site sync setups, see SETUP_GUIDE.md.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. Apotheca Marketing Suite requires WooCommerce 8.0 or later.

= Does this send emails directly? =

The plugin uses WordPress's wp_mail() function. For production use, configure a transactional email service (e.g., Amazon SES, Mailgun, Postmark) via an SMTP plugin.

= Is this GDPR compliant? =

Yes. The plugin includes double opt-in, consent tracking with timestamps, tokenised one-click unsubscribe, and a preference centre.

== Changelog ==

= 1.0.0 =
* Initial release with all 11 session features complete.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
