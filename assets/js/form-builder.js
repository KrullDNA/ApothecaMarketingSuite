/**
 * Form Builder — React admin UI for creating and managing opt-in forms.
 *
 * Multi-step wizard: Fields > Design > Targeting > Success
 * Live preview panel with desktop/mobile toggle.
 *
 * @package Apotheca\Marketing
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, Fragment } = wp.element;
    const apiFetch = wp.apiFetch;

    const API = '/ams/v1';
    function api(path, opts = {}) { return apiFetch({ path: API + path, ...opts }); }

    function uid() { return 'f_' + Math.random().toString(36).substr(2, 9); }

    const FORM_TYPES = [
        { value: 'modal', label: 'Modal Pop-up' },
        { value: 'flyout', label: 'Flyout / Slide-in' },
        { value: 'embedded', label: 'Embedded' },
        { value: 'full_page', label: 'Full Page' },
        { value: 'sticky_bar', label: 'Sticky Bar' },
        { value: 'spin_to_win', label: 'Spin-to-Win' },
    ];

    const FIELD_TYPES = [
        { value: 'email', label: 'Email Address' },
        { value: 'phone', label: 'Phone / Mobile' },
        { value: 'first_name', label: 'First Name' },
        { value: 'last_name', label: 'Last Name' },
        { value: 'birthday', label: 'Birthday' },
        { value: 'radio', label: 'Radio Buttons' },
        { value: 'checkbox', label: 'Checkboxes' },
        { value: 'dropdown', label: 'Dropdown' },
        { value: 'hidden', label: 'Hidden Field' },
    ];

    const DEFAULT_DESIGN = {
        background_color: '#ffffff',
        text_color: '#333333',
        button_color: '#4A90D9',
        button_text_color: '#ffffff',
        button_hover_color: '#3a7bc8',
        border_radius: 8,
        title: 'Subscribe to our newsletter',
        description: 'Get exclusive deals and updates delivered to your inbox.',
        button_text: 'Subscribe',
        consent_text: '',
        header_image: '',
        font_family: '',
        flyout_position: 'right',
        bar_position: 'bottom',
    };

    const DEFAULT_TARGETING = {
        pages: 'all',
        page_ids: [],
        exclude_page_ids: [],
        device: 'all',
        visitor_type: 'all',
        cart_value_min: '',
        utm_rules: [],
        segment_id: '',
        frequency_cap_days: 7,
    };

    const DEFAULT_TRIGGER = {
        scroll_depth: '',
        time_on_page: '',
        exit_intent: false,
    };

    const DEFAULT_SUCCESS = {
        action: 'message',
        message: 'Thank you for subscribing!',
        redirect_url: '',
        add_tags: '',
        enrol_flow_id: '',
        double_optin: false,
    };

    /* ── App ── */

    function App() {
        const [view, setView] = useState('list');
        const [editId, setEditId] = useState(null);
        return view === 'edit'
            ? h(FormEditor, { formId: editId, onBack: () => { setView('list'); setEditId(null); } })
            : h(FormList, { onEdit: id => { setEditId(id); setView('edit'); }, onCreate: () => { setEditId(null); setView('edit'); } });
    }

    /* ── Form List ── */

    function FormList({ onEdit, onCreate }) {
        const [forms, setForms] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => { api('/forms').then(d => { setForms(d); setLoading(false); }); }, []);

        function handleDelete(id, name) {
            if (!confirm('Delete "' + name + '"?')) return;
            api('/forms/' + id, { method: 'DELETE' }).then(() => setForms(f => f.filter(x => x.id !== id)));
        }

        function handleToggle(form) {
            const newStatus = form.status === 'active' ? 'draft' : 'active';
            api('/forms/' + form.id, { method: 'PUT', data: { status: newStatus } })
                .then(updated => setForms(f => f.map(x => x.id === form.id ? updated : x)));
        }

        const typeLabel = t => (FORM_TYPES.find(ft => ft.value === t) || {}).label || t;

        return h('div', { className: 'ams-form-list' },
            h('div', { className: 'ams-fl-header' },
                h('h2', null, 'Forms'),
                h('button', { className: 'button button-primary', onClick: onCreate }, '+ New Form')
            ),
            loading ? h('p', null, 'Loading…')
                : forms.length === 0 ? h('p', { className: 'ams-empty' }, 'No forms yet.')
                : h('table', { className: 'widefat fixed striped' },
                    h('thead', null, h('tr', null,
                        h('th', null, 'Name'),
                        h('th', { style: { width: '120px' } }, 'Type'),
                        h('th', { style: { width: '80px' } }, 'Status'),
                        h('th', { style: { width: '80px' } }, 'Views'),
                        h('th', { style: { width: '100px' } }, 'Submissions'),
                        h('th', { style: { width: '80px' } }, 'Rate'),
                        h('th', { style: { width: '200px' } }, 'Actions')
                    )),
                    h('tbody', null, forms.map(f =>
                        h('tr', { key: f.id },
                            h('td', null, h('a', { href: '#', onClick: e => { e.preventDefault(); onEdit(f.id); } }, h('strong', null, f.name))),
                            h('td', null, typeLabel(f.type)),
                            h('td', null, h('span', { className: 'ams-status-badge ams-status-' + f.status }, f.status)),
                            h('td', null, Number(f.views || 0).toLocaleString()),
                            h('td', null, Number(f.submissions || 0).toLocaleString()),
                            h('td', null, f.views > 0 ? ((f.submissions / f.views * 100).toFixed(1) + '%') : '—'),
                            h('td', null,
                                h('button', { className: 'button button-small', onClick: () => onEdit(f.id) }, 'Edit'), ' ',
                                h('button', { className: 'button button-small', onClick: () => handleToggle(f) }, f.status === 'active' ? 'Deactivate' : 'Activate'), ' ',
                                h('button', { className: 'button button-small button-link-delete', onClick: () => handleDelete(f.id, f.name) }, 'Delete')
                            )
                        )
                    ))
                )
        );
    }

    /* ── Form Editor (Multi-Step Wizard) ── */

    function FormEditor({ formId, onBack }) {
        const [step, setStep] = useState(0);
        const [name, setName] = useState('');
        const [type, setType] = useState('modal');
        const [fields, setFields] = useState([{ id: uid(), type: 'email', name: 'email', label: 'Email', placeholder: 'Email address', required: true }]);
        const [design, setDesign] = useState({ ...DEFAULT_DESIGN });
        const [targeting, setTargeting] = useState({ ...DEFAULT_TARGETING });
        const [trigger, setTrigger] = useState({ ...DEFAULT_TRIGGER });
        const [success, setSuccess] = useState({ ...DEFAULT_SUCCESS });
        const [spinConfig, setSpinConfig] = useState({ segments: [] });
        const [saving, setSaving] = useState(false);
        const [loading, setLoading] = useState(!!formId);
        const [previewMobile, setPreviewMobile] = useState(false);

        useEffect(() => {
            if (!formId) return;
            api('/forms/' + formId).then(f => {
                setName(f.name || '');
                setType(f.type || 'modal');
                setFields(JSON.parse(f.fields || '[]') || []);
                setDesign({ ...DEFAULT_DESIGN, ...(JSON.parse(f.design_config || '{}') || {}) });
                setTargeting({ ...DEFAULT_TARGETING, ...(JSON.parse(f.targeting_config || '{}') || {}) });
                setTrigger({ ...DEFAULT_TRIGGER, ...(JSON.parse(f.trigger_config || '{}') || {}) });
                setSuccess({ ...DEFAULT_SUCCESS, ...(JSON.parse(f.success_config || '{}') || {}) });
                setSpinConfig(JSON.parse(f.spin_config || '{}') || { segments: [] });
                setLoading(false);
            });
        }, [formId]);

        function save() {
            if (!name.trim()) { alert('Please enter a form name.'); return; }
            setSaving(true);
            const payload = {
                name: name.trim(), type, fields,
                design_config: design, targeting_config: targeting,
                trigger_config: trigger, success_config: success,
                spin_config: spinConfig,
            };
            const req = formId
                ? api('/forms/' + formId, { method: 'PUT', data: payload })
                : api('/forms', { method: 'POST', data: payload });
            req.then(() => { setSaving(false); onBack(); })
                .catch(e => { setSaving(false); alert('Error: ' + (e.message || 'Unknown')); });
        }

        if (loading) return h('p', null, 'Loading…');

        const steps = ['Fields', 'Design', 'Targeting', 'Success'];
        if (type === 'spin_to_win') steps.splice(1, 0, 'Spin Config');

        return h('div', { className: 'ams-fb' },
            h('div', { className: 'ams-fb-header' },
                h('button', { className: 'button', onClick: onBack }, '← Back'),
                h('h2', null, formId ? 'Edit Form' : 'New Form'),
                h('div', { style: { flex: 1 } }),
                h('button', { className: 'button button-primary', onClick: save, disabled: saving }, saving ? 'Saving…' : 'Save Form')
            ),
            h('div', { className: 'ams-fb-meta' },
                h('div', { className: 'ams-fb-field' },
                    h('label', null, 'Form Name'),
                    h('input', { type: 'text', className: 'regular-text', value: name, onChange: e => setName(e.target.value), placeholder: 'e.g., Homepage Pop-up' })
                ),
                h('div', { className: 'ams-fb-field' },
                    h('label', null, 'Form Type'),
                    h('select', { value: type, onChange: e => setType(e.target.value) },
                        FORM_TYPES.map(t => h('option', { key: t.value, value: t.value }, t.label))
                    )
                )
            ),
            h('div', { className: 'ams-fb-steps' },
                steps.map((s, i) =>
                    h('button', { key: s, className: 'ams-fb-step' + (i === step ? ' active' : ''), onClick: () => setStep(i) }, (i + 1) + '. ' + s)
                )
            ),
            h('div', { className: 'ams-fb-content' },
                h('div', { className: 'ams-fb-panel' },
                    steps[step] === 'Fields' && h(FieldsStep, { fields, setFields }),
                    steps[step] === 'Spin Config' && h(SpinConfigStep, { spinConfig, setSpinConfig }),
                    steps[step] === 'Design' && h(DesignStep, { design, setDesign, type }),
                    steps[step] === 'Targeting' && h(TargetingStep, { targeting, setTargeting, trigger, setTrigger }),
                    steps[step] === 'Success' && h(SuccessStep, { success, setSuccess })
                ),
                h('div', { className: 'ams-fb-preview' },
                    h('div', { className: 'ams-fb-preview-toggle' },
                        h('button', { className: 'button button-small' + (!previewMobile ? ' button-primary' : ''), onClick: () => setPreviewMobile(false) }, 'Desktop'),
                        h('button', { className: 'button button-small' + (previewMobile ? ' button-primary' : ''), onClick: () => setPreviewMobile(true) }, 'Mobile')
                    ),
                    h(LivePreview, { type, fields, design, previewMobile, spinConfig })
                )
            ),
            h('div', { className: 'ams-fb-nav' },
                step > 0 && h('button', { className: 'button', onClick: () => setStep(step - 1) }, '← Previous'),
                h('span', { style: { flex: 1 } }),
                step < steps.length - 1 && h('button', { className: 'button button-primary', onClick: () => setStep(step + 1) }, 'Next →')
            )
        );
    }

    /* ── Step 1: Fields ── */

    function FieldsStep({ fields, setFields }) {
        function addField() {
            setFields([...fields, { id: uid(), type: 'first_name', name: 'first_name', label: 'First Name', placeholder: '', required: false, options: [] }]);
        }
        function updateField(index, key, val) {
            const updated = [...fields];
            updated[index] = { ...updated[index], [key]: val };
            if (key === 'type') {
                updated[index].name = val;
                updated[index].label = (FIELD_TYPES.find(f => f.value === val) || {}).label || val;
            }
            setFields(updated);
        }
        function removeField(index) {
            if (fields[index].type === 'email') return;
            setFields(fields.filter((_, i) => i !== index));
        }
        function moveField(from, to) {
            if (to < 0 || to >= fields.length) return;
            const updated = [...fields];
            const item = updated.splice(from, 1)[0];
            updated.splice(to, 0, item);
            setFields(updated);
        }

        return h('div', null,
            h('h3', null, 'Form Fields'),
            h('p', { className: 'description' }, 'Configure the fields on your form. Email is always required.'),
            fields.map((f, i) =>
                h('div', { key: f.id || i, className: 'ams-fb-field-row' },
                    h('div', { className: 'ams-fb-field-controls' },
                        h('button', { className: 'button button-small', onClick: () => moveField(i, i - 1), disabled: i === 0 }, '↑'),
                        h('button', { className: 'button button-small', onClick: () => moveField(i, i + 1), disabled: i === fields.length - 1 }, '↓')
                    ),
                    h('select', { value: f.type, onChange: e => updateField(i, 'type', e.target.value), disabled: f.type === 'email' },
                        FIELD_TYPES.map(ft => h('option', { key: ft.value, value: ft.value }, ft.label))
                    ),
                    h('input', { type: 'text', placeholder: 'Label', value: f.label || '', onChange: e => updateField(i, 'label', e.target.value), style: { width: '120px' } }),
                    h('input', { type: 'text', placeholder: 'Placeholder', value: f.placeholder || '', onChange: e => updateField(i, 'placeholder', e.target.value), style: { width: '120px' } }),
                    ['radio', 'checkbox', 'dropdown'].includes(f.type) &&
                        h('input', { type: 'text', placeholder: 'Options (comma-sep)', value: (f.options || []).join(', '), onChange: e => updateField(i, 'options', e.target.value.split(',').map(s => s.trim()).filter(Boolean)), style: { width: '160px' } }),
                    f.type === 'hidden' &&
                        h('input', { type: 'text', placeholder: 'Value', value: f.value || '', onChange: e => updateField(i, 'value', e.target.value), style: { width: '120px' } }),
                    h('label', { style: { fontSize: '12px', display: 'flex', alignItems: 'center', gap: '4px' } },
                        h('input', { type: 'checkbox', checked: f.required || false, onChange: e => updateField(i, 'required', e.target.checked), disabled: f.type === 'email' }),
                        'Required'
                    ),
                    f.type !== 'email' && h('button', { className: 'ams-remove-btn', onClick: () => removeField(i) }, '×')
                )
            ),
            h('button', { className: 'button button-small', onClick: addField, style: { marginTop: '8px' } }, '+ Add Field')
        );
    }

    /* ── Spin Config Step ── */

    function SpinConfigStep({ spinConfig, setSpinConfig }) {
        const segments = spinConfig.segments || [];
        const colors = ['#4A90D9', '#E8544F', '#FFB74D', '#66BB6A', '#AB47BC', '#26C6DA', '#FF7043', '#78909C'];

        function addSegment() {
            if (segments.length >= 8) { alert('Maximum 8 segments.'); return; }
            setSpinConfig({
                ...spinConfig,
                segments: [...segments, { label: 'Prize ' + (segments.length + 1), probability: 10, discount_type: 'percent', discount_value: 10, color: colors[segments.length % 8], expiry_days: 30 }]
            });
        }
        function updateSeg(i, key, val) {
            const updated = [...segments];
            updated[i] = { ...updated[i], [key]: val };
            setSpinConfig({ ...spinConfig, segments: updated });
        }
        function removeSeg(i) {
            setSpinConfig({ ...spinConfig, segments: segments.filter((_, idx) => idx !== i) });
        }

        return h('div', null,
            h('h3', null, 'Spin-to-Win Segments'),
            h('p', { className: 'description' }, 'Configure up to 8 prize segments. Probability weights determine likelihood.'),
            segments.map((seg, i) =>
                h('div', { key: i, className: 'ams-fb-spin-seg' },
                    h('input', { type: 'color', value: seg.color || colors[i % 8], onChange: e => updateSeg(i, 'color', e.target.value), style: { width: '40px', height: '30px' } }),
                    h('input', { type: 'text', placeholder: 'Label', value: seg.label || '', onChange: e => updateSeg(i, 'label', e.target.value), style: { width: '120px' } }),
                    h('input', { type: 'number', placeholder: 'Weight', value: seg.probability || '', onChange: e => updateSeg(i, 'probability', parseFloat(e.target.value) || 0), style: { width: '60px' }, title: 'Probability weight' }),
                    h('select', { value: seg.discount_type || 'percent', onChange: e => updateSeg(i, 'discount_type', e.target.value) },
                        h('option', { value: 'percent' }, '% Off'),
                        h('option', { value: 'fixed' }, '$ Off'),
                        h('option', { value: 'free_shipping' }, 'Free Shipping')
                    ),
                    seg.discount_type !== 'free_shipping' && h('input', { type: 'number', placeholder: 'Value', value: seg.discount_value || '', onChange: e => updateSeg(i, 'discount_value', parseFloat(e.target.value) || 0), style: { width: '60px' } }),
                    h('button', { className: 'ams-remove-btn', onClick: () => removeSeg(i) }, '×')
                )
            ),
            h('button', { className: 'button button-small', onClick: addSegment, style: { marginTop: '8px' } }, '+ Add Segment')
        );
    }

    /* ── Step 2: Design ── */

    function DesignStep({ design, setDesign, type }) {
        function upd(key, val) { setDesign({ ...design, [key]: val }); }

        return h('div', null,
            h('h3', null, 'Design'),
            h('div', { className: 'ams-fb-design-grid' },
                h(ColorField, { label: 'Background', value: design.background_color, onChange: v => upd('background_color', v) }),
                h(ColorField, { label: 'Text', value: design.text_color, onChange: v => upd('text_color', v) }),
                h(ColorField, { label: 'Button', value: design.button_color, onChange: v => upd('button_color', v) }),
                h(ColorField, { label: 'Button Text', value: design.button_text_color, onChange: v => upd('button_text_color', v) }),
                h(ColorField, { label: 'Button Hover', value: design.button_hover_color, onChange: v => upd('button_hover_color', v) })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Border Radius (px)'),
                h('input', { type: 'number', value: design.border_radius, onChange: e => upd('border_radius', parseInt(e.target.value) || 0), style: { width: '80px' } })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Font Family'),
                h('select', { value: design.font_family || '', onChange: e => upd('font_family', e.target.value) },
                    h('option', { value: '' }, 'System Default'),
                    h('option', { value: 'Montserrat' }, 'Montserrat'),
                    h('option', { value: 'Open Sans' }, 'Open Sans'),
                    h('option', { value: 'Roboto' }, 'Roboto'),
                    h('option', { value: 'Lato' }, 'Lato'),
                    h('option', { value: 'Poppins' }, 'Poppins'),
                    h('option', { value: 'Playfair Display' }, 'Playfair Display'),
                    h('option', { value: 'Merriweather' }, 'Merriweather'),
                    h('option', { value: 'Raleway' }, 'Raleway'),
                    h('option', { value: 'Nunito' }, 'Nunito'),
                    h('option', { value: 'Inter' }, 'Inter')
                )
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Title'),
                h('input', { type: 'text', className: 'regular-text', value: design.title || '', onChange: e => upd('title', e.target.value) })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Description'),
                h('textarea', { className: 'regular-text', rows: 2, value: design.description || '', onChange: e => upd('description', e.target.value) })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Button Text'),
                h('input', { type: 'text', value: design.button_text || '', onChange: e => upd('button_text', e.target.value), style: { width: '200px' } })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Consent Text (HTML allowed)'),
                h('textarea', { className: 'regular-text', rows: 2, value: design.consent_text || '', onChange: e => upd('consent_text', e.target.value), placeholder: 'I agree to the <a href="/privacy">Privacy Policy</a>' })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Header Image URL'),
                h('input', { type: 'url', className: 'regular-text', value: design.header_image || '', onChange: e => upd('header_image', e.target.value) })
            ),
            type === 'flyout' && h('div', { className: 'ams-fb-field' },
                h('label', null, 'Flyout Position'),
                h('select', { value: design.flyout_position || 'right', onChange: e => upd('flyout_position', e.target.value) },
                    h('option', { value: 'right' }, 'Bottom Right'),
                    h('option', { value: 'left' }, 'Bottom Left')
                )
            ),
            type === 'sticky_bar' && h('div', { className: 'ams-fb-field' },
                h('label', null, 'Bar Position'),
                h('select', { value: design.bar_position || 'bottom', onChange: e => upd('bar_position', e.target.value) },
                    h('option', { value: 'bottom' }, 'Bottom'),
                    h('option', { value: 'top' }, 'Top')
                )
            )
        );
    }

    function ColorField({ label, value, onChange }) {
        return h('div', { className: 'ams-fb-color' },
            h('label', null, label),
            h('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } },
                h('input', { type: 'color', value: value || '#000000', onChange: e => onChange(e.target.value), style: { width: '40px', height: '30px', border: 'none', padding: 0 } }),
                h('input', { type: 'text', value: value || '', onChange: e => onChange(e.target.value), style: { width: '80px', fontSize: '12px' } })
            )
        );
    }

    /* ── Step 3: Targeting ── */

    function TargetingStep({ targeting, setTargeting, trigger, setTrigger }) {
        function upd(key, val) { setTargeting({ ...targeting, [key]: val }); }
        function updT(key, val) { setTrigger({ ...trigger, [key]: val }); }

        return h('div', null,
            h('h3', null, 'Targeting'),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Show on Pages'),
                h('select', { value: targeting.pages || 'all', onChange: e => upd('pages', e.target.value) },
                    h('option', { value: 'all' }, 'All Pages'),
                    h('option', { value: 'specific' }, 'Specific Pages (by ID)'),
                    h('option', { value: 'exclude' }, 'Exclude Pages (by ID)')
                )
            ),
            targeting.pages === 'specific' && h('div', { className: 'ams-fb-field' },
                h('label', null, 'Page IDs (comma-separated)'),
                h('input', { type: 'text', value: (targeting.page_ids || []).join(', '), onChange: e => upd('page_ids', e.target.value.split(',').map(s => parseInt(s.trim())).filter(Boolean)) })
            ),
            targeting.pages === 'exclude' && h('div', { className: 'ams-fb-field' },
                h('label', null, 'Exclude Page IDs'),
                h('input', { type: 'text', value: (targeting.exclude_page_ids || []).join(', '), onChange: e => upd('exclude_page_ids', e.target.value.split(',').map(s => parseInt(s.trim())).filter(Boolean)) })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Device'),
                h('select', { value: targeting.device || 'all', onChange: e => upd('device', e.target.value) },
                    h('option', { value: 'all' }, 'All Devices'),
                    h('option', { value: 'desktop' }, 'Desktop Only'),
                    h('option', { value: 'mobile' }, 'Mobile Only')
                )
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Visitor Type'),
                h('select', { value: targeting.visitor_type || 'all', onChange: e => upd('visitor_type', e.target.value) },
                    h('option', { value: 'all' }, 'All Visitors'),
                    h('option', { value: 'new' }, 'New Visitors Only'),
                    h('option', { value: 'returning' }, 'Returning Visitors Only')
                )
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Minimum Cart Value ($)'),
                h('input', { type: 'number', value: targeting.cart_value_min || '', onChange: e => upd('cart_value_min', e.target.value), style: { width: '100px' }, placeholder: '0' })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Segment Match (ID)'),
                h('input', { type: 'number', value: targeting.segment_id || '', onChange: e => upd('segment_id', e.target.value), style: { width: '100px' }, placeholder: 'Optional' })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Frequency Cap (days)'),
                h('input', { type: 'number', value: targeting.frequency_cap_days || '', onChange: e => upd('frequency_cap_days', parseInt(e.target.value) || 0), style: { width: '80px' } })
            ),
            h('hr'),
            h('h3', null, 'Display Triggers'),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Scroll Depth (%)'),
                h('input', { type: 'number', value: trigger.scroll_depth || '', onChange: e => updT('scroll_depth', e.target.value), style: { width: '80px' }, placeholder: '50', min: 1, max: 100 })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Time on Page (seconds)'),
                h('input', { type: 'number', value: trigger.time_on_page || '', onChange: e => updT('time_on_page', e.target.value), style: { width: '80px' }, placeholder: '5' })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null,
                    h('input', { type: 'checkbox', checked: trigger.exit_intent || false, onChange: e => updT('exit_intent', e.target.checked) }),
                    ' Exit Intent (desktop only)'
                )
            )
        );
    }

    /* ── Step 4: Success ── */

    function SuccessStep({ success, setSuccess }) {
        function upd(key, val) { setSuccess({ ...success, [key]: val }); }

        return h('div', null,
            h('h3', null, 'Success Actions'),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'On Submit'),
                h('select', { value: success.action || 'message', onChange: e => upd('action', e.target.value) },
                    h('option', { value: 'message' }, 'Show Success Message'),
                    h('option', { value: 'redirect' }, 'Redirect to URL')
                )
            ),
            success.action === 'message' && h('div', { className: 'ams-fb-field' },
                h('label', null, 'Success Message'),
                h('input', { type: 'text', className: 'regular-text', value: success.message || '', onChange: e => upd('message', e.target.value) })
            ),
            success.action === 'redirect' && h('div', { className: 'ams-fb-field' },
                h('label', null, 'Redirect URL'),
                h('input', { type: 'url', className: 'regular-text', value: success.redirect_url || '', onChange: e => upd('redirect_url', e.target.value) })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Add Tags (comma-separated)'),
                h('input', { type: 'text', className: 'regular-text', value: success.add_tags || '', onChange: e => upd('add_tags', e.target.value), placeholder: 'e.g., newsletter, homepage-optin' })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null, 'Enrol in Flow (ID)'),
                h('input', { type: 'number', value: success.enrol_flow_id || '', onChange: e => upd('enrol_flow_id', e.target.value), style: { width: '100px' }, placeholder: 'Optional' })
            ),
            h('div', { className: 'ams-fb-field' },
                h('label', null,
                    h('input', { type: 'checkbox', checked: success.double_optin || false, onChange: e => upd('double_optin', e.target.checked) }),
                    ' Require double opt-in for this form'
                )
            )
        );
    }

    /* ── Live Preview ── */

    function LivePreview({ type, fields, design, previewMobile, spinConfig }) {
        const d = design || {};
        const style = {
            background: d.background_color || '#fff',
            color: d.text_color || '#333',
            borderRadius: (d.border_radius || 8) + 'px',
            fontFamily: d.font_family || 'inherit',
            padding: '20px',
            maxWidth: previewMobile ? '320px' : '100%',
            margin: '0 auto',
            boxShadow: '0 4px 16px rgba(0,0,0,0.1)',
            border: '1px solid #ddd',
            transition: 'max-width .3s ease',
        };

        return h('div', { className: 'ams-fb-preview-frame', style },
            d.header_image && h('img', { src: d.header_image, style: { width: '100%', borderRadius: (d.border_radius || 8) + 'px ' + (d.border_radius || 8) + 'px 0 0', marginBottom: '12px' } }),
            d.title && h('h3', { style: { margin: '0 0 4px', fontSize: '20px' } }, d.title),
            d.description && h('p', { style: { margin: '0 0 12px', fontSize: '13px', opacity: .7 } }, d.description),
            (fields || []).map((f, i) => {
                if (f.type === 'hidden') return null;
                if (f.type === 'birthday') return h('div', { key: i, style: { display: 'flex', gap: '6px', marginBottom: '8px' } },
                    h('select', { style: { flex: 1, padding: '8px' }, disabled: true }, h('option', null, 'Month')),
                    h('select', { style: { flex: 1, padding: '8px' }, disabled: true }, h('option', null, 'Day'))
                );
                if (['radio', 'checkbox', 'dropdown'].includes(f.type)) {
                    return h('div', { key: i, style: { marginBottom: '8px' } },
                        f.label && h('div', { style: { fontSize: '12px', fontWeight: 600, marginBottom: '2px' } }, f.label),
                        h('div', { style: { fontSize: '13px', color: '#999' } }, (f.options || []).join(', ') || 'Options…')
                    );
                }
                return h('div', { key: i, style: { marginBottom: '8px' } },
                    h('input', { type: 'text', placeholder: f.placeholder || f.label || f.type, disabled: true, style: { width: '100%', padding: '8px 10px', border: '1px solid #ccc', borderRadius: '4px', boxSizing: 'border-box' } })
                );
            }),
            d.consent_text && h('div', { style: { fontSize: '11px', marginBottom: '8px', opacity: .7 } }, '☐ ' + d.consent_text.replace(/<[^>]+>/g, '')),
            h('div', {
                style: {
                    background: d.button_color || '#4A90D9',
                    color: d.button_text_color || '#fff',
                    padding: '10px',
                    borderRadius: (d.border_radius || 8) + 'px',
                    textAlign: 'center',
                    fontWeight: 600,
                    fontSize: '14px',
                    cursor: 'default',
                }
            }, d.button_text || 'Subscribe'),
            type === 'spin_to_win' && spinConfig && spinConfig.segments && spinConfig.segments.length > 0 &&
                h('div', { style: { marginTop: '12px', textAlign: 'center', fontSize: '12px', color: '#999' } },
                    'Wheel: ' + spinConfig.segments.map(s => s.label).join(', ')
                )
        );
    }

    /* ── Styles ── */

    function injectStyles() {
        if (document.getElementById('ams-form-builder-css')) return;
        const s = document.createElement('style');
        s.id = 'ams-form-builder-css';
        s.textContent = `
.ams-fl-header,.ams-fb-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.ams-fl-header h2,.ams-fb-header h2{margin:0}
.ams-fb-meta{display:flex;gap:16px;margin-bottom:16px;background:#fff;border:1px solid #c3c4c7;padding:12px}
.ams-fb-field{margin-bottom:12px}
.ams-fb-field label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
.ams-fb-steps{display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #c3c4c7}
.ams-fb-step{background:none;border:none;padding:8px 16px;cursor:pointer;font-size:13px;font-weight:500;border-bottom:2px solid transparent;margin-bottom:-2px}
.ams-fb-step.active{border-bottom-color:#2271b1;color:#2271b1;font-weight:600}
.ams-fb-step:hover{color:#2271b1}
.ams-fb-content{display:flex;gap:20px}
.ams-fb-panel{flex:1;background:#fff;border:1px solid #c3c4c7;padding:16px;min-height:400px}
.ams-fb-preview{width:360px;flex-shrink:0}
.ams-fb-preview-toggle{margin-bottom:8px}
.ams-fb-preview-toggle .button{margin-right:4px}
.ams-fb-nav{display:flex;align-items:center;gap:8px;margin-top:16px;padding-top:12px;border-top:1px solid #ddd}
.ams-fb-field-row{display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;padding:8px;background:#f9f9f9;border:1px solid #eee;border-radius:4px}
.ams-fb-field-controls{display:flex;flex-direction:column;gap:2px}
.ams-fb-design-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.ams-fb-color label{font-size:12px;font-weight:600;display:block;margin-bottom:2px}
.ams-fb-spin-seg{display:flex;align-items:center;gap:8px;margin-bottom:6px;padding:6px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;flex-wrap:wrap}
.ams-remove-btn{background:none;border:none;color:#b32d2e;font-size:18px;cursor:pointer;padding:2px 6px}
.ams-status-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;text-transform:uppercase}
.ams-status-active{background:#d4edda;color:#155724}
.ams-status-draft{background:#f0f0f1;color:#666}
.ams-empty{color:#666;font-style:italic}
        `;
        document.head.appendChild(s);
    }

    /* ── Mount ── */

    function init() {
        const root = document.getElementById('ams-admin-forms');
        if (!root) return;
        injectStyles();
        wp.element.render(h(App), root);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
