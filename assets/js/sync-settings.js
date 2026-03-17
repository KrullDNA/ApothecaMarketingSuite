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

        var s7 = useState('');
        var storeUrl = s7[0], setStoreUrl = s7[1];

        var s8 = useState(false);
        var showSecret = s8[0], setShowSecret = s8[1];

        var s9 = useState('');
        var testMsg = s9[0], setTestMsg = s9[1];

        var s10 = useState(false);
        var testing = s10[0], setTesting = s10[1];

        var s11 = useState(false);
        var copied = s11[0], setCopied = s11[1];

        useEffect(function () {
            apiFetch({ path: '/ams/v1/sync/settings' }).then(function (data) {
                setSettings(data);
                setDomain(data.allowed_domain || '');
                setStoreUrl(data.store_url || '');
            });
            apiFetch({ path: '/ams/v1/sync/log' }).then(function (data) {
                setLog(data || []);
            });
        }, []);

        var handleSave = useCallback(function () {
            setSaving(true);
            var payload = { allowed_domain: domain, store_url: storeUrl };
            if (secret) payload.shared_secret = secret;

            apiFetch({ path: '/ams/v1/sync/settings', method: 'POST', data: payload })
                .then(function () {
                    setSaving(false);
                    setMsg('Settings saved!');
                    setSecret('');
                    apiFetch({ path: '/ams/v1/sync/settings' }).then(setSettings);
                    setTimeout(function () { setMsg(''); }, 3000);
                });
        }, [secret, domain, storeUrl]);

        var handleTest = useCallback(function () {
            setTesting(true);
            setTestMsg('');
            apiFetch({ path: '/ams/v1/sync/test', method: 'POST' })
                .then(function (data) {
                    setTesting(false);
                    if (data.status === 'ok') {
                        setTestMsg('Connection successful! HTTP ' + (data.http_code || 200));
                    } else {
                        setTestMsg('Connection failed: ' + (data.message || JSON.stringify(data.response || {})));
                    }
                })
                .catch(function (err) {
                    setTesting(false);
                    setTestMsg('Test failed: ' + (err.message || 'Unknown error'));
                });
        }, []);

        var handleCopyUrl = useCallback(function () {
            var url = settings.ingest_url || '';
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    setCopied(true);
                    setTimeout(function () { setCopied(false); }, 2000);
                });
            }
        }, [settings.ingest_url]);

        var handleClearLog = useCallback(function () {
            if (!confirm('Clear all sync log entries?')) return;
            apiFetch({ path: '/ams/v1/sync/log/clear', method: 'POST' }).then(function () {
                setLog([]);
            });
        }, []);

        var labelStyle = { display: 'block', marginBottom: '14px' };
        var strongStyle = { display: 'block', marginBottom: '4px', fontSize: '13px' };
        var inputStyle = { width: '400px', padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: '4px' };
        var helpStyle = { fontSize: '12px', color: '#6b7280', marginTop: '4px' };

        return h('div', { style: { maxWidth: '800px' } },
            // Settings card.
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: '8px', padding: '20px', marginBottom: '20px' } },
                h('h3', { style: { margin: '0 0 16px' } }, 'Sync Configuration'),

                // Store URL.
                h('label', { style: labelStyle },
                    h('strong', { style: strongStyle }, 'Store URL'),
                    h('input', {
                        type: 'url',
                        value: storeUrl,
                        onChange: function (e) { setStoreUrl(e.target.value); },
                        placeholder: 'https://yoursite.com',
                        style: inputStyle,
                    }),
                    h('p', { style: helpStyle }, 'Base URL of the main WooCommerce store (e.g. https://yoursite.com).')
                ),

                // Shared Secret Key.
                h('label', { style: labelStyle },
                    h('strong', { style: strongStyle }, 'Shared Secret Key'),
                    h('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } },
                        h('input', {
                            type: showSecret ? 'text' : 'password',
                            value: secret,
                            onChange: function (e) { setSecret(e.target.value); },
                            placeholder: settings.shared_secret_set ? 'Configured \u2014 leave blank to keep' : 'Enter shared secret',
                            style: inputStyle,
                        }),
                        h('button', {
                            type: 'button',
                            onClick: function () { setShowSecret(!showSecret); },
                            style: { padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: '4px', background: '#fff', cursor: 'pointer', fontSize: '12px' },
                        }, showSecret ? 'Hide' : 'Show')
                    ),
                    h('p', { style: helpStyle }, 'Copy this value into the Shared Secret field in the Apotheca Marketing Sync plugin on your main store.')
                ),

                // Allowed Source Domain.
                h('label', { style: labelStyle },
                    h('strong', { style: strongStyle }, 'Allowed Source Domain'),
                    h('input', {
                        type: 'text',
                        value: domain,
                        onChange: function (e) { setDomain(e.target.value); },
                        placeholder: 'yoursite.com',
                        style: inputStyle,
                    }),
                    h('p', { style: helpStyle }, 'Only accept sync events from this domain. Leave blank to allow any.')
                ),

                // Ingest Endpoint URL (read-only).
                h('label', { style: labelStyle },
                    h('strong', { style: strongStyle }, 'Ingest Endpoint URL'),
                    h('div', { style: { display: 'flex', gap: '8px', alignItems: 'center' } },
                        h('input', {
                            type: 'text',
                            value: settings.ingest_url || '',
                            readOnly: true,
                            style: Object.assign({}, inputStyle, { background: '#f9fafb', color: '#374151' }),
                        }),
                        h('button', {
                            type: 'button',
                            onClick: handleCopyUrl,
                            style: { padding: '8px 12px', border: '1px solid #d1d5db', borderRadius: '4px', background: '#fff', cursor: 'pointer', fontSize: '12px' },
                        }, copied ? 'Copied!' : 'Copy')
                    ),
                    h('p', { style: helpStyle }, 'Paste this URL into the Endpoint URL field in the Apotheca Marketing Sync plugin on your main store.')
                ),

                // Save and Test buttons.
                h('div', { style: { display: 'flex', gap: '12px', alignItems: 'center', marginTop: '8px' } },
                    h('button', {
                        onClick: handleSave,
                        disabled: saving,
                        style: { padding: '8px 20px', background: '#7c3aed', color: '#fff', border: 'none', borderRadius: '4px', cursor: 'pointer', fontWeight: '600' },
                    }, saving ? 'Saving...' : 'Save Settings'),
                    h('button', {
                        onClick: handleTest,
                        disabled: testing,
                        style: { padding: '8px 20px', background: '#fff', color: '#7c3aed', border: '1px solid #7c3aed', borderRadius: '4px', cursor: 'pointer', fontWeight: '600' },
                    }, testing ? 'Testing...' : 'Test Connection'),
                    msg ? h('span', { style: { color: '#065f46', fontWeight: '500' } }, msg) : null,
                    testMsg ? h('span', { style: { color: testMsg.indexOf('successful') >= 0 ? '#065f46' : '#991b1b', fontWeight: '500' } }, testMsg) : null
                )
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
                                var statusColor = entry.status === 'processed' ? '#065f46' : '#991b1b';
                                return h('tr', { key: entry.id || i },
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6' } }, entry.event_type),
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6' } }, entry.source_site_url || '\u2014'),
                                    h('td', { style: { padding: '8px 12px', borderBottom: '1px solid #f3f4f6', color: statusColor, fontWeight: '500' } }, entry.status || String(entry.http_response_sent)),
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
