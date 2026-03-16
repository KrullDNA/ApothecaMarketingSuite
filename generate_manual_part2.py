#!/usr/bin/env python3
"""Generate Apotheca Marketing Suite User Manual Part 2 as a Word document."""

from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml.ns import qn
import os

doc = Document()

# ── Style Configuration ──────────────────────────────────────────────────────

style = doc.styles['Normal']
style.font.name = 'Calibri'
style.font.size = Pt(11)
style.paragraph_format.space_after = Pt(6)

for level in range(1, 4):
    h = doc.styles[f'Heading {level}']
    h.font.name = 'Calibri'
    h.font.color.rgb = RGBColor(0x1B, 0x3A, 0x5C)

doc.styles['Heading 1'].font.size = Pt(22)
doc.styles['Heading 2'].font.size = Pt(16)
doc.styles['Heading 3'].font.size = Pt(13)

list_style = doc.styles['List Number']
list_style.font.name = 'Calibri'
list_style.font.size = Pt(11)


def add_para(text, bold=False, style_name='Normal'):
    p = doc.add_paragraph(style=style_name)
    run = p.add_run(text)
    run.bold = bold
    return p


def add_callout(prefix, text):
    """Add a TIP: or IMPORTANT: callout as a bold-prefixed paragraph."""
    p = doc.add_paragraph()
    r1 = p.add_run(f'{prefix}: ')
    r1.bold = True
    if prefix == 'IMPORTANT':
        r1.font.color.rgb = RGBColor(0xCC, 0x00, 0x00)
    else:
        r1.font.color.rgb = RGBColor(0x00, 0x70, 0x30)
    p.add_run(text)
    return p


def add_steps(steps):
    """Add a numbered step list."""
    for i, step in enumerate(steps, 1):
        p = doc.add_paragraph()
        r = p.add_run(f'Step {i}. ')
        r.bold = True
        p.add_run(step)


def add_bullet_list(items):
    for item in items:
        doc.add_paragraph(item, style='List Bullet')


def add_table_row_data(table, rows):
    """Populate table rows. First row is header."""
    for i, row_data in enumerate(rows):
        if i == 0:
            row = table.rows[0]
        else:
            row = table.add_row()
        for j, cell_text in enumerate(row_data):
            cell = row.cells[j]
            cell.text = ''
            p = cell.paragraphs[0]
            run = p.add_run(str(cell_text))
            if i == 0:
                run.bold = True
                run.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
                shading = cell._element.get_or_add_tcPr()
                sh = shading.makeelement(qn('w:shd'), {
                    qn('w:fill'): '1B3A5C',
                    qn('w:val'): 'clear',
                })
                shading.append(sh)


# ═══════════════════════════════════════════════════════════════════════════════
# TITLE PAGE
# ═══════════════════════════════════════════════════════════════════════════════

for _ in range(6):
    doc.add_paragraph()

title = doc.add_paragraph()
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = title.add_run('Apotheca\u00ae Marketing Suite')
r.bold = True
r.font.size = Pt(28)
r.font.color.rgb = RGBColor(0x1B, 0x3A, 0x5C)

subtitle = doc.add_paragraph()
subtitle.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = subtitle.add_run('User Manual \u2014 Part 2')
r.font.size = Pt(18)
r.font.color.rgb = RGBColor(0x55, 0x55, 0x55)

ver = doc.add_paragraph()
ver.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = ver.add_run('Version 1.0.0')
r.font.size = Pt(12)
r.font.color.rgb = RGBColor(0x88, 0x88, 0x88)

chapters_note = doc.add_paragraph()
chapters_note.alignment = WD_ALIGN_PARAGRAPH.CENTER
r = chapters_note.add_run(
    'Chapters 5\u201310: Automated Flows, Flow Triggers, Flow Steps, '
    'Flow Templates, Smart Segmentation, RFM Scoring & Predictive Analytics'
)
r.font.size = Pt(11)
r.font.color.rgb = RGBColor(0x88, 0x88, 0x88)

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# TABLE OF CONTENTS
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Table of Contents', level=1)
toc_items = [
    'Chapter 5: Automated Flows Overview',
    '    5.1 What Is an Automated Flow?',
    '    5.2 Navigating to the Flows Screen',
    '    5.3 Understanding the Flow List',
    '    5.4 Creating a New Flow',
    '    5.5 The Flow Editor Interface',
    '    5.6 Adding Steps to a Flow',
    '    5.7 Reordering Steps',
    '    5.8 Activating, Pausing, and Deleting a Flow',
    '    5.9 How Enrolment Works',
    '    5.10 Auto-Exit on Unsubscribe',
    '',
    'Chapter 6: Flow Triggers',
    '    6.1 What Is a Trigger?',
    '    6.2 Welcome Series',
    '    6.3 Abandoned Cart',
    '    6.4 Post-Purchase',
    '    6.5 Win-Back',
    '    6.6 Browse Abandonment',
    '    6.7 Birthday',
    '    6.8 RFM Segment Change',
    '    6.9 Custom Event',
    '',
    'Chapter 7: Flow Steps',
    '    7.1 Overview of Step Types',
    '    7.2 Send Email',
    '    7.3 Send SMS',
    '    7.4 Wait / Delay',
    '    7.5 Condition (Branch)',
    '    7.6 Add Tag',
    '    7.7 Remove Tag',
    '    7.8 Update Field',
    '    7.9 Exit Flow',
    '    7.10 Frequency Caps and Send Windows',
    '',
    'Chapter 8: Pre-Built Flow Templates',
    '    8.1 Importing a Template',
    '    8.2 Welcome Series Template',
    '    8.3 Abandoned Cart Recovery Template',
    '    8.4 Post-Purchase Thank You Template',
    '    8.5 Win-Back Template',
    '    8.6 Browse Abandonment Template',
    '    8.7 Customising a Template After Import',
    '',
    'Chapter 9: Smart Segmentation',
    '    9.1 What Is a Segment?',
    '    9.2 Navigating to the Segments Screen',
    '    9.3 Creating a New Segment',
    '    9.4 Condition Types Reference',
    '    9.5 Using AND / OR Logic',
    '    9.6 Nested Condition Groups',
    '    9.7 Live Preview Count',
    '    9.8 Editing and Deleting Segments',
    '    9.9 Background Recalculation',
    '',
    'Chapter 10: RFM Scoring & Predictive Analytics',
    '    10.1 What Is RFM Scoring?',
    '    10.2 How Scores Are Calculated',
    '    10.3 RFM Segments Explained',
    '    10.4 Viewing RFM Data in the Subscriber List',
    '    10.5 Predictive Customer Lifetime Value (CLV)',
    '    10.6 Churn Risk Score',
    '    10.7 Predicted Next Order Date',
    '    10.8 Nightly Recalculation Schedule',
    '    10.9 Using RFM and Predictive Data in Flows and Segments',
]
for item in toc_items:
    if item == '':
        doc.add_paragraph()
    elif item.startswith('    '):
        p = doc.add_paragraph(item.strip())
        p.paragraph_format.left_indent = Inches(0.4)
    else:
        p = doc.add_paragraph()
        r = p.add_run(item)
        r.bold = True

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# CHAPTER 5: AUTOMATED FLOWS OVERVIEW
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Chapter 5: Automated Flows Overview', level=1)

add_para(
    'Automated flows let you send the right message to the right subscriber at the right time \u2014 '
    'without lifting a finger after the initial setup. A flow is a sequence of steps (emails, SMS messages, '
    'delays, conditions, and tags) that runs automatically when a trigger fires. '
    'This chapter introduces the flow system and walks you through the editor interface.'
)

# 5.1
doc.add_heading('5.1 What Is an Automated Flow?', level=2)
add_para(
    'An automated flow is a pre-defined series of actions that executes whenever a specific event occurs. '
    'For example, when a new subscriber opts in, a Welcome Series flow can automatically send a sequence '
    'of emails over several days \u2014 introducing your brand, sharing customer favourites, and offering a discount.'
)
add_para('Every flow has two core components:')
add_bullet_list([
    'Trigger \u2014 the event that starts the flow (e.g., new subscription, abandoned cart, completed purchase).',
    'Steps \u2014 the ordered sequence of actions the flow performs (e.g., send email, wait 2 days, check a condition).',
])
add_para(
    'Flows run in the background using Action Scheduler, so your site remains responsive while messages '
    'are sent and steps are processed.'
)

# 5.2
doc.add_heading('5.2 Navigating to the Flows Screen', level=2)
add_steps([
    'In your WordPress admin sidebar, click **Apotheca Marketing**.',
    'Click **Flows** in the sub-menu.',
    'You will see the Flows list screen, which shows all existing flows.',
])

# 5.3
doc.add_heading('5.3 Understanding the Flow List', level=2)
add_para('The flow list table displays the following columns:')
t = doc.add_table(rows=1, cols=2)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Column', 'Description'],
    ['Name', 'The name you gave the flow when you created it.'],
    ['Trigger', 'The event type that starts this flow (e.g., Welcome Series, Abandoned Cart).'],
    ['Status', 'Current state: Draft, Active, or Paused.'],
    ['Enrolments', 'Total number of subscribers who have entered this flow.'],
    ['Created', 'The date the flow was first saved.'],
])

add_callout('TIP', 'You can click any column header to sort the list by that column.')

# 5.4
doc.add_heading('5.4 Creating a New Flow', level=2)
add_steps([
    'On the Flows list screen, click the **Add New Flow** button in the top-right corner.',
    'Enter a descriptive **Flow Name** (e.g., "Welcome Series", "Abandoned Cart Recovery").',
    'Select a **Trigger Type** from the drop-down menu. The eight available triggers are covered in Chapter 6.',
    'Click **Save Draft** to create the flow in draft status. You can now add steps.',
])
add_callout('IMPORTANT', 'A flow in Draft status will not trigger for any subscribers. You must change it to Active before it will run.')

# 5.5
doc.add_heading('5.5 The Flow Editor Interface', level=2)
add_para(
    'After creating a flow, you are taken to the Flow Editor. The editor has three areas:'
)
add_bullet_list([
    'Header bar \u2014 shows the flow name, trigger type, and status toggle (Draft / Active / Paused).',
    'Steps timeline \u2014 the main area displaying each step as a card in sequence, from top to bottom.',
    'Step editor panel \u2014 when you click a step card, a side panel opens where you configure that step\u2019s settings.',
])

# 5.6
doc.add_heading('5.6 Adding Steps to a Flow', level=2)
add_steps([
    'In the Flow Editor, click the **+ Add Step** button below the last step (or below the trigger card if no steps exist yet).',
    'A step-type picker appears. Choose from: **Send Email**, **Send SMS**, **Wait / Delay**, **Condition**, **Add Tag**, **Remove Tag**, **Update Field**, or **Exit Flow**.',
    'The new step card appears in the timeline and the step editor panel opens on the right.',
    'Configure the step\u2019s settings (covered in detail in Chapter 7).',
    'Click **Save** in the step editor panel to save your changes.',
])

# 5.7
doc.add_heading('5.7 Reordering Steps', level=2)
add_para(
    'You can drag and drop step cards to reorder them. Click and hold the grip handle on the left side '
    'of any step card, then drag it to the desired position. Release to drop. '
    'The step order numbers update automatically.'
)
add_callout('TIP', 'Reordering only affects subscribers who have not yet reached the moved step. Subscribers already past that point continue on their original path.')

# 5.8
doc.add_heading('5.8 Activating, Pausing, and Deleting a Flow', level=2)

doc.add_heading('Activating a Flow', level=3)
add_steps([
    'Open the flow in the Flow Editor.',
    'In the header bar, change the **Status** drop-down from **Draft** to **Active**.',
    'Click **Save**. The flow is now live and will enrol subscribers when the trigger fires.',
])

doc.add_heading('Pausing a Flow', level=3)
add_para(
    'Change the status to **Paused**. No new subscribers will be enrolled, but subscribers already '
    'in the flow will continue to receive their remaining steps.'
)

doc.add_heading('Deleting a Flow', level=3)
add_steps([
    'On the Flows list screen, hover over the flow you want to remove.',
    'Click **Delete**.',
    'Confirm the deletion in the dialog that appears.',
])
add_callout('IMPORTANT', 'Deleting a flow immediately exits all subscribers currently enrolled in it. This action cannot be undone.')

# 5.9
doc.add_heading('5.9 How Enrolment Works', level=2)
add_para(
    'When a trigger fires, the plugin checks all active flows that use that trigger type. '
    'For each matching flow, it enrols the subscriber \u2014 unless that subscriber is already '
    'actively enrolled in the same flow. This deduplication prevents a subscriber from receiving '
    'the same flow twice simultaneously.'
)
add_para(
    'Once enrolled, the subscriber proceeds through each step in order. The system records the '
    'current step, and Action Scheduler handles timing for wait steps and delays.'
)

# 5.10
doc.add_heading('5.10 Auto-Exit on Unsubscribe', level=2)
add_para(
    'If a subscriber unsubscribes from your mailing list, the plugin automatically exits them '
    'from every flow they are currently enrolled in. The exit reason is recorded as "unsubscribed". '
    'This ensures you never send a message to someone who has opted out.'
)
add_callout('TIP', 'You can view a subscriber\u2019s flow history (including exit reasons) on their profile page under the Flows tab.')

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# CHAPTER 6: FLOW TRIGGERS
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Chapter 6: Flow Triggers', level=1)

add_para(
    'Every flow begins with a trigger \u2014 the event that decides when and for whom the flow starts. '
    'The Apotheca Marketing Suite provides eight built-in trigger types. This chapter explains each one, '
    'how it fires, and any configuration options it supports.'
)

# 6.1
doc.add_heading('6.1 What Is a Trigger?', level=2)
add_para(
    'A trigger is the entry point of a flow. When the specified event occurs, the plugin finds all '
    'active flows that use that trigger type and enrols the relevant subscriber. '
    'Each flow can have exactly one trigger type, selected when you create the flow.'
)

t = doc.add_table(rows=1, cols=2)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Trigger Type', 'Fires When\u2026'],
    ['Welcome Series', 'A new subscriber confirms their opt-in (or subscribes, if double opt-in is disabled).'],
    ['Abandoned Cart', 'A subscriber\u2019s cart is detected as abandoned after the configured timeout period.'],
    ['Post-Purchase', 'A WooCommerce order reaches "Completed" status.'],
    ['Win-Back', 'A subscriber has not placed an order for a configurable number of days (default: 90).'],
    ['Browse Abandonment', 'A subscriber views a product but does not add it to their cart within 30 minutes.'],
    ['Birthday', 'The subscriber\u2019s birthday matches today\u2019s date (based on their custom fields).'],
    ['RFM Segment Change', 'A subscriber\u2019s RFM segment changes (e.g., from "Loyal" to "At Risk").'],
    ['Custom Event', 'A custom event is recorded for a subscriber via the plugin\u2019s event API.'],
])

# 6.2
doc.add_heading('6.2 Welcome Series', level=2)
add_para(
    'The Welcome Series trigger fires when a subscriber first becomes active on your list. '
    'The exact moment depends on your GDPR settings:'
)
add_bullet_list([
    'If **Double Opt-In** is enabled (Settings \u2192 GDPR Double Opt-In), the trigger fires when the subscriber clicks the confirmation link in their verification email.',
    'If Double Opt-In is disabled, the trigger fires immediately when the subscriber is created \u2014 either at checkout or on registration.',
])
add_callout('TIP', 'The Welcome Series is the most popular flow type. Use it to introduce your brand, share best-sellers, and offer a first-purchase discount.')

# 6.3
doc.add_heading('6.3 Abandoned Cart', level=2)
add_para(
    'The Abandoned Cart trigger fires when the plugin\u2019s cart-tracking system detects that a subscriber '
    'has left items in their cart without completing checkout. The timeout period is controlled by the '
    '**Abandoned Cart Timeout** setting (Settings \u2192 Abandoned Cart Timeout, default: 60 minutes).'
)
add_para(
    'When the timeout elapses without a completed order, the plugin fires the internal '
    'ams_cart_abandoned event and enrols the subscriber in any active Abandoned Cart flows.'
)
add_callout('IMPORTANT', 'This trigger requires the subscriber to be identified (i.e., they must have entered their email during checkout or be logged in). Anonymous carts cannot trigger flows.')

# 6.4
doc.add_heading('6.4 Post-Purchase', level=2)
add_para(
    'The Post-Purchase trigger fires when a WooCommerce order status changes to **Completed**. '
    'The plugin looks up the subscriber by the order\u2019s billing email and enrols them if their status is "subscribed".'
)
add_para(
    'Use this trigger to send thank-you emails, request reviews, cross-sell related products, or deliver '
    'post-purchase education about the products the customer bought.'
)

# 6.5
doc.add_heading('6.5 Win-Back', level=2)
add_para(
    'The Win-Back trigger runs on a daily schedule via Action Scheduler. It identifies subscribers who:'
)
add_bullet_list([
    'Have placed at least one order in the past (total_orders > 0).',
    'Have not placed an order for at least the configured number of days.',
    'Are still subscribed (status = "subscribed").',
])
add_para('To configure the inactivity threshold:')
add_steps([
    'Open or create a flow with the **Win-Back** trigger.',
    'In the trigger configuration section, set the **Days Since Last Order** value. The default is **90 days**.',
    'Save the flow.',
])
add_callout('TIP', 'The daily scan processes up to 200 subscribers per run to avoid server load spikes. If you have more lapsed subscribers, they will be picked up in the next run.')

# 6.6
doc.add_heading('6.6 Browse Abandonment', level=2)
add_para(
    'The Browse Abandonment trigger runs hourly via Action Scheduler. It finds subscribers who viewed '
    'a product page but did not add any product to their cart within 30 minutes.'
)
add_para('The trigger uses event tracking to identify qualifying subscribers:')
add_bullet_list([
    'A viewed_product event was recorded more than 30 minutes ago.',
    'No added_to_cart event exists from the same subscriber after the view.',
    'No previous browse_abandonment_triggered event exists for the subscriber (prevents re-firing).',
])
add_callout('TIP', 'Up to 100 subscribers are processed per hourly run. The plugin records a browse_abandonment_triggered event after enrolment to ensure each subscriber is only enrolled once per browse session.')

# 6.7
doc.add_heading('6.7 Birthday', level=2)
add_para(
    'The Birthday trigger runs daily via Action Scheduler. It checks each subscriber\u2019s '
    'custom_fields for a birthday value matching today\u2019s date.'
)
add_para('The plugin supports two birthday field formats:')
add_bullet_list([
    'A single field: custom_fields \u2192 birthday = "MM-DD" (e.g., "03-15" for 15 March).',
    'Two separate fields: custom_fields \u2192 birthday_month = "MM" and birthday_day = "DD".',
])
add_steps([
    'Ensure subscribers have their birthday recorded in their custom fields. You can set this via the subscriber profile, an import, or the Update Field flow step.',
    'Create a flow with the **Birthday** trigger.',
    'Add your birthday-greeting steps (e.g., a "Happy Birthday" email with a discount code).',
    'Set the flow to **Active**.',
])
add_callout('IMPORTANT', 'Up to 200 subscribers are processed per daily birthday check. Birthday matching uses MySQL JSON_EXTRACT, which requires MySQL 5.7+ or MariaDB 10.2+.')

# 6.8
doc.add_heading('6.8 RFM Segment Change', level=2)
add_para(
    'The RFM Segment Change trigger fires whenever the nightly RFM recalculation changes a subscriber\u2019s '
    'segment (e.g., from "Loyal" to "At Risk"). You can optionally filter by specific segment transitions.'
)
add_para('To configure segment filters:')
add_steps([
    'Open or create a flow with the **RFM Segment Change** trigger.',
    'Optionally set a **From Segment** filter to only trigger when the subscriber was previously in that segment.',
    'Optionally set a **To Segment** filter to only trigger when the subscriber moves into that segment.',
    'If you leave both filters empty, the trigger fires on any segment change.',
])
add_para('Available RFM segments for filtering:')
add_bullet_list([
    'Champions', 'Big Spenders', 'Loyal', 'New Customers',
    'Potential', 'At Risk', 'About to Sleep', 'Lost', 'Other',
])
add_callout('TIP', 'A common use case is creating a flow that triggers when a subscriber moves into "At Risk" \u2014 sending them a re-engagement offer before they lapse further.')

# 6.9
doc.add_heading('6.9 Custom Event', level=2)
add_para(
    'The Custom Event trigger fires when a custom event is recorded for a subscriber through the plugin\u2019s '
    'event API. This is the most flexible trigger, designed for developers and third-party integrations.'
)
add_para('To configure:')
add_steps([
    'Open or create a flow with the **Custom Event** trigger.',
    'In the trigger configuration, enter the **Event Type** name (e.g., "loyalty_milestone", "quiz_completed"). This value is case-sensitive.',
    'If you leave the event type blank, the flow triggers on any custom event that is not a standard system event.',
])
add_para('The following standard event types are automatically excluded from the Custom Event trigger:')
add_bullet_list([
    'placed_order, completed_purchase, refund_requested',
    'viewed_product, added_to_cart, started_checkout',
    'abandoned_cart, wrote_review, browse_abandonment_triggered',
])
add_callout('TIP', 'Developers can record custom events using the do_action(\'ams_event_recorded\', $subscriber_id, $event_type, $event_data) hook from any WordPress plugin or theme.')

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# CHAPTER 7: FLOW STEPS
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Chapter 7: Flow Steps', level=1)

add_para(
    'Flow steps are the individual actions that make up a flow. After the trigger enrols a subscriber, '
    'the system executes each step in order. This chapter covers all eight step types, their configuration '
    'options, and the global frequency cap and send window settings that govern delivery.'
)

# 7.1
doc.add_heading('7.1 Overview of Step Types', level=2)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Step Type', 'Icon', 'Purpose'],
    ['Send Email', '\u2709', 'Send an email message to the subscriber.'],
    ['Send SMS', '\U0001f4f1', 'Send an SMS (or MMS) message to the subscriber.'],
    ['Wait / Delay', '\u23f1', 'Pause the flow for a specified amount of time.'],
    ['Condition', '\u2462', 'Branch the flow based on subscriber data or behaviour.'],
    ['Add Tag', '+\U0001f3f7', 'Add a tag to the subscriber\u2019s profile.'],
    ['Remove Tag', '-\U0001f3f7', 'Remove a tag from the subscriber\u2019s profile.'],
    ['Update Field', '\u270e', 'Update a custom field on the subscriber\u2019s profile.'],
    ['Exit Flow', '\u23f9', 'Immediately remove the subscriber from the flow.'],
])

# 7.2
doc.add_heading('7.2 Send Email', level=2)
add_para(
    'The Send Email step sends an email message to the subscriber. It is the most commonly used step type.'
)

doc.add_heading('Configuration Fields', level=3)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Field', 'Required', 'Description'],
    ['Subject', 'Yes', 'The email subject line. Supports personalisation tokens.'],
    ['Preview Text', 'No', 'The snippet shown in the inbox after the subject line.'],
    ['Body (HTML)', 'Yes', 'The full HTML body of the email. Supports tokens.'],
    ['Body (Plain Text)', 'No', 'A plain-text fallback version. Recommended for accessibility.'],
])

doc.add_heading('Available Personalisation Tokens', level=3)
t = doc.add_table(rows=1, cols=2)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Token', 'Replaced With'],
    ['{{first_name}}', 'Subscriber\u2019s first name, or "there" if blank.'],
    ['{{last_name}}', 'Subscriber\u2019s last name.'],
    ['{{email}}', 'Subscriber\u2019s email address.'],
    ['{{phone}}', 'Subscriber\u2019s phone number.'],
    ['{{full_name}}', 'First name + last name, trimmed.'],
    ['{{total_orders}}', 'Subscriber\u2019s total order count.'],
    ['{{total_spent}}', 'Subscriber\u2019s total spend, formatted to 2 decimal places.'],
    ['{{rfm_segment}}', 'Subscriber\u2019s current RFM segment name.'],
    ['{{site_name}}', 'Your WordPress site name.'],
    ['{{site_url}}', 'Your WordPress home URL.'],
    ['{{unsubscribe_url}}', 'One-click unsubscribe link with the subscriber\u2019s unique token.'],
])

add_callout('IMPORTANT', 'Every email automatically includes an unsubscribe footer with a GDPR-compliant link. You do not need to add {{unsubscribe_url}} manually, but you may use it in your body for a custom placement.')

# 7.3
doc.add_heading('7.3 Send SMS', level=2)
add_para(
    'The Send SMS step sends a text message to the subscriber\u2019s phone number.'
)

doc.add_heading('Configuration Fields', level=3)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Field', 'Required', 'Description'],
    ['SMS Body', 'Yes', 'The message text. Supports {{first_name}}, {{last_name}}, {{email}}, and {{site_name}} tokens.'],
])

add_para('Before sending, the plugin checks three conditions:')
add_bullet_list([
    'The subscriber has a phone number on file.',
    'The subscriber\u2019s status is "subscribed".',
    'The subscriber\u2019s **SMS Opt-In** flag is enabled.',
])
add_para(
    'If any condition is not met, the step is skipped and the flow advances to the next step. '
    'The text "Reply STOP to unsubscribe" is appended automatically to every SMS.'
)
add_callout('TIP', 'To enable SMS opt-in for a subscriber, set the sms_opt_in field to 1 on their profile or via the Update Field step.')

# 7.4
doc.add_heading('7.4 Wait / Delay', level=2)
add_para(
    'The Wait step pauses the flow for a specified duration before proceeding to the next step.'
)

doc.add_heading('Configuration Fields', level=3)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Field', 'Required', 'Description'],
    ['Delay Value', 'Yes', 'A positive integer (minimum 1).'],
    ['Delay Unit', 'Yes', 'The time unit: Minutes, Hours, Days, or Weeks.'],
])

add_para(
    'When the flow reaches a Wait step, Action Scheduler creates a delayed job that fires after '
    'the specified duration. For example, a Wait step set to 3 Days will schedule the next step '
    'to execute 72 hours from now.'
)

# 7.5
doc.add_heading('7.5 Condition (Branch)', level=2)
add_para(
    'The Condition step evaluates one or more rules against the subscriber\u2019s data and branches '
    'the flow accordingly. If all rules match, the flow continues to the Yes branch. If any rule '
    'fails, the flow follows the No branch.'
)

doc.add_heading('Configuration Fields', level=3)
add_para('Each rule in a condition has three parts:')
t = doc.add_table(rows=1, cols=2)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Part', 'Description'],
    ['Field', 'The subscriber attribute to check (e.g., total_orders, rfm_segment, has_tag, status).'],
    ['Operator', 'The comparison type: equals, not_equals, greater_than, less_than, contains, is_true, is_false.'],
    ['Value', 'The value to compare against.'],
])

add_para('After defining your rules, you select which step each branch leads to:')
add_bullet_list([
    '**Yes Step** \u2014 the step to jump to if all rules match.',
    '**No Step** \u2014 the step to jump to if any rule does not match.',
])

doc.add_heading('Available Condition Fields', level=3)
add_bullet_list([
    '**total_orders** \u2014 the subscriber\u2019s total order count.',
    '**total_spent** \u2014 the subscriber\u2019s total spend amount.',
    '**status** \u2014 the subscriber\u2019s current status (subscribed, unsubscribed, pending, etc.).',
    '**rfm_segment** \u2014 the subscriber\u2019s RFM segment name.',
    '**has_tag** \u2014 checks if the subscriber has a specific tag.',
    '**source** \u2014 how the subscriber was captured (checkout, registration, etc.).',
    '**has_opened_any** \u2014 whether the subscriber has opened any email (queries send records).',
    '**has_clicked_any** \u2014 whether the subscriber has clicked any email link.',
])

add_callout('TIP', 'Use conditions to personalise your flow. For example, after a wait step, check if total_orders equals 0 to send a reminder only to subscribers who have not yet purchased.')

# 7.6
doc.add_heading('7.6 Add Tag', level=2)
add_para(
    'The Add Tag step adds a tag to the subscriber\u2019s profile. Tags are stored as a JSON array '
    'in the subscriber\u2019s tags field.'
)
add_steps([
    'Click **+ Add Step** and select **Add Tag**.',
    'In the step editor, enter the **Tag** name (e.g., "welcome-completed", "vip").',
    'Click **Save**.',
])
add_para(
    'If the subscriber already has the specified tag, the step does nothing and the flow advances normally.'
)

# 7.7
doc.add_heading('7.7 Remove Tag', level=2)
add_para(
    'The Remove Tag step removes a tag from the subscriber\u2019s profile.'
)
add_steps([
    'Click **+ Add Step** and select **Remove Tag**.',
    'Enter the **Tag** name to remove.',
    'Click **Save**.',
])
add_para(
    'If the subscriber does not have the specified tag, the step does nothing and the flow advances normally.'
)

# 7.8
doc.add_heading('7.8 Update Field', level=2)
add_para(
    'The Update Field step sets a key\u2013value pair in the subscriber\u2019s custom_fields JSON object.'
)
add_steps([
    'Click **+ Add Step** and select **Update Field**.',
    'Enter the **Field Name** (e.g., "birthday", "loyalty_tier", "preferred_category").',
    'Enter the **Field Value** (e.g., "03-15", "gold", "skincare").',
    'Click **Save**.',
])
add_callout('TIP', 'Use Update Field to store data for later use in conditions. For example, set a "flow_completed" field to "yes" after a welcome series, then use a condition step in future flows to check this value.')

# 7.9
doc.add_heading('7.9 Exit Flow', level=2)
add_para(
    'The Exit Flow step immediately removes the subscriber from the current flow. '
    'The exit reason is recorded as "exit_step". No further steps in the flow are executed.'
)
add_para(
    'This step is useful in condition branches \u2014 for example, if a subscriber has already purchased, '
    'you may want to exit them from an abandoned-cart flow instead of sending further reminders.'
)

# 7.10
doc.add_heading('7.10 Frequency Caps and Send Windows', level=2)
add_para(
    'Two global settings protect your subscribers from over-messaging. These are enforced automatically '
    'before every Send Email and Send SMS step.'
)

doc.add_heading('Frequency Caps', level=3)
add_para(
    'Frequency caps limit the number of messages a subscriber can receive per day across all flows.'
)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Channel', 'Setting', 'Default'],
    ['Email', 'frequency_cap_email', '3 per day'],
    ['SMS', 'frequency_cap_sms', '2 per day'],
])
add_para(
    'When a subscriber hits the cap, the step is not skipped \u2014 it is rescheduled to retry '
    'in 1 hour. This ensures the message is eventually delivered without bombarding the subscriber.'
)

doc.add_heading('Send Windows', level=3)
add_para(
    'The send window restricts email and SMS delivery to reasonable hours in the subscriber\u2019s '
    'local time zone.'
)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Setting', 'Description', 'Default'],
    ['Send Window Start', 'Earliest hour to send messages.', '8:00 AM'],
    ['Send Window End', 'Latest hour to send messages.', '9:00 PM'],
])
add_para(
    'If a step is scheduled to execute outside the send window, it is held until the next window '
    'opens. The subscriber\u2019s time zone is derived from their WooCommerce billing country.'
)
add_callout('TIP', 'You can adjust frequency caps and send windows under Settings \u2192 Frequency Caps and Settings \u2192 Send Window.')

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# CHAPTER 8: PRE-BUILT FLOW TEMPLATES
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Chapter 8: Pre-Built Flow Templates', level=1)

add_para(
    'The Apotheca Marketing Suite ships with five professionally designed flow templates. '
    'Each template provides a complete, ready-to-customise flow with a trigger, steps, and '
    'pre-written email/SMS content. This chapter walks you through importing and customising them.'
)

# 8.1
doc.add_heading('8.1 Importing a Template', level=2)
add_steps([
    'Navigate to **Apotheca Marketing \u2192 Flows**.',
    'Click the **Import Template** button at the top of the flow list.',
    'A dialog appears listing the five available templates. Click the template you want to import.',
    'The plugin creates a new flow in **Draft** status, pre-populated with the template\u2019s trigger, steps, and content.',
    'Review and customise the flow as needed (see Section 8.7).',
    'Change the status to **Active** and click **Save** to go live.',
])
add_callout('TIP', 'You can import the same template multiple times if you want to create variations (e.g., different welcome series for different customer segments).')

# 8.2
doc.add_heading('8.2 Welcome Series Template', level=2)
add_para('Trigger: **Welcome Series** (fires on subscriber opt-in)')
add_para('This template includes 10 steps designed to onboard new subscribers over two weeks:')

t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Step', 'Type', 'Details'],
    ['1', 'Send Email', 'Welcome email \u2014 introduces your brand.'],
    ['2', 'Wait', '2 days.'],
    ['3', 'Send Email', '"Our Story" \u2014 shares your brand narrative.'],
    ['4', 'Wait', '3 days.'],
    ['5', 'Send Email', '"Customers\u2019 Favourites" \u2014 highlights popular products.'],
    ['6', 'Wait', '2 days.'],
    ['7', 'Send Email', '"A Special Treat Just for You" \u2014 offers a 10% discount (code: WELCOME10).'],
    ['8', 'Wait', '3 days.'],
    ['9', 'Condition', 'Checks if total_orders equals 0 (no purchase yet).'],
    ['10', 'Send SMS', 'Reminder about the discount code for non-purchasers.'],
])

# 8.3
doc.add_heading('8.3 Abandoned Cart Recovery Template', level=2)
add_para('Trigger: **Abandoned Cart**')
add_para('This template includes 6 steps spread over approximately 48 hours:')

t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Step', 'Type', 'Details'],
    ['1', 'Wait', '1 hour after cart abandonment.'],
    ['2', 'Send Email', '"You left something behind" \u2014 gentle reminder.'],
    ['3', 'Wait', '23 hours.'],
    ['4', 'Send Email', '"People are loving these products" \u2014 social proof.'],
    ['5', 'Wait', '24 hours.'],
    ['6', 'Send Email', '"Last chance \u2014 10% off your cart" \u2014 urgency + discount (code: CART10).'],
])

# 8.4
doc.add_heading('8.4 Post-Purchase Thank You Template', level=2)
add_para('Trigger: **Post-Purchase** (fires on order completion)')
add_para('This template includes 3 steps:')

t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Step', 'Type', 'Details'],
    ['1', 'Send Email', '"Thank you for your order" \u2014 immediate gratitude email.'],
    ['2', 'Wait', '7 days (allows time for delivery).'],
    ['3', 'Send Email', '"How was your experience?" \u2014 review request email.'],
])

# 8.5
doc.add_heading('8.5 Win-Back Template', level=2)
add_para('Trigger: **Win-Back** (days_since_last_order: 90)')
add_para('This template includes 5 steps designed to re-engage lapsed customers over 30 days:')

t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Step', 'Type', 'Details'],
    ['1', 'Send Email', '"We miss you!" \u2014 re-engagement email.'],
    ['2', 'Wait', '15 days.'],
    ['3', 'Send Email', '"A special offer to welcome you back" \u2014 15% discount (code: COMEBACK15, valid 14 days).'],
    ['4', 'Wait', '15 days.'],
    ['5', 'Send SMS', '"Your discount code is about to expire" \u2014 urgency reminder.'],
])

# 8.6
doc.add_heading('8.6 Browse Abandonment Template', level=2)
add_para('Trigger: **Browse Abandonment**')
add_para('This template includes 4 steps:')

t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Step', 'Type', 'Details'],
    ['1', 'Wait', '30 minutes after the product view.'],
    ['2', 'Send Email', '"Still looking?" \u2014 reminder of the viewed product.'],
    ['3', 'Wait', '24 hours.'],
    ['4', 'Send Email', '"These might be just what you\u2019re looking for" \u2014 curated product recommendations.'],
])

# 8.7
doc.add_heading('8.7 Customising a Template After Import', level=2)
add_para(
    'After importing a template, you will almost certainly want to customise it for your brand. '
    'Here is a checklist of items to review:'
)
add_bullet_list([
    '**Email subject lines** \u2014 adjust the tone and wording to match your brand voice.',
    '**Email body content** \u2014 replace placeholder text with your own copy, product images, and links.',
    '**Discount codes** \u2014 the templates reference codes like WELCOME10, CART10, and COMEBACK15. Create these coupons in WooCommerce \u2192 Coupons before activating the flow.',
    '**Wait durations** \u2014 adjust the timing to suit your customer journey. Shorter waits for impulse-buy products; longer waits for high-consideration purchases.',
    '**Condition values** \u2014 the Welcome Series template checks total_orders equals 0. Adjust this if your logic differs.',
    '**SMS content** \u2014 update the text to match your brand and ensure your SMS provider is configured.',
    '**Win-Back threshold** \u2014 the default is 90 days since last order. Adjust based on your typical purchase cycle.',
])
add_callout('IMPORTANT', 'Templates are imported in Draft status. Remember to change the status to Active and click Save before the flow will trigger for subscribers.')

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# CHAPTER 9: SMART SEGMENTATION
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Chapter 9: Smart Segmentation', level=1)

add_para(
    'Segments let you group subscribers based on shared characteristics \u2014 from simple filters like '
    '"all subscribers from checkout" to complex, multi-layered conditions like "customers who spent over '
    '\u00a3100, have not ordered in 60 days, and opened an email in the last 30 days." '
    'This chapter covers the full segment builder interface and all 24 condition types.'
)

# 9.1
doc.add_heading('9.1 What Is a Segment?', level=2)
add_para(
    'A segment is a saved set of conditions that dynamically identifies which subscribers match. '
    'Segments are not static lists \u2014 they are recalculated automatically every 6 hours and on demand '
    'when you edit them. As subscriber data changes, the segment membership updates accordingly.'
)

# 9.2
doc.add_heading('9.2 Navigating to the Segments Screen', level=2)
add_steps([
    'In your WordPress admin sidebar, click **Apotheca Marketing**.',
    'Click **Segments** in the sub-menu.',
    'You will see the segment list showing all saved segments, their subscriber counts, and last-calculated dates.',
])

# 9.3
doc.add_heading('9.3 Creating a New Segment', level=2)
add_steps([
    'On the Segments screen, click the **Add New Segment** button.',
    'Enter a **Segment Name** (e.g., "High-Value At-Risk Customers", "Email Openers Last 30 Days").',
    'Use the condition builder to add your first condition (see Section 9.4 for the full reference).',
    'Add more conditions as needed. Use the **AND / OR** toggle to control how conditions combine (see Section 9.5).',
    'Check the **Live Preview Count** at the bottom to verify the segment matches the expected number of subscribers.',
    'Click **Save Segment**.',
])

# 9.4
doc.add_heading('9.4 Condition Types Reference', level=2)
add_para(
    'The segment builder provides 24 condition types organised into three groups. '
    'Each condition has a type, an operator, and (usually) a value.'
)

doc.add_heading('Subscriber Data Conditions', level=3)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Condition', 'Operators', 'Notes'],
    ['Email Domain', 'is, is_not, contains', 'Matches the part after @ in the email address.'],
    ['First Name', 'is, is_not, is_blank, is_not_blank, contains', 'Text comparison on the first_name field.'],
    ['Tag', 'has, does_not_have', 'Checks the subscriber\u2019s tags JSON array.'],
    ['Custom Field', 'equals, not_equals, contains, greater_than, less_than', 'Requires a field_name. Checks custom_fields JSON.'],
    ['Source', 'is, is_not', 'e.g., checkout, registration.'],
    ['GDPR Consent', 'is_true, is_false', 'Boolean check on gdpr_consent field.'],
    ['Subscribed Date', 'before, after, within_last_X_days, more_than_X_days_ago', 'Date comparison on subscribed_at.'],
    ['Predicted CLV', 'greater_than, less_than, equals, between', 'Numeric. "between" requires two values.'],
    ['Churn Risk Score', 'greater_than, less_than, equals', 'Numeric, range 0\u2013100.'],
    ['RFM Segment', 'is, is_not', 'One of: Champions, Big Spenders, Loyal, New Customers, Potential, At Risk, About to Sleep, Lost, Other.'],
])

doc.add_heading('Ecommerce Conditions', level=3)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Condition', 'Operators', 'Notes'],
    ['Total Orders', 'greater_than, less_than, equals, between', 'Numeric count of orders.'],
    ['Total Spent', 'greater_than, less_than, equals, between', 'Numeric sum of order totals.'],
    ['Average Order Value', 'greater_than, less_than, equals, between', 'Calculated: total_spent \u00f7 total_orders.'],
    ['Last Order Date', 'before, after, within_last_X_days, more_than_X_days_ago', 'Date comparison.'],
    ['Purchased Product', 'has, has_not', 'Accepts a product ID or SKU.'],
    ['Purchased Category', 'has, has_not', 'Accepts a category slug.'],
    ['Last Order Status', 'is, is_not', 'Options: completed, processing, on-hold, cancelled, refunded, failed.'],
    ['Used Coupon', 'has, has_not', 'Case-insensitive coupon code match.'],
])

doc.add_heading('Engagement Conditions', level=3)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Condition', 'Operators', 'Notes'],
    ['Opened Campaign', 'has, has_not', 'Requires a campaign ID.'],
    ['Clicked Campaign', 'has, has_not', 'Requires a campaign ID.'],
    ['Opened Any Email', 'ever, never, within_last_X_days', 'Checks ams_sends.opened_at records.'],
    ['Clicked Any Email', 'ever, never, within_last_X_days', 'Checks ams_sends.clicked_at records.'],
    ['SMS Opt-In', 'is_true, is_false', 'Checks whether subscriber has opted in for SMS.'],
    ['Email Bounce Status', 'is, is_not', 'Options: none, soft, hard.'],
])

# 9.5
doc.add_heading('9.5 Using AND / OR Logic', level=2)
add_para(
    'By default, all conditions in a group are combined with **AND** logic \u2014 a subscriber must match '
    'every condition to be included in the segment.'
)
add_para(
    'You can switch any condition group to **OR** logic by clicking the toggle between conditions. '
    'With OR logic, a subscriber only needs to match at least one condition in the group.'
)
add_para('Example:')
add_bullet_list([
    '**AND**: total_orders greater_than 5 AND total_spent greater_than 200 \u2014 only subscribers who meet both criteria.',
    '**OR**: source is "checkout" OR source is "registration" \u2014 subscribers from either source.',
])

# 9.6
doc.add_heading('9.6 Nested Condition Groups', level=2)
add_para(
    'For advanced targeting, you can nest condition groups up to 3 levels deep. A nested group '
    'is a group-within-a-group that uses its own AND/OR logic.'
)
add_steps([
    'In the condition builder, click the **+ Add Group** button to create a nested group.',
    'Add conditions inside the nested group. Set the group\u2019s logic to AND or OR.',
    'The nested group is evaluated as a single unit within the parent group.',
])
add_para('Example of a nested segment:')
add_para(
    'Root group (AND): total_spent greater_than 100 AND (nested group OR: rfm_segment is "At Risk" '
    'OR rfm_segment is "About to Sleep"). This matches high-spending subscribers who are in either '
    'the "At Risk" or "About to Sleep" RFM segment.',
    bold=False
)
add_callout('TIP', 'The maximum nesting depth is 3 levels. Most segments only need 1\u20132 levels. If you find yourself needing more, consider splitting into multiple segments.')

# 9.7
doc.add_heading('9.7 Live Preview Count', level=2)
add_para(
    'As you build your segment, the **Preview Count** at the bottom of the editor shows how many '
    'subscribers currently match your conditions. This count updates each time you add, remove, or '
    'change a condition.'
)
add_para(
    'The preview sends your conditions to the server via the REST API (POST /ams/v1/segments/preview), '
    'which evaluates them against all subscribed subscribers in batches of 500.'
)
add_callout('TIP', 'If the preview count shows 0, double-check your condition values and operators. A common mistake is using "equals" when "contains" is more appropriate.')

# 9.8
doc.add_heading('9.8 Editing and Deleting Segments', level=2)

doc.add_heading('Editing a Segment', level=3)
add_steps([
    'On the Segments list, click the segment name to open the editor.',
    'Modify the conditions, logic, or name as needed.',
    'Click **Save Segment**. The subscriber count is automatically recalculated.',
])

doc.add_heading('Deleting a Segment', level=3)
add_steps([
    'On the Segments list, hover over the segment you want to remove.',
    'Click **Delete** and confirm.',
])
add_callout('IMPORTANT', 'Deleting a segment does not affect the subscribers themselves \u2014 it only removes the saved condition set.')

# 9.9
doc.add_heading('9.9 Background Recalculation', level=2)
add_para(
    'Segment subscriber counts are recalculated automatically every 6 hours via Action Scheduler. '
    'This ensures segment counts stay current even as subscriber data changes from orders, tag updates, '
    'and RFM recalculations.'
)
add_para(
    'During each recalculation cycle, the plugin iterates through all saved segments, evaluates their '
    'conditions against every subscribed subscriber (in batches of 500), and updates the stored count.'
)
add_callout('TIP', 'If you need an immediate count, simply open the segment in the editor \u2014 the live preview will show the current number.')

doc.add_page_break()

# ═══════════════════════════════════════════════════════════════════════════════
# CHAPTER 10: RFM SCORING & PREDICTIVE ANALYTICS
# ═══════════════════════════════════════════════════════════════════════════════

doc.add_heading('Chapter 10: RFM Scoring & Predictive Analytics', level=1)

add_para(
    'The Apotheca Marketing Suite automatically scores every subscriber using RFM analysis '
    '(Recency, Frequency, Monetary) and generates predictive metrics including Customer Lifetime Value, '
    'Churn Risk, and Predicted Next Order Date. All calculations run nightly via Action Scheduler, '
    'so your data is always fresh without any manual effort.'
)

# 10.1
doc.add_heading('10.1 What Is RFM Scoring?', level=2)
add_para(
    'RFM is a proven customer-segmentation method used in direct marketing. It scores each customer '
    'on three dimensions:'
)
add_bullet_list([
    '**Recency (R)** \u2014 How recently did the customer place an order? More recent = higher score.',
    '**Frequency (F)** \u2014 How often does the customer order? More frequent = higher score.',
    '**Monetary (M)** \u2014 How much has the customer spent in total? Higher spend = higher score.',
])
add_para(
    'Each dimension is scored from 1 (lowest) to 5 (highest). The three scores are concatenated into a '
    'composite RFM score (e.g., "555" for the best customers, "111" for the least engaged).'
)

# 10.2
doc.add_heading('10.2 How Scores Are Calculated', level=2)

doc.add_heading('Recency Scoring', level=3)
add_para(
    'Recency is scored using fixed day-range thresholds based on the number of days since the '
    'subscriber\u2019s last order:'
)
t = doc.add_table(rows=1, cols=2)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Days Since Last Order', 'Recency Score'],
    ['0\u201314 days', '5'],
    ['15\u201330 days', '4'],
    ['31\u201360 days', '3'],
    ['61\u2013180 days', '2'],
    ['181+ days', '1'],
])

doc.add_heading('Frequency Scoring', level=3)
add_para(
    'Frequency is scored using quintile boundaries. The plugin calculates the 20th, 40th, 60th, '
    'and 80th percentile of order counts across all qualifying subscribers, then assigns scores 1\u20135 '
    'based on which quintile the subscriber falls into.'
)
add_callout('TIP', 'Quintile-based scoring means scores are relative to your customer base. A score of 5 means the subscriber is in the top 20% of order frequency for your store.')

doc.add_heading('Monetary Scoring', level=3)
add_para(
    'Monetary scoring works identically to frequency scoring, but uses total spend instead of order count. '
    'The plugin calculates quintile boundaries for total_spent and assigns scores 1\u20135 accordingly.'
)
add_callout('IMPORTANT', 'If your store has fewer than 5 subscribers with orders, the plugin falls back to dividing the maximum value by 5 to create score boundaries. This ensures scoring works even for new stores.')

# 10.3
doc.add_heading('10.3 RFM Segments Explained', level=2)
add_para(
    'After calculating R, F, and M scores, the plugin assigns each subscriber to one of nine named segments. '
    'The segments are evaluated in priority order \u2014 the first matching rule wins:'
)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Segment', 'Criteria', 'Description'],
    ['Champions', 'R \u2265 4, F \u2265 4, M \u2265 4', 'Your best customers: recent, frequent, high-spending.'],
    ['Big Spenders', 'M = 5 (any R, F)', 'Highest monetary value, regardless of recency or frequency.'],
    ['Loyal', 'R \u2265 3, F \u2265 4', 'Frequent buyers who are still relatively recent.'],
    ['New Customers', 'R = 5, F = 1', 'Made their first purchase very recently.'],
    ['Potential', 'R \u2265 4, F \u2264 2', 'Recent but infrequent \u2014 could become loyal with nurturing.'],
    ['At Risk', 'R \u2264 2, F \u2265 3, M \u2265 3', 'Were valuable but have not purchased recently.'],
    ['About to Sleep', 'R \u2264 3, F \u2264 2, M \u2264 2', 'Low engagement across all dimensions.'],
    ['Lost', 'R = 1, F \u2264 2', 'Have not ordered in 181+ days and were infrequent.'],
    ['Other', 'Default', 'Any subscriber who does not match the above rules.'],
])
add_callout('TIP', 'Use the RFM Segment Change trigger (Chapter 6.8) to automatically enrol subscribers in targeted flows when their segment changes \u2014 for example, sending a win-back offer when someone moves into "At Risk".')

# 10.4
doc.add_heading('10.4 Viewing RFM Data in the Subscriber List', level=2)
add_para(
    'RFM scores and segments are visible in two places:'
)
add_bullet_list([
    '**Subscriber list table** \u2014 the RFM Score and RFM Segment columns show each subscriber\u2019s current values. You can sort by either column.',
    '**Subscriber profile page** \u2014 the Analytics section displays the full R, F, M breakdown along with the named segment.',
])
add_para(
    'You can also filter the subscriber list by RFM segment using the search and filter controls.'
)

# 10.5
doc.add_heading('10.5 Predictive Customer Lifetime Value (CLV)', level=2)
add_para(
    'The Predictive Engine calculates a 12-month Customer Lifetime Value for each subscriber with at least '
    'one order. The formula is:'
)
add_para('Predicted CLV = Average Order Value \u00d7 Predicted Orders in 12 Months', bold=True)
add_para('Where:')
add_bullet_list([
    '**Average Order Value (AOV)** = total_spent \u00f7 total_orders.',
    '**Average Order Gap** = the mean number of days between consecutive orders.',
    '**Predicted Orders in 12 Months** = 365 \u00f7 Average Order Gap.',
])
add_para(
    'For single-order subscribers (where no gap can be calculated), the engine assumes one additional '
    'order in the next 12 months, so CLV equals 2 \u00d7 AOV.'
)
add_callout('TIP', 'CLV is displayed on the subscriber profile page and can be used in segment conditions (Predicted CLV greater_than, less_than, or between).')

# 10.6
doc.add_heading('10.6 Churn Risk Score', level=2)
add_para(
    'The Churn Risk Score is a number from 0 to 100 that indicates how likely a subscriber is to stop '
    'purchasing. The formula is:'
)
add_para(
    'Churn Risk = (Days Since Last Order \u00f7 Average Order Gap) \u00d7 50, capped at 100',
    bold=True
)
add_para('How to interpret the score:')
t = doc.add_table(rows=1, cols=2)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Score Range', 'Interpretation'],
    ['0\u201325', 'Low risk \u2014 ordering on schedule or ahead of schedule.'],
    ['26\u201350', 'Moderate risk \u2014 approaching their typical order interval.'],
    ['51\u201375', 'High risk \u2014 overdue for an order based on past behaviour.'],
    ['76\u2013100', 'Very high risk \u2014 significantly overdue; may have churned.'],
])
add_para(
    'For single-order subscribers, the engine uses a default interval of 90 days. If no last_order_date '
    'exists, the score is set to 100.'
)
add_callout('TIP', 'Create a segment for subscribers with churn_risk_score greater_than 70 and pair it with a Win-Back flow to re-engage them before they are lost.')

# 10.7
doc.add_heading('10.7 Predicted Next Order Date', level=2)
add_para(
    'The Predictive Engine estimates when each subscriber is likely to place their next order. '
    'The calculation is:'
)
add_para(
    'Predicted Next Order = Last Order Date + Average Order Gap',
    bold=True
)
add_para(
    'If the projected date is in the past (meaning the subscriber is already overdue), the engine '
    'projects forward iteratively by adding the average gap until the date is in the future.'
)
add_para(
    'This field is displayed on the subscriber profile page and can be used in segments via the '
    'Last Order Date condition type.'
)

# 10.8
doc.add_heading('10.8 Nightly Recalculation Schedule', level=2)
add_para(
    'Both the RFM Engine and the Predictive Engine run nightly via Action Scheduler:'
)
t = doc.add_table(rows=1, cols=3)
t.style = 'Table Grid'
add_table_row_data(t, [
    ['Engine', 'Hook', 'Behaviour'],
    ['RFM Engine', 'ams_rfm_nightly', 'Recalculates R, F, M scores and named segments for all subscribers with at least one order. Fires ams_rfm_segment_changed when a segment changes.'],
    ['Predictive Engine', 'ams_predictive_nightly', 'Recalculates CLV, churn risk score, and predicted next order date for all subscribers with at least one order.'],
])
add_para(
    'Both engines process subscribers in batches of 500 to avoid memory issues on large databases. '
    'Only subscribers with total_orders > 0 and a last_order_date are included.'
)

# 10.9
doc.add_heading('10.9 Using RFM and Predictive Data in Flows and Segments', level=2)
add_para(
    'RFM scores and predictive metrics integrate seamlessly with the flow and segment systems:'
)

doc.add_heading('In Flows', level=3)
add_bullet_list([
    '**RFM Segment Change trigger** \u2014 start a flow when a subscriber\u2019s segment changes (e.g., to "At Risk").',
    '**Condition step** \u2014 branch a flow based on rfm_segment, total_orders, or total_spent.',
    '**Email tokens** \u2014 use {{rfm_segment}}, {{total_orders}}, and {{total_spent}} in email content.',
])

doc.add_heading('In Segments', level=3)
add_bullet_list([
    '**RFM Segment condition** \u2014 filter by named segment (e.g., is "Champions").',
    '**Predicted CLV condition** \u2014 target high-value subscribers (e.g., greater_than 500).',
    '**Churn Risk Score condition** \u2014 find at-risk subscribers (e.g., greater_than 70).',
    '**Combine conditions** \u2014 e.g., RFM Segment is "At Risk" AND Predicted CLV greater_than 200 to find valuable customers who need re-engagement.',
])
add_callout('TIP', 'The most powerful marketing automation combines triggers, conditions, and segments. For example: trigger a flow on RFM Segment Change to "At Risk", use a condition to check if Predicted CLV > 200, and send a personalised win-back offer to high-value at-risk customers while sending a different message to lower-value ones.')

# ═══════════════════════════════════════════════════════════════════════════════
# SAVE
# ═══════════════════════════════════════════════════════════════════════════════

output_path = os.path.join(os.path.dirname(__file__), 'Apotheca_User_Manual_Part2.docx')
doc.save(output_path)
print(f'Manual Part 2 saved to {output_path}')
