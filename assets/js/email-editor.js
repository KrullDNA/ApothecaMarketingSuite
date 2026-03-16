/**
 * Apotheca® Marketing Suite — Visual Email Template Editor.
 *
 * Block-based drag-and-drop editor with live preview, built on wp.element.
 */
(function () {
    'use strict';

    const { createElement: h, useState, useEffect, useCallback, useRef, Fragment } = wp.element;
    const apiFetch = wp.apiFetch;

    /* ------------------------------------------------------------------ */
    /*  Constants                                                          */
    /* ------------------------------------------------------------------ */

    const BLOCK_TYPES = [
        { type: 'header',  label: 'Header',  icon: '🖼' },
        { type: 'text',    label: 'Text',    icon: '📝' },
        { type: 'image',   label: 'Image',   icon: '🖼' },
        { type: 'button',  label: 'Button',  icon: '🔘' },
        { type: 'divider', label: 'Divider', icon: '➖' },
        { type: 'spacer',  label: 'Spacer',  icon: '↕' },
        { type: 'columns', label: 'Columns', icon: '▥' },
        { type: 'product', label: 'Product', icon: '🛒' },
        { type: 'social',  label: 'Social',  icon: '🔗' },
        { type: 'reviews', label: 'Reviews', icon: '⭐' },
        { type: 'footer',  label: 'Footer',  icon: '📄' },
        { type: 'html',    label: 'HTML',    icon: '<>' },
    ];

    const DEFAULT_GLOBAL_STYLE = {
        bg_color: '#f4f4f5',
        content_bg: '#ffffff',
        text_color: '#1f2937',
        link_color: '#7c3aed',
        content_width: '600',
        preheader: '',
    };

    /* ------------------------------------------------------------------ */
    /*  Utility                                                            */
    /* ------------------------------------------------------------------ */

    let blockIdCounter = Date.now();
    function generateId() { return 'blk_' + (++blockIdCounter); }

    function createDefaultBlock(type) {
        const id = generateId();
        const defaults = {
            header:  { logo_url: '', logo_width: '150', align: 'center', padding: '20px 0' },
            text:    { content: '<p>Your text here...</p>', align: 'left', font_size: '16px', padding: '10px 20px' },
            image:   { src: '', alt: '', width: '100%', align: 'center', link: '', padding: '10px 0' },
            button:  { text: 'Click Here', url: '#', bg_color: '#7c3aed', text_color: '#ffffff', border_radius: '4px', align: 'center', button_padding: '12px 30px', padding: '10px 0' },
            divider: { color: '#e5e7eb', width: '100%', height: '1px', padding: '10px 20px' },
            spacer:  { height: '20px' },
            columns: { layout: '50-50', columns: [[], []], padding: '10px 0' },
            product: { product_id: 0, padding: '10px 20px' },
            social:  { links: [], align: 'center', icon_size: '32', padding: '10px 0' },
            reviews: { mode: 'social_proof', product_id: 0, max_reviews: 3, heading: 'What our customers say', padding: '10px 20px' },
            footer:  { content: '<p>© {{shop_name}} | <a href="{{unsubscribe_url}}">Unsubscribe</a></p>', padding: '20px' },
            html:    { content: '', padding: '10px 0' },
        };
        return { id, type, ...( defaults[type] || {} ) };
    }

    /* ------------------------------------------------------------------ */
    /*  Block Palette (left sidebar)                                       */
    /* ------------------------------------------------------------------ */

    function BlockPalette({ onAdd }) {
        return h('div', { className: 'ams-ee-palette' },
            h('h3', null, 'Blocks'),
            h('div', { className: 'ams-ee-palette__grid' },
                BLOCK_TYPES.map(bt =>
                    h('button', {
                        key: bt.type,
                        className: 'ams-ee-palette__item',
                        onClick: () => onAdd(bt.type),
                        draggable: true,
                        onDragStart: (e) => e.dataTransfer.setData('text/plain', bt.type),
                    },
                        h('span', { className: 'ams-ee-palette__icon' }, bt.icon),
                        h('span', null, bt.label)
                    )
                )
            )
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Block Settings Panel (right sidebar)                               */
    /* ------------------------------------------------------------------ */

    function FieldInput({ label, value, onChange, type }) {
        type = type || 'text';
        return h('label', { className: 'ams-ee-field' },
            h('span', null, label),
            type === 'textarea'
                ? h('textarea', { value: value || '', onChange: e => onChange(e.target.value), rows: 4 })
                : h('input', { type, value: value || '', onChange: e => onChange(e.target.value) })
        );
    }

    function FieldSelect({ label, value, options, onChange }) {
        return h('label', { className: 'ams-ee-field' },
            h('span', null, label),
            h('select', { value: value || '', onChange: e => onChange(e.target.value) },
                options.map(o => h('option', { key: o.value, value: o.value }, o.label))
            )
        );
    }

    function BlockSettings({ block, onChange, onDelete }) {
        if (!block) {
            return h('div', { className: 'ams-ee-settings' },
                h('p', { style: { color: '#999', padding: '20px' } }, 'Select a block to edit its settings.')
            );
        }

        const update = (key, val) => onChange({ ...block, [key]: val });

        const fields = [];

        // Common padding field.
        fields.push(h(FieldInput, { key: 'padding', label: 'Padding', value: block.padding, onChange: v => update('padding', v) }));

        switch (block.type) {
            case 'header':
                fields.push(
                    h(FieldInput, { key: 'logo_url', label: 'Logo URL', value: block.logo_url, onChange: v => update('logo_url', v) }),
                    h(FieldInput, { key: 'logo_width', label: 'Logo Width', value: block.logo_width, onChange: v => update('logo_width', v) }),
                    h(FieldSelect, { key: 'align', label: 'Alignment', value: block.align, onChange: v => update('align', v), options: [{value:'left',label:'Left'},{value:'center',label:'Center'},{value:'right',label:'Right'}] })
                );
                break;

            case 'text':
                fields.push(
                    h(FieldInput, { key: 'content', label: 'Content (HTML)', value: block.content, onChange: v => update('content', v), type: 'textarea' }),
                    h(FieldInput, { key: 'font_size', label: 'Font Size', value: block.font_size, onChange: v => update('font_size', v) }),
                    h(FieldSelect, { key: 'align', label: 'Alignment', value: block.align, onChange: v => update('align', v), options: [{value:'left',label:'Left'},{value:'center',label:'Center'},{value:'right',label:'Right'}] })
                );
                break;

            case 'image':
                fields.push(
                    h(FieldInput, { key: 'src', label: 'Image URL', value: block.src, onChange: v => update('src', v) }),
                    h(FieldInput, { key: 'alt', label: 'Alt Text', value: block.alt, onChange: v => update('alt', v) }),
                    h(FieldInput, { key: 'width', label: 'Width', value: block.width, onChange: v => update('width', v) }),
                    h(FieldInput, { key: 'link', label: 'Link URL', value: block.link, onChange: v => update('link', v) }),
                    h(FieldSelect, { key: 'align', label: 'Alignment', value: block.align, onChange: v => update('align', v), options: [{value:'left',label:'Left'},{value:'center',label:'Center'},{value:'right',label:'Right'}] })
                );
                break;

            case 'button':
                fields.push(
                    h(FieldInput, { key: 'text', label: 'Button Text', value: block.text, onChange: v => update('text', v) }),
                    h(FieldInput, { key: 'url', label: 'URL', value: block.url, onChange: v => update('url', v) }),
                    h(FieldInput, { key: 'bg_color', label: 'Background Color', value: block.bg_color, onChange: v => update('bg_color', v), type: 'color' }),
                    h(FieldInput, { key: 'text_color', label: 'Text Color', value: block.text_color, onChange: v => update('text_color', v), type: 'color' }),
                    h(FieldInput, { key: 'border_radius', label: 'Border Radius', value: block.border_radius, onChange: v => update('border_radius', v) }),
                    h(FieldInput, { key: 'button_padding', label: 'Button Padding', value: block.button_padding, onChange: v => update('button_padding', v) }),
                    h(FieldSelect, { key: 'align', label: 'Alignment', value: block.align, onChange: v => update('align', v), options: [{value:'left',label:'Left'},{value:'center',label:'Center'},{value:'right',label:'Right'}] })
                );
                break;

            case 'divider':
                fields.push(
                    h(FieldInput, { key: 'color', label: 'Color', value: block.color, onChange: v => update('color', v), type: 'color' }),
                    h(FieldInput, { key: 'width', label: 'Width', value: block.width, onChange: v => update('width', v) }),
                    h(FieldInput, { key: 'height', label: 'Height', value: block.height, onChange: v => update('height', v) })
                );
                break;

            case 'spacer':
                fields.push(
                    h(FieldInput, { key: 'height', label: 'Height', value: block.height, onChange: v => update('height', v) })
                );
                break;

            case 'columns':
                fields.push(
                    h(FieldSelect, { key: 'layout', label: 'Layout', value: block.layout, onChange: v => {
                        const colCount = v === '33-33-33' ? 3 : 2;
                        const cols = block.columns ? [...block.columns] : [];
                        while (cols.length < colCount) cols.push([]);
                        update('layout', v);
                        update('columns', cols.slice(0, colCount));
                    }, options: [{value:'50-50',label:'50/50'},{value:'60-40',label:'60/40'},{value:'40-60',label:'40/60'},{value:'33-33-33',label:'33/33/33'}] })
                );
                break;

            case 'product':
                fields.push(
                    h(FieldInput, { key: 'product_id', label: 'Product ID', value: block.product_id, onChange: v => update('product_id', parseInt(v) || 0) })
                );
                break;

            case 'social':
                fields.push(
                    h(FieldSelect, { key: 'align', label: 'Alignment', value: block.align, onChange: v => update('align', v), options: [{value:'left',label:'Left'},{value:'center',label:'Center'},{value:'right',label:'Right'}] }),
                    h(FieldInput, { key: 'icon_size', label: 'Icon Size (px)', value: block.icon_size, onChange: v => update('icon_size', v) }),
                    h('div', { key: 'social-links', className: 'ams-ee-field' },
                        h('span', null, 'Social Links'),
                        h('p', { style: { fontSize: '12px', color: '#999' } }, 'Add links as JSON: [{"platform":"facebook","url":"..."}]'),
                        h('textarea', {
                            rows: 4,
                            value: JSON.stringify(block.links || [], null, 2),
                            onChange: e => {
                                try { update('links', JSON.parse(e.target.value)); } catch(ex) {}
                            }
                        })
                    )
                );
                break;

            case 'reviews':
                fields.push(
                    h(FieldSelect, { key: 'mode', label: 'Mode', value: block.mode, onChange: v => update('mode', v), options: [{value:'social_proof',label:'Social Proof'},{value:'review_request',label:'Review Request'},{value:'review_gating',label:'Review Gating'}] }),
                    h(FieldInput, { key: 'heading', label: 'Heading', value: block.heading, onChange: v => update('heading', v) }),
                    h(FieldInput, { key: 'product_id', label: 'Product ID', value: block.product_id, onChange: v => update('product_id', parseInt(v) || 0) }),
                    h(FieldInput, { key: 'max_reviews', label: 'Max Reviews', value: block.max_reviews, onChange: v => update('max_reviews', parseInt(v) || 3) })
                );
                if (block.mode === 'review_request') {
                    fields.push(
                        h(FieldInput, { key: 'body', label: 'Body Text', value: block.body, onChange: v => update('body', v), type: 'textarea' }),
                        h(FieldInput, { key: 'cta_text', label: 'CTA Text', value: block.cta_text, onChange: v => update('cta_text', v) }),
                        h(FieldInput, { key: 'cta_url', label: 'CTA URL', value: block.cta_url, onChange: v => update('cta_url', v) })
                    );
                }
                if (block.mode === 'review_gating') {
                    fields.push(
                        h(FieldInput, { key: 'positive_text', label: 'Positive Button', value: block.positive_text, onChange: v => update('positive_text', v) }),
                        h(FieldInput, { key: 'positive_url', label: 'Positive URL', value: block.positive_url, onChange: v => update('positive_url', v) }),
                        h(FieldInput, { key: 'negative_text', label: 'Negative Button', value: block.negative_text, onChange: v => update('negative_text', v) }),
                        h(FieldInput, { key: 'negative_url', label: 'Negative URL', value: block.negative_url, onChange: v => update('negative_url', v) })
                    );
                }
                break;

            case 'footer':
                fields.push(
                    h(FieldInput, { key: 'content', label: 'Content (HTML)', value: block.content, onChange: v => update('content', v), type: 'textarea' })
                );
                break;

            case 'html':
                fields.push(
                    h(FieldInput, { key: 'content', label: 'HTML Content', value: block.content, onChange: v => update('content', v), type: 'textarea' })
                );
                break;
        }

        return h('div', { className: 'ams-ee-settings' },
            h('div', { className: 'ams-ee-settings__header' },
                h('h3', null, block.type.charAt(0).toUpperCase() + block.type.slice(1) + ' Block'),
                h('button', { className: 'ams-ee-btn ams-ee-btn--danger', onClick: onDelete }, 'Delete')
            ),
            h('div', { className: 'ams-ee-settings__body' }, ...fields)
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Block Canvas Item                                                  */
    /* ------------------------------------------------------------------ */

    function CanvasBlock({ block, isSelected, onSelect, onMoveUp, onMoveDown, index, total }) {
        const typeLabel = (BLOCK_TYPES.find(b => b.type === block.type) || {}).label || block.type;
        const icon = (BLOCK_TYPES.find(b => b.type === block.type) || {}).icon || '?';

        return h('div', {
            className: 'ams-ee-canvas-block' + (isSelected ? ' ams-ee-canvas-block--selected' : ''),
            onClick: (e) => { e.stopPropagation(); onSelect(); },
        },
            h('div', { className: 'ams-ee-canvas-block__header' },
                h('span', null, icon + ' ' + typeLabel),
                h('div', { className: 'ams-ee-canvas-block__actions' },
                    index > 0 ? h('button', { onClick: (e) => { e.stopPropagation(); onMoveUp(); }, title: 'Move up' }, '↑') : null,
                    index < total - 1 ? h('button', { onClick: (e) => { e.stopPropagation(); onMoveDown(); }, title: 'Move down' }, '↓') : null
                )
            ),
            h('div', { className: 'ams-ee-canvas-block__preview' },
                renderBlockPreview(block)
            )
        );
    }

    function renderBlockPreview(block) {
        switch (block.type) {
            case 'header':
                return block.logo_url
                    ? h('img', { src: block.logo_url, style: { maxWidth: block.logo_width + 'px', height: 'auto' } })
                    : h('span', { style: { color: '#999' } }, '[Header — set logo URL]');
            case 'text':
                return h('div', { dangerouslySetInnerHTML: { __html: block.content || '<em>Empty text</em>' }, style: { fontSize: block.font_size, textAlign: block.align } });
            case 'image':
                return block.src
                    ? h('img', { src: block.src, alt: block.alt, style: { maxWidth: '100%', height: 'auto' } })
                    : h('span', { style: { color: '#999' } }, '[Image — set URL]');
            case 'button':
                return h('div', { style: { textAlign: block.align } },
                    h('span', { style: { display: 'inline-block', padding: block.button_padding || '12px 30px', background: block.bg_color, color: block.text_color, borderRadius: block.border_radius, fontWeight: 600 } }, block.text)
                );
            case 'divider':
                return h('hr', { style: { border: 0, height: block.height, background: block.color } });
            case 'spacer':
                return h('div', { style: { height: block.height, background: '#f9fafb', border: '1px dashed #e5e7eb', textAlign: 'center', lineHeight: block.height, color: '#ccc', fontSize: '12px' } }, block.height);
            case 'columns':
                return h('div', { style: { display: 'flex', gap: '8px' } },
                    (block.columns || []).map((col, i) =>
                        h('div', { key: i, style: { flex: 1, background: '#f9fafb', border: '1px dashed #d1d5db', padding: '8px', minHeight: '40px', fontSize: '12px', color: '#999' } },
                            'Column ' + (i + 1) + ' (' + (col.length || 0) + ' blocks)'
                        )
                    )
                );
            case 'product':
                return h('span', { style: { color: '#999' } }, block.product_id ? 'Product #' + block.product_id : '[Select product]');
            case 'social':
                return h('span', { style: { color: '#999' } }, (block.links || []).length + ' social links');
            case 'reviews':
                return h('span', { style: { color: '#999' } }, 'Reviews: ' + block.mode);
            case 'footer':
                return h('div', { dangerouslySetInnerHTML: { __html: block.content || '<em>Footer</em>' }, style: { fontSize: '12px', color: '#999', textAlign: 'center' } });
            case 'html':
                return h('span', { style: { color: '#999', fontFamily: 'monospace', fontSize: '12px' } }, (block.content || '').substring(0, 80) || '[Empty HTML]');
            default:
                return h('span', { style: { color: '#999' } }, block.type);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Global Style Panel                                                 */
    /* ------------------------------------------------------------------ */

    function GlobalStylePanel({ style, onChange }) {
        const update = (key, val) => onChange({ ...style, [key]: val });

        return h('div', { className: 'ams-ee-global-style' },
            h('h3', null, 'Global Styles'),
            h(FieldInput, { label: 'Background Color', value: style.bg_color, onChange: v => update('bg_color', v), type: 'color' }),
            h(FieldInput, { label: 'Content Background', value: style.content_bg, onChange: v => update('content_bg', v), type: 'color' }),
            h(FieldInput, { label: 'Text Color', value: style.text_color, onChange: v => update('text_color', v), type: 'color' }),
            h(FieldInput, { label: 'Link Color', value: style.link_color, onChange: v => update('link_color', v), type: 'color' }),
            h(FieldInput, { label: 'Content Width (px)', value: style.content_width, onChange: v => update('content_width', v) }),
            h(FieldInput, { label: 'Preheader Text', value: style.preheader, onChange: v => update('preheader', v) })
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Template List                                                      */
    /* ------------------------------------------------------------------ */

    function TemplateList({ onEdit, onNew }) {
        const [templates, setTemplates] = useState([]);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            apiFetch({ path: '/ams/v1/email-templates' }).then(data => {
                setTemplates(data || []);
                setLoading(false);
            });
        }, []);

        if (loading) return h('p', null, 'Loading templates...');

        return h('div', { className: 'ams-ee-template-list' },
            h('div', { className: 'ams-ee-template-list__header' },
                h('h2', null, 'Email Templates'),
                h('button', { className: 'ams-ee-btn ams-ee-btn--primary', onClick: onNew }, '+ New Template')
            ),
            templates.length === 0
                ? h('p', { style: { color: '#999' } }, 'No templates yet. Create your first one!')
                : h('div', { className: 'ams-ee-template-grid' },
                    templates.map(tpl =>
                        h('div', { key: tpl.id, className: 'ams-ee-template-card' },
                            h('h3', null, tpl.name),
                            h('span', { className: 'ams-ee-template-card__date' }, 'Updated: ' + (tpl.updated_at || 'N/A')),
                            h('div', { className: 'ams-ee-template-card__actions' },
                                h('button', { className: 'ams-ee-btn', onClick: () => onEdit(tpl.id) }, 'Edit'),
                                h('button', { className: 'ams-ee-btn', onClick: () => {
                                    apiFetch({ path: '/ams/v1/email-templates/' + tpl.id + '/duplicate', method: 'POST' }).then(() => {
                                        apiFetch({ path: '/ams/v1/email-templates' }).then(data => setTemplates(data || []));
                                    });
                                }}, 'Duplicate'),
                                h('button', { className: 'ams-ee-btn ams-ee-btn--danger', onClick: () => {
                                    if (confirm('Delete this template?')) {
                                        apiFetch({ path: '/ams/v1/email-templates/' + tpl.id, method: 'DELETE' }).then(() => {
                                            setTemplates(prev => prev.filter(t => t.id !== tpl.id));
                                        });
                                    }
                                }}, 'Delete')
                            )
                        )
                    )
                )
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Editor Main                                                        */
    /* ------------------------------------------------------------------ */

    function Editor({ templateId, onBack }) {
        const [blocks, setBlocks] = useState([]);
        const [globalStyle, setGlobalStyle] = useState({ ...DEFAULT_GLOBAL_STYLE });
        const [templateName, setTemplateName] = useState('Untitled Template');
        const [selectedBlockId, setSelectedBlockId] = useState(null);
        const [saving, setSaving] = useState(false);
        const [previewHtml, setPreviewHtml] = useState('');
        const [showPreview, setShowPreview] = useState(false);
        const [activePanel, setActivePanel] = useState('blocks'); // blocks | style

        const isNew = !templateId;

        // Load template data.
        useEffect(() => {
            if (templateId) {
                apiFetch({ path: '/ams/v1/email-templates/' + templateId }).then(data => {
                    setBlocks(data.blocks || []);
                    setGlobalStyle(data.global_style || { ...DEFAULT_GLOBAL_STYLE });
                    setTemplateName(data.name || 'Untitled');
                });
            }
        }, [templateId]);

        const selectedBlock = blocks.find(b => b.id === selectedBlockId) || null;

        const addBlock = useCallback((type) => {
            const newBlock = createDefaultBlock(type);
            setBlocks(prev => [...prev, newBlock]);
            setSelectedBlockId(newBlock.id);
        }, []);

        const updateBlock = useCallback((updated) => {
            setBlocks(prev => prev.map(b => b.id === updated.id ? updated : b));
        }, []);

        const deleteBlock = useCallback(() => {
            setBlocks(prev => prev.filter(b => b.id !== selectedBlockId));
            setSelectedBlockId(null);
        }, [selectedBlockId]);

        const moveBlock = useCallback((index, direction) => {
            setBlocks(prev => {
                const arr = [...prev];
                const newIndex = index + direction;
                if (newIndex < 0 || newIndex >= arr.length) return arr;
                [arr[index], arr[newIndex]] = [arr[newIndex], arr[index]];
                return arr;
            });
        }, []);

        const handleSave = useCallback(() => {
            setSaving(true);
            const payload = { name: templateName, blocks, global_style: globalStyle };

            const request = templateId
                ? apiFetch({ path: '/ams/v1/email-templates/' + templateId, method: 'PUT', data: payload })
                : apiFetch({ path: '/ams/v1/email-templates', method: 'POST', data: payload });

            request.then(() => setSaving(false));
        }, [templateId, templateName, blocks, globalStyle]);

        const handlePreview = useCallback(() => {
            apiFetch({ path: '/ams/v1/email-templates/render', method: 'POST', data: { blocks, global_style: globalStyle } })
                .then(data => {
                    setPreviewHtml(data.html || '');
                    setShowPreview(true);
                });
        }, [blocks, globalStyle]);

        // Drop handler for canvas.
        const handleDrop = useCallback((e) => {
            e.preventDefault();
            const type = e.dataTransfer.getData('text/plain');
            if (type && BLOCK_TYPES.find(bt => bt.type === type)) {
                addBlock(type);
            }
        }, [addBlock]);

        if (showPreview) {
            return h('div', { className: 'ams-ee-preview' },
                h('div', { className: 'ams-ee-preview__toolbar' },
                    h('button', { className: 'ams-ee-btn', onClick: () => setShowPreview(false) }, 'Back to Editor'),
                    h('h3', null, 'Email Preview')
                ),
                h('div', { className: 'ams-ee-preview__frame' },
                    h('iframe', {
                        srcDoc: previewHtml,
                        style: { width: '100%', height: '800px', border: '1px solid #e5e7eb', borderRadius: '4px' },
                        sandbox: 'allow-same-origin',
                    })
                )
            );
        }

        return h('div', { className: 'ams-ee-editor' },
            // Toolbar.
            h('div', { className: 'ams-ee-toolbar' },
                h('button', { className: 'ams-ee-btn', onClick: onBack }, '← Back'),
                h('input', {
                    className: 'ams-ee-toolbar__name',
                    value: templateName,
                    onChange: e => setTemplateName(e.target.value),
                    placeholder: 'Template name...',
                }),
                h('div', { className: 'ams-ee-toolbar__actions' },
                    h('button', { className: 'ams-ee-btn', onClick: handlePreview }, 'Preview'),
                    h('button', { className: 'ams-ee-btn ams-ee-btn--primary', onClick: handleSave, disabled: saving }, saving ? 'Saving...' : 'Save')
                )
            ),

            // Main layout.
            h('div', { className: 'ams-ee-layout' },
                // Left panel — block palette + global style.
                h('div', { className: 'ams-ee-left' },
                    h('div', { className: 'ams-ee-tabs' },
                        h('button', { className: 'ams-ee-tab' + (activePanel === 'blocks' ? ' ams-ee-tab--active' : ''), onClick: () => setActivePanel('blocks') }, 'Blocks'),
                        h('button', { className: 'ams-ee-tab' + (activePanel === 'style' ? ' ams-ee-tab--active' : ''), onClick: () => setActivePanel('style') }, 'Style')
                    ),
                    activePanel === 'blocks'
                        ? h(BlockPalette, { onAdd: addBlock })
                        : h(GlobalStylePanel, { style: globalStyle, onChange: setGlobalStyle })
                ),

                // Center — canvas.
                h('div', {
                    className: 'ams-ee-canvas',
                    onClick: () => setSelectedBlockId(null),
                    onDragOver: (e) => e.preventDefault(),
                    onDrop: handleDrop,
                    style: { backgroundColor: globalStyle.bg_color },
                },
                    h('div', { className: 'ams-ee-canvas__inner', style: { maxWidth: globalStyle.content_width + 'px', backgroundColor: globalStyle.content_bg } },
                        blocks.length === 0
                            ? h('div', { className: 'ams-ee-canvas__empty' },
                                h('p', null, 'Drag blocks here or click to add'),
                                h('button', { className: 'ams-ee-btn ams-ee-btn--primary', onClick: () => addBlock('text') }, 'Add Text Block')
                            )
                            : blocks.map((block, idx) =>
                                h(CanvasBlock, {
                                    key: block.id,
                                    block,
                                    index: idx,
                                    total: blocks.length,
                                    isSelected: block.id === selectedBlockId,
                                    onSelect: () => setSelectedBlockId(block.id),
                                    onMoveUp: () => moveBlock(idx, -1),
                                    onMoveDown: () => moveBlock(idx, 1),
                                })
                            )
                    )
                ),

                // Right panel — block settings.
                h('div', { className: 'ams-ee-right' },
                    h(BlockSettings, { block: selectedBlock, onChange: updateBlock, onDelete: deleteBlock })
                )
            )
        );
    }

    /* ------------------------------------------------------------------ */
    /*  App Root                                                           */
    /* ------------------------------------------------------------------ */

    function App() {
        const [view, setView] = useState('list');
        const [editingId, setEditingId] = useState(null);

        if (view === 'editor') {
            return h(Editor, {
                templateId: editingId,
                onBack: () => { setView('list'); setEditingId(null); },
            });
        }

        return h(TemplateList, {
            onEdit: (id) => { setEditingId(id); setView('editor'); },
            onNew: () => { setEditingId(null); setView('editor'); },
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Mount                                                              */
    /* ------------------------------------------------------------------ */

    const container = document.getElementById('ams-email-editor-app');
    if (container) {
        wp.element.render(h(App), container);
    }
})();
