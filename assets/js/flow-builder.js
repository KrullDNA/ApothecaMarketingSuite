/**
 * AMS Flow Builder — React-based drag-and-drop flow canvas.
 *
 * Bundled with wp_enqueue_script into the WP admin Flows page.
 * Uses wp.element (React) bundled with WordPress.
 *
 * @package Apotheca\Marketing
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback, useRef, Fragment } = wp.element;
    const { render } = wp.element;
    const apiFetch = wp.apiFetch;

    /* ── Constants ────────────────────────────────────────────────── */
    const TRIGGER_TYPES = [
        { value: 'welcome_series', label: 'Welcome Series' },
        { value: 'abandoned_cart', label: 'Abandoned Cart' },
        { value: 'post_purchase', label: 'Post-Purchase' },
        { value: 'win_back', label: 'Win-Back' },
        { value: 'browse_abandonment', label: 'Browse Abandonment' },
        { value: 'birthday', label: 'Birthday' },
        { value: 'rfm_change', label: 'RFM Segment Change' },
        { value: 'custom_event', label: 'Custom Event' },
    ];

    const STEP_TYPES = [
        { value: 'send_email', label: 'Send Email', icon: '✉' },
        { value: 'send_sms', label: 'Send SMS', icon: '📱' },
        { value: 'wait', label: 'Wait / Delay', icon: '⏱' },
        { value: 'condition', label: 'Condition', icon: '⑂' },
        { value: 'add_tag', label: 'Add Tag', icon: '+🏷' },
        { value: 'remove_tag', label: 'Remove Tag', icon: '-🏷' },
        { value: 'update_field', label: 'Update Field', icon: '✎' },
        { value: 'exit', label: 'Exit Flow', icon: '⏹' },
    ];

    const STATUS_OPTIONS = [
        { value: 'draft', label: 'Draft' },
        { value: 'active', label: 'Active' },
        { value: 'paused', label: 'Paused' },
    ];

    /* ── API helpers ──────────────────────────────────────────────── */
    function api(path, opts = {}) {
        return apiFetch({
            path: '/ams/v1' + path,
            ...opts,
            headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
        });
    }

    /* ── FlowList Component ───────────────────────────────────────── */
    function FlowList({ onEdit, onNew }) {
        const [flows, setFlows] = useState([]);
        const [templates, setTemplates] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            Promise.all([
                api('/flows'),
                api('/flows/templates'),
            ]).then(([f, t]) => {
                setFlows(f || []);
                setTemplates(t || []);
                setLoading(false);
            });
        }, []);

        function importTemplate(slug) {
            api('/flows/templates/import', {
                method: 'POST',
                data: { slug },
            }).then((flow) => {
                onEdit(flow.id);
            });
        }

        function deleteFlow(id) {
            if (!confirm('Delete this flow? This cannot be undone.')) return;
            api('/flows/' + id, { method: 'DELETE' }).then(() => {
                setFlows(flows.filter(f => f.id !== id));
            });
        }

        if (loading) return h('p', null, 'Loading flows...');

        return h(Fragment, null,
            h('div', { className: 'ams-flow-actions', style: { marginBottom: '20px' } },
                h('button', { className: 'button button-primary', onClick: onNew }, '+ New Flow'),
            ),

            flows.length > 0 && h('table', { className: 'wp-list-table widefat fixed striped', style: { marginBottom: '30px' } },
                h('thead', null,
                    h('tr', null,
                        h('th', null, 'Name'),
                        h('th', null, 'Trigger'),
                        h('th', null, 'Steps'),
                        h('th', null, 'Status'),
                        h('th', null, 'Actions'),
                    ),
                ),
                h('tbody', null,
                    flows.map(flow =>
                        h('tr', { key: flow.id },
                            h('td', null, h('strong', null, h('a', {
                                href: '#',
                                onClick: (e) => { e.preventDefault(); onEdit(flow.id); }
                            }, flow.name))),
                            h('td', null, (TRIGGER_TYPES.find(t => t.value === flow.trigger_type) || {}).label || flow.trigger_type),
                            h('td', null, flow.step_count || 0),
                            h('td', null, h('span', {
                                className: 'ams-status-badge ams-status-' + flow.status,
                                style: {
                                    padding: '3px 8px',
                                    borderRadius: '3px',
                                    fontSize: '12px',
                                    backgroundColor: flow.status === 'active' ? '#dff0d8' : flow.status === 'paused' ? '#fcf8e3' : '#f5f5f5',
                                    color: flow.status === 'active' ? '#3c763d' : flow.status === 'paused' ? '#8a6d3b' : '#666',
                                },
                            }, flow.status.charAt(0).toUpperCase() + flow.status.slice(1))),
                            h('td', null,
                                h('button', { className: 'button button-small', onClick: () => onEdit(flow.id), style: { marginRight: '5px' } }, 'Edit'),
                                h('button', { className: 'button button-small button-link-delete', onClick: () => deleteFlow(flow.id) }, 'Delete'),
                            ),
                        ),
                    ),
                ),
            ),

            flows.length === 0 && h('p', { style: { color: '#666', fontStyle: 'italic', marginBottom: '20px' } }, 'No flows created yet. Start from a template or create a new flow.'),

            templates.length > 0 && h(Fragment, null,
                h('h3', null, 'Flow Templates'),
                h('div', { className: 'ams-templates', style: { display: 'flex', gap: '15px', flexWrap: 'wrap' } },
                    templates.map(tpl =>
                        h('div', {
                            key: tpl.slug,
                            style: {
                                background: '#fff', border: '1px solid #ccd0d4', borderRadius: '4px',
                                padding: '15px', width: '250px',
                            },
                        },
                            h('strong', null, tpl.name),
                            h('p', { style: { fontSize: '13px', color: '#666', margin: '8px 0' } }, tpl.description),
                            h('p', { style: { fontSize: '12px', color: '#999' } }, tpl.step_count + ' steps'),
                            h('button', { className: 'button button-small', onClick: () => importTemplate(tpl.slug) }, 'Import'),
                        ),
                    ),
                ),
            ),
        );
    }

    /* ── FlowEditor Component ─────────────────────────────────────── */
    function FlowEditor({ flowId, onBack }) {
        const [flow, setFlow] = useState(null);
        const [steps, setSteps] = useState([]);
        const [saving, setSaving] = useState(false);
        const [editingStep, setEditingStep] = useState(null);
        const [dirty, setDirty] = useState(false);
        const dragItem = useRef(null);
        const dragOverItem = useRef(null);

        useEffect(() => {
            if (flowId === 'new') {
                setFlow({ name: '', trigger_type: 'welcome_series', trigger_config: {}, status: 'draft' });
                setSteps([]);
            } else {
                api('/flows/' + flowId).then(data => {
                    setFlow(data);
                    setSteps(data.steps || []);
                });
            }
        }, [flowId]);

        function updateFlow(key, value) {
            setFlow({ ...flow, [key]: value });
            setDirty(true);
        }

        function addStep(type) {
            const newStep = {
                step_type: type,
                step_order: steps.length,
                delay_value: type === 'wait' ? 1 : 0,
                delay_unit: type === 'wait' ? 'days' : 'minutes',
                subject: '',
                preview_text: '',
                body_html: '',
                body_text: '',
                sms_body: '',
                conditions: type === 'condition' ? { rules: [], yes_step_id: null, no_step_id: null } : [],
            };
            setSteps([...steps, newStep]);
            setDirty(true);
        }

        function removeStep(index) {
            const updated = steps.filter((_, i) => i !== index).map((s, i) => ({ ...s, step_order: i }));
            setSteps(updated);
            setDirty(true);
            if (editingStep === index) setEditingStep(null);
        }

        function updateStep(index, key, value) {
            const updated = [...steps];
            updated[index] = { ...updated[index], [key]: value };
            setSteps(updated);
            setDirty(true);
        }

        function handleDragStart(index) {
            dragItem.current = index;
        }

        function handleDragEnter(index) {
            dragOverItem.current = index;
        }

        function handleDragEnd() {
            const updated = [...steps];
            const draggedItem = updated.splice(dragItem.current, 1)[0];
            updated.splice(dragOverItem.current, 0, draggedItem);
            dragItem.current = null;
            dragOverItem.current = null;
            setSteps(updated.map((s, i) => ({ ...s, step_order: i })));
            setDirty(true);
        }

        function save() {
            setSaving(true);
            const payload = { ...flow, steps };

            const promise = flowId === 'new'
                ? api('/flows', { method: 'POST', data: payload })
                : api('/flows/' + flowId, { method: 'PUT', data: payload });

            promise.then(() => {
                setSaving(false);
                setDirty(false);
                if (flowId === 'new') onBack();
            }).catch(() => {
                setSaving(false);
                alert('Error saving flow. Please try again.');
            });
        }

        if (!flow) return h('p', null, 'Loading...');

        return h(Fragment, null,
            h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '15px' } },
                h('button', { className: 'button', onClick: onBack }, '← Back to Flows'),
                h('div', null,
                    dirty && h('span', { style: { color: '#d63638', marginRight: '10px', fontSize: '13px' } }, 'Unsaved changes'),
                    h('button', {
                        className: 'button button-primary',
                        onClick: save,
                        disabled: saving,
                    }, saving ? 'Saving...' : 'Save Flow'),
                ),
            ),

            /* Flow settings */
            h('div', { style: { background: '#fff', border: '1px solid #ccd0d4', borderRadius: '4px', padding: '20px', marginBottom: '20px' } },
                h('div', { style: { display: 'flex', gap: '15px', flexWrap: 'wrap' } },
                    h('div', { style: { flex: '1', minWidth: '200px' } },
                        h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '5px' } }, 'Flow Name'),
                        h('input', {
                            type: 'text', className: 'regular-text', style: { width: '100%' },
                            value: flow.name || '', onChange: e => updateFlow('name', e.target.value),
                        }),
                    ),
                    h('div', { style: { minWidth: '180px' } },
                        h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '5px' } }, 'Trigger'),
                        h('select', {
                            value: flow.trigger_type || '', onChange: e => updateFlow('trigger_type', e.target.value),
                        },
                            TRIGGER_TYPES.map(t => h('option', { key: t.value, value: t.value }, t.label)),
                        ),
                    ),
                    h('div', { style: { minWidth: '120px' } },
                        h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '5px' } }, 'Status'),
                        h('select', {
                            value: flow.status || 'draft', onChange: e => updateFlow('status', e.target.value),
                        },
                            STATUS_OPTIONS.map(s => h('option', { key: s.value, value: s.value }, s.label)),
                        ),
                    ),
                ),

                /* Trigger-specific config */
                flow.trigger_type === 'win_back' && h('div', { style: { marginTop: '10px' } },
                    h('label', { style: { fontWeight: '600', marginRight: '8px' } }, 'Days since last order:'),
                    h('input', {
                        type: 'number', min: '1', style: { width: '80px' },
                        value: (flow.trigger_config || {}).days_since_last_order || 90,
                        onChange: e => updateFlow('trigger_config', { ...flow.trigger_config, days_since_last_order: parseInt(e.target.value) || 90 }),
                    }),
                ),
                flow.trigger_type === 'custom_event' && h('div', { style: { marginTop: '10px' } },
                    h('label', { style: { fontWeight: '600', marginRight: '8px' } }, 'Event type:'),
                    h('input', {
                        type: 'text', className: 'regular-text',
                        value: (flow.trigger_config || {}).event_type || '',
                        placeholder: 'e.g. custom_signup',
                        onChange: e => updateFlow('trigger_config', { ...flow.trigger_config, event_type: e.target.value }),
                    }),
                ),
                flow.trigger_type === 'rfm_change' && h('div', { style: { marginTop: '10px', display: 'flex', gap: '15px' } },
                    h('div', null,
                        h('label', { style: { fontWeight: '600', marginRight: '8px' } }, 'From segment:'),
                        h('input', {
                            type: 'text', placeholder: 'Any',
                            value: (flow.trigger_config || {}).from_segment || '',
                            onChange: e => updateFlow('trigger_config', { ...flow.trigger_config, from_segment: e.target.value }),
                        }),
                    ),
                    h('div', null,
                        h('label', { style: { fontWeight: '600', marginRight: '8px' } }, 'To segment:'),
                        h('input', {
                            type: 'text', placeholder: 'Any',
                            value: (flow.trigger_config || {}).to_segment || '',
                            onChange: e => updateFlow('trigger_config', { ...flow.trigger_config, to_segment: e.target.value }),
                        }),
                    ),
                ),
            ),

            /* Flow canvas (step list) */
            h('div', { style: { marginBottom: '20px' } },
                h('h3', null, 'Flow Steps'),

                /* Trigger node */
                h('div', { style: { textAlign: 'center', marginBottom: '10px' } },
                    h('div', { style: {
                        display: 'inline-block', background: '#0073aa', color: '#fff',
                        padding: '8px 20px', borderRadius: '20px', fontWeight: '600', fontSize: '13px',
                    } }, 'TRIGGER: ' + ((TRIGGER_TYPES.find(t => t.value === flow.trigger_type) || {}).label || flow.trigger_type)),
                    h('div', { style: { width: '2px', height: '20px', background: '#ccc', margin: '0 auto' } }),
                ),

                /* Steps */
                steps.map((step, index) => {
                    const typeInfo = STEP_TYPES.find(t => t.value === step.step_type) || { label: step.step_type, icon: '?' };
                    return h(Fragment, { key: index },
                        h('div', {
                            draggable: true,
                            onDragStart: () => handleDragStart(index),
                            onDragEnter: () => handleDragEnter(index),
                            onDragEnd: handleDragEnd,
                            onDragOver: e => e.preventDefault(),
                            style: {
                                background: '#fff', border: '1px solid #ccd0d4', borderRadius: '4px',
                                padding: '12px 15px', marginBottom: '0', cursor: 'grab',
                                borderLeft: editingStep === index ? '3px solid #0073aa' : '1px solid #ccd0d4',
                            },
                        },
                            h('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                                h('div', { style: { display: 'flex', alignItems: 'center', gap: '10px' } },
                                    h('span', { style: { fontSize: '18px' } }, typeInfo.icon),
                                    h('span', { style: { fontWeight: '600' } }, typeInfo.label),
                                    step.step_type === 'wait' && h('span', { style: { color: '#666', fontSize: '13px' } },
                                        ' — ' + (step.delay_value || 0) + ' ' + (step.delay_unit || 'minutes'),
                                    ),
                                    step.step_type === 'send_email' && step.subject && h('span', { style: { color: '#666', fontSize: '13px' } },
                                        ' — ' + step.subject,
                                    ),
                                    step.step_type === 'send_sms' && step.sms_body && h('span', { style: { color: '#666', fontSize: '13px', maxWidth: '300px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', display: 'inline-block' } },
                                        ' — ' + step.sms_body.substring(0, 50) + (step.sms_body.length > 50 ? '...' : ''),
                                    ),
                                ),
                                h('div', { style: { display: 'flex', gap: '5px' } },
                                    h('button', {
                                        className: 'button button-small',
                                        onClick: () => setEditingStep(editingStep === index ? null : index),
                                    }, editingStep === index ? 'Close' : 'Edit'),
                                    h('button', {
                                        className: 'button button-small button-link-delete',
                                        onClick: () => removeStep(index),
                                    }, 'Remove'),
                                ),
                            ),

                            /* Slide-in editor panel */
                            editingStep === index && h('div', { style: { marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' } },
                                h(StepEditor, { step, index, onChange: updateStep }),
                            ),
                        ),

                        /* Connector line */
                        index < steps.length - 1 && h('div', { style: { textAlign: 'center' } },
                            h('div', { style: { width: '2px', height: '20px', background: '#ccc', margin: '0 auto' } }),
                        ),
                    );
                }),
            ),

            /* Add step buttons */
            h('div', { style: { background: '#f9f9f9', border: '1px dashed #ccc', borderRadius: '4px', padding: '15px', textAlign: 'center' } },
                h('p', { style: { margin: '0 0 10px', color: '#666', fontSize: '13px' } }, 'Add a step:'),
                h('div', { style: { display: 'flex', gap: '8px', justifyContent: 'center', flexWrap: 'wrap' } },
                    STEP_TYPES.map(type =>
                        h('button', {
                            key: type.value,
                            className: 'button button-small',
                            onClick: () => addStep(type.value),
                        }, type.icon + ' ' + type.label),
                    ),
                ),
            ),
        );
    }

    /* ── StepEditor Component ─────────────────────────────────────── */
    function StepEditor({ step, index, onChange }) {
        const type = step.step_type;

        if (type === 'send_email') {
            return h(Fragment, null,
                h('div', { style: { marginBottom: '10px' } },
                    h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '3px' } }, 'Subject Line'),
                    h('input', {
                        type: 'text', className: 'large-text',
                        value: step.subject || '', onChange: e => onChange(index, 'subject', e.target.value),
                    }),
                ),
                h('div', { style: { marginBottom: '10px' } },
                    h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '3px' } }, 'Preview Text'),
                    h('input', {
                        type: 'text', className: 'large-text',
                        value: step.preview_text || '', onChange: e => onChange(index, 'preview_text', e.target.value),
                    }),
                ),
                h('div', { style: { marginBottom: '10px' } },
                    h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '3px' } }, 'Email Body (HTML)'),
                    h('textarea', {
                        rows: 8, className: 'large-text',
                        value: step.body_html || '', onChange: e => onChange(index, 'body_html', e.target.value),
                    }),
                ),
                h('div', null,
                    h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '3px' } }, 'Plain Text Version'),
                    h('textarea', {
                        rows: 4, className: 'large-text',
                        value: step.body_text || '', onChange: e => onChange(index, 'body_text', e.target.value),
                    }),
                ),
                h('p', { style: { fontSize: '12px', color: '#999', marginTop: '8px' } },
                    'Tokens: {{first_name}}, {{last_name}}, {{email}}, {{full_name}}, {{site_name}}, {{site_url}}, {{unsubscribe_url}}, {{total_orders}}, {{total_spent}}, {{rfm_segment}}'),
            );
        }

        if (type === 'send_sms') {
            return h(Fragment, null,
                h('div', null,
                    h('label', { style: { display: 'block', fontWeight: '600', marginBottom: '3px' } }, 'SMS Body'),
                    h('textarea', {
                        rows: 4, className: 'large-text',
                        value: step.sms_body || '', onChange: e => onChange(index, 'sms_body', e.target.value),
                    }),
                ),
                h('p', { style: { fontSize: '12px', color: '#999', marginTop: '5px' } },
                    '"Reply STOP to unsubscribe" will be appended automatically. Tokens: {{first_name}}, {{last_name}}, {{email}}, {{site_name}}'),
            );
        }

        if (type === 'wait') {
            return h('div', { style: { display: 'flex', gap: '10px', alignItems: 'center' } },
                h('label', { style: { fontWeight: '600' } }, 'Wait for:'),
                h('input', {
                    type: 'number', min: '1', style: { width: '80px' },
                    value: step.delay_value || 1, onChange: e => onChange(index, 'delay_value', parseInt(e.target.value) || 1),
                }),
                h('select', {
                    value: step.delay_unit || 'days',
                    onChange: e => onChange(index, 'delay_unit', e.target.value),
                },
                    h('option', { value: 'minutes' }, 'Minutes'),
                    h('option', { value: 'hours' }, 'Hours'),
                    h('option', { value: 'days' }, 'Days'),
                    h('option', { value: 'weeks' }, 'Weeks'),
                ),
            );
        }

        if (type === 'condition') {
            const conditions = typeof step.conditions === 'string'
                ? JSON.parse(step.conditions || '{}')
                : (step.conditions || {});
            const rules = conditions.rules || [];

            function updateConditions(newCond) {
                onChange(index, 'conditions', newCond);
            }

            function addRule() {
                updateConditions({
                    ...conditions,
                    rules: [...rules, { field: 'total_orders', operator: 'greater_than', value: '' }],
                });
            }

            function updateRule(ri, key, val) {
                const updated = [...rules];
                updated[ri] = { ...updated[ri], [key]: val };
                updateConditions({ ...conditions, rules: updated });
            }

            function removeRule(ri) {
                updateConditions({ ...conditions, rules: rules.filter((_, i) => i !== ri) });
            }

            return h(Fragment, null,
                h('p', { style: { fontWeight: '600', margin: '0 0 8px' } }, 'If ALL conditions match:'),
                rules.map((rule, ri) =>
                    h('div', { key: ri, style: { display: 'flex', gap: '8px', marginBottom: '5px', alignItems: 'center' } },
                        h('select', { value: rule.field, onChange: e => updateRule(ri, 'field', e.target.value) },
                            h('option', { value: 'total_orders' }, 'Total Orders'),
                            h('option', { value: 'total_spent' }, 'Total Spent'),
                            h('option', { value: 'status' }, 'Status'),
                            h('option', { value: 'rfm_segment' }, 'RFM Segment'),
                            h('option', { value: 'has_tag' }, 'Has Tag'),
                            h('option', { value: 'source' }, 'Source'),
                            h('option', { value: 'has_opened_any' }, 'Has Opened Any Email'),
                            h('option', { value: 'has_clicked_any' }, 'Has Clicked Any Email'),
                        ),
                        h('select', { value: rule.operator, onChange: e => updateRule(ri, 'operator', e.target.value) },
                            h('option', { value: 'equals' }, 'equals'),
                            h('option', { value: 'not_equals' }, 'not equals'),
                            h('option', { value: 'greater_than' }, 'greater than'),
                            h('option', { value: 'less_than' }, 'less than'),
                            h('option', { value: 'contains' }, 'contains'),
                            h('option', { value: 'is_true' }, 'is true'),
                            h('option', { value: 'is_false' }, 'is false'),
                        ),
                        h('input', {
                            type: 'text', style: { width: '120px' },
                            value: rule.value || '', onChange: e => updateRule(ri, 'value', e.target.value),
                        }),
                        h('button', { className: 'button button-small button-link-delete', onClick: () => removeRule(ri) }, 'x'),
                    ),
                ),
                h('button', { className: 'button button-small', onClick: addRule, style: { marginTop: '5px' } }, '+ Add Condition'),
            );
        }

        if (type === 'add_tag' || type === 'remove_tag') {
            const conditions = typeof step.conditions === 'string'
                ? JSON.parse(step.conditions || '{}')
                : (step.conditions || {});
            return h('div', { style: { display: 'flex', gap: '10px', alignItems: 'center' } },
                h('label', { style: { fontWeight: '600' } }, 'Tag:'),
                h('input', {
                    type: 'text', className: 'regular-text',
                    value: conditions.tag || '',
                    onChange: e => onChange(index, 'conditions', { ...conditions, tag: e.target.value }),
                }),
            );
        }

        if (type === 'update_field') {
            const conditions = typeof step.conditions === 'string'
                ? JSON.parse(step.conditions || '{}')
                : (step.conditions || {});
            return h('div', { style: { display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' } },
                h('div', null,
                    h('label', { style: { fontWeight: '600', marginRight: '5px' } }, 'Field:'),
                    h('input', {
                        type: 'text', value: conditions.field_name || '',
                        onChange: e => onChange(index, 'conditions', { ...conditions, field_name: e.target.value }),
                    }),
                ),
                h('div', null,
                    h('label', { style: { fontWeight: '600', marginRight: '5px' } }, 'Value:'),
                    h('input', {
                        type: 'text', value: conditions.field_value || '',
                        onChange: e => onChange(index, 'conditions', { ...conditions, field_value: e.target.value }),
                    }),
                ),
            );
        }

        if (type === 'exit') {
            return h('p', { style: { color: '#666', fontStyle: 'italic' } }, 'Subscriber will be removed from this flow at this point.');
        }

        return null;
    }

    /* ── App Component ────────────────────────────────────────────── */
    function App() {
        const [view, setView] = useState('list');
        const [editId, setEditId] = useState(null);

        function onEdit(id) {
            setEditId(id);
            setView('editor');
        }

        function onNew() {
            setEditId('new');
            setView('editor');
        }

        function onBack() {
            setView('list');
            setEditId(null);
        }

        if (view === 'editor') {
            return h(FlowEditor, { flowId: editId, onBack });
        }

        return h(FlowList, { onEdit, onNew });
    }

    /* ── Mount ────────────────────────────────────────────────────── */
    const container = document.getElementById('ams-admin-flows');
    if (container) {
        render(h(App), container);
    }
})();
