/**
 * SMS Campaign Manager — React admin UI.
 *
 * Tabs: Settings (Twilio credentials), Campaigns (CRUD + send).
 * Character counter, MMS support, token preview.
 *
 * @package Apotheca\Marketing
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, Fragment } = wp.element;
    const apiFetch = wp.apiFetch;

    const API = '/ams/v1';
    function api(path, opts = {}) { return apiFetch({ path: API + path, ...opts }); }

    const SMS_TOKENS = [
        '{{first_name}}', '{{last_name}}', '{{email}}', '{{phone}}',
        '{{shop_name}}', '{{shop_url}}', '{{unsubscribe_url}}',
        '{{order_number}}', '{{order_total}}', '{{product_name}}',
        '{{cart_url}}', '{{coupon_code}}'
    ];

    /* ── App ── */

    function App() {
        const [tab, setTab] = useState('campaigns');
        return h('div', { className: 'ams-sms' },
            h('div', { className: 'ams-sms-header' },
                h('h2', null, 'SMS Marketing'),
                h('div', { className: 'ams-sms-tabs' },
                    h('button', { className: 'button' + (tab === 'campaigns' ? ' button-primary' : ''), onClick: () => setTab('campaigns') }, 'Campaigns'),
                    h('button', { className: 'button' + (tab === 'settings' ? ' button-primary' : ''), onClick: () => setTab('settings') }, 'SMS Settings')
                )
            ),
            tab === 'settings' ? h(SmsSettings) : h(SmsCampaigns)
        );
    }

    /* ── SMS Settings (Twilio Credentials) ── */

    function SmsSettings() {
        const [creds, setCreds] = useState({ account_sid: '', auth_token: '', from_number: '', help_text: '' });
        const [saving, setSaving] = useState(false);
        const [testPhone, setTestPhone] = useState('');
        const [testMsg, setTestMsg] = useState('');
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            api('/sms/credentials').then(d => { setCreds(d); setLoading(false); });
        }, []);

        function save() {
            setSaving(true);
            api('/sms/credentials', { method: 'POST', data: creds })
                .then(() => { setSaving(false); alert('Credentials saved.'); })
                .catch(() => { setSaving(false); alert('Error saving.'); });
        }

        function sendTest() {
            if (!testPhone) { alert('Enter a phone number.'); return; }
            api('/sms/test', { method: 'POST', data: { phone: testPhone, body: 'Test from Apotheca Marketing Suite.' } })
                .then(r => setTestMsg(r.message || 'Sent.'))
                .catch(e => setTestMsg('Error: ' + (e.message || 'Failed')));
        }

        if (loading) return h('p', null, 'Loading…');

        function upd(k, v) { setCreds({ ...creds, [k]: v }); }

        return h('div', { className: 'ams-sms-panel' },
            h('h3', null, 'Twilio Configuration'),
            creds.is_configured && h('div', { className: 'ams-sms-status-ok' }, 'SMS provider is configured and ready.'),
            !creds.is_configured && h('div', { className: 'ams-sms-status-warn' }, 'SMS provider is not configured. Enter your Twilio credentials below.'),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'Account SID'),
                h('input', { type: 'text', className: 'regular-text', value: creds.account_sid || '', onChange: e => upd('account_sid', e.target.value), placeholder: 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' })
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'Auth Token'),
                h('input', { type: 'password', className: 'regular-text', value: creds.auth_token || '', onChange: e => upd('auth_token', e.target.value), placeholder: 'Your auth token' })
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'From Number / Messaging Service SID'),
                h('input', { type: 'text', className: 'regular-text', value: creds.from_number || '', onChange: e => upd('from_number', e.target.value), placeholder: '+1234567890 or MGxxxxxxxx' })
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'HELP Reply Text'),
                h('textarea', { className: 'regular-text', rows: 2, value: creds.help_text || '', onChange: e => upd('help_text', e.target.value) })
            ),
            h('button', { className: 'button button-primary', onClick: save, disabled: saving }, saving ? 'Saving…' : 'Save Credentials'),
            h('div', { className: 'ams-sms-field', style: { marginTop: '8px' } },
                h('label', null, 'Webhook URLs'),
                h('p', { className: 'description' },
                    'Inbound (STOP/HELP): ', h('code', null, creds.webhook_url || ''),
                    h('br'),
                    'Status Callback: ', h('code', null, creds.status_url || '')
                )
            ),
            h('hr'),
            h('h3', null, 'Test Send'),
            h('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } },
                h('input', { type: 'tel', placeholder: '+1234567890', value: testPhone, onChange: e => setTestPhone(e.target.value), style: { width: '180px' } }),
                h('button', { className: 'button', onClick: sendTest }, 'Send Test SMS')
            ),
            testMsg && h('p', { className: 'description' }, testMsg)
        );
    }

    /* ── SMS Campaigns ── */

    function SmsCampaigns() {
        const [campaigns, setCampaigns] = useState([]);
        const [loading, setLoading] = useState(true);
        const [editId, setEditId] = useState(null);
        const [creating, setCreating] = useState(false);

        useEffect(() => {
            api('/sms/campaigns').then(d => { setCampaigns(d); setLoading(false); });
        }, []);

        function refresh() {
            api('/sms/campaigns').then(setCampaigns);
            setEditId(null);
            setCreating(false);
        }

        function handleDelete(id, name) {
            if (!confirm('Delete "' + name + '"?')) return;
            api('/sms/campaigns/' + id, { method: 'DELETE' }).then(refresh);
        }

        function handleSend(id, name) {
            if (!confirm('Send "' + name + '" now? This cannot be undone.')) return;
            api('/sms/campaigns/' + id + '/send', { method: 'POST' })
                .then(r => { alert(r.message); refresh(); })
                .catch(e => alert('Error: ' + (e.message || 'Failed')));
        }

        if (editId || creating) {
            return h(CampaignEditor, { campaignId: editId, onBack: refresh });
        }

        return h('div', null,
            h('div', { style: { display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '12px' } },
                h('h3', { style: { margin: 0 } }, 'SMS Campaigns'),
                h('button', { className: 'button button-primary', onClick: () => setCreating(true) }, '+ New Campaign')
            ),
            loading ? h('p', null, 'Loading…')
                : campaigns.length === 0 ? h('p', { className: 'ams-empty' }, 'No SMS campaigns yet.')
                : h('table', { className: 'widefat fixed striped' },
                    h('thead', null, h('tr', null,
                        h('th', null, 'Name'),
                        h('th', { style: { width: '80px' } }, 'Status'),
                        h('th', { style: { width: '140px' } }, 'Sent'),
                        h('th', { style: { width: '220px' } }, 'Actions')
                    )),
                    h('tbody', null, campaigns.map(c =>
                        h('tr', { key: c.id },
                            h('td', null, h('a', { href: '#', onClick: e => { e.preventDefault(); setEditId(c.id); } }, h('strong', null, c.name))),
                            h('td', null, h('span', { className: 'ams-status-badge ams-status-' + c.status }, c.status)),
                            h('td', null, c.sent_at || '—'),
                            h('td', null,
                                h('button', { className: 'button button-small', onClick: () => setEditId(c.id) }, 'Edit'), ' ',
                                c.status === 'draft' && h('button', { className: 'button button-small button-primary', onClick: () => handleSend(c.id, c.name) }, 'Send Now'), ' ',
                                h('button', { className: 'button button-small button-link-delete', onClick: () => handleDelete(c.id, c.name) }, 'Delete')
                            )
                        )
                    ))
                )
        );
    }

    /* ── Campaign Editor ── */

    function CampaignEditor({ campaignId, onBack }) {
        const [name, setName] = useState('');
        const [smsBody, setSmsBody] = useState('');
        const [segmentId, setSegmentId] = useState('');
        const [mediaUrl, setMediaUrl] = useState('');
        const [saving, setSaving] = useState(false);
        const [loading, setLoading] = useState(!!campaignId);

        useEffect(() => {
            if (!campaignId) return;
            api('/sms/campaigns/' + campaignId).then(c => {
                setName(c.name || '');
                setSmsBody(c.sms_body || '');
                setSegmentId(c.segment_id || '');
                setMediaUrl(c.body_html || '');
                setLoading(false);
            });
        }, [campaignId]);

        function save() {
            if (!name.trim()) { alert('Enter a campaign name.'); return; }
            setSaving(true);
            const payload = { name: name.trim(), sms_body: smsBody, segment_id: segmentId, media_url: mediaUrl };
            const req = campaignId
                ? api('/sms/campaigns/' + campaignId, { method: 'PUT', data: payload })
                : api('/sms/campaigns', { method: 'POST', data: payload });
            req.then(() => { setSaving(false); onBack(); })
                .catch(e => { setSaving(false); alert('Error: ' + (e.message || '')); });
        }

        function insertToken(token) {
            setSmsBody(prev => prev + token);
        }

        if (loading) return h('p', null, 'Loading…');

        // Character counting.
        const hasTokens = SMS_TOKENS.some(t => smsBody.includes(t));
        const charLimit = hasTokens ? 153 : 160;
        const charCount = smsBody.length;
        const segments = Math.ceil(charCount / charLimit) || 1;
        const charColor = charCount > charLimit ? '#d63638' : '#666';

        // Preview with sample token replacement.
        const preview = smsBody
            .replace(/\{\{first_name\}\}/g, 'Jane')
            .replace(/\{\{last_name\}\}/g, 'Doe')
            .replace(/\{\{email\}\}/g, 'jane@example.com')
            .replace(/\{\{phone\}\}/g, '+1234567890')
            .replace(/\{\{shop_name\}\}/g, 'My Store')
            .replace(/\{\{shop_url\}\}/g, 'https://mystore.com')
            .replace(/\{\{order_number\}\}/g, '1234')
            .replace(/\{\{order_total\}\}/g, '$49.99')
            .replace(/\{\{product_name\}\}/g, 'Sample Product')
            .replace(/\{\{cart_url\}\}/g, 'https://mystore.com/cart')
            .replace(/\{\{coupon_code\}\}/g, 'SAVE10')
            .replace(/\{\{unsubscribe_url\}\}/g, 'https://mystore.com/ams-unsubscribe/?token=xxx');

        return h('div', { className: 'ams-sms-panel' },
            h('div', { style: { display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '16px' } },
                h('button', { className: 'button', onClick: onBack }, '← Back'),
                h('h3', { style: { margin: 0 } }, campaignId ? 'Edit SMS Campaign' : 'New SMS Campaign'),
                h('span', { style: { flex: 1 } }),
                h('button', { className: 'button button-primary', onClick: save, disabled: saving }, saving ? 'Saving…' : 'Save')
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'Campaign Name'),
                h('input', { type: 'text', className: 'regular-text', value: name, onChange: e => setName(e.target.value), placeholder: 'e.g., Flash Sale SMS' })
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'Segment ID (optional — leave blank for all SMS subscribers)'),
                h('input', { type: 'number', value: segmentId, onChange: e => setSegmentId(e.target.value), style: { width: '100px' } })
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'Message Body'),
                h('div', { className: 'ams-sms-tokens' },
                    SMS_TOKENS.map(t => h('button', { key: t, className: 'button button-small', onClick: () => insertToken(t), style: { fontSize: '11px', marginRight: '4px', marginBottom: '4px' } }, t))
                ),
                h('textarea', { className: 'large-text', rows: 5, value: smsBody, onChange: e => setSmsBody(e.target.value), placeholder: 'Hi {{first_name}}, check out our latest deals!' }),
                h('div', { style: { display: 'flex', justifyContent: 'space-between', fontSize: '12px', marginTop: '4px' } },
                    h('span', { style: { color: charColor } }, charCount + '/' + charLimit + ' chars' + (segments > 1 ? ' (' + segments + ' SMS segments)' : '')),
                    h('span', { style: { color: '#999' } }, '"Reply STOP to unsubscribe." auto-appended')
                )
            ),
            h('div', { className: 'ams-sms-field' },
                h('label', null, 'MMS Image URL (optional)'),
                h('input', { type: 'url', className: 'regular-text', value: mediaUrl, onChange: e => setMediaUrl(e.target.value), placeholder: 'https://example.com/image.jpg' })
            ),
            h('div', { className: 'ams-sms-preview' },
                h('h4', null, 'Preview'),
                h('div', { className: 'ams-sms-bubble' },
                    preview || 'Type a message above…',
                    h('div', { className: 'ams-sms-stop' }, 'Reply STOP to unsubscribe.')
                )
            )
        );
    }

    /* ── Styles ── */

    function injectStyles() {
        if (document.getElementById('ams-sms-css')) return;
        const s = document.createElement('style');
        s.id = 'ams-sms-css';
        s.textContent = `
.ams-sms-header{display:flex;align-items:center;gap:16px;margin-bottom:16px}
.ams-sms-header h2{margin:0}
.ams-sms-tabs{display:flex;gap:4px}
.ams-sms-panel{background:#fff;border:1px solid #c3c4c7;padding:20px;margin-top:8px}
.ams-sms-field{margin-bottom:14px}
.ams-sms-field label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
.ams-sms-tokens{margin-bottom:6px}
.ams-sms-status-ok{background:#d4edda;color:#155724;padding:8px 12px;border-radius:4px;margin-bottom:16px;font-size:13px}
.ams-sms-status-warn{background:#fff3cd;color:#856404;padding:8px 12px;border-radius:4px;margin-bottom:16px;font-size:13px}
.ams-sms-preview{margin-top:16px;padding-top:16px;border-top:1px solid #ddd}
.ams-sms-preview h4{margin:0 0 8px}
.ams-sms-bubble{background:#e9ecef;border-radius:12px;padding:12px 16px;max-width:320px;font-size:14px;white-space:pre-wrap;word-break:break-word;line-height:1.5}
.ams-sms-stop{font-size:11px;color:#999;margin-top:8px;border-top:1px solid #ddd;padding-top:6px}
.ams-status-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;text-transform:uppercase}
.ams-status-sent{background:#cce5ff;color:#004085}
.ams-status-draft{background:#f0f0f1;color:#666}
.ams-empty{color:#666;font-style:italic}
        `;
        document.head.appendChild(s);
    }

    /* ── Mount ── */

    function init() {
        const root = document.getElementById('ams-admin-sms');
        if (!root) return;
        injectStyles();
        wp.element.render(h(App), root);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
