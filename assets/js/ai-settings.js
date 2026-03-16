/**
 * Apotheca® Marketing Suite — AI Settings & Controls
 *
 * React admin UI for managing OpenAI API key, feature toggles,
 * token budget, and usage tracking.
 *
 * Uses wp.element (React) + wp.apiFetch (WordPress bundled).
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, Fragment } = wp.element;
    const apiFetch = wp.apiFetch;
    const cfg = window.amsAiSettings || {};

    const api = (path, opts = {}) => apiFetch({
        path: 'ams/v1/' + path,
        method: opts.method || 'GET',
        data: opts.data,
        headers: { 'X-WP-Nonce': cfg.nonce },
    });

    const fmt = (n) => typeof n === 'number' ? n.toLocaleString() : n;

    // ── Usage Card ──────────────────────────────────────────────────────

    function UsageCard({ usage }) {
        if (!usage) return null;
        const pct = usage.budget_pct || 0;
        const barColor = pct >= 100 ? '#ef4444' : pct >= 80 ? '#f59e0b' : '#10b981';

        return h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20, marginBottom: 24 } },
            h('h3', { style: { margin: '0 0 12px', fontSize: 15 } }, 'Token Usage This Month'),
            h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, marginBottom: 16 } },
                h('div', null,
                    h('div', { style: { fontSize: 12, color: '#6b7280' } }, 'Tokens Used'),
                    h('div', { style: { fontSize: 22, fontWeight: 700 } }, fmt(usage.tokens_used))
                ),
                h('div', null,
                    h('div', { style: { fontSize: 12, color: '#6b7280' } }, 'Budget'),
                    h('div', { style: { fontSize: 22, fontWeight: 700 } }, fmt(usage.budget))
                ),
                h('div', null,
                    h('div', { style: { fontSize: 12, color: '#6b7280' } }, 'Cost'),
                    h('div', { style: { fontSize: 22, fontWeight: 700 } }, '$' + usage.cost_usd.toFixed(2))
                ),
                h('div', null,
                    h('div', { style: { fontSize: 12, color: '#6b7280' } }, 'API Calls'),
                    h('div', { style: { fontSize: 22, fontWeight: 700 } }, fmt(usage.calls))
                )
            ),
            h('div', { style: { height: 20, background: '#f3f4f6', borderRadius: 10, overflow: 'hidden' } },
                h('div', { style: { height: '100%', width: Math.min(pct, 100) + '%', background: barColor, borderRadius: 10, transition: 'width 0.3s' } })
            ),
            h('div', { style: { fontSize: 12, color: '#6b7280', marginTop: 4, textAlign: 'right' } }, pct.toFixed(1) + '% of budget used'),
            usage.budget_exceeded && h('div', { style: { marginTop: 8, padding: '8px 12px', background: '#fef2f2', border: '1px solid #fecaca', borderRadius: 6, color: '#991b1b', fontSize: 13 } },
                'Monthly token budget exceeded. AI features are paused until next month.'
            ),
            usage.budget_warning && !usage.budget_exceeded && h('div', { style: { marginTop: 8, padding: '8px 12px', background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 6, color: '#92400e', fontSize: 13 } },
                'Warning: 80% of monthly token budget consumed.'
            )
        );
    }

    // ── Settings Form ───────────────────────────────────────────────────

    function SettingsForm({ settings, onSave }) {
        const [form, setForm] = useState(settings);
        const [apiKey, setApiKey] = useState('');
        const [saving, setSaving] = useState(false);
        const [msg, setMsg] = useState('');

        const update = (key, val) => setForm(f => ({ ...f, [key]: val }));

        const save = async () => {
            setSaving(true);
            setMsg('');
            const data = { ...form };
            if (apiKey) data.api_key = apiKey;
            try {
                const res = await api('ai/settings', { method: 'POST', data });
                onSave(res);
                setMsg('Settings saved.');
                setApiKey('');
            } catch (e) {
                setMsg('Error saving settings.');
            }
            setSaving(false);
        };

        const toggleStyle = (on) => ({
            width: 44, height: 24, borderRadius: 12, background: on ? '#4f46e5' : '#d1d5db',
            cursor: 'pointer', position: 'relative', transition: 'background 0.2s', display: 'inline-block'
        });
        const knobStyle = (on) => ({
            width: 20, height: 20, borderRadius: 10, background: '#fff', position: 'absolute',
            top: 2, left: on ? 22 : 2, transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)'
        });

        const Toggle = ({ value, onChange, label }) => h('div', { style: { display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 } },
            h('div', { style: toggleStyle(value), onClick: () => onChange(!value) },
                h('div', { style: knobStyle(value) })
            ),
            h('span', { style: { fontSize: 14 } }, label)
        );

        return h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20, marginBottom: 24 } },
            h('h3', { style: { margin: '0 0 16px', fontSize: 15 } }, 'AI Configuration'),

            // API Key.
            h('div', { style: { marginBottom: 16 } },
                h('label', { style: { display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 } }, 'OpenAI API Key'),
                h('input', {
                    type: 'password', value: apiKey, onChange: e => setApiKey(e.target.value),
                    placeholder: settings.has_api_key ? '••••••••••••••••••••••••••••' : 'sk-...',
                    style: { width: '100%', maxWidth: 400, padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 14 }
                }),
                settings.has_api_key && h('span', { style: { marginLeft: 8, fontSize: 12, color: '#10b981' } }, 'Configured')
            ),

            // Feature toggles.
            h(Toggle, { value: form.ai_subject_lines_enabled, onChange: v => update('ai_subject_lines_enabled', v), label: 'AI Subject Line Generator' }),
            h(Toggle, { value: form.ai_email_body_enabled, onChange: v => update('ai_email_body_enabled', v), label: 'AI Email Body Generator' }),
            h(Toggle, { value: form.ai_send_time_enabled, onChange: v => update('ai_send_time_enabled', v), label: 'AI Send-Time Optimisation' }),
            h(Toggle, { value: form.ai_product_recs_enabled, onChange: v => update('ai_product_recs_enabled', v), label: 'AI Product Recommendations' }),
            h(Toggle, { value: form.ai_segment_suggestions_enabled, onChange: v => update('ai_segment_suggestions_enabled', v), label: 'AI Segment Suggestions' }),

            // Budget.
            h('div', { style: { marginTop: 16, marginBottom: 16 } },
                h('label', { style: { display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 } }, 'Monthly Token Budget'),
                h('input', {
                    type: 'number', value: form.ai_monthly_token_budget, min: 0, step: 10000,
                    onChange: e => update('ai_monthly_token_budget', parseInt(e.target.value) || 0),
                    style: { width: 200, padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 14 }
                }),
                h('span', { style: { marginLeft: 8, fontSize: 12, color: '#6b7280' } }, 'tokens/month (0 = unlimited)')
            ),

            // Product card template.
            h('div', { style: { marginBottom: 16 } },
                h('label', { style: { display: 'block', fontSize: 13, fontWeight: 600, marginBottom: 4 } }, 'Product Card HTML Template (optional)'),
                h('textarea', {
                    value: form.ai_product_card_template || '', rows: 4,
                    onChange: e => update('ai_product_card_template', e.target.value),
                    placeholder: 'Leave empty for default template. Use {{rec_image}}, {{rec_name}}, {{rec_price}}, {{rec_url}}',
                    style: { width: '100%', maxWidth: 600, padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: 6, fontSize: 13, fontFamily: 'monospace' }
                })
            ),

            // Save.
            h('div', { style: { display: 'flex', alignItems: 'center', gap: 12 } },
                h('button', {
                    onClick: save, disabled: saving,
                    style: { padding: '10px 24px', background: '#4f46e5', color: '#fff', border: 'none', borderRadius: 6, fontSize: 14, fontWeight: 600, cursor: 'pointer' }
                }, saving ? 'Saving...' : 'Save Settings'),
                msg && h('span', { style: { fontSize: 13, color: msg.includes('Error') ? '#ef4444' : '#10b981' } }, msg)
            )
        );
    }

    // ── AI Log Table ────────────────────────────────────────────────────

    function AiLogTable() {
        const [logs, setLogs] = useState([]);

        useEffect(() => {
            // Fetch recent AI log entries via a simple REST call.
            apiFetch({ path: 'ams/v1/ai/usage', headers: { 'X-WP-Nonce': cfg.nonce } })
                .then(() => {
                    // The usage endpoint gives summary; we'll show that.
                });
        }, []);

        return null; // Log browsing can be added in future.
    }

    // ── Main App ─────────────────────────────────────────────────────────

    function App() {
        const [settings, setSettings] = useState(null);
        const [usage, setUsage] = useState(null);

        useEffect(() => {
            api('ai/settings').then(setSettings);
            api('ai/usage').then(setUsage);
        }, []);

        if (!settings) return h('p', null, 'Loading AI settings...');

        return h('div', { style: { fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif', maxWidth: 800 } },
            h(UsageCard, { usage }),
            h(SettingsForm, { settings, onSave: (s) => { setSettings(s); api('ai/usage').then(setUsage); } })
        );
    }

    // ── Mount ────────────────────────────────────────────────────────────
    const root = document.getElementById('ams-ai-settings-app');
    if (root) {
        wp.element.render(h(App), root);
    }
})();
