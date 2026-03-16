#!/usr/bin/env python3
"""Generate Apotheca Marketing Suite User Manual — Part 3 (Chapters 11–15)."""

from docx import Document
from docx.shared import Pt, RGBColor
import os

doc = Document()

# ── Styles ──────────────────────────────────────────────────────────────────

style = doc.styles['Normal']
font = style.font
font.name = 'Calibri'
font.size = Pt(11)
style.paragraph_format.space_after = Pt(6)

for level in range(1, 4):
    heading_style = doc.styles[f'Heading {level}']
    heading_style.font.color.rgb = RGBColor(0x1B, 0x3A, 0x5C)


# Helper functions
def add_heading1(text):
    p = doc.add_heading(text, level=1)
    doc.add_paragraph()
    return p

def add_heading2(text):
    return doc.add_heading(text, level=2)

def add_heading3(text):
    return doc.add_heading(text, level=3)

def add_para(text):
    return doc.add_paragraph(text)

def add_bold_para(label, text):
    p = doc.add_paragraph()
    run = p.add_run(label)
    run.bold = True
    p.add_run(text)
    return p

def add_tip(text):
    p = doc.add_paragraph()
    run = p.add_run('TIP: ')
    run.bold = True
    run.font.color.rgb = RGBColor(0x0B, 0x6E, 0x2F)
    p.add_run(text)
    return p

def add_important(text):
    p = doc.add_paragraph()
    run = p.add_run('IMPORTANT: ')
    run.bold = True
    run.font.color.rgb = RGBColor(0xC0, 0x39, 0x2B)
    p.add_run(text)
    return p

def add_step(number, text):
    p = doc.add_paragraph()
    run = p.add_run(f'Step {number}: ')
    run.bold = True
    p.add_run(text)
    return p

def add_bullet(text):
    return doc.add_paragraph(text, style='List Bullet')

def add_bold_bullet(label, text):
    p = doc.add_paragraph(style='List Bullet')
    run = p.add_run(label)
    run.bold = True
    p.add_run(text)
    return p


# ════════════════════════════════════════════════════════════════════════════
# CHAPTER 11: POP-UP & OPT-IN FORMS
# ════════════════════════════════════════════════════════════════════════════

add_heading1('Chapter 11: Pop-Up & Opt-In Forms')

add_heading2('11.1 Overview')
add_para(
    'Apotheca Marketing Suite includes a powerful form builder that lets you create opt-in forms '
    'to grow your subscriber list. Forms can collect email addresses, phone numbers, names, birthdays, '
    'and custom data — all without any coding. Each form can be precisely targeted to specific pages, '
    'devices, visitor types, and behaviours.'
)

add_heading2('11.2 The 6 Form Types')
add_para('You can create six different types of opt-in forms, each suited to different situations:')

add_heading3('11.2.1 Modal (Pop-Up)')
add_para(
    'A modal is a classic pop-up that appears in the centre of the screen with a dimmed overlay behind it. '
    'It demands attention and is excellent for high-impact offers like welcome discounts or flash sales.'
)
add_bold_para('When to use: ', 'Welcome discount offers, exit-intent promotions, major announcements. '
    'Modals have the highest conversion rates but can be intrusive, so use targeting rules to show them at the right moment.')

add_heading3('11.2.2 Flyout')
add_para(
    'A flyout is a smaller form that slides in from the bottom-right or bottom-left corner of the screen. '
    'It is less intrusive than a modal but still eye-catching.'
)
add_bold_para('When to use: ', 'Newsletter sign-ups, content upgrades, secondary offers. '
    'Flyouts work well on content-heavy pages where a full modal would disrupt the reading experience.')

add_heading3('11.2.3 Embedded')
add_para(
    'An embedded form is placed directly within your page content using the [ams_form id="123"] shortcode. '
    'It becomes part of the page layout and does not overlay or interrupt the user experience.'
)
add_bold_para('When to use: ', 'Inline newsletter sign-ups within blog posts, footer sign-up sections, '
    'sidebar widgets. Embedded forms are the least intrusive option and feel native to the page.')

add_heading3('11.2.4 Full Page')
add_para(
    'A full-page form takes over the entire browser viewport, creating a landing page-style experience. '
    'It blocks all other content until the visitor interacts with it.'
)
add_bold_para('When to use: ', 'High-stakes lead capture, product launch announcements, age verification gates. '
    'Use sparingly — full-page forms are the most aggressive option and should be reserved for situations where capturing the lead is your primary goal.')

add_heading3('11.2.5 Sticky Bar')
add_para(
    'A sticky bar is a thin horizontal banner that sticks to the top or bottom of the browser window. '
    'It remains visible as the visitor scrolls without blocking any content.'
)
add_bold_para('When to use: ', 'Site-wide promotions, free shipping announcements, '
    'low-urgency newsletter sign-ups. Sticky bars have lower conversion rates than modals but are non-intrusive and can run continuously.')

add_heading3('11.2.6 Spin-to-Win')
add_para(
    'A spin-to-win form is a gamified modal featuring an interactive prize wheel. '
    'Visitors enter their email address, spin the wheel, and win a randomly selected prize (typically a discount coupon). '
    'The plugin automatically generates WooCommerce coupon codes for winners.'
)
add_bold_para('When to use: ', 'Gamified lead capture, holiday promotions, engagement campaigns. '
    'Spin-to-win forms have exceptionally high conversion rates because of the game element. See Chapter 12 for a detailed deep-dive.')

add_heading2('11.3 Creating a New Form')
add_step(1, 'In your WordPress admin, navigate to Apotheca Marketing > Forms.')
add_step(2, 'Click the Create New Form button.')
add_step(3, 'Select a Form Type from the six options (Modal, Flyout, Embedded, Full Page, Sticky Bar, or Spin-to-Win).')
add_step(4, 'Enter a Form Name to identify this form in your dashboard (e.g., "Homepage Welcome Pop-Up" or "Blog Sidebar Sign-Up").')
add_step(5, 'The form builder wizard opens with multiple configuration steps. Complete each step (see sections below).')
add_step(6, 'Click Save Form when finished.')
add_step(7, 'Set the form status to Active to make it live on your site.')

add_heading2('11.4 Form Builder Interface Walkthrough')
add_para(
    'The form builder is a multi-step wizard with the following tabs. For Spin-to-Win forms, '
    'an additional Spin Config tab is available.'
)

add_heading3('Tab 1: Fields')
add_para(
    'Configure which fields appear on your form. You can add, remove, and reorder fields. '
    'Every form requires at least an Email field.'
)

add_heading3('Tab 2: Design')
add_para(
    'Customise the visual appearance of your form, including colours, fonts, background images, '
    'title text, description text, and button labels.'
)

add_heading3('Tab 3: Targeting')
add_para(
    'Define when, where, and to whom your form should be displayed. '
    'Configure page targeting, device targeting, display triggers, and frequency caps.'
)

add_heading3('Tab 4: Success')
add_para(
    'Configure what happens after a visitor submits the form — success messages, redirects, '
    'tag application, flow enrolment, and double opt-in settings.'
)

add_heading3('Tab 5: Spin Config (Spin-to-Win forms only)')
add_para(
    'Configure the prize wheel segments, probabilities, discount types, and coupon settings. '
    'This tab only appears when you select the Spin-to-Win form type.'
)

add_para('The form builder includes a Live Preview panel that shows a real-time preview of your form as you make changes. You can toggle between desktop and mobile views to ensure your form looks great on all devices.')

add_heading2('11.5 Field Types Available (9 Types)')
add_para('The form builder supports 9 field types that you can add to your forms:')

add_heading3('1. Email')
add_para('Collects the visitor\'s email address. This field is required on every form and cannot be removed. It is the primary identifier for creating subscriber records.')

add_heading3('2. Phone')
add_para('Collects the visitor\'s phone number for SMS marketing. When provided, the subscriber is marked as SMS opt-in eligible.')

add_heading3('3. First Name')
add_para('Collects the visitor\'s first name for personalisation in emails and SMS messages (used with the {{first_name}} token).')

add_heading3('4. Last Name')
add_para('Collects the visitor\'s last name for personalisation (used with the {{last_name}} and {{full_name}} tokens).')

add_heading3('5. Birthday')
add_para('Collects the visitor\'s birthday in MM-DD format. This data is stored in the subscriber\'s custom fields and is used by the Birthday flow trigger (see Chapter 6).')

add_heading3('6. Radio')
add_para('A radio button group that lets the visitor select one option from a predefined list. Useful for collecting preferences (e.g., "Which product category interests you most?"). The selected value is stored as a custom field.')

add_heading3('7. Checkbox')
add_para('A single checkbox for binary choices (e.g., "I agree to receive marketing communications"). Can also be used for collecting simple yes/no preferences. The value is stored as a custom field.')

add_heading3('8. Dropdown')
add_para('A dropdown select menu for choosing one option from a list. Similar to radio buttons but takes up less space. The selected value is stored as a custom field.')

add_heading3('9. Hidden')
add_para('A hidden field that is not visible to the visitor. Useful for passing tracking data like UTM parameters, page URL, or campaign identifiers. The value is set programmatically and stored as a custom field.')

add_heading2('11.6 Targeting Rules')
add_para(
    'Targeting rules control when and where your form is displayed. Some rules are evaluated on the server '
    '(before the page loads) and others are evaluated in the browser (client-side). Together, they give you '
    'precise control over your form\'s visibility.'
)

add_heading3('Server-Side Targeting Rules')

add_bold_bullet('Page URL (Pages) — ', 'control which pages show the form. Options: All pages (show everywhere), '
    'Specific pages (only show on selected page IDs), Exclude pages (show everywhere except selected page IDs).')

add_bold_bullet('Device — ', 'target based on the visitor\'s device type. Options: All devices, Desktop only, Mobile only. '
    'Useful when your form design works better on one device type, or when you want different offers for mobile vs. desktop visitors.')

add_bold_bullet('Segment Match — ', 'show the form only to visitors who belong to a specific subscriber segment. '
    'This requires the visitor to be a known subscriber (identified via cookie). If no segment is selected, the form shows to all visitors.')

add_heading3('Client-Side Targeting Rules (Browser Triggers)')

add_bold_bullet('Scroll Depth — ', 'trigger the form when the visitor scrolls past a specified percentage of the page. '
    'For example, set to 50 to show the form when the visitor is halfway down the page. '
    'Great for engaging visitors who are actively reading your content.')

add_bold_bullet('Time on Page — ', 'trigger the form after the visitor has spent a specified number of seconds on the page. '
    'For example, set to 30 to show the form after 30 seconds. This ensures the visitor has had time to engage with your content before being asked to subscribe.')

add_bold_bullet('Exit Intent — ', 'trigger the form when the visitor moves their mouse cursor towards the top of the browser window, '
    'indicating they are about to leave the page. This is desktop-only — exit intent detection does not work on mobile devices. '
    'This is one of the most effective triggers because it catches visitors at the moment they would otherwise leave.')

add_bold_bullet('Cart Value — ', 'show the form only when the visitor\'s WooCommerce cart value meets a minimum threshold. '
    'For example, set to 50 to only show a free shipping offer when the cart contains at least $50 worth of products.')

add_bold_bullet('Visitor Type — ', 'target new visitors (first visit) or returning visitors. '
    'Options: All visitors, New visitors only, Returning visitors only. '
    'Useful for showing a welcome offer only to first-time visitors.')

add_bold_bullet('UTM Rules — ', 'show the form only when the visitor arrived via specific UTM parameters in the URL. '
    'You can match on utm_source, utm_medium, utm_campaign, and other UTM tags. '
    'This is powerful for showing campaign-specific offers to visitors from particular traffic sources (e.g., show a Facebook-specific discount to visitors with utm_source=facebook).')

add_bold_bullet('Frequency Cap — ', 'limit how often the same visitor sees the form. Set a number of days between displays. '
    'For example, set to 7 to prevent the form from appearing more than once every 7 days for the same visitor. '
    'A value of 0 means no frequency cap (the form can appear on every page load).')

add_tip('Combine multiple targeting rules for precision. For example: show a modal on the homepage only, to mobile visitors, after 15 seconds, with a 30-day frequency cap.')

add_important('Exit intent only works on desktop devices. For mobile visitors, use scroll depth or time on page triggers instead.')

add_heading2('11.7 Success Actions')
add_para(
    'After a visitor submits the form, the plugin processes the submission and executes one or more success actions. '
    'These actions determine what happens next.'
)

add_heading3('Success Message')
add_para('Display a thank-you message within the form. The form fields are replaced with your custom message text (e.g., "Thanks for subscribing! Check your inbox for a welcome email.").')

add_heading3('Redirect URL')
add_para('Redirect the visitor to a specific page after submission. You can redirect to a custom thank-you page, a special offer page, or any URL. If both a message and a redirect are configured, the message is shown briefly before the redirect occurs.')

add_heading3('Tag Application')
add_para('Automatically add one or more tags to the new subscriber. Tags applied via form submission are useful for tracking which form a subscriber came from (e.g., "source:homepage-popup" or "interest:skincare"). These tags can be used in segments and flow condition branches.')

add_heading3('Flow Enrolment')
add_para('Automatically enrol the new subscriber into a specific flow. This is a powerful way to chain a form submission directly into an automation. For example, a welcome pop-up could enrol subscribers directly into your Welcome Series flow.')

add_heading3('Double Opt-In')
add_para('When enabled, the subscriber receives a confirmation email and must click the confirmation link before being marked as "subscribed". This is recommended for GDPR compliance and helps maintain a clean, engaged subscriber list.')

add_heading2('11.8 GDPR Consent Checkbox')
add_para(
    'The form builder supports adding a GDPR consent checkbox to your forms via the Checkbox field type. '
    'When a visitor checks this box, their GDPR consent is recorded on their subscriber profile.'
)
add_step(1, 'In the Fields tab, click Add Field and select Checkbox.')
add_step(2, 'Set the field label to your consent text (e.g., "I agree to receive marketing emails and accept the Privacy Policy").')
add_step(3, 'The consent status is stored on the subscriber\'s profile and can be used in segment conditions (gdpr_consent is_true / is_false).')

add_important('If your store serves EU customers, you should enable the GDPR consent checkbox on all opt-in forms. Consult your legal advisor for the specific consent language required for your jurisdiction.')

add_heading2('11.9 Rate Limiting')
add_para(
    'To prevent abuse and spam submissions, the form submission handler enforces rate limiting:'
)
add_bold_bullet('Limit: ', '10 form submissions per IP address per minute.')
add_para(
    'If a visitor exceeds this limit, further submissions from that IP are rejected until the rate limit window resets. '
    'This protects your subscriber database from bot spam and malicious form flooding without affecting legitimate visitors.'
)

add_tip('If you experience issues with legitimate users being rate-limited (e.g., in office environments where many users share an IP), consider that the 10-per-minute limit is generous enough for normal use. If problems persist, check for bot traffic on your site.')


# ════════════════════════════════════════════════════════════════════════════
# CHAPTER 12: SPIN-TO-WIN FORMS
# ════════════════════════════════════════════════════════════════════════════

add_heading1('Chapter 12: Spin-to-Win Forms')

add_heading2('12.1 What Are Spin-to-Win Forms?')
add_para(
    'Spin-to-win forms are a gamified variation of the modal opt-in form. Instead of a simple sign-up, '
    'the visitor enters their email address and spins an interactive prize wheel. The wheel lands on a randomly '
    'selected segment, and the visitor receives the corresponding prize — typically a discount coupon that is '
    'automatically generated as a WooCommerce coupon.'
)
add_para(
    'The gamification element dramatically increases conversion rates because visitors feel they are '
    '"winning" something rather than simply signing up. The randomness creates excitement and urgency.'
)

add_heading2('12.2 Setting Up Prize Segments')
add_para(
    'Each spin-to-win form can have up to 8 prize segments on the wheel. Each segment represents a possible '
    'outcome when the visitor spins.'
)

add_step(1, 'Create a new form and select Spin-to-Win as the form type.')
add_step(2, 'Complete the Fields, Design, and Targeting tabs as with any other form type.')
add_step(3, 'Navigate to the Spin Config tab.')
add_step(4, 'You will see the prize segment editor. Each segment has the following options:')

add_bold_bullet('Label — ', 'the text displayed on the wheel segment (e.g., "10% Off", "Free Shipping", "25% Off", "Try Again"). Keep labels short for readability.')
add_bold_bullet('Colour — ', 'the background colour of the wheel segment. Use alternating colours for visual contrast.')
add_bold_bullet('Probability — ', 'the weight (likelihood) that this segment will be selected. Higher numbers make the segment more likely to be chosen. See Section 12.4 for details on how probabilities work.')
add_bold_bullet('Discount Type — ', 'the type of discount for this prize. Options: percent (percentage discount), fixed (fixed cart discount in your store currency), or leave empty for a "no prize" segment (e.g., "Try Again").')
add_bold_bullet('Discount Value — ', 'the numeric discount amount (e.g., 10 for 10% off or 5 for $5 off).')
add_bold_bullet('Free Shipping — ', 'check this to make the prize a free shipping coupon instead of (or in addition to) a discount amount.')
add_bold_bullet('Minimum Spend — ', 'an optional minimum cart value required to use the coupon (e.g., set to 50 to require a $50+ cart).')
add_bold_bullet('Expiry Days — ', 'how many days until the generated coupon expires. Default is 30 days.')

add_step(5, 'Add segments by clicking Add Segment. Configure each segment\'s label, colour, probability, and discount settings.')
add_step(6, 'Click Save Form when all segments are configured.')

add_tip('A good wheel has 6-8 segments with a mix of prizes. Include at least one "Try Again" or "Better Luck Next Time" segment (with no discount value) to create realistic odds and control your discount exposure.')

add_heading2('12.3 How WooCommerce Coupons Are Auto-Generated')
add_para(
    'When a visitor spins the wheel and wins a prize that includes a discount value or free shipping, '
    'the plugin automatically creates a real WooCommerce coupon. Here is how it works:'
)

add_step(1, 'The visitor enters their email and clicks the Spin button.')
add_step(2, 'The server-side SpinToWinHandler selects a winning segment using the weighted random algorithm (see Section 12.4).')
add_step(3, 'If the winning segment has a discount_value or free_shipping flag, a WooCommerce coupon is automatically generated.')
add_step(4, 'The coupon is created with the following properties:')

add_bold_bullet('Coupon code format: ', 'AMS-SPIN-XXXXXXXX (where XXXXXXXX is 8 random alphanumeric characters). Example: AMS-SPIN-K7M3NP2Q.')
add_bold_bullet('Discount type: ', 'either "percent" (percentage discount) or "fixed_cart" (fixed amount discount), based on the segment\'s discount_type setting.')
add_bold_bullet('Individual use: ', 'Yes — the coupon cannot be combined with other coupons.')
add_bold_bullet('Usage limit: ', '1 — the coupon can only be used once.')
add_bold_bullet('Email restriction: ', 'if the visitor provided an email, the coupon is restricted to that email address only.')
add_bold_bullet('Expiry: ', 'set to the segment\'s expiry_days value (default: 30 days from creation).')
add_bold_bullet('Minimum spend: ', 'applied if configured on the segment.')
add_bold_bullet('Free shipping: ', 'enabled if the segment has the free_shipping flag set.')

add_step(5, 'The coupon code is returned to the browser and displayed to the visitor along with the winning message.')

add_important('Coupons are real WooCommerce coupons. You can view and manage them in WooCommerce > Coupons. They appear with the description "Spin-to-win prize: [segment label]".')

add_heading2('12.4 Configuring Win Probabilities')
add_para(
    'Each prize segment has a probability weight that determines how likely it is to be selected. '
    'The probability system uses weighted random selection — segments with higher weights are chosen more often.'
)

add_heading3('How Probability Weights Work')
add_para(
    'The system adds up all segment probability values to get a total weight, then randomly selects '
    'a number within that total. The segment whose cumulative range includes the random number wins.'
)

add_para('Example with 4 segments:')
add_bullet('10% Off — probability: 40')
add_bullet('25% Off — probability: 15')
add_bullet('Free Shipping — probability: 20')
add_bullet('Try Again — probability: 25')
add_para('Total weight: 40 + 15 + 20 + 25 = 100')
add_para('In this example:')
add_bullet('10% Off wins 40% of the time (40/100)')
add_bullet('25% Off wins 15% of the time (15/100)')
add_bullet('Free Shipping wins 20% of the time (20/100)')
add_bullet('Try Again wins 25% of the time (25/100)')

add_tip('You don\'t need probabilities to add up to 100. The system normalises the weights automatically. If your weights are 2, 1, 1, that\'s equivalent to 50%, 25%, 25%.')

add_important('Prize determination happens entirely on the server side. The wheel animation in the browser is purely visual — the server decides the outcome first, then the wheel animates to the winning segment. This prevents any client-side manipulation.')

add_heading2('12.5 The Subscriber Experience')
add_para('Here is what a visitor experiences when interacting with a spin-to-win form:')

add_step(1, 'The spin-to-win modal appears based on your targeting rules (e.g., after 10 seconds on the page).')
add_step(2, 'The visitor sees the prize wheel with all segment labels visible, plus your form fields (at minimum, an email field).')
add_step(3, 'The visitor enters their email address and any other required fields, then clicks the Spin button.')
add_step(4, 'The form data is submitted to the server. The server creates (or updates) the subscriber record, determines the winning segment, and generates a coupon if applicable.')
add_step(5, 'The wheel animates and lands on the winning segment.')
add_step(6, 'The visitor sees the result — either a winning message with their coupon code, or a "Try Again" / "Better Luck Next Time" message for no-prize segments.')
add_step(7, 'The coupon code is displayed prominently so the visitor can copy it. They may also receive it via email if you have a flow configured to send a follow-up.')

add_tip('Create a flow with a Custom Event trigger or tag-based automation to email the coupon code to the winner as well. This way they have a copy in their inbox even if they close the pop-up.')


# ════════════════════════════════════════════════════════════════════════════
# CHAPTER 13: SMS MARKETING
# ════════════════════════════════════════════════════════════════════════════

add_heading1('Chapter 13: SMS Marketing')

add_heading2('13.1 Overview')
add_para(
    'Apotheca Marketing Suite includes built-in SMS marketing powered by Twilio. You can send SMS and MMS messages '
    'to your subscribers, create SMS campaigns targeted at specific segments, and include SMS steps in your automated flows. '
    'The plugin handles consent management, delivery tracking, retry logic, and TCPA compliance automatically.'
)

add_heading2('13.2 Setting Up Twilio Integration')
add_para(
    'Before you can send SMS messages, you need a Twilio account and must configure your Twilio credentials in the plugin.'
)

add_heading3('Getting Your Twilio Credentials')
add_step(1, 'Go to twilio.com and create an account (or sign in to your existing account).')
add_step(2, 'From the Twilio Console dashboard, locate your Account SID — this is a string starting with "AC" followed by 32 characters.')
add_step(3, 'Locate your Auth Token — click the eye icon to reveal it. Copy it immediately as it may be hidden after you navigate away.')
add_step(4, 'Purchase a phone number or set up a Messaging Service in Twilio. You need either a phone number (e.g., +15551234567) or a Messaging Service SID (starts with "MG").')

add_heading3('Entering Credentials in the Plugin')
add_step(1, 'In your WordPress admin, navigate to Apotheca Marketing > SMS.')
add_step(2, 'Click the Settings tab.')
add_step(3, 'Enter your Account SID in the Account SID field.')
add_step(4, 'Enter your Auth Token in the Auth Token field.')
add_step(5, 'Enter your Twilio phone number or Messaging Service SID in the From Number field. If you use a Messaging Service, enter the full SID starting with "MG".')
add_step(6, 'Optionally customise the Help Text that is included when subscribers text HELP (default: "Reply STOP to opt out. Reply HELP for help. Msg&data rates may apply.").')
add_step(7, 'Click Save Credentials.')
add_step(8, 'The configuration status indicator will turn green if all three credentials are present.')

add_para('The settings page also displays two webhook URLs that you must configure in your Twilio account:')
add_bold_bullet('Inbound Webhook URL — ', 'configure this as the webhook URL for incoming messages on your Twilio phone number. This handles STOP/UNSTOP/HELP replies.')
add_bold_bullet('Status Callback URL — ', 'this is automatically included with every outbound message to track delivery status.')

add_heading2('13.3 How Credentials Are Encrypted')
add_para(
    'Your Twilio credentials are sensitive and are stored encrypted in the WordPress database using AES-256-CBC encryption.'
)
add_bullet('The encryption key is derived from your WordPress AUTH_KEY constant (defined in wp-config.php) using SHA-256 hashing to produce a 32-byte key.')
add_bullet('Each encryption operation uses a random initialisation vector (IV) for security.')
add_bullet('The encrypted credentials are stored in the wp_options table under the key ams_sms_credentials.')
add_bullet('Credentials are decrypted on the fly when needed for sending messages — they are never stored or transmitted in plain text.')

add_important('Your WordPress AUTH_KEY must remain consistent. If you change your AUTH_KEY in wp-config.php, you will need to re-enter your Twilio credentials as the old encrypted values can no longer be decrypted.')

add_heading2('13.4 Creating an SMS Campaign')
add_step(1, 'Navigate to Apotheca Marketing > SMS.')
add_step(2, 'On the Campaigns tab, click Create New Campaign.')
add_step(3, 'Enter a Campaign Name (e.g., "Holiday Sale Announcement").')
add_step(4, 'Write your SMS Body. Use the token insertion buttons to add personalisation tokens (see Section 13.5).')
add_step(5, 'Select a Segment to target. The campaign will be sent to all subscribers in the selected segment who have SMS opt-in enabled.')
add_step(6, 'Optionally add a Media URL for MMS (e.g., a product image URL — see Section 13.10).')
add_step(7, 'Review the character count indicator. Standard SMS is 160 characters; messages with personalisation tokens may vary in length.')
add_step(8, 'Click Save to save the campaign as a draft.')
add_step(9, 'When ready, click Send Campaign and confirm the send action.')

add_para('The campaign editor shows a live preview with sample token replacements so you can see approximately what the final message will look like.')

add_important('Sending a campaign is immediate and cannot be undone. Double-check your message, segment, and subscriber count before confirming.')

add_heading2('13.5 Personalisation Tokens')
add_para('The following personalisation tokens are available in SMS messages:')

add_heading3('Subscriber Tokens')
add_bullet('{{first_name}} — subscriber\'s first name (defaults to "there" if empty)')
add_bullet('{{last_name}} — subscriber\'s last name')
add_bullet('{{email}} — subscriber\'s email address')
add_bullet('{{phone}} — subscriber\'s phone number')
add_bullet('{{shop_name}} — your WordPress site name')
add_bullet('{{shop_url}} — your WordPress site URL')
add_bullet('{{unsubscribe_url}} — one-click unsubscribe link')

add_heading3('WooCommerce Tokens')
add_bullet('{{order_number}} — the related order number (for order-related flows)')
add_bullet('{{order_total}} — the order total amount')
add_bullet('{{product_name}} — the primary product name')
add_bullet('{{cart_url}} — link to the WooCommerce cart page')
add_bullet('{{coupon_code}} — a coupon code (when provided in the flow context)')

add_tip('Use the token insertion buttons in the campaign editor rather than typing tokens manually. This prevents typos that would cause tokens to appear as literal text in your messages.')

add_heading2('13.6 TCPA Compliance')
add_para(
    'The Telephone Consumer Protection Act (TCPA) requires explicit consent before sending marketing text messages '
    'and mandates that recipients can opt out at any time. Apotheca Marketing Suite handles TCPA compliance automatically.'
)

add_heading3('STOP / UNSTOP / HELP Keywords')
add_para('The plugin\'s SMS consent manager processes three keyword types from inbound messages:')
add_bold_bullet('STOP — ', 'when a subscriber replies STOP, the system automatically sets their sms_opt_in flag to 0 (opted out). No further SMS messages will be sent to this subscriber. Phone number normalisation ensures the match works regardless of formatting differences.')
add_bold_bullet('UNSTOP / START — ', 'when a previously opted-out subscriber replies UNSTOP or START, the system re-enables their sms_opt_in flag, allowing SMS messages to resume.')
add_bold_bullet('HELP — ', 'when a subscriber replies HELP, they receive your configured help text (default: "Reply STOP to opt out. Reply HELP for help. Msg&data rates may apply.").')

add_important('Every SMS message sent by the plugin automatically has "Reply STOP to unsubscribe." appended to the message body. Do not remove this — it is required for TCPA compliance.')

add_heading2('13.7 SMS Consent Management')
add_para(
    'SMS consent is tracked separately from email subscription. A subscriber can be subscribed to email but opted out of SMS, and vice versa.'
)
add_bold_bullet('Opt-in: ', 'subscribers opt in to SMS when they provide their phone number via a form (with a Phone field) or when they are explicitly opted in via the SmsConsentManager.')
add_bold_bullet('Opt-out: ', 'subscribers opt out by replying STOP to any SMS message. The system identifies the subscriber by normalising the phone number (stripping non-digit characters and handling country code variations).')
add_bold_bullet('Re-opt-in: ', 'subscribers can re-opt in by replying UNSTOP or START.')
add_bold_bullet('Validation: ', 'before sending any SMS, the system checks the subscriber\'s sms_opt_in flag. If the flag is 0 or empty, the SMS is skipped and marked as "skipped" in the send log.')

add_heading2('13.8 MMS Support')
add_para(
    'The plugin supports MMS (Multimedia Messaging Service) for sending images alongside your text message. '
    'MMS messages can include a single image URL.'
)
add_step(1, 'When creating an SMS campaign or configuring a Send SMS flow step, enter a Media URL.')
add_step(2, 'The URL must point to a publicly accessible image file (JPEG, PNG, or GIF).')
add_step(3, 'The image is sent as an MMS alongside your text message via Twilio\'s MediaUrl parameter.')

add_tip('MMS messages have higher engagement rates than plain SMS. Use product images, promotional graphics, or branded visuals to increase impact.')

add_heading2('13.9 Delivery Tracking and Status Updates')
add_para(
    'Every SMS message sent through the plugin is tracked with delivery status updates.'
)
add_para('The delivery tracking process works as follows:')
add_bullet('When an SMS is queued, a send record is created in the ams_sends table with status "queued".')
add_bullet('When the SMS is sent to Twilio successfully, the status is updated to "sent" and the sent_at timestamp is recorded.')
add_bullet('The Twilio message SID is stored for status callback correlation.')
add_bullet('Twilio sends delivery status updates back to the plugin via the status callback webhook URL.')
add_bullet('If sending fails, the message is automatically retried once after 30 minutes. If the retry also fails, the status is set to "permanently_failed".')

add_para('Send statuses you may see:')
add_bullet('queued — message is waiting to be sent')
add_bullet('sent — message was successfully sent to Twilio')
add_bullet('retry_queued — first attempt failed, retry scheduled for 30 minutes later')
add_bullet('permanently_failed — both attempts failed')
add_bullet('skipped — subscriber was not opted in to SMS')

add_heading2('13.10 SMS Frequency Caps')
add_para(
    'SMS messages sent via flows are subject to the global frequency cap: 2 SMS messages per subscriber per 24-hour period (default). '
    'This is configurable in the plugin settings under the frequency_cap_sms setting. '
    'SMS campaigns (one-time blasts) are not subject to the flow frequency cap.'
)

add_heading2('13.11 Sending Test Messages')
add_step(1, 'Navigate to Apotheca Marketing > SMS > Settings tab.')
add_step(2, 'In the Test Send section, enter a phone number in the test phone number field.')
add_step(3, 'Click Send Test.')
add_step(4, 'A test message will be sent to the specified number. Check that it arrives correctly.')

add_tip('Always send a test message after configuring your Twilio credentials to verify that the integration is working correctly.')

add_heading2('13.12 Viewing Send History and Delivery Reports')
add_para(
    'All SMS sends — from both campaigns and flows — are logged in the ams_sends table. '
    'You can view the SMS campaign list to see each campaign\'s status (draft or sent) and the timestamp of when it was sent. '
    'Individual send records track the subscriber, channel (sms), status, and timestamps for detailed reporting.'
)


# ════════════════════════════════════════════════════════════════════════════
# CHAPTER 14: VISUAL EMAIL EDITOR
# ════════════════════════════════════════════════════════════════════════════

add_heading1('Chapter 14: Visual Email Editor')

add_heading2('14.1 Overview')
add_para(
    'The Apotheca Visual Email Editor is a block-based, drag-and-drop email template builder. '
    'You design emails by adding, arranging, and configuring content blocks — no HTML knowledge required. '
    'The editor renders email-safe HTML with inline CSS, Outlook compatibility, and mobile responsiveness built in.'
)

add_heading2('14.2 Navigating to the Email Editor')
add_step(1, 'In your WordPress admin, navigate to Apotheca Marketing > Email Templates.')
add_step(2, 'You will see a list of all saved email templates.')
add_step(3, 'Click Create New Template to start a new design, or click on an existing template to edit it.')
add_step(4, 'The email editor interface will open.')

add_heading2('14.3 The Drag-and-Drop Interface')
add_para('The email editor has three main areas:')

add_bold_bullet('Block Palette (left sidebar) — ', 'displays all 12 available block types as draggable items. Each block shows an icon and label. Drag a block from the palette and drop it onto the canvas to add it to your email.')

add_bold_bullet('Email Canvas (centre) — ', 'shows your email layout as a stack of blocks. Each block on the canvas has a header bar with the block name and move up/move down buttons for reordering. Click a block to select it and edit its settings.')

add_bold_bullet('Settings Panel (right sidebar) — ', 'shows context-sensitive configuration options for the selected block. When no block is selected, it shows the Global Style panel for overall email styling.')

add_para('The editor toolbar at the top provides:')
add_bullet('A Save button to save your template')
add_bullet('A Preview button to see a rendered HTML preview in an iframe sandbox')
add_bullet('Template name editing')

add_heading2('14.4 The 12 Block Types')

add_heading3('1. Header')
add_para('Displays your brand logo at the top of the email.')
add_bold_bullet('Settings: ', 'Logo URL (the image URL of your logo), Logo Width (in pixels, default: 150), Alignment (left, centre, right), Padding.')

add_heading3('2. Text')
add_para('A rich text block for paragraphs, headings, and formatted content. This is your primary content block.')
add_bold_bullet('Settings: ', 'Content (HTML text), Alignment (left, centre, right, justify), Font Size (default: 16px), Padding.')

add_heading3('3. Image')
add_para('Displays a single image with an optional click-through link.')
add_bold_bullet('Settings: ', 'Image Source URL, Alt Text, Width (percentage or pixels, default: 100%), Alignment, Link URL (optional), Padding.')

add_heading3('4. Button')
add_para('A call-to-action button with customisable text, link, colours, and border radius. The button is rendered using dual HTML+VML code for maximum Outlook compatibility.')
add_bold_bullet('Settings: ', 'Button Text, URL, Background Colour, Text Colour, Border Radius (default: 4px), Alignment, Button Padding (default: 12px 30px), Block Padding.')

add_heading3('5. Divider')
add_para('A horizontal line to visually separate sections of your email.')
add_bold_bullet('Settings: ', 'Colour (default: #e5e7eb), Width (default: 100%), Height/thickness (default: 1px), Padding.')

add_heading3('6. Spacer')
add_para('An invisible block that adds vertical spacing between other blocks.')
add_bold_bullet('Settings: ', 'Height (default: 20px).')

add_heading3('7. Columns')
add_para('Creates a multi-column layout for side-by-side content. Supports several layout presets and includes MSO table fallback for Outlook.')
add_bold_bullet('Settings: ', 'Layout (50-50, 60-40, 40-60, or 33-33-33), Column Content (each column can contain its own blocks), Padding.')

add_heading3('8. Product')
add_para('Displays a WooCommerce product card with the product\'s featured image, name, and price. The product data is pulled live from WooCommerce.')
add_bold_bullet('Settings: ', 'Product ID (the WooCommerce product ID to display), Padding.')

add_heading3('9. Social')
add_para('Displays social media icon links. Supports 7 platforms.')
add_bold_bullet('Settings: ', 'Social Links (an array of platform+URL pairs), Alignment, Icon Size (default: 32px), Padding.')
add_para('Supported platforms: Facebook, Twitter/X, Instagram, LinkedIn, YouTube, TikTok, Pinterest.')

add_heading3('10. Reviews')
add_para('Embeds product reviews in your email. This block has three modes:')
add_bold_bullet('Social Proof mode — ', 'displays existing reviews with star ratings. Great for building trust in promotional and abandoned cart emails.')
add_bold_bullet('Review Request mode — ', 'displays a call-to-action asking the customer to leave a review. Used in post-purchase follow-up emails.')
add_bold_bullet('Review Gating mode — ', 'displays satisfaction-first routing with positive/negative feedback buttons. Routes happy customers to leave a public review and unhappy customers to private feedback. See Chapter 15 for details.')
add_bold_bullet('Settings: ', 'Mode, Product ID (optional), Max Reviews (default: 3), Heading Text, Padding.')

add_heading3('11. Footer')
add_para('A footer block for legal text, unsubscribe links, and company information. Renders in a smaller font size.')
add_bold_bullet('Settings: ', 'Content (HTML), Padding.')

add_heading3('12. HTML')
add_para('A raw HTML block for advanced users who want to insert custom HTML code directly. The content is passed through without modification.')
add_bold_bullet('Settings: ', 'Content (raw HTML), Padding.')

add_heading2('14.5 Adding and Arranging Blocks')

add_heading3('Adding Blocks')
add_step(1, 'In the Block Palette on the left, find the block type you want to add.')
add_step(2, 'Click and drag the block onto the email canvas.')
add_step(3, 'Drop it in the desired position. The block appears in the canvas with default settings.')
add_step(4, 'Click the block to select it and configure its settings in the right panel.')

add_heading3('Rearranging Blocks')
add_step(1, 'Each block on the canvas has Move Up and Move Down buttons in its header bar.')
add_step(2, 'Click Move Up to swap the block with the one above it.')
add_step(3, 'Click Move Down to swap the block with the one below it.')

add_heading3('Removing Blocks')
add_step(1, 'Select the block you want to remove on the canvas.')
add_step(2, 'Click the Delete button (shown when the block is selected).')
add_step(3, 'The block is immediately removed from the email.')

add_heading2('14.6 Personalisation Tokens in Emails')
add_para(
    'The email renderer supports the same personalisation tokens as the flow Send Email step. '
    'You can use these tokens in any Text, Button, Footer, or HTML block:'
)
add_bullet('{{first_name}}, {{last_name}}, {{email}}, {{full_name}}')
add_bullet('{{site_name}}, {{site_url}}, {{unsubscribe_url}}')
add_bullet('{{total_orders}}, {{total_spent}}, {{rfm_segment}}')

add_heading2('14.7 Google Fonts Support')
add_para(
    'The email editor uses a carefully selected font strategy to ensure your emails look great across all email clients:'
)
add_bold_bullet('Primary font: ', 'Montserrat (loaded via Google Fonts @import). This is a clean, modern sans-serif font that renders in Gmail, Apple Mail, and other clients that support web fonts.')
add_bold_bullet('Outlook fallback: ', 'Century Gothic. Since Outlook does not support web fonts, a conditional comment (MSO) specifies Century Gothic as the fallback, which is visually similar to Montserrat.')
add_bold_bullet('Final fallback: ', 'Sans-serif. For email clients that support neither web fonts nor Century Gothic, the generic sans-serif family is used.')
add_para('This three-tier fallback ensures consistent typography across Gmail, Outlook, Apple Mail, Yahoo Mail, and all other major email clients.')

add_heading2('14.8 Live Preview')
add_step(1, 'Click the Preview button in the editor toolbar.')
add_step(2, 'A rendered HTML preview of your email appears in a sandboxed iframe.')
add_step(3, 'The preview shows exactly how your email will look when rendered, including all inline styles.')
add_step(4, 'Close the preview to return to the editor.')

add_para('The preview is generated by sending your block configuration to the server\'s render endpoint, which processes all blocks through the EmailRenderer and CssInliner to produce final email HTML.')

add_heading2('14.9 How CSS Inlining Works')
add_para(
    'Email clients are notoriously inconsistent in their CSS support. Most webmail clients (Gmail, Yahoo, Outlook.com) '
    'strip out <style> blocks entirely and only respect inline style attributes on HTML elements. '
    'The Apotheca CSS Inliner solves this by automatically converting your email\'s CSS into inline styles.'
)

add_para('The inlining process:')
add_step(1, 'All <style> blocks are extracted from the email HTML.')
add_step(2, '@import rules (for Google Fonts) are preserved and re-injected into the <head> — they cannot be inlined but are needed for webmail clients that support them.')
add_step(3, '@media rules (for responsive design) are preserved and re-injected into the <head> — they cannot be inlined but are needed for responsive email rendering.')
add_step(4, 'All remaining CSS rules are parsed into a selector-to-declarations map.')
add_step(5, 'Each CSS selector is converted to an XPath query, and the matching HTML elements receive the declarations as inline style attributes.')
add_step(6, 'The final HTML has both inline styles (for maximum compatibility) and preserved @import/@media rules (for enhanced rendering in clients that support them).')

add_para('Supported CSS selector types: tag names (table, div), class selectors (.ams-col), ID selectors (#header), combined selectors (table.ams-table), and descendant selectors (div p td). Pseudo-classes (:hover) and pseudo-elements (::before) are automatically excluded as they cannot be inlined.')

add_heading2('14.10 Outlook Compatibility')
add_para(
    'Microsoft Outlook (desktop) uses the Microsoft Word rendering engine for HTML, which has severe CSS limitations. '
    'The Apotheca email renderer handles this with several techniques:'
)
add_bullet('MSO conditional comments — buttons are rendered with dual HTML+VML code. The VML version renders correctly in Outlook while the HTML version renders in all other clients.')
add_bullet('Century Gothic font fallback — Outlook ignores web fonts, so Century Gothic is specified as the Outlook-specific fallback via MSO conditional comments.')
add_bullet('Table-based column layouts — the Columns block uses MSO table fallbacks to ensure multi-column layouts render correctly in Outlook.')
add_bullet('Explicit widths and heights — all layout elements include explicit sizing attributes that Outlook requires.')

add_heading2('14.11 Mobile Responsiveness')
add_para(
    'The email renderer generates responsive emails using @media queries that are preserved during CSS inlining. '
    'On mobile screens, multi-column layouts stack vertically, images scale to 100% width, '
    'and font sizes are adjusted for readability on small screens.'
)

add_heading2('14.12 Saving and Using Templates')

add_heading3('Saving a Template')
add_step(1, 'After designing your email, enter a template name in the toolbar.')
add_step(2, 'Click Save. The template is stored via the REST API at /ams/v1/email-templates.')
add_step(3, 'You can update an existing template by making changes and clicking Save again.')

add_heading3('Managing Templates')
add_para('On the template list page, you can:')
add_bullet('Edit — click a template to open it in the editor.')
add_bullet('Duplicate — create a copy of an existing template to use as a starting point for a new design.')
add_bullet('Delete — permanently remove a template.')

add_heading3('Using Templates in Campaigns and Flows')
add_para(
    'When creating an email campaign or configuring a Send Email flow step, you select an email template '
    'as the content source. The template\'s blocks are rendered into final HTML at send time, '
    'with personalisation tokens replaced for each individual subscriber.'
)


# ════════════════════════════════════════════════════════════════════════════
# CHAPTER 15: REVIEWS INTEGRATION
# ════════════════════════════════════════════════════════════════════════════

add_heading1('Chapter 15: Reviews Integration')

add_heading2('15.1 Overview')
add_para(
    'Apotheca Marketing Suite includes a reviews integration system that imports, caches, and embeds product reviews '
    'into your marketing emails. Reviews add powerful social proof that increases trust, engagement, and conversions. '
    'The system supports both native WooCommerce reviews and Judge.me reviews, with automatic nightly cache refreshing.'
)

add_heading2('15.2 Setting Up WooCommerce Reviews Import')
add_para(
    'The WooCommerce reviews importer pulls approved reviews from your WooCommerce store and caches them for use in emails.'
)

add_step(1, 'Navigate to Apotheca Marketing > Reviews.')
add_step(2, 'WooCommerce reviews import is enabled by default — no additional setup is needed for basic functionality.')
add_step(3, 'Configure the Minimum Rating filter to control which reviews are imported. The default is 4 stars, meaning only reviews rated 4 or 5 stars are imported. Options: 3 stars, 4 stars, or 5 stars only.')
add_step(4, 'Click Save Settings.')

add_para('The importer:')
add_bullet('Pulls approved WooCommerce product reviews in batches of 200.')
add_bullet('Extracts the reviewer\'s name, rating, review text, product name, product image URL, product URL, verified purchase status, and review date.')
add_bullet('Performs upsert to avoid duplicates (checks source, product ID, reviewer email hash, and review date).')
add_bullet('Stores reviews in the ams_reviews_cache table for fast retrieval.')

add_heading2('15.3 Setting Up Judge.me Integration (Optional)')
add_para(
    'If you use Judge.me for product reviews, the plugin can import those reviews as well. This is optional — '
    'you only need to set this up if you use Judge.me.'
)

add_step(1, 'Install and activate the Judge.me plugin on your WooCommerce store.')
add_step(2, 'Navigate to Apotheca Marketing > Reviews.')
add_step(3, 'In the Judge.me Integration section, you will see the status indicator. It should show "Plugin Detected" if Judge.me is installed.')
add_step(4, 'Enter your Judge.me API Key in the API Key field. You can find this in your Judge.me dashboard under Settings > API.')
add_step(5, 'Click Test Connection to verify the API key works. You should see a success message.')
add_step(6, 'Click Save Settings.')

add_para(
    'The Judge.me importer connects to the Judge.me public API (https://judge.me/api/v1/reviews), '
    'paginates through reviews (100 per page), and stores them in the same ams_reviews_cache table. '
    'Your API key is encrypted using AES-256-CBC before storage.'
)

add_heading2('15.4 How Review Gating Works')
add_para(
    'Review gating is a technique that routes customers to different destinations based on their satisfaction level. '
    'Happy customers (4-5 stars) are directed to leave a public review, while unhappy customers (1-3 stars) '
    'are directed to private feedback. This helps you build positive public reviews while still collecting '
    'valuable negative feedback privately.'
)

add_heading3('The Review Gate Flow')
add_step(1, 'You send a post-purchase email using the Reviews block in "Review Gating" mode. This email contains star rating buttons (1-5 stars).')
add_step(2, 'The customer clicks a star rating. This sends them to the /ams-review-gate/ endpoint with their token, order ID, and selected rating.')
add_step(3, 'The system validates the request: verifies the subscriber token, checks that the gate link has not expired and has not already been used, and confirms the order belongs to the subscriber.')
add_step(4, 'The click is logged to the ams_events table as a "review_gate_click" event with the rating, order ID, and product IDs.')
add_step(5, 'Based on the rating:')

add_bold_bullet('4-5 stars (positive): ', 'the customer is redirected to the product review page on your WooCommerce store (specifically to the #tab-reviews section). If a submit-review page exists, the form is pre-filled with the customer\'s name and star rating.')
add_bold_bullet('1-3 stars (negative): ', 'the customer is redirected to a private feedback page that you configure in the plugin settings. If no private feedback page is configured, an inline thank-you page is displayed instead.')

add_heading3('Gate Link Expiry')
add_para('Review gate links have a configurable expiry window:')
add_bold_bullet('Default expiry: ', '72 hours from when the order was completed.')
add_bold_bullet('Configurable: ', 'you can set the expiry from 1 to 720 hours in the Reviews settings (Gate Link Expiry Hours field).')
add_bold_bullet('One-time use: ', 'once a customer clicks a review gate link for a specific order, it cannot be used again. Attempting to reuse it shows a "Link Expired" page.')

add_heading2('15.5 The Reviews Block in the Email Editor')
add_para(
    'The Reviews block in the email editor (see Chapter 14, Block Type #10) lets you embed reviews in any email template. '
    'It supports three modes:'
)

add_heading3('Social Proof Mode')
add_para('Displays existing product reviews with star ratings, reviewer names, review text, and verified badges. Use this in:')
add_bullet('Abandoned cart emails — show reviews for products in the cart')
add_bullet('Win-back emails — show top-rated reviews to re-engage')
add_bullet('Promotional emails — add social proof alongside product features')

add_heading3('Review Request Mode')
add_para('Displays a call-to-action asking the customer to leave a review. Use this in post-purchase follow-up emails after the customer has had time to use the product.')

add_heading3('Review Gating Mode')
add_para('Displays star rating buttons (1-5 stars) that link to the review gate endpoint. Use this in post-purchase emails when you want to implement the review gating strategy described in Section 15.4.')

add_heading2('15.6 How Contextual Review Selection Works')
add_para(
    'When the Reviews block is set to Social Proof mode, the plugin intelligently selects which reviews '
    'to display based on the context of the email being sent. This is called contextual review selection.'
)

add_heading3('Selection Modes')
add_bold_bullet('Auto Contextual (default) — ', 'the system automatically picks reviews based on the flow trigger type:')
add_bullet('For Abandoned Cart flows: shows reviews for the specific products in the subscriber\'s abandoned cart.')
add_bullet('For Win-Back flows: shows reviews from the product category the subscriber has purchased most often.')
add_bullet('For all other flows: falls back to verified 5-star reviews from across the site.')

add_bold_bullet('Specific Product — ', 'displays reviews for a specific product ID that you configure. Only reviews rated 4+ stars are shown.')

add_bold_bullet('Top Rated Sitewide — ', 'displays the highest-rated reviews across your entire store, ordered by rating descending.')

add_bold_bullet('Most Recent Sitewide — ', 'displays the most recent high-rated reviews (4+ stars) across your store, ordered by date descending.')

add_tip('Auto Contextual mode is the best choice for most flows. It ensures reviews are always relevant to the subscriber\'s situation — showing cart product reviews in abandoned cart emails and category-relevant reviews in win-back emails.')

add_heading2('15.7 Review Card Design')
add_para('Each review is rendered as an email-safe HTML table card with the following elements:')
add_bold_bullet('Product link — ', 'the product name as a clickable link to the product page.')
add_bold_bullet('Star rating — ', 'rendered as coloured star characters. The default star colour is gold.')
add_bold_bullet('Review title — ', 'the review heading (if available).')
add_bold_bullet('Review body — ', 'the review text, trimmed to 30 words for email readability.')
add_bold_bullet('Reviewer name — ', 'the name of the reviewer.')
add_bold_bullet('Verified badge — ', 'a "Verified Purchase" indicator shown when the review was left by a verified buyer.')
add_bold_bullet('Max reviews — ', 'configurable limit on how many reviews to show per block (default: 3).')

add_heading2('15.8 Nightly Cache Refresh')
add_para(
    'The reviews cache is automatically refreshed every night to ensure your email templates always include '
    'up-to-date reviews.'
)
add_para('The nightly refresh process (scheduled at 3:00 AM):')
add_step(1, 'Stale cache entries older than 48 hours are purged from the ams_reviews_cache table.')
add_step(2, 'WooCommerce reviews are imported in a batch of 200.')
add_step(3, 'Judge.me reviews are imported (if Judge.me integration is configured).')
add_step(4, 'The last refresh timestamp is updated.')

add_para('The Cache Stats section on the Reviews settings page shows:')
add_bullet('Total cached reviews count')
add_bullet('WooCommerce reviews count')
add_bullet('Judge.me reviews count')
add_bullet('Last refresh timestamp')

add_heading3('Manual Refresh')
add_step(1, 'Navigate to Apotheca Marketing > Reviews.')
add_step(2, 'In the Cache Stats section, click the Refresh Now button.')
add_step(3, 'The cache is immediately refreshed, and the counts are updated.')

add_heading2('15.9 Review Settings Configuration')
add_para('The Reviews settings page provides the following configuration options:')

add_bold_bullet('Minimum Rating — ', 'the minimum star rating for reviews to be imported and displayed. Options: 3 stars, 4 stars (default), or 5 stars. This controls which reviews are pulled from WooCommerce and Judge.me.')

add_bold_bullet('Private Feedback Page — ', 'select a published WordPress page to use as the private feedback destination for negative reviews (1-3 stars) in review gating. This page should contain a feedback form. You can select from any published page on your site.')

add_bold_bullet('Gate Link Expiry Hours — ', 'the number of hours after order completion before review gate links expire. Range: 1 to 720 hours. Default: 72 hours (3 days). After this period, gate links will show a "Link Expired" page.')

add_step(1, 'Navigate to Apotheca Marketing > Reviews.')
add_step(2, 'Configure the settings as described above.')
add_step(3, 'Click Save Settings.')
add_step(4, 'A confirmation message appears when settings are saved successfully.')

add_tip('Set the minimum rating to 4 stars for the best balance. This ensures only positive reviews appear in your marketing emails while still giving you a good pool of reviews to choose from.')

add_important('If you change the minimum rating setting, the next nightly cache refresh will import reviews matching the new threshold. Existing cached reviews below the new threshold will be purged during the next refresh cycle.')


# ── Save ────────────────────────────────────────────────────────────────────

output_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'Apotheca_User_Manual_Part3.docx')
doc.save(output_path)
print(f'Document saved to: {output_path}')
