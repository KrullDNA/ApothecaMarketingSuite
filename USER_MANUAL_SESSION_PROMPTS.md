# User Manual Build Prompts — 4 Sessions

Copy each prompt below into a new Claude Code session. Each session writes one section of the user manual as a Word document (.docx). After all 4 sessions, combine them into the final manual.

---

## SESSION 1: Installation, Settings & Subscriber Management

```
TASK: Write Part 1 of the Apotheca Marketing Suite User Manual as a Word document.

OUTPUT FILE: Apotheca_User_Manual_Part1.docx

You are writing a user manual for a WordPress/WooCommerce plugin called "Apotheca Marketing Suite". This plugin is brand new — no one has ever used it before. Every instruction must be explicit, step-by-step, and assume the reader knows WordPress basics but has never seen this plugin.

Write the following chapters with full step-by-step instructions. Read the source code files listed to understand exactly how each feature works before writing.

CHAPTER 1: INSTALLATION & ACTIVATION
- Read: apotheca-marketing-suite.php, includes/Plugin.php, includes/Database/Installer.php
- Cover: System requirements (WP 6.4+, PHP 8.0+, WooCommerce 8.0-9.0), uploading and activating the plugin, what happens on activation (14 database tables created), verifying successful installation, the admin menu location ("Apotheca® Marketing" in sidebar)

CHAPTER 2: GLOBAL SETTINGS & CONFIGURATION
- Read: includes/Settings.php, includes/Admin/Menu.php
- Cover: Navigating to Settings page, every setting explained:
  - GDPR: double opt-in toggle, what it does
  - Checkout opt-in checkbox text customisation
  - Registration subscriber capture toggle
  - Abandoned cart timeout (default 60 minutes)
  - Frequency caps: email (default 3/day), SMS (default 2/day)
  - Send window (default 8 AM – 9 PM) and what it means
  - Attribution window (default 5 days)
  - Unsubscribe page title and message customisation

CHAPTER 3: GDPR & COMPLIANCE
- Read: includes/GDPR/Handler.php
- Cover: How double opt-in works (confirmation email flow), the unsubscribe endpoint (/ams-unsubscribe/), how tokenised unsubscribe links work, consent timestamp tracking, one-click unsubscribe for subscribers

CHAPTER 4: SUBSCRIBER MANAGEMENT
- Read: includes/Subscriber/Repository.php, includes/Subscriber/CaptureHandler.php
- Cover: How subscribers are captured (checkout, registration, forms), viewing the subscriber list, searching and filtering subscribers, subscriber profile page (event timeline, send history, RFM score, tags), manually adding subscribers, CSV import/export, understanding subscriber statuses and tags

FORMAT RULES:
- Use Heading 1 for chapter titles, Heading 2 for sections, Heading 3 for subsections
- Use numbered lists for step-by-step instructions (Step 1, Step 2, etc.)
- Use bold for UI element names (button labels, menu items, field names)
- Add "TIP:" callouts for helpful advice
- Add "IMPORTANT:" callouts for critical warnings
- Write in second person ("You can...", "Click the...")
- Use python-docx to create the .docx file with proper formatting
- Install python-docx first if needed: pip install python-docx
```

---

## SESSION 2: Automated Flows, Segmentation & RFM Scoring

```
TASK: Write Part 2 of the Apotheca Marketing Suite User Manual as a Word document.

OUTPUT FILE: Apotheca_User_Manual_Part2.docx

You are writing a user manual for a WordPress/WooCommerce plugin called "Apotheca Marketing Suite". This plugin is brand new — no one has ever used it before. Every instruction must be explicit, step-by-step, and assume the reader knows WordPress basics but has never seen this plugin.

Write the following chapters with full step-by-step instructions. Read the source code files listed to understand exactly how each feature works before writing.

CHAPTER 5: AUTOMATED FLOWS — OVERVIEW
- Read: includes/Flows/FlowManager.php, includes/Flows/FlowRepository.php, includes/Flows/StepExecutor.php, includes/Flows/EnrolmentRepository.php
- Read: assets/js/flow-builder.js (scan for UI clues)
- Cover: What flows are and how they work, navigating to the Flows page, the flow builder interface overview, creating a new flow, naming and saving flows, activating/deactivating flows, how flow enrolment works (deduplication), frequency caps and send windows in flows

CHAPTER 6: FLOW TRIGGERS (8 TYPES) — STEP BY STEP FOR EACH
- Read: includes/Flows/Triggers/ (all 8 files: WelcomeSeries.php, AbandonedCart.php, PostPurchase.php, WinBack.php, BrowseAbandonment.php, Birthday.php, RfmChange.php, CustomEvent.php)
- For EACH of the 8 triggers, write:
  - What it does and when it fires
  - How to set it up step by step
  - Configuration options available
  - Example use case

CHAPTER 7: FLOW STEPS (8 TYPES) — STEP BY STEP FOR EACH
- Read: includes/Flows/Steps/ (all 8 files: SendEmail.php, SendSms.php, AddTag.php, RemoveTag.php, UpdateField.php, ConditionBranch.php, Wait.php, ExitFlow.php)
- For EACH of the 8 step types, write:
  - What it does
  - How to add it to a flow
  - Configuration options
  - Example use case

CHAPTER 8: PRE-BUILT FLOW TEMPLATES
- Read: templates/flows/ (all template files)
- Cover: What templates are available, how to import a template, what each template includes, how to customise imported templates

CHAPTER 9: SMART SEGMENTATION
- Read: includes/Segments/SegmentRepository.php, includes/Segments/SegmentCalculator.php, includes/Segments/ConditionEvaluator.php
- Read: assets/js/segment-builder.js (scan for UI clues)
- Cover: What segments are, navigating to Segments page, creating a new segment step by step, the 25+ condition types (list and explain each category: subscriber data, e-commerce data, engagement data), AND/OR logic and nested groups (up to 3 levels), live subscriber count preview, how automatic recalculation works (every 6 hours), editing and deleting segments

CHAPTER 10: RFM SCORING
- Read: includes/Analytics/RfmEngine.php, includes/Analytics/PredictiveEngine.php
- Cover: What RFM scoring is (Recency, Frequency, Monetary), the 8 named segments (Champions, Big Spenders, Loyal, New Customers, Potential, At Risk, About to Sleep, Lost) and what each means, how scores are calculated nightly, how to use RFM segments in flows and segments, viewing RFM data on subscriber profiles, predictive CLV and churn risk scores

FORMAT RULES:
- Use Heading 1 for chapter titles, Heading 2 for sections, Heading 3 for subsections
- Use numbered lists for step-by-step instructions (Step 1, Step 2, etc.)
- Use bold for UI element names (button labels, menu items, field names)
- Add "TIP:" callouts for helpful advice
- Add "IMPORTANT:" callouts for critical warnings
- Write in second person ("You can...", "Click the...")
- Use python-docx to create the .docx file with proper formatting
- Install python-docx first if needed: pip install python-docx
```

---

## SESSION 3: Forms, SMS, Email Editor & Reviews

```
TASK: Write Part 3 of the Apotheca Marketing Suite User Manual as a Word document.

OUTPUT FILE: Apotheca_User_Manual_Part3.docx

You are writing a user manual for a WordPress/WooCommerce plugin called "Apotheca Marketing Suite". This plugin is brand new — no one has ever used it before. Every instruction must be explicit, step-by-step, and assume the reader knows WordPress basics but has never seen this plugin.

Write the following chapters with full step-by-step instructions. Read the source code files listed to understand exactly how each feature works before writing.

CHAPTER 11: POP-UP & OPT-IN FORMS
- Read: includes/Forms/FormRepository.php, includes/Forms/FormLoader.php, includes/Forms/FormSubmissionHandler.php, includes/Forms/TargetingEvaluator.php, includes/Forms/SpinToWinHandler.php
- Read: assets/js/form-builder.js (scan for UI clues)
- Cover: The 6 form types (modal, flyout, embedded, full-page, sticky bar, spin-to-win) — describe each with when to use it. Creating a new form step by step, form builder interface walkthrough, field types available (9 types), targeting rules (10+ rules: page URL, device, scroll depth, exit intent, time on page, cart value, UTM, segment match, frequency cap) — explain each, success actions (tag application, flow enrolment, redirect, message), GDPR consent checkbox, rate limiting (10 per IP per minute)

CHAPTER 12: SPIN-TO-WIN FORMS
- Read: includes/Forms/SpinToWinHandler.php
- Cover: Dedicated deep-dive on spin-to-win, setting up prize segments, how WooCommerce coupons are auto-generated, configuring win probabilities, the subscriber experience

CHAPTER 13: SMS MARKETING
- Read: includes/Sms/SmsSender.php, includes/Sms/TwilioProvider.php, includes/Sms/SmsConsentManager.php, includes/Sms/CredentialEncryptor.php
- Read: assets/js/sms-campaign.js (scan for UI clues)
- Cover: Setting up Twilio integration step by step (where to get credentials, entering them in the plugin), how credentials are encrypted (AES-256-CBC), creating an SMS campaign, personalisation tokens available ({{first_name}}, {{order_total}}, {{cart_url}}, {{coupon_code}}, etc.), TCPA compliance (STOP/UNSTOP/HELP keywords), SMS consent management, MMS support (image URLs), delivery tracking and status updates, SMS frequency caps, sending test messages, viewing send history and delivery reports

CHAPTER 14: VISUAL EMAIL EDITOR
- Read: includes/Email/EmailRenderer.php, includes/Email/CssInliner.php
- Read: assets/js/email-editor.js (scan for UI clues)
- Cover: Navigating to the Email Editor, the drag-and-drop interface, the 12 block types (list and explain each), adding and arranging blocks, editing block content, personalisation tokens in emails, Google Fonts support (Montserrat and curated list), live preview functionality, how CSS inlining works for email clients, Outlook compatibility, mobile responsiveness, saving and using templates

CHAPTER 15: REVIEWS INTEGRATION
- Read: includes/Reviews/CacheRefresher.php, includes/Reviews/WooCommerceImporter.php, includes/Reviews/JudgeMeImporter.php, includes/Reviews/ReviewGateHandler.php, includes/Reviews/ReviewSelector.php
- Read: assets/js/reviews-settings.js (scan for UI clues)
- Cover: Setting up WooCommerce reviews import, setting up Judge.me integration (optional), how review gating works (4-5 stars → public review, 1-3 stars → private feedback), the Reviews Block in the email editor, how contextual review selection works (cart products for abandoned cart emails, top-rated for win-back), review card design customisation (star colour, reviewer name, date, verified badge, max reviews), nightly cache refresh, review settings configuration

FORMAT RULES:
- Use Heading 1 for chapter titles, Heading 2 for sections, Heading 3 for subsections
- Use numbered lists for step-by-step instructions (Step 1, Step 2, etc.)
- Use bold for UI element names (button labels, menu items, field names)
- Add "TIP:" callouts for helpful advice
- Add "IMPORTANT:" callouts for critical warnings
- Write in second person ("You can...", "Click the...")
- Use python-docx to create the .docx file with proper formatting
- Install python-docx first if needed: pip install python-docx
```

---

## SESSION 4: Analytics, AI Features, Elementor Widgets, Sync & Troubleshooting

```
TASK: Write Part 4 of the Apotheca Marketing Suite User Manual as a Word document.

OUTPUT FILE: Apotheca_User_Manual_Part4.docx

You are writing a user manual for a WordPress/WooCommerce plugin called "Apotheca Marketing Suite". This plugin is brand new — no one has ever used it before. Every instruction must be explicit, step-by-step, and assume the reader knows WordPress basics but has never seen this plugin.

Write the following chapters with full step-by-step instructions. Read the source code files listed to understand exactly how each feature works before writing.

CHAPTER 16: ANALYTICS DASHBOARD
- Read: includes/Analytics/AnalyticsAggregator.php, includes/Analytics/RevenueAttributor.php, includes/Analytics/RfmEngine.php, includes/Analytics/PredictiveEngine.php
- Read: assets/js/analytics-dashboard.js (scan for UI clues)
- Cover: Navigating to Analytics, the dashboard tabs (Overview, Email Performance, SMS Performance, Subscriber Insights, Flow Analytics), understanding each metric displayed, revenue attribution (last-click, 5-day window) — how it works and what it means, RFM heatmap — how to read it, churn risk chart, CLV histogram, flow funnel visualisation — understanding drop-off rates, CSV export functionality, how daily aggregation works (via Action Scheduler)

CHAPTER 17: AI-POWERED FEATURES
- Read: includes/AI/OpenAiClient.php, includes/AI/SubjectLineGenerator.php, includes/AI/EmailBodyGenerator.php, includes/AI/SendTimeOptimiser.php, includes/AI/ProductRecommender.php, includes/AI/SegmentSuggester.php
- Read: assets/js/ai-settings.js (scan for UI clues)
- Cover: Setting up OpenAI integration step by step (getting API key, entering it), how the API key is encrypted, enabling/disabling individual AI features, monthly token budget (default 500K) and warnings at 80%, AI Subject Line Generator — how to use it (generates 5 options with emoji variants), AI Email Body Generator — how to use it (structured output by goal and tone), AI Send-Time Optimisation — what it does and how to enable, Product Recommendations — how they work, AI Segment Suggestions — how to use and import suggestions, viewing AI usage logs (tokens, cost, feature type), cost tracking

CHAPTER 18: ELEMENTOR WIDGETS
- Read: includes/Elementor/WidgetLoader.php, includes/Elementor/Widgets/OptInForm.php, includes/Elementor/Widgets/SubscriberCountBadge.php, includes/Elementor/Widgets/CampaignArchive.php, includes/Elementor/Widgets/PreferenceCentre.php
- Cover: Requirements (Elementor must be installed), how the 4 widgets appear in Elementor. For EACH widget:
  - Widget 1: Opt-In Form — adding to a page, all customisation options (typography, fields, button styles, spacing, consent text)
  - Widget 2: Subscriber Count Badge — adding to a page, customising number/label typography, container styling
  - Widget 3: Campaign Archive — adding to a page, grid/list layout options, card styling (normal + hover), typography
  - Widget 4: Preference Centre — adding to a page, typography, toggle styling, button styles, section and spacing controls

CHAPTER 19: SUBDOMAIN SYNC & SSO
- Read: includes/Sync/SSOHandler.php, includes/Sync/SyncIngestor.php
- Read: apotheca-marketing-sync/ (the sync plugin files)
- Read: assets/js/sync-settings.js (scan for UI clues)
- Cover: What subdomain sync is and when you need it, installing the sync plugin on the main store, configuring the shared secret on both sites, what events are synced (new order, order status change, customer registration, product view, cart update, abandoned cart), how HMAC-SHA256 authentication works (simplified for users), SSO — one-click login from main store toolbar, sync health dashboard on main store, troubleshooting sync issues

CHAPTER 20: CAMPAIGNS
- Read: includes/API/SmsController.php and any campaign-related files
- Cover: Creating and sending email campaigns, creating and sending SMS campaigns, scheduling campaigns, campaign statuses (draft, scheduled, sent), viewing campaign results

CHAPTER 21: BACKGROUND JOBS & MAINTENANCE
- Cover: What Action Scheduler does for the plugin, the 14 scheduled jobs and what each does (list them), the job health monitor in Settings, what to do if jobs aren't running, abandoned cart detection timing, segment recalculation timing, RFM nightly calculation, analytics aggregation

CHAPTER 22: UNINSTALLING THE PLUGIN
- Read: uninstall.php
- Cover: Deactivating vs uninstalling, what happens on deactivation (scheduled jobs removed), the data retention choice on uninstall, what data is deleted if you choose full removal (14 tables, all options, metadata, encrypted credentials)

CHAPTER 23: TROUBLESHOOTING & FAQ
- Cover common issues: Plugin won't activate (check requirements), emails not sending (check WordPress mail), SMS not sending (check Twilio credentials), flows not running (check Action Scheduler), segments showing 0 (wait for recalculation), AI features not working (check API key and budget), forms not appearing (check targeting rules), sync not working (check shared secret and domain)

FORMAT RULES:
- Use Heading 1 for chapter titles, Heading 2 for sections, Heading 3 for subsections
- Use numbered lists for step-by-step instructions (Step 1, Step 2, etc.)
- Use bold for UI element names (button labels, menu items, field names)
- Add "TIP:" callouts for helpful advice
- Add "IMPORTANT:" callouts for critical warnings
- Write in second person ("You can...", "Click the...")
- Use python-docx to create the .docx file with proper formatting
- Install python-docx first if needed: pip install python-docx
```

---

## AFTER ALL 4 SESSIONS

After completing all 4 sessions, you will have:
- `Apotheca_User_Manual_Part1.docx` (Chapters 1-4)
- `Apotheca_User_Manual_Part2.docx` (Chapters 5-10)
- `Apotheca_User_Manual_Part3.docx` (Chapters 11-15)
- `Apotheca_User_Manual_Part4.docx` (Chapters 16-23)

You can then combine them into one document or use them as separate volumes.
