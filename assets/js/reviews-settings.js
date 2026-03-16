/**
 * Apotheca® Marketing Suite — Reviews Settings admin page.
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback } = wp.element;
    const apiFetch = wp.apiFetch;

    /* ------------------------------------------------------------------ */
    /*  Cache Stats Card                                                   */
    /* ------------------------------------------------------------------ */

    function CacheStats({ stats, onRefresh, refreshing }) {
        return h('div', { className: 'ams-rs-card' },
            h('h3', null, 'Review Cache'),
            h('div', { className: 'ams-rs-stats-grid' },
                h('div', { className: 'ams-rs-stat' },
                    h('span', { className: 'ams-rs-stat__value' }, String(stats.total || 0)),
                    h('span', { className: 'ams-rs-stat__label' }, 'Total Cached')
                ),
                h('div', { className: 'ams-rs-stat' },
                    h('span', { className: 'ams-rs-stat__value' }, String(stats.woocommerce || 0)),
                    h('span', { className: 'ams-rs-stat__label' }, 'WooCommerce')
                ),
                h('div', { className: 'ams-rs-stat' },
                    h('span', { className: 'ams-rs-stat__value' }, String(stats.judgeme || 0)),
                    h('span', { className: 'ams-rs-stat__label' }, 'Judge.me')
                )
            ),
            h('div', { className: 'ams-rs-refresh' },
                h('span', { className: 'ams-rs-refresh__time' },
                    stats.last_refresh ? 'Last refreshed: ' + stats.last_refresh : 'Never refreshed'
                ),
                h('button', {
                    className: 'ams-rs-btn ams-rs-btn--primary',
                    onClick: onRefresh,
                    disabled: refreshing,
                }, refreshing ? 'Refreshing...' : 'Refresh Now')
            )
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Judge.me Status Card                                               */
    /* ------------------------------------------------------------------ */

    function JudgeMeCard({ settings, apiKey, onApiKeyChange, onTest, testResult, testing }) {
        var status = 'Not Detected';
        var statusClass = 'ams-rs-status--inactive';

        if (settings.judgeme_available) {
            if (settings.judgeme_api_key_set) {
                status = 'Active';
                statusClass = 'ams-rs-status--active';
            } else {
                status = 'Plugin Detected — API Key Required';
                statusClass = 'ams-rs-status--warning';
            }
        }

        return h('div', { className: 'ams-rs-card' },
            h('h3', null, 'Judge.me Integration'),
            h('div', { className: 'ams-rs-status ' + statusClass },
                h('span', null, 'Status: ' + status)
            ),
            h('label', { className: 'ams-rs-field' },
                h('span', null, 'Judge.me API Key'),
                h('div', { className: 'ams-rs-field__row' },
                    h('input', {
                        type: 'password',
                        value: apiKey,
                        onChange: function (e) { onApiKeyChange(e.target.value); },
                        placeholder: settings.judgeme_api_key_set ? '••••••••' : 'Enter API key',
                    }),
                    h('button', {
                        className: 'ams-rs-btn',
                        onClick: onTest,
                        disabled: testing,
                    }, testing ? 'Testing...' : 'Test Connection')
                ),
                testResult ? h('div', {
                    className: 'ams-rs-test-result ' + (testResult.success ? 'ams-rs-test-result--ok' : 'ams-rs-test-result--error'),
                }, testResult.message) : null
            )
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Settings Form                                                      */
    /* ------------------------------------------------------------------ */

    function SettingsForm({ settings, pages, onSave, saving }) {
        var s = useState(settings);
        var formData = s[0];
        var setFormData = s[1];

        useEffect(function () {
            setFormData(settings);
        }, [settings]);

        var update = function (key, val) {
            setFormData(function (prev) {
                var n = Object.assign({}, prev);
                n[key] = val;
                return n;
            });
        };

        return h('div', { className: 'ams-rs-card' },
            h('h3', null, 'Review Settings'),

            h('label', { className: 'ams-rs-field' },
                h('span', null, 'Minimum Rating to Cache'),
                h('select', {
                    value: String(formData.min_rating || 4),
                    onChange: function (e) { update('min_rating', parseInt(e.target.value)); },
                },
                    h('option', { value: '3' }, '3 Stars & Above'),
                    h('option', { value: '4' }, '4 Stars & Above'),
                    h('option', { value: '5' }, '5 Stars Only')
                )
            ),

            h('label', { className: 'ams-rs-field' },
                h('span', null, 'Private Feedback Page'),
                h('select', {
                    value: String(formData.private_feedback_page || 0),
                    onChange: function (e) { update('private_feedback_page', parseInt(e.target.value)); },
                },
                    h('option', { value: '0' }, '— None (show inline thank you) —'),
                    pages.map(function (p) {
                        return h('option', { key: p.id, value: String(p.id) }, p.title);
                    })
                )
            ),

            h('label', { className: 'ams-rs-field' },
                h('span', null, 'Review Gate Link Expiry (hours)'),
                h('input', {
                    type: 'number',
                    min: 1,
                    max: 720,
                    value: String(formData.gate_expiry_hours || 72),
                    onChange: function (e) { update('gate_expiry_hours', parseInt(e.target.value) || 72); },
                })
            ),

            h('button', {
                className: 'ams-rs-btn ams-rs-btn--primary',
                onClick: function () { onSave(formData); },
                disabled: saving,
            }, saving ? 'Saving...' : 'Save Settings')
        );
    }

    /* ------------------------------------------------------------------ */
    /*  App Root                                                           */
    /* ------------------------------------------------------------------ */

    function App() {
        var s1 = useState({});
        var settings = s1[0], setSettings = s1[1];

        var s2 = useState({ total: 0, woocommerce: 0, judgeme: 0, last_refresh: '' });
        var stats = s2[0], setStats = s2[1];

        var s3 = useState([]);
        var pages = s3[0], setPages = s3[1];

        var s4 = useState(false);
        var saving = s4[0], setSaving = s4[1];

        var s5 = useState(false);
        var refreshing = s5[0], setRefreshing = s5[1];

        var s6 = useState('');
        var apiKey = s6[0], setApiKey = s6[1];

        var s7 = useState(null);
        var testResult = s7[0], setTestResult = s7[1];

        var s8 = useState(false);
        var testing = s8[0], setTesting = s8[1];

        var s9 = useState('');
        var saveMsg = s9[0], setSaveMsg = s9[1];

        // Load data.
        useEffect(function () {
            apiFetch({ path: '/ams/v1/reviews/settings' }).then(setSettings);
            apiFetch({ path: '/ams/v1/reviews/stats' }).then(setStats);

            // Fetch published pages for feedback page selector.
            apiFetch({ path: '/wp/v2/pages?per_page=100&status=publish&_fields=id,title' })
                .then(function (data) {
                    setPages((data || []).map(function (p) {
                        return { id: p.id, title: p.title.rendered || 'Page #' + p.id };
                    }));
                });
        }, []);

        var handleSave = useCallback(function (formData) {
            setSaving(true);
            var payload = {
                min_rating: formData.min_rating,
                private_feedback_page: formData.private_feedback_page,
                gate_expiry_hours: formData.gate_expiry_hours,
            };

            if (apiKey) {
                payload.judgeme_api_key = apiKey;
            }

            apiFetch({ path: '/ams/v1/reviews/settings', method: 'POST', data: payload })
                .then(function () {
                    setSaving(false);
                    setSaveMsg('Settings saved!');
                    setTimeout(function () { setSaveMsg(''); }, 3000);
                    apiFetch({ path: '/ams/v1/reviews/settings' }).then(setSettings);
                });
        }, [apiKey]);

        var handleRefresh = useCallback(function () {
            setRefreshing(true);
            apiFetch({ path: '/ams/v1/reviews/refresh', method: 'POST' })
                .then(function () {
                    setRefreshing(false);
                    apiFetch({ path: '/ams/v1/reviews/stats' }).then(setStats);
                });
        }, []);

        var handleTest = useCallback(function () {
            setTesting(true);
            setTestResult(null);

            // Save the key first if entered.
            var chain = apiKey
                ? apiFetch({ path: '/ams/v1/reviews/settings', method: 'POST', data: { judgeme_api_key: apiKey } })
                : Promise.resolve();

            chain.then(function () {
                return apiFetch({ path: '/ams/v1/reviews/test-judgeme', method: 'POST' });
            }).then(function (r) {
                setTestResult(r);
                setTesting(false);
            }).catch(function (e) {
                setTestResult({ success: false, message: e.message || 'Connection failed.' });
                setTesting(false);
            });
        }, [apiKey]);

        return h('div', { className: 'ams-rs-wrap' },
            h(CacheStats, { stats: stats, onRefresh: handleRefresh, refreshing: refreshing }),
            h(JudgeMeCard, {
                settings: settings,
                apiKey: apiKey,
                onApiKeyChange: setApiKey,
                onTest: handleTest,
                testResult: testResult,
                testing: testing,
            }),
            h(SettingsForm, {
                settings: settings,
                pages: pages,
                onSave: handleSave,
                saving: saving,
            }),
            saveMsg ? h('div', { className: 'ams-rs-toast' }, saveMsg) : null
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Mount                                                              */
    /* ------------------------------------------------------------------ */

    var container = document.getElementById('ams-reviews-settings-app');
    if (container) {
        wp.element.render(h(App), container);
    }
})();
