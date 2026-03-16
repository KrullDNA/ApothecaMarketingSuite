/**
 * Apotheca® Marketing Suite — Analytics Dashboard
 *
 * React SPA with 5 tabs: Overview, Email Performance, SMS Performance,
 * Subscriber Insights, Flow Analytics. All data from REST API.
 *
 * Uses wp.element (React) + wp.apiFetch (WordPress bundled).
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback, Fragment } = wp.element;
    const apiFetch = wp.apiFetch;
    const cfg = window.amsAnalytics || {};

    // ── Helpers ──────────────────────────────────────────────────────────
    const api = (path, params = {}) => {
        const query = Object.entries(params)
            .filter(([, v]) => v !== undefined && v !== '')
            .map(([k, v]) => `${k}=${encodeURIComponent(v)}`)
            .join('&');
        const url = cfg.restUrl + path + (query ? '?' + query : '');
        return apiFetch({ path: url.replace(cfg.restUrl.replace(/\/+$/, '').replace(/.*\/wp-json\//, '/wp-json/'), ''), headers: { 'X-WP-Nonce': cfg.nonce } });
    };

    const fmt = (n) => typeof n === 'number' ? n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) : n;
    const fmtCurrency = (n) => '$' + fmt(n);
    const fmtPct = (n) => fmt(n) + '%';

    const downloadCsv = (csv, filename) => {
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = filename; a.click();
        URL.revokeObjectURL(url);
    };

    // ── Lightweight SVG Chart Components ─────────────────────────────────
    // (No external charting lib — pure SVG)

    function LineChart({ data, xKey, yKey, width = 600, height = 200, color = '#4f46e5' }) {
        if (!data || data.length === 0) return h('p', { style: { color: '#999' } }, 'No data');
        const vals = data.map(d => d[yKey]);
        const maxVal = Math.max(...vals, 1);
        const pad = { t: 20, r: 20, b: 40, l: 50 };
        const w = width - pad.l - pad.r;
        const hh = height - pad.t - pad.b;
        const points = data.map((d, i) => {
            const x = pad.l + (i / Math.max(data.length - 1, 1)) * w;
            const y = pad.t + hh - (d[yKey] / maxVal) * hh;
            return `${x},${y}`;
        }).join(' ');

        const gridLines = [0, 0.25, 0.5, 0.75, 1].map(pct => {
            const y = pad.t + hh - pct * hh;
            const label = Math.round(maxVal * pct);
            return h(Fragment, { key: pct },
                h('line', { x1: pad.l, y1: y, x2: pad.l + w, y2: y, stroke: '#e5e7eb', strokeWidth: 1 }),
                h('text', { x: pad.l - 8, y: y + 4, textAnchor: 'end', fontSize: 10, fill: '#6b7280' }, fmt(label))
            );
        });

        const xLabels = data.filter((_, i) => i % Math.max(1, Math.floor(data.length / 6)) === 0).map((d, _, arr) => {
            const idx = data.indexOf(d);
            const x = pad.l + (idx / Math.max(data.length - 1, 1)) * w;
            return h('text', { key: idx, x, y: height - 8, textAnchor: 'middle', fontSize: 10, fill: '#6b7280' },
                d[xKey] ? d[xKey].slice(5) : '');
        });

        return h('svg', { width: '100%', viewBox: `0 0 ${width} ${height}`, style: { maxWidth: width } },
            ...gridLines,
            h('polyline', { points, fill: 'none', stroke: color, strokeWidth: 2 }),
            data.map((d, i) => {
                const x = pad.l + (i / Math.max(data.length - 1, 1)) * w;
                const y = pad.t + hh - (d[yKey] / maxVal) * hh;
                return h('circle', { key: i, cx: x, cy: y, r: 3, fill: color });
            }),
            ...xLabels
        );
    }

    function BarChart({ data, xKey, bars, width = 600, height = 250, colors = ['#4f46e5', '#10b981'] }) {
        if (!data || data.length === 0) return h('p', { style: { color: '#999' } }, 'No data');
        const allVals = data.flatMap(d => bars.map(b => d[b] || 0));
        const maxVal = Math.max(...allVals, 1);
        const pad = { t: 20, r: 20, b: 50, l: 60 };
        const w = width - pad.l - pad.r;
        const hh = height - pad.t - pad.b;
        const groupW = w / data.length;
        const barW = groupW / (bars.length + 1);

        return h('svg', { width: '100%', viewBox: `0 0 ${width} ${height}`, style: { maxWidth: width } },
            [0, 0.25, 0.5, 0.75, 1].map(pct => {
                const y = pad.t + hh - pct * hh;
                return h(Fragment, { key: 'g' + pct },
                    h('line', { x1: pad.l, y1: y, x2: pad.l + w, y2: y, stroke: '#e5e7eb', strokeWidth: 1 }),
                    h('text', { x: pad.l - 8, y: y + 4, textAnchor: 'end', fontSize: 10, fill: '#6b7280' }, fmtCurrency(maxVal * pct))
                );
            }),
            data.flatMap((d, i) => bars.map((b, bi) => {
                const x = pad.l + i * groupW + (bi + 0.5) * barW;
                const bh = (d[b] / maxVal) * hh;
                return h('rect', { key: `${i}-${bi}`, x, y: pad.t + hh - bh, width: barW * 0.8, height: bh, fill: colors[bi % colors.length], rx: 2 });
            })),
            data.filter((_, i) => i % Math.max(1, Math.floor(data.length / 8)) === 0).map((d, _, arr) => {
                const idx = data.indexOf(d);
                const x = pad.l + idx * groupW + groupW / 2;
                return h('text', { key: 'x' + idx, x, y: height - 8, textAnchor: 'middle', fontSize: 10, fill: '#6b7280' }, d[xKey] ? d[xKey].slice(5) : '');
            })
        );
    }

    function DoughnutChart({ data, labelKey, valueKey, width = 300, colors }) {
        if (!data || data.length === 0) return h('p', { style: { color: '#999' } }, 'No data');
        const total = data.reduce((s, d) => s + d[valueKey], 0) || 1;
        const defaultColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#84cc16'];
        const cols = colors || defaultColors;
        const cx = width / 2, cy = width / 2, r = width * 0.35;
        let cumAngle = -Math.PI / 2;
        const arcs = data.map((d, i) => {
            const angle = (d[valueKey] / total) * 2 * Math.PI;
            const startX = cx + r * Math.cos(cumAngle);
            const startY = cy + r * Math.sin(cumAngle);
            cumAngle += angle;
            const endX = cx + r * Math.cos(cumAngle);
            const endY = cy + r * Math.sin(cumAngle);
            const large = angle > Math.PI ? 1 : 0;
            const path = `M ${cx} ${cy} L ${startX} ${startY} A ${r} ${r} 0 ${large} 1 ${endX} ${endY} Z`;
            return h('path', { key: i, d: path, fill: cols[i % cols.length] });
        });
        return h('div', { style: { display: 'flex', gap: 20, alignItems: 'center' } },
            h('svg', { width: width, height: width, viewBox: `0 0 ${width} ${width}` }, ...arcs),
            h('div', { style: { fontSize: 13 } },
                data.map((d, i) => h('div', { key: i, style: { display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 } },
                    h('span', { style: { width: 12, height: 12, borderRadius: 2, background: cols[i % cols.length], display: 'inline-block' } }),
                    h('span', null, `${d[labelKey]}: ${fmt(d[valueKey])} (${fmtPct(Math.round((d[valueKey] / total) * 100))})`)
                ))
            )
        );
    }

    // ── Shared Components ────────────────────────────────────────────────

    function Card({ title, value, subtitle }) {
        return h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20, minWidth: 180 } },
            h('div', { style: { fontSize: 13, color: '#6b7280', marginBottom: 4 } }, title),
            h('div', { style: { fontSize: 28, fontWeight: 700, color: '#111827' } }, value),
            subtitle && h('div', { style: { fontSize: 12, color: '#9ca3af', marginTop: 4 } }, subtitle)
        );
    }

    function PeriodSelector({ period, onChange }) {
        const opts = [['7d', '7 Days'], ['30d', '30 Days'], ['all', 'All Time']];
        return h('div', { style: { display: 'flex', gap: 8, marginBottom: 16 } },
            opts.map(([val, label]) =>
                h('button', {
                    key: val,
                    onClick: () => onChange(val),
                    style: {
                        padding: '6px 14px', borderRadius: 6, border: '1px solid ' + (period === val ? '#4f46e5' : '#d1d5db'),
                        background: period === val ? '#4f46e5' : '#fff', color: period === val ? '#fff' : '#374151',
                        cursor: 'pointer', fontSize: 13, fontWeight: 500
                    }
                }, label)
            )
        );
    }

    function ExportButton({ type, label, period }) {
        const [loading, setLoading] = useState(false);
        const handleExport = async () => {
            setLoading(true);
            try {
                const res = await api('analytics/export', { type, period });
                if (res.csv) downloadCsv(res.csv, res.filename);
            } catch (e) { /* ignore */ }
            setLoading(false);
        };
        return h('button', {
            onClick: handleExport,
            disabled: loading,
            style: { padding: '6px 14px', borderRadius: 6, border: '1px solid #d1d5db', background: '#fff', cursor: 'pointer', fontSize: 13 }
        }, loading ? 'Exporting...' : (label || 'Export CSV'));
    }

    function SortableTable({ columns, data, defaultSort, onSort }) {
        const [sortCol, setSortCol] = useState(defaultSort || '');
        const [sortDir, setSortDir] = useState('desc');

        const sorted = [...(data || [])].sort((a, b) => {
            if (!sortCol) return 0;
            const av = a[sortCol], bv = b[sortCol];
            const diff = typeof av === 'number' ? av - bv : String(av).localeCompare(String(bv));
            return sortDir === 'asc' ? diff : -diff;
        });

        const toggleSort = (col) => {
            if (sortCol === col) setSortDir(d => d === 'asc' ? 'desc' : 'asc');
            else { setSortCol(col); setSortDir('desc'); }
        };

        return h('table', { style: { width: '100%', borderCollapse: 'collapse', fontSize: 13 } },
            h('thead', null,
                h('tr', null, columns.map(col =>
                    h('th', {
                        key: col.key,
                        onClick: () => toggleSort(col.key),
                        style: { padding: '10px 12px', textAlign: col.align || 'left', cursor: 'pointer', borderBottom: '2px solid #e5e7eb', fontWeight: 600, color: '#374151', userSelect: 'none' }
                    }, col.label + (sortCol === col.key ? (sortDir === 'asc' ? ' ↑' : ' ↓') : ''))
                ))
            ),
            h('tbody', null,
                sorted.map((row, i) =>
                    h('tr', { key: i, style: { background: i % 2 === 0 ? '#f9fafb' : '#fff' } },
                        columns.map(col =>
                            h('td', { key: col.key, style: { padding: '8px 12px', textAlign: col.align || 'left', borderBottom: '1px solid #f3f4f6' } },
                                col.render ? col.render(row[col.key], row) : row[col.key])
                        )
                    )
                )
            )
        );
    }

    // ── Tab: Overview ────────────────────────────────────────────────────

    function OverviewTab() {
        const [period, setPeriod] = useState('30d');
        const [data, setData] = useState(null);
        const [growth, setGrowth] = useState([]);
        const [revChannel, setRevChannel] = useState([]);

        useEffect(() => {
            api('analytics/overview', { period }).then(setData);
            api('analytics/subscriber-growth', { period }).then(setGrowth);
            api('analytics/revenue-by-channel', { period }).then(setRevChannel);
        }, [period]);

        if (!data) return h('p', null, 'Loading...');

        return h(Fragment, null,
            h(PeriodSelector, { period, onChange: setPeriod }),
            h('div', { style: { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 16, marginBottom: 24 } },
                h(Card, { title: 'Total Revenue', value: fmtCurrency(data.total_revenue) }),
                h(Card, { title: 'Email Revenue', value: fmtCurrency(data.email_revenue) }),
                h(Card, { title: 'SMS Revenue', value: fmtCurrency(data.sms_revenue) }),
                h(Card, { title: 'Active Subscribers', value: fmt(data.active_subscribers) }),
                h(Card, { title: 'Avg Order Value', value: fmtCurrency(data.aov), subtitle: `${data.attributed_orders} attributed orders` })
            ),
            h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 } },
                h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                    h('h3', { style: { margin: '0 0 12px', fontSize: 15 } }, 'Subscriber Growth'),
                    h(LineChart, { data: growth, xKey: 'date', yKey: 'count' })
                ),
                h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                    h('h3', { style: { margin: '0 0 12px', fontSize: 15 } }, 'Revenue by Channel'),
                    h(BarChart, { data: revChannel, xKey: 'date', bars: ['email_revenue', 'sms_revenue'] }),
                    h('div', { style: { display: 'flex', gap: 16, marginTop: 8, fontSize: 12 } },
                        h('span', null, h('span', { style: { display: 'inline-block', width: 10, height: 10, background: '#4f46e5', borderRadius: 2, marginRight: 4 } }), 'Email'),
                        h('span', null, h('span', { style: { display: 'inline-block', width: 10, height: 10, background: '#10b981', borderRadius: 2, marginRight: 4 } }), 'SMS')
                    )
                )
            )
        );
    }

    // ── Tab: Email Performance ───────────────────────────────────────────

    function EmailTab() {
        const [data, setData] = useState([]);
        const [bounces, setBounces] = useState({ items: [], total: 0 });
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            Promise.all([
                api('analytics/email-performance'),
                api('analytics/bounce-log')
            ]).then(([perf, bl]) => {
                setData(perf);
                setBounces(bl);
                setLoading(false);
            });
        }, []);

        if (loading) return h('p', null, 'Loading...');

        const columns = [
            { key: 'name', label: 'Name' },
            { key: 'type', label: 'Type' },
            { key: 'sent', label: 'Sent', align: 'right' },
            { key: 'deliverability', label: 'Deliverability', align: 'right', render: v => fmtPct(v) },
            { key: 'open_rate', label: 'Open Rate', align: 'right', render: v => fmtPct(v) },
            { key: 'click_rate', label: 'Click Rate', align: 'right', render: v => fmtPct(v) },
            { key: 'unsub_rate', label: 'Unsub Rate', align: 'right', render: v => fmtPct(v) },
            { key: 'revenue', label: 'Revenue', align: 'right', render: v => fmtCurrency(v) },
            { key: 'revenue_per_send', label: 'Rev/Send', align: 'right', render: v => fmtCurrency(v) },
        ];

        const bounceColumns = [
            { key: 'email', label: 'Email' },
            { key: 'name', label: 'Name' },
            { key: 'channel', label: 'Channel' },
            { key: 'bounced_at', label: 'Bounced At' },
        ];

        return h(Fragment, null,
            h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 } },
                h('h3', { style: { margin: 0 } }, 'Email Campaign & Flow Performance'),
                h(ExportButton, { type: 'email-performance', label: 'Export CSV' })
            ),
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 16, marginBottom: 24, overflowX: 'auto' } },
                h(SortableTable, { columns, data, defaultSort: 'sent' })
            ),
            h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 } },
                h('h3', { style: { margin: 0 } }, `Bounce Log (${bounces.total} total)`),
                h(ExportButton, { type: 'bounce-log', label: 'Export Bounces' })
            ),
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 16, overflowX: 'auto' } },
                h(SortableTable, { columns: bounceColumns, data: bounces.items, defaultSort: 'bounced_at' })
            )
        );
    }

    // ── Tab: SMS Performance ─────────────────────────────────────────────

    function SmsTab() {
        const [data, setData] = useState([]);
        const [trend, setTrend] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            Promise.all([
                api('analytics/sms-performance'),
                api('analytics/sms-delivery-trend', { period: '30d' })
            ]).then(([perf, t]) => {
                setData(perf);
                setTrend(t);
                setLoading(false);
            });
        }, []);

        if (loading) return h('p', null, 'Loading...');

        const columns = [
            { key: 'name', label: 'Campaign' },
            { key: 'sent', label: 'Sent', align: 'right' },
            { key: 'delivered', label: 'Delivered', align: 'right' },
            { key: 'delivery_rate', label: 'Delivery Rate', align: 'right', render: v => fmtPct(v) },
            { key: 'opt_outs', label: 'Opt-Outs', align: 'right' },
            { key: 'opt_out_rate', label: 'Opt-Out Rate', align: 'right', render: v => fmtPct(v) },
            { key: 'revenue', label: 'Revenue', align: 'right', render: v => fmtCurrency(v) },
        ];

        return h(Fragment, null,
            h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 } },
                h('h3', { style: { margin: 0 } }, 'SMS Campaign Performance'),
                h(ExportButton, { type: 'sms-performance', label: 'Export CSV' })
            ),
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 16, marginBottom: 24, overflowX: 'auto' } },
                h(SortableTable, { columns, data, defaultSort: 'sent' })
            ),
            h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                h('h3', { style: { margin: '0 0 12px', fontSize: 15 } }, 'SMS Delivery Rate Trend (30 days)'),
                h(LineChart, { data: trend, xKey: 'date', yKey: 'delivery_rate', color: '#10b981' })
            )
        );
    }

    // ── Tab: Subscriber Insights ─────────────────────────────────────────

    function InsightsTab() {
        const [heatmap, setHeatmap] = useState(null);
        const [segments, setSegments] = useState([]);
        const [churn, setChurn] = useState([]);
        const [clv, setClv] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            Promise.all([
                api('analytics/rfm-heatmap'),
                api('analytics/segment-breakdown'),
                api('analytics/churn-distribution'),
                api('analytics/clv-distribution')
            ]).then(([hm, seg, ch, cl]) => {
                setHeatmap(hm);
                setSegments(seg);
                setChurn(ch);
                setClv(cl);
                setLoading(false);
            });
        }, []);

        if (loading) return h('p', null, 'Loading...');

        return h(Fragment, null,
            h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 } },
                h('h3', { style: { margin: 0 } }, 'Subscriber Insights'),
                h(ExportButton, { type: 'subscribers', label: 'Export All Subscribers' })
            ),
            h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24, marginBottom: 24 } },
                // RFM Heatmap
                h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                    h('h4', { style: { margin: '0 0 12px', fontSize: 14 } }, 'RFM Heatmap (Recency x Frequency)'),
                    h(RfmHeatmap, { data: heatmap })
                ),
                // Segment Breakdown
                h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                    h('h4', { style: { margin: '0 0 12px', fontSize: 14 } }, 'RFM Segment Breakdown'),
                    h(DoughnutChart, { data: segments, labelKey: 'segment', valueKey: 'count', width: 220 })
                )
            ),
            h('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24 } },
                // Churn Risk
                h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                    h('h4', { style: { margin: '0 0 12px', fontSize: 14 } }, 'Churn Risk Distribution'),
                    h(HorizontalBarChart, { data: churn, labelKey: 'label', valueKey: 'count', colors: ['#10b981', '#f59e0b', '#ef4444'] })
                ),
                // CLV Distribution
                h('div', { style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 20 } },
                    h('h4', { style: { margin: '0 0 12px', fontSize: 14 } }, 'Predicted CLV Distribution'),
                    h(HorizontalBarChart, { data: clv, labelKey: 'bucket', valueKey: 'count', colors: ['#4f46e5'] })
                )
            )
        );
    }

    function RfmHeatmap({ data }) {
        if (!data) return null;
        const maxVal = Math.max(...Object.values(data), 1);
        const cellSize = 52;

        const cells = [];
        for (let r = 5; r >= 1; r--) {
            for (let f = 1; f <= 5; f++) {
                const key = `${r}-${f}`;
                const count = data[key] || 0;
                const intensity = count / maxVal;
                const bg = `rgba(79, 70, 229, ${0.1 + intensity * 0.8})`;
                const color = intensity > 0.5 ? '#fff' : '#374151';
                cells.push(h('div', {
                    key,
                    title: `R=${r}, F=${f}: ${count} subscribers`,
                    style: {
                        width: cellSize, height: cellSize, background: bg, display: 'flex',
                        alignItems: 'center', justifyContent: 'center', fontSize: 12,
                        fontWeight: 600, color, borderRadius: 4, cursor: 'default'
                    }
                }, count));
            }
        }

        return h('div', null,
            h('div', { style: { display: 'flex', gap: 4, marginBottom: 4 } },
                h('div', { style: { width: 30 } }),
                [1, 2, 3, 4, 5].map(f => h('div', { key: f, style: { width: cellSize, textAlign: 'center', fontSize: 11, color: '#6b7280', fontWeight: 600 } }, 'F' + f))
            ),
            [5, 4, 3, 2, 1].map((r, ri) =>
                h('div', { key: r, style: { display: 'flex', gap: 4, marginBottom: 4, alignItems: 'center' } },
                    h('div', { style: { width: 30, fontSize: 11, color: '#6b7280', fontWeight: 600, textAlign: 'right', paddingRight: 4 } }, 'R' + r),
                    ...cells.slice(ri * 5, ri * 5 + 5)
                )
            )
        );
    }

    function HorizontalBarChart({ data, labelKey, valueKey, colors }) {
        if (!data || data.length === 0) return h('p', { style: { color: '#999' } }, 'No data');
        const maxVal = Math.max(...data.map(d => d[valueKey]), 1);

        return h('div', { style: { display: 'flex', flexDirection: 'column', gap: 8 } },
            data.map((d, i) =>
                h('div', { key: i },
                    h('div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: 2, fontSize: 13 } },
                        h('span', null, d[labelKey]),
                        h('span', { style: { fontWeight: 600 } }, fmt(d[valueKey]))
                    ),
                    h('div', { style: { height: 24, background: '#f3f4f6', borderRadius: 4, overflow: 'hidden' } },
                        h('div', { style: { height: '100%', width: ((d[valueKey] / maxVal) * 100) + '%', background: colors[i % colors.length] || colors[0], borderRadius: 4, transition: 'width 0.3s' } })
                    )
                )
            )
        );
    }

    // ── Tab: Flow Analytics ──────────────────────────────────────────────

    function FlowsTab() {
        const [flows, setFlows] = useState([]);
        const [loading, setLoading] = useState(true);
        const [expanded, setExpanded] = useState(null);

        useEffect(() => {
            api('analytics/flow-funnels').then(f => { setFlows(f); setLoading(false); });
        }, []);

        if (loading) return h('p', null, 'Loading...');
        if (flows.length === 0) return h('p', { style: { color: '#999' } }, 'No flows found.');

        return h(Fragment, null,
            h('h3', { style: { margin: '0 0 16px' } }, 'Flow Analytics'),
            flows.map(flow =>
                h('div', { key: flow.id, style: { background: '#fff', border: '1px solid #e5e7eb', borderRadius: 8, padding: 16, marginBottom: 16 } },
                    h('div', {
                        onClick: () => setExpanded(expanded === flow.id ? null : flow.id),
                        style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer' }
                    },
                        h('div', null,
                            h('strong', { style: { fontSize: 15 } }, flow.name),
                            h('span', { style: { marginLeft: 12, fontSize: 13, color: '#6b7280' } },
                                `Enrolled: ${fmt(flow.enrolled)} | Completed: ${fmt(flow.completed)} | Exited: ${fmt(flow.exited)} | Revenue: ${fmtCurrency(flow.revenue)}`)
                        ),
                        h('span', { style: { fontSize: 18 } }, expanded === flow.id ? '▾' : '▸')
                    ),
                    expanded === flow.id && flow.steps.length > 0 && h('div', { style: { marginTop: 16 } },
                        h(FlowFunnel, { steps: flow.steps, enrolled: flow.enrolled })
                    )
                )
            )
        );
    }

    function FlowFunnel({ steps, enrolled }) {
        const maxCount = Math.max(enrolled, ...steps.map(s => s.count), 1);

        return h('div', { style: { display: 'flex', flexDirection: 'column', gap: 4 } },
            h('div', { style: { display: 'flex', alignItems: 'center', gap: 12, marginBottom: 8 } },
                h('div', { style: { width: 140, fontSize: 13, fontWeight: 600 } }, 'Enrolled'),
                h('div', { style: { flex: 1, height: 28, background: '#f3f4f6', borderRadius: 4, overflow: 'hidden' } },
                    h('div', { style: { height: '100%', width: '100%', background: '#4f46e5', borderRadius: 4 } })
                ),
                h('span', { style: { fontSize: 13, fontWeight: 600, minWidth: 50 } }, fmt(enrolled))
            ),
            steps.map(step =>
                h('div', { key: step.id, style: { display: 'flex', alignItems: 'center', gap: 12 } },
                    h('div', { style: { width: 140, fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' } },
                        `${step.order + 1}. ${step.label}`),
                    h('div', { style: { flex: 1, height: 28, background: '#f3f4f6', borderRadius: 4, overflow: 'hidden' } },
                        h('div', { style: {
                            height: '100%',
                            width: ((step.count / maxCount) * 100) + '%',
                            background: step.highlight ? '#f59e0b' : '#4f46e5',
                            borderRadius: 4, transition: 'width 0.3s'
                        } })
                    ),
                    h('span', { style: { fontSize: 12, minWidth: 90, textAlign: 'right' } },
                        `${fmt(step.count)} (${fmtPct(step.drop_off)} drop)`)
                )
            )
        );
    }

    // ── Main App ─────────────────────────────────────────────────────────

    function App() {
        const [tab, setTab] = useState('overview');

        const tabs = [
            { id: 'overview', label: 'Overview' },
            { id: 'email', label: 'Email Performance' },
            { id: 'sms', label: 'SMS Performance' },
            { id: 'insights', label: 'Subscriber Insights' },
            { id: 'flows', label: 'Flow Analytics' },
        ];

        const tabStyle = (id) => ({
            padding: '10px 20px', cursor: 'pointer', fontSize: 14, fontWeight: 500, borderBottom: tab === id ? '3px solid #4f46e5' : '3px solid transparent',
            color: tab === id ? '#4f46e5' : '#6b7280', background: 'none', border: 'none', borderBottomWidth: 3, borderBottomStyle: 'solid',
            borderBottomColor: tab === id ? '#4f46e5' : 'transparent'
        });

        return h('div', { style: { fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif' } },
            h('div', { style: { display: 'flex', borderBottom: '1px solid #e5e7eb', marginBottom: 24 } },
                tabs.map(t => h('button', { key: t.id, onClick: () => setTab(t.id), style: tabStyle(t.id) }, t.label))
            ),
            tab === 'overview' && h(OverviewTab),
            tab === 'email' && h(EmailTab),
            tab === 'sms' && h(SmsTab),
            tab === 'insights' && h(InsightsTab),
            tab === 'flows' && h(FlowsTab)
        );
    }

    // ── Mount ────────────────────────────────────────────────────────────
    const root = document.getElementById('ams-analytics-app');
    if (root) {
        wp.element.render(h(App), root);
    }
})();
