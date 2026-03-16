/**
 * Segment Builder — React UI for creating and managing segments.
 *
 * Uses wp.element (React) and wp.apiFetch for REST API communication.
 *
 * @package Apotheca\Marketing
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback, Fragment } = wp.element;
    const apiFetch = wp.apiFetch;

    const API_BASE = '/ams/v1';

    function api(path, opts = {}) {
        return apiFetch({ path: API_BASE + path, ...opts });
    }

    /* ── Utility ── */

    function generateId() {
        return 'r_' + Math.random().toString(36).substr(2, 9);
    }

    function formatOperator(op) {
        return op.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    /* ── App Router ── */

    function App() {
        const [view, setView] = useState('list');
        const [editId, setEditId] = useState(null);

        function goToList() { setView('list'); setEditId(null); }
        function goToEdit(id) { setEditId(id); setView('edit'); }
        function goToCreate() { setEditId(null); setView('edit'); }

        if (view === 'edit') {
            return h(SegmentEditor, { segmentId: editId, onBack: goToList });
        }
        return h(SegmentList, { onEdit: goToEdit, onCreate: goToCreate });
    }

    /* ── Segment List ── */

    function SegmentList({ onEdit, onCreate }) {
        const [segments, setSegments] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            api('/segments').then(data => {
                setSegments(data);
                setLoading(false);
            });
        }, []);

        function handleDelete(id, name) {
            if (!confirm('Delete segment "' + name + '"? This cannot be undone.')) return;
            api('/segments/' + id, { method: 'DELETE' }).then(() => {
                setSegments(prev => prev.filter(s => s.id !== id));
            });
        }

        return h('div', { className: 'ams-segment-list' },
            h('div', { className: 'ams-segment-header' },
                h('h2', null, 'Segments'),
                h('button', { className: 'button button-primary', onClick: onCreate }, '+ New Segment')
            ),
            loading
                ? h('p', null, 'Loading segments…')
                : segments.length === 0
                    ? h('p', { className: 'ams-empty' }, 'No segments created yet. Click "+ New Segment" to get started.')
                    : h('table', { className: 'widefat fixed striped' },
                        h('thead', null,
                            h('tr', null,
                                h('th', null, 'Name'),
                                h('th', { style: { width: '120px' } }, 'Subscribers'),
                                h('th', { style: { width: '180px' } }, 'Last Calculated'),
                                h('th', { style: { width: '140px' } }, 'Actions')
                            )
                        ),
                        h('tbody', null,
                            segments.map(seg =>
                                h('tr', { key: seg.id },
                                    h('td', null,
                                        h('a', { href: '#', onClick: e => { e.preventDefault(); onEdit(seg.id); } },
                                            h('strong', null, seg.name)
                                        )
                                    ),
                                    h('td', null, Number(seg.subscriber_count || 0).toLocaleString()),
                                    h('td', null, seg.last_calculated || '—'),
                                    h('td', null,
                                        h('button', { className: 'button button-small', onClick: () => onEdit(seg.id) }, 'Edit'),
                                        ' ',
                                        h('button', {
                                            className: 'button button-small button-link-delete',
                                            onClick: () => handleDelete(seg.id, seg.name)
                                        }, 'Delete')
                                    )
                                )
                            )
                        )
                    )
        );
    }

    /* ── Segment Editor ── */

    function SegmentEditor({ segmentId, onBack }) {
        const [name, setName] = useState('');
        const [conditions, setConditions] = useState({ logic: 'AND', rules: [] });
        const [conditionTypes, setConditionTypes] = useState([]);
        const [saving, setSaving] = useState(false);
        const [previewCount, setPreviewCount] = useState(null);
        const [previewing, setPreviewing] = useState(false);
        const [loading, setLoading] = useState(!!segmentId);

        useEffect(() => {
            api('/segments/condition-types').then(setConditionTypes);

            if (segmentId) {
                api('/segments/' + segmentId).then(seg => {
                    setName(seg.name || '');
                    const parsed = typeof seg.conditions === 'string'
                        ? JSON.parse(seg.conditions || '{}')
                        : (seg.conditions || {});
                    if (parsed.logic && parsed.rules) {
                        setConditions(parsed);
                    }
                    setLoading(false);
                });
            }
        }, [segmentId]);

        function handleSave() {
            if (!name.trim()) { alert('Please enter a segment name.'); return; }
            setSaving(true);

            const payload = { name: name.trim(), conditions };
            const request = segmentId
                ? api('/segments/' + segmentId, { method: 'PUT', data: payload })
                : api('/segments', { method: 'POST', data: payload });

            request.then(() => { setSaving(false); onBack(); })
                .catch(err => { setSaving(false); alert('Error saving segment: ' + (err.message || 'Unknown error')); });
        }

        function handlePreview() {
            setPreviewing(true);
            api('/segments/preview', { method: 'POST', data: { conditions } })
                .then(res => { setPreviewCount(res.count); setPreviewing(false); })
                .catch(() => { setPreviewing(false); });
        }

        if (loading) return h('p', null, 'Loading segment…');

        const flatTypes = [];
        conditionTypes.forEach(group => {
            (group.conditions || []).forEach(c => flatTypes.push(c));
        });

        return h('div', { className: 'ams-segment-editor' },
            h('div', { className: 'ams-segment-header' },
                h('button', { className: 'button', onClick: onBack }, '← Back to Segments'),
                h('h2', null, segmentId ? 'Edit Segment' : 'New Segment')
            ),
            h('div', { className: 'ams-segment-form' },
                h('div', { className: 'ams-field' },
                    h('label', null, 'Segment Name'),
                    h('input', {
                        type: 'text',
                        className: 'regular-text',
                        value: name,
                        onChange: e => setName(e.target.value),
                        placeholder: 'e.g., High-Value Customers'
                    })
                ),
                h('div', { className: 'ams-conditions-section' },
                    h('h3', null, 'Conditions'),
                    h('p', { className: 'description' }, 'Define which subscribers belong to this segment.'),
                    h(ConditionGroup, {
                        group: conditions,
                        onChange: setConditions,
                        conditionTypes: conditionTypes,
                        flatTypes: flatTypes,
                        depth: 0
                    })
                ),
                h('div', { className: 'ams-segment-actions' },
                    h('button', {
                        className: 'button button-secondary',
                        onClick: handlePreview,
                        disabled: previewing
                    }, previewing ? 'Counting…' : 'Preview Count'),
                    previewCount !== null && h('span', { className: 'ams-preview-count' },
                        ' ', previewCount.toLocaleString(), ' subscriber', previewCount !== 1 ? 's' : '', ' match'
                    ),
                    h('span', { style: { flex: 1 } }),
                    h('button', { className: 'button', onClick: onBack }, 'Cancel'),
                    ' ',
                    h('button', {
                        className: 'button button-primary',
                        onClick: handleSave,
                        disabled: saving
                    }, saving ? 'Saving…' : 'Save Segment')
                )
            )
        );
    }

    /* ── Condition Group (recursive) ── */

    function ConditionGroup({ group, onChange, conditionTypes, flatTypes, depth }) {
        const logic = group.logic || 'AND';
        const rules = group.rules || [];

        function setLogic(newLogic) {
            onChange({ ...group, logic: newLogic });
        }

        function updateRule(index, updated) {
            const newRules = [...rules];
            newRules[index] = updated;
            onChange({ ...group, rules: newRules });
        }

        function removeRule(index) {
            const newRules = rules.filter((_, i) => i !== index);
            onChange({ ...group, rules: newRules });
        }

        function addRule() {
            const newRule = { id: generateId(), type: '', operator: '', value: '' };
            onChange({ ...group, rules: [...rules, newRule] });
        }

        function addGroup() {
            if (depth >= 2) { alert('Maximum nesting depth (3 levels) reached.'); return; }
            const newGroup = { id: generateId(), logic: 'OR', rules: [] };
            onChange({ ...group, rules: [...rules, newGroup] });
        }

        return h('div', { className: 'ams-condition-group ams-depth-' + depth },
            rules.length > 0 && h('div', { className: 'ams-logic-toggle' },
                h('button', {
                    className: 'button button-small' + (logic === 'AND' ? ' button-primary' : ''),
                    onClick: () => setLogic('AND')
                }, 'AND'),
                h('button', {
                    className: 'button button-small' + (logic === 'OR' ? ' button-primary' : ''),
                    onClick: () => setLogic('OR')
                }, 'OR')
            ),
            rules.map((rule, index) =>
                h(Fragment, { key: rule.id || index },
                    index > 0 && h('div', { className: 'ams-logic-label' }, logic),
                    rule.logic !== undefined
                        ? h('div', { className: 'ams-nested-group' },
                            h('button', {
                                className: 'ams-remove-btn',
                                onClick: () => removeRule(index),
                                title: 'Remove group'
                            }, '×'),
                            h(ConditionGroup, {
                                group: rule,
                                onChange: updated => updateRule(index, updated),
                                conditionTypes,
                                flatTypes,
                                depth: depth + 1
                            })
                        )
                        : h(ConditionRow, {
                            rule,
                            onChange: updated => updateRule(index, updated),
                            onRemove: () => removeRule(index),
                            conditionTypes,
                            flatTypes
                        })
                )
            ),
            h('div', { className: 'ams-add-buttons' },
                h('button', { className: 'button button-small', onClick: addRule }, '+ Add Condition'),
                depth < 2 && h('button', { className: 'button button-small', onClick: addGroup }, '+ Add Group')
            )
        );
    }

    /* ── Condition Row ── */

    function ConditionRow({ rule, onChange, onRemove, conditionTypes, flatTypes }) {
        const typeDef = flatTypes.find(t => t.type === rule.type);
        const operators = typeDef ? typeDef.operators : [];
        const valueType = typeDef ? typeDef.value_type : 'text';
        const options = typeDef ? (typeDef.options || []) : [];
        const hasExtraFields = typeDef && typeDef.extra_fields;
        const needsValue = valueType !== 'none' && rule.operator && !['is_blank', 'is_not_blank', 'is_true', 'is_false', 'ever', 'never'].includes(rule.operator);
        const needsValue2 = rule.operator === 'between';

        function update(field, val) {
            const updated = { ...rule, [field]: val };
            if (field === 'type') {
                updated.operator = '';
                updated.value = '';
                updated.value2 = '';
                updated.field_name = '';
            }
            onChange(updated);
        }

        return h('div', { className: 'ams-condition-row' },
            h('select', {
                className: 'ams-condition-type',
                value: rule.type || '',
                onChange: e => update('type', e.target.value)
            },
                h('option', { value: '' }, '— Select condition —'),
                conditionTypes.map(group =>
                    h('optgroup', { key: group.group, label: group.group },
                        group.conditions.map(c =>
                            h('option', { key: c.type, value: c.type }, c.label)
                        )
                    )
                )
            ),
            hasExtraFields && rule.type === 'custom_field' && h('input', {
                type: 'text',
                className: 'ams-field-name',
                placeholder: 'Field name',
                value: rule.field_name || '',
                onChange: e => update('field_name', e.target.value)
            }),
            rule.type && h('select', {
                className: 'ams-condition-operator',
                value: rule.operator || '',
                onChange: e => update('operator', e.target.value)
            },
                h('option', { value: '' }, '— Operator —'),
                operators.map(op =>
                    h('option', { key: op, value: op }, formatOperator(op))
                )
            ),
            needsValue && (
                valueType === 'select'
                    ? h('select', {
                        className: 'ams-condition-value',
                        value: rule.value || '',
                        onChange: e => update('value', e.target.value)
                    },
                        h('option', { value: '' }, '— Select —'),
                        options.map(opt =>
                            h('option', { key: opt, value: opt }, opt)
                        )
                    )
                    : h('input', {
                        type: valueType === 'number' ? 'number' : (valueType === 'date' ? 'date' : 'text'),
                        className: 'ams-condition-value',
                        placeholder: valueType === 'number' ? '0' : (valueType === 'date' ? 'YYYY-MM-DD' : 'Value'),
                        value: rule.value || '',
                        onChange: e => update('value', e.target.value)
                    })
            ),
            needsValue2 && h(Fragment, null,
                h('span', { className: 'ams-between-label' }, 'and'),
                h('input', {
                    type: 'number',
                    className: 'ams-condition-value',
                    placeholder: '0',
                    value: rule.value2 || '',
                    onChange: e => update('value2', e.target.value)
                })
            ),
            h('button', {
                className: 'ams-remove-btn',
                onClick: onRemove,
                title: 'Remove condition'
            }, '×')
        );
    }

    /* ── Styles ── */

    function injectStyles() {
        const css = `
            .ams-segment-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
            .ams-segment-header h2 { margin: 0; }
            .ams-segment-form { background: #fff; border: 1px solid #c3c4c7; padding: 20px; }
            .ams-field { margin-bottom: 16px; }
            .ams-field label { display: block; font-weight: 600; margin-bottom: 4px; }
            .ams-conditions-section { margin-top: 20px; }
            .ams-conditions-section h3 { margin-bottom: 4px; }
            .ams-condition-group { border: 1px solid #ddd; border-radius: 4px; padding: 12px; background: #f9f9f9; margin-top: 8px; }
            .ams-depth-1 { background: #f0f0f1; }
            .ams-depth-2 { background: #e8e8ea; }
            .ams-logic-toggle { margin-bottom: 8px; }
            .ams-logic-toggle .button { margin-right: 4px; }
            .ams-logic-label { text-align: center; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #666; margin: 6px 0; }
            .ams-condition-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
            .ams-condition-row select, .ams-condition-row input { min-width: 120px; }
            .ams-condition-type { min-width: 180px !important; }
            .ams-condition-operator { min-width: 150px !important; }
            .ams-condition-value { min-width: 140px !important; }
            .ams-field-name { min-width: 120px !important; }
            .ams-between-label { font-size: 12px; color: #666; }
            .ams-remove-btn { background: none; border: none; color: #b32d2e; font-size: 18px; cursor: pointer; padding: 2px 6px; line-height: 1; }
            .ams-remove-btn:hover { color: #a00; }
            .ams-nested-group { position: relative; }
            .ams-nested-group > .ams-remove-btn { position: absolute; top: 4px; right: 4px; z-index: 1; }
            .ams-add-buttons { margin-top: 8px; }
            .ams-add-buttons .button { margin-right: 8px; }
            .ams-segment-actions { display: flex; align-items: center; gap: 8px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #ddd; }
            .ams-preview-count { font-weight: 600; color: #007017; }
            .ams-empty { color: #666; font-style: italic; }
        `;
        const style = document.createElement('style');
        style.textContent = css;
        document.head.appendChild(style);
    }

    /* ── Mount ── */

    function init() {
        const root = document.getElementById('ams-admin-segments');
        if (!root) return;
        injectStyles();
        wp.element.render(h(App), root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
