/**
 * Apotheca® Marketing Suite — Sync Settings admin page.
 */
(function () {
    'use strict';

    var h = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useCallback = wp.element.useCallback;
    var apiFetch = wp.apiFetch;

    function App() {
        var s1 = useState({});
        var settings = s1[0], setSettings = s1[1];

        var s2 = useState([]);
        var log = s2[0], setLog = s2[1];

        var s3 = useState('');
        var secret = s3[0], setSecret = s3[1];

        var s4 = useState('');
        var domain = s4[0], setDomain = s4[1];

        var s5 = useState(false);
        var saving = s5[0], setSaving = s5[1];

        var s6 = useState('');
        var msg = s6[0], setMsg = s6[1];

        useEffect(function () {
            apiFetch({ path: '/ams/v1/sync/settings' }).then(function (data) {
                setSettings(data);
                setDomain(data.allowed_domain || '');
            });
            apiFetch({ path: '/ams/v1/sync/log' }).then(function (data) {
                setLog(data || []);
            });
        }, []);

        var handleSave = useCallback(function () {
            setSaving(true);
            var payload = { allowed_domain: domain };
            if (secret) payload.shared_secret = secret;

            apiFetch({ path: '/ams/v1/sync/settings', method: 'POST', data: payload })
                .then(function () {
                    setSaving(false);
                    setMsg('Settings saved!');
                    setSecret('');
                    apiFetch({ path: '/ams/v1/sync/settings' }).then(setSettings);
                    setTimeout(function () { setMsg(''); }, 3000);
                });
        }, [secret, domain]);

        var handleClearLog = useCallback(function () {
            if (!confirm('Clear all sync log entries?')) return;
            apiFetch({ path: '/ams/v1/sync/log/clear', method: 'POST' }).then(function () {
                setLog([]);
            });
        }, []);

        return h('div', { style: { maxWidth: '800px' } },
            // Settings card.
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '20px', marginBottom: '20px' } },
                h('h3', { style: { margin: '0 0 16px' } }, 'Sync Configuration'),

                h('label', { style: { display: 'block', marginBottom: '14px' } },
                    h('strong', { style: { display: 'block', marginBottom: '4px', fontSize: '13px' } }, 'Shared Secret Key'),
                    h('input', {
                        type: 'password',
                        value: secret,
                        onChange: function (e) { setSecret(e.target.value); },
                        placeholder: settings.shared_secret_set ? 'Configured — leave blank to keep' : 'Enter shared secret',
                        style: { width: '400px', padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: '4px' },
                    }),
                    h('p', { style: { fontSize: '12px', color: '#6b7280', marginTop: '4px' } }, 'Must match the secret configured in apotheca-marketing-sync on the main store.')
                ),

                h('label', { style: { display: 'block', marginBottom: '14px' } },
                    h('strong', { style: { display: 'block', marginBottom: '4px', fontSize: '13px' } }, 'Allowed Source Domain'),
                    h('input', {
                        type: 'text',
                        value: domain,
                        onChange: function (e) { setDomain(e.target.value); },
                        placeholder: 'yoursite.com',
                        style: { width: '400px', padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: '4px' },
                    }),
                    h('p', { style: { fontSize: '12px', color: '#6b7280', marginTop: '4px' } }, 'Only accept sync events from this domain. Leave blank to allow any.')
                ),

                h('button', {
                    onClick: handleSave,
                    disabled: saving,
                    style: { padding: '8px 20px', background: '#7c3aed', color: '#fff', border: 'none', borderRadius: '4px', cursor: 'pointer', fontWeight: '600' },
                }, saving ? 'Saving...' : 'Save Settings'),

                msg ? h('span', { style: { marginLeft: '12px', color: '#065f46', fontWeight: '500' } }, msg) : null
            ),

            // Status card.
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '20px', marginBottom: '20px' } },
                h('h3', { style: { margin: '0 0 12px' } }, 'Ingest Status'),
                h('p', { style: { fontSize: '14px', color: '#374151' } },
                    'Last event received: ',
                    h('strong', null, settings.last_received || 'Never')
                )
            ),

            // Sync log.
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '20px' } },
                h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' } },
                    h('h3', { style: { margin: 0 } }, 'Sync Log (Last 50)'),
                    h('button', {
                        onClick: handleClearLog,
                        style: { padding: '6px 14px', border: '1px solid #fca5a5', borderRadius: '4px', background: '#fff', color: '#dc2626', cursor: 'pointer', fontSize: '13px' },
                    }, 'Clear Log')
                ),
                log.length === 0
                    ? h('p', { style: { color: '#9ca3af' } }, 'No events received yet.')
                    : h('table', { style: { width: '100%', borderCollapse: 'collapse', fontSize: '13px' } },
                        h('thead', null,
                            h('tr', { style: { background: '#f9fafb', textAlign: 'left' } },
                                h('th', { style: { padding: '8px 12px', borderBottom: '1px solid #e5e7eb' } }, 'Event'),
                                h('th', { style: { padding: '8px 12px', borderBottom: '1px solid #e5e7eb' } }, 'Source'),
                                h('th', { style: { padding: '8px 12px', borderBottom: '1px solid #e5e7eb' } }, 'Status'),
                                h('th', { style: { padding: '8px 12px', borderBottom: '1px solid #e5e7eb' } }, 'Time')
                            )
                        ),
                        h('tbody', null,
                            log.map(function (entry, i) {
                                var statusColor = entry.http_response_sent == 200 ? '#065f46' : '#991b1b';
                                return h('tr', { key: entry.id || i },
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6' } }, entry.event_type),
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6' } }, entry.source_site_url || '—'),
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6', color: statusColor, fontWeight: '500' } }, String(entry.http_response_sent)),
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6', color: '#6b7280' } }, entry.received_at)
                                );
                            })
                        )
                    )
            )
        );
    }

    var container = document.getElementById('ams-sync-settings-app');
    if (container) {
        wp.element.render(h(App), container);
    }
})();
