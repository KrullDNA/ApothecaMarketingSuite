/**
 * AMS Forms — Lightweight front-end form renderer.
 *
 * Vanilla ES6+, no jQuery. Renders modal, flyout, embedded, full-page,
 * sticky-bar, and spin-to-win forms. Uses Intersection Observer for scroll
 * depth and CSS transitions for animations.
 *
 * < 15kb gzipped target.
 *
 * @package Apotheca\Marketing
 */
(function () {
    'use strict';

    /* ── Config ── */

    const REST_URL = (window.amsFormsConfig && window.amsFormsConfig.restUrl) || '/wp-json/ams/v1';
    const PAGE_ID = (window.amsFormsConfig && window.amsFormsConfig.pageId) || 0;

    /* ── State ── */

    const shownForms = new Set();
    const activeForms = [];

    /* ── Helpers ── */

    function api(path, opts = {}) {
        const url = REST_URL + path;
        const config = {
            headers: { 'Content-Type': 'application/json' },
            ...opts,
        };
        return fetch(url, config).then(r => r.json());
    }

    function el(tag, attrs, ...children) {
        const elem = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(k => {
                if (k === 'className') elem.className = attrs[k];
                else if (k === 'style' && typeof attrs[k] === 'object') Object.assign(elem.style, attrs[k]);
                else if (k.startsWith('on')) elem.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
                else elem.setAttribute(k, attrs[k]);
            });
        }
        children.forEach(c => {
            if (typeof c === 'string') elem.appendChild(document.createTextNode(c));
            else if (c) elem.appendChild(c);
        });
        return elem;
    }

    function getStorage(key) {
        try { return localStorage.getItem('ams_' + key); } catch (e) { return null; }
    }

    function setStorage(key, val) {
        try { localStorage.setItem('ams_' + key, val); } catch (e) { /* noop */ }
    }

    function isFreqCapped(formId, days) {
        if (!days) return false;
        const ts = getStorage('fc_' + formId);
        if (!ts) return false;
        const elapsed = (Date.now() - parseInt(ts, 10)) / 86400000;
        return elapsed < days;
    }

    function setFreqCap(formId) {
        setStorage('fc_' + formId, Date.now().toString());
    }

    function isReturningVisitor() {
        const visited = getStorage('visited');
        setStorage('visited', '1');
        return visited === '1';
    }

    function getCartTotal() {
        if (window.amsFormsConfig && typeof window.amsFormsConfig.cartTotal === 'number') {
            return window.amsFormsConfig.cartTotal;
        }
        return 0;
    }

    function getUtmParam(name) {
        const params = new URLSearchParams(window.location.search);
        return params.get(name) || '';
    }

    /* ── CSS Injection ── */

    function injectGlobalStyles() {
        if (document.getElementById('ams-forms-css')) return;
        const style = document.createElement('style');
        style.id = 'ams-forms-css';
        style.textContent = `
.ams-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;opacity:0;transition:opacity .3s ease}
.ams-backdrop.ams-visible{opacity:1}
.ams-form-wrap{z-index:99999;opacity:0;transition:opacity .3s ease,transform .3s ease}
.ams-form-wrap.ams-visible{opacity:1}
.ams-form-wrap.ams-modal{position:fixed;top:50%;left:50%;transform:translate(-50%,-48%);max-width:480px;width:90%}
.ams-form-wrap.ams-modal.ams-visible{transform:translate(-50%,-50%)}
.ams-form-wrap.ams-flyout{position:fixed;bottom:20px;right:20px;max-width:380px;width:90%;transform:translateY(20px)}
.ams-form-wrap.ams-flyout.ams-left{right:auto;left:20px}
.ams-form-wrap.ams-flyout.ams-visible{transform:translateY(0)}
.ams-form-wrap.ams-fullpage{position:fixed;inset:0;display:flex;align-items:center;justify-content:center}
.ams-form-wrap.ams-sticky-bar{position:fixed;left:0;right:0;z-index:99999;transform:translateY(-100%)}
.ams-form-wrap.ams-sticky-bar.ams-bottom{top:auto;bottom:0;transform:translateY(100%)}
.ams-form-wrap.ams-sticky-bar.ams-top{top:0;bottom:auto}
.ams-form-wrap.ams-sticky-bar.ams-visible{transform:translateY(0)}
.ams-form-wrap.ams-embedded{position:relative}
.ams-form-wrap.ams-embedded.ams-visible{opacity:1}
.ams-form-wrap.ams-spin{position:fixed;top:50%;left:50%;transform:translate(-50%,-48%);max-width:560px;width:95%}
.ams-form-wrap.ams-spin.ams-visible{transform:translate(-50%,-50%)}
.ams-form-inner{border-radius:8px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,.15)}
.ams-sticky-bar .ams-form-inner{border-radius:0;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.ams-form-body{padding:24px}
.ams-form-close{position:absolute;top:8px;right:12px;background:none;border:none;font-size:24px;cursor:pointer;color:inherit;opacity:.6;z-index:1}
.ams-form-close:hover{opacity:1}
.ams-form-header-img{width:100%;display:block}
.ams-form-title{margin:0 0 4px;font-size:22px;font-weight:700}
.ams-form-desc{margin:0 0 16px;font-size:14px;opacity:.8}
.ams-form-field{margin-bottom:12px}
.ams-form-field label{display:block;font-size:13px;font-weight:600;margin-bottom:4px}
.ams-form-field input,.ams-form-field select{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box}
.ams-form-field input:focus,.ams-form-field select:focus{outline:none;border-color:#4A90D9}
.ams-form-row{display:flex;gap:8px}
.ams-form-row .ams-form-field{flex:1}
.ams-form-checkbox{display:flex;align-items:flex-start;gap:8px;margin-bottom:12px;font-size:12px}
.ams-form-checkbox input{margin-top:2px;flex-shrink:0}
.ams-form-btn{display:block;width:100%;padding:12px;border:none;border-radius:4px;font-size:16px;font-weight:600;cursor:pointer;transition:background .2s ease}
.ams-form-success{text-align:center;padding:20px;font-size:16px}
.ams-form-error{color:#d63638;font-size:13px;margin-top:8px}
.ams-sticky-bar .ams-form-body{display:flex;align-items:center;gap:12px;padding:12px 20px}
.ams-sticky-bar .ams-form-field{margin-bottom:0;flex:1}
.ams-sticky-bar .ams-form-btn{width:auto;white-space:nowrap}
.ams-sticky-bar .ams-form-title{font-size:14px;margin:0;white-space:nowrap}
.ams-spin-container{display:flex;gap:20px;align-items:center;flex-wrap:wrap;justify-content:center}
.ams-spin-wheel{position:relative;flex-shrink:0}
.ams-spin-wheel canvas{display:block}
.ams-spin-pointer{position:absolute;top:50%;right:-10px;transform:translateY(-50%);width:0;height:0;border-top:12px solid transparent;border-bottom:12px solid transparent;border-right:16px solid #333}
.ams-spin-form{flex:1;min-width:200px}
.ams-form-radio-group,.ams-form-checkbox-group{display:flex;flex-direction:column;gap:6px}
.ams-form-radio-group label,.ams-form-checkbox-group label{display:flex;align-items:center;gap:6px;font-size:14px;font-weight:400;cursor:pointer}
        `;
        document.head.appendChild(style);
    }

    /* ── Trigger Engine ── */

    function setupTriggers(form, triggers, showFn) {
        const formId = form.id;
        if (isFreqCapped(formId, triggers.frequency_cap_days)) return;

        // Visitor type check.
        if (triggers.visitor_type) {
            const returning = isReturningVisitor();
            if (triggers.visitor_type === 'new' && returning) return;
            if (triggers.visitor_type === 'returning' && !returning) return;
        } else {
            isReturningVisitor(); // always set the flag
        }

        // Cart value check.
        if (triggers.cart_value_min && getCartTotal() < triggers.cart_value_min) return;

        // UTM check.
        if (triggers.utm_rules && triggers.utm_rules.length) {
            const match = triggers.utm_rules.every(rule => {
                const actual = getUtmParam(rule.param);
                return actual && actual.toLowerCase() === (rule.value || '').toLowerCase();
            });
            if (!match) return;
        }

        let triggered = false;
        const fire = () => { if (!triggered) { triggered = true; showFn(); } };

        // Embedded forms show immediately.
        if (form.type === 'embedded') { fire(); return; }

        // Scroll depth (Intersection Observer).
        if (triggers.scroll_depth) {
            const sentinel = document.createElement('div');
            sentinel.style.cssText = 'position:absolute;left:0;width:1px;height:1px;pointer-events:none';
            sentinel.style.top = triggers.scroll_depth + '%';
            document.body.style.position = document.body.style.position || 'relative';
            document.body.appendChild(sentinel);
            const obs = new IntersectionObserver(entries => {
                if (entries[0].isIntersecting) { fire(); obs.disconnect(); }
            });
            obs.observe(sentinel);
        }

        // Time on page.
        if (triggers.time_on_page) {
            setTimeout(fire, triggers.time_on_page * 1000);
        }

        // Exit intent (desktop only).
        if (triggers.exit_intent && !('ontouchstart' in window)) {
            const handler = e => {
                if (e.clientY < 5) { fire(); document.removeEventListener('mouseout', handler); }
            };
            document.addEventListener('mouseout', handler);
        }

        // Default: if no triggers set, show after 1 second.
        if (!triggers.scroll_depth && !triggers.time_on_page && !triggers.exit_intent) {
            setTimeout(fire, 1000);
        }
    }

    /* ── Form Rendering ── */

    function buildFormFields(fields, design) {
        const container = el('div', { className: 'ams-form-fields' });

        fields.forEach(field => {
            const fieldType = field.type || 'email';
            const fieldName = field.name || fieldType;
            const label = field.label || '';
            const required = field.required !== false;
            const placeholder = field.placeholder || '';

            if (fieldType === 'birthday') {
                const row = el('div', { className: 'ams-form-row' });
                const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const monthSelect = el('select', { name: 'birthday_month' },
                    ...months.map((m, i) => {
                        const opt = el('option', { value: i || '' }, m || 'Month');
                        return opt;
                    })
                );
                const daySelect = el('select', { name: 'birthday_day' },
                    el('option', { value: '' }, 'Day'),
                    ...Array.from({ length: 31 }, (_, i) => el('option', { value: i + 1 }, String(i + 1)))
                );

                const monthField = el('div', { className: 'ams-form-field' },
                    label ? el('label', null, label) : null,
                    monthSelect
                );
                const dayField = el('div', { className: 'ams-form-field' }, daySelect);
                row.appendChild(monthField);
                row.appendChild(dayField);
                container.appendChild(row);
                return;
            }

            if (fieldType === 'radio') {
                const group = el('div', { className: 'ams-form-field' },
                    label ? el('label', null, label) : null,
                    el('div', { className: 'ams-form-radio-group' },
                        ...(field.options || []).map(opt =>
                            el('label', null,
                                el('input', { type: 'radio', name: fieldName, value: opt }),
                                opt
                            )
                        )
                    )
                );
                container.appendChild(group);
                return;
            }

            if (fieldType === 'checkbox') {
                const group = el('div', { className: 'ams-form-field' },
                    label ? el('label', null, label) : null,
                    el('div', { className: 'ams-form-checkbox-group' },
                        ...(field.options || []).map(opt =>
                            el('label', null,
                                el('input', { type: 'checkbox', name: fieldName, value: opt }),
                                opt
                            )
                        )
                    )
                );
                container.appendChild(group);
                return;
            }

            if (fieldType === 'dropdown') {
                const selectEl = el('select', { name: fieldName },
                    el('option', { value: '' }, placeholder || 'Select...'),
                    ...(field.options || []).map(opt => el('option', { value: opt }, opt))
                );
                container.appendChild(el('div', { className: 'ams-form-field' },
                    label ? el('label', null, label) : null,
                    selectEl
                ));
                return;
            }

            if (fieldType === 'hidden') {
                container.appendChild(el('input', {
                    type: 'hidden',
                    name: fieldName,
                    value: field.value || ''
                }));
                return;
            }

            // Standard input fields: email, phone, first_name, last_name, text.
            const inputType = fieldType === 'email' ? 'email' : (fieldType === 'phone' ? 'tel' : 'text');
            const wrap = el('div', { className: 'ams-form-field' },
                label ? el('label', null, label) : null,
                el('input', {
                    type: inputType,
                    name: fieldName,
                    placeholder: placeholder,
                    ...(required ? { required: 'required' } : {})
                })
            );
            container.appendChild(wrap);
        });

        return container;
    }

    function buildForm(formData) {
        const { id, type, fields, design, success, triggers, spin_config } = formData;
        const d = design || {};

        const bgColor = d.background_color || '#ffffff';
        const textColor = d.text_color || '#333333';
        const btnColor = d.button_color || '#4A90D9';
        const btnTextColor = d.button_text_color || '#ffffff';
        const btnHoverColor = d.button_hover_color || '#3a7bc8';
        const borderRadius = (d.border_radius != null ? d.border_radius : 8) + 'px';
        const title = d.title || '';
        const description = d.description || '';
        const buttonText = d.button_text || 'Subscribe';
        const consentText = d.consent_text || '';
        const headerImage = d.header_image || '';

        // Determine wrapper class.
        let wrapClass = 'ams-form-wrap';
        if (type === 'modal') wrapClass += ' ams-modal';
        else if (type === 'flyout') wrapClass += ' ams-flyout' + (d.flyout_position === 'left' ? ' ams-left' : '');
        else if (type === 'full_page') wrapClass += ' ams-fullpage';
        else if (type === 'sticky_bar') wrapClass += ' ams-sticky-bar' + (d.bar_position === 'top' ? ' ams-top' : ' ams-bottom');
        else if (type === 'embedded') wrapClass += ' ams-embedded';
        else if (type === 'spin_to_win') wrapClass += ' ams-spin';

        const wrap = el('div', { className: wrapClass, 'data-ams-form': id });

        // Build inner content.
        const inner = el('div', { className: 'ams-form-inner', style: {
            background: bgColor, color: textColor, borderRadius: borderRadius
        }});

        // Close button (not for embedded or sticky bar).
        if (type !== 'embedded') {
            const closeBtn = el('button', {
                className: 'ams-form-close',
                'aria-label': 'Close',
                onClick: () => closeForm(id)
            }, '\u00d7');
            inner.appendChild(closeBtn);
        }

        // Header image.
        if (headerImage && type !== 'sticky_bar') {
            inner.appendChild(el('img', { className: 'ams-form-header-img', src: headerImage, alt: '' }));
        }

        const body = el('div', { className: 'ams-form-body' });

        if (type === 'spin_to_win' && spin_config) {
            buildSpinToWin(body, formData, d, btnColor, btnTextColor, btnHoverColor, borderRadius, buttonText, consentText, title, description);
        } else {
            // Title and description.
            if (title && type !== 'sticky_bar') body.appendChild(el('h3', { className: 'ams-form-title' }, title));
            if (description && type !== 'sticky_bar') body.appendChild(el('p', { className: 'ams-form-desc' }, description));
            if (title && type === 'sticky_bar') body.appendChild(el('span', { className: 'ams-form-title' }, title));

            // Fields.
            const formFields = buildFormFields(fields || [{ type: 'email', name: 'email', label: '', placeholder: 'Email address', required: true }], d);
            body.appendChild(formFields);

            // Consent checkbox.
            if (consentText && type !== 'sticky_bar') {
                const consentWrap = el('div', { className: 'ams-form-checkbox' },
                    el('input', { type: 'checkbox', name: 'gdpr_consent', id: 'ams-consent-' + id }),
                    el('label', { for: 'ams-consent-' + id })
                );
                consentWrap.lastChild.innerHTML = consentText;
                body.appendChild(consentWrap);
            }

            // Submit button.
            const btn = el('button', {
                className: 'ams-form-btn',
                style: { background: btnColor, color: btnTextColor, borderRadius: borderRadius },
                onMouseenter: function () { this.style.background = btnHoverColor; },
                onMouseleave: function () { this.style.background = btnColor; },
                onClick: () => submitForm(id)
            }, buttonText);
            body.appendChild(btn);

            // Error container.
            body.appendChild(el('div', { className: 'ams-form-error', id: 'ams-error-' + id }));
        }

        inner.appendChild(body);
        wrap.appendChild(inner);

        return wrap;
    }

    /* ── Spin-to-Win ── */

    function buildSpinToWin(body, formData, d, btnColor, btnTextColor, btnHoverColor, borderRadius, buttonText, consentText, title, description) {
        const segments = (formData.spin_config && formData.spin_config.segments) || [];
        const id = formData.id;

        const spinContainer = el('div', { className: 'ams-spin-container' });

        // Wheel.
        const wheelWrap = el('div', { className: 'ams-spin-wheel' });
        const canvas = el('canvas', { width: '240', height: '240', id: 'ams-wheel-' + id });
        wheelWrap.appendChild(canvas);
        wheelWrap.appendChild(el('div', { className: 'ams-spin-pointer' }));
        spinContainer.appendChild(wheelWrap);

        // Form side.
        const formSide = el('div', { className: 'ams-spin-form' });
        if (title) formSide.appendChild(el('h3', { className: 'ams-form-title' }, title));
        if (description) formSide.appendChild(el('p', { className: 'ams-form-desc' }, description));

        const fields = formData.fields || [{ type: 'email', name: 'email', placeholder: 'Email address', required: true }];
        formSide.appendChild(buildFormFields(fields, d));

        if (consentText) {
            const cw = el('div', { className: 'ams-form-checkbox' },
                el('input', { type: 'checkbox', name: 'gdpr_consent', id: 'ams-consent-' + id }),
                el('label', { for: 'ams-consent-' + id })
            );
            cw.lastChild.innerHTML = consentText;
            formSide.appendChild(cw);
        }

        formSide.appendChild(el('button', {
            className: 'ams-form-btn',
            style: { background: btnColor, color: btnTextColor, borderRadius: borderRadius },
            onMouseenter: function () { this.style.background = btnHoverColor; },
            onMouseleave: function () { this.style.background = btnColor; },
            onClick: () => submitSpinForm(id)
        }, buttonText));
        formSide.appendChild(el('div', { className: 'ams-form-error', id: 'ams-error-' + id }));

        spinContainer.appendChild(formSide);
        body.appendChild(spinContainer);

        // Draw wheel after render.
        requestAnimationFrame(() => drawWheel(canvas, segments));
    }

    function drawWheel(canvas, segments) {
        if (!canvas || !segments.length) return;
        const ctx = canvas.getContext('2d');
        const cx = canvas.width / 2;
        const cy = canvas.height / 2;
        const r = Math.min(cx, cy) - 4;
        const arc = (2 * Math.PI) / segments.length;

        const defaultColors = ['#4A90D9', '#E8544F', '#FFB74D', '#66BB6A', '#AB47BC', '#26C6DA', '#FF7043', '#78909C'];

        segments.forEach((seg, i) => {
            const startAngle = i * arc;
            const endAngle = (i + 1) * arc;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, r, startAngle, endAngle);
            ctx.closePath();
            ctx.fillStyle = seg.color || defaultColors[i % defaultColors.length];
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Label.
            ctx.save();
            ctx.translate(cx, cy);
            ctx.rotate(startAngle + arc / 2);
            ctx.textAlign = 'right';
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 11px sans-serif';
            const label = (seg.label || '').length > 14 ? seg.label.substring(0, 12) + '…' : (seg.label || '');
            ctx.fillText(label, r - 8, 4);
            ctx.restore();
        });
    }

    function animateWheel(formId, segmentIndex, totalSegments, callback) {
        const canvas = document.getElementById('ams-wheel-' + formId);
        if (!canvas) { callback(); return; }

        const arc = 360 / totalSegments;
        const targetAngle = 360 * 5 + (360 - (segmentIndex * arc + arc / 2));
        let current = 0;
        const duration = 4000;
        const start = performance.now();

        function animate(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            current = eased * targetAngle;
            canvas.style.transform = 'rotate(' + current + 'deg)';
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                callback();
            }
        }
        requestAnimationFrame(animate);
    }

    /* ── Show / Close ── */

    function showForm(formData) {
        const id = formData.id;
        if (shownForms.has(id)) return;
        shownForms.add(id);

        const wrapper = buildForm(formData);

        // Backdrop for modal, fullpage, spin.
        if (['modal', 'full_page', 'spin_to_win'].includes(formData.type)) {
            const backdrop = el('div', { className: 'ams-backdrop', 'data-ams-backdrop': id, onClick: () => closeForm(id) });
            document.body.appendChild(backdrop);
            requestAnimationFrame(() => backdrop.classList.add('ams-visible'));
        }

        document.body.appendChild(wrapper);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => wrapper.classList.add('ams-visible'));
        });

        // Record view.
        api('/forms/' + id + '/view', { method: 'POST' });

        // Store form data for submission.
        activeForms.push(formData);
    }

    function closeForm(formId) {
        const wrap = document.querySelector('[data-ams-form="' + formId + '"]');
        const backdrop = document.querySelector('[data-ams-backdrop="' + formId + '"]');

        if (wrap) {
            wrap.classList.remove('ams-visible');
            setTimeout(() => wrap.remove(), 300);
        }
        if (backdrop) {
            backdrop.classList.remove('ams-visible');
            setTimeout(() => backdrop.remove(), 300);
        }

        setFreqCap(formId);
    }

    function showSuccess(formId, message) {
        const wrap = document.querySelector('[data-ams-form="' + formId + '"]');
        if (!wrap) return;
        const body = wrap.querySelector('.ams-form-body');
        if (!body) return;
        body.innerHTML = '';
        body.appendChild(el('div', { className: 'ams-form-success' }, message));
        setTimeout(() => closeForm(formId), 4000);
    }

    /* ── Submission ── */

    function collectFormData(formId) {
        const wrap = document.querySelector('[data-ams-form="' + formId + '"]');
        if (!wrap) return {};

        const data = {};
        wrap.querySelectorAll('input, select').forEach(input => {
            const name = input.name;
            if (!name) return;

            if (input.type === 'checkbox' && name !== 'gdpr_consent') {
                if (!data[name]) data[name] = [];
                if (input.checked) data[name].push(input.value);
            } else if (input.type === 'radio') {
                if (input.checked) data[name] = input.value;
            } else if (input.type === 'checkbox' && name === 'gdpr_consent') {
                data[name] = input.checked ? '1' : '';
            } else {
                data[name] = input.value;
            }
        });

        return data;
    }

    function submitForm(formId) {
        const data = collectFormData(formId);
        const errorEl = document.getElementById('ams-error-' + formId);

        if (!data.email) {
            if (errorEl) errorEl.textContent = 'Please enter your email address.';
            return;
        }

        if (errorEl) errorEl.textContent = '';

        // Disable button.
        const btn = document.querySelector('[data-ams-form="' + formId + '"] .ams-form-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

        api('/forms/' + formId + '/submit', { method: 'POST', body: JSON.stringify(data) })
            .then(result => {
                if (result.success) {
                    setFreqCap(formId);
                    // Set subscriber cookie if we have a token.
                    if (result.data && result.data.redirect_url) {
                        window.location.href = result.data.redirect_url;
                        return;
                    }
                    showSuccess(formId, result.message || 'Thank you!');
                } else {
                    if (errorEl) errorEl.textContent = result.message || 'An error occurred.';
                    if (btn) { btn.disabled = false; btn.textContent = 'Subscribe'; }
                }
            })
            .catch(() => {
                if (errorEl) errorEl.textContent = 'Network error. Please try again.';
                if (btn) { btn.disabled = false; btn.textContent = 'Subscribe'; }
            });
    }

    function submitSpinForm(formId) {
        const data = collectFormData(formId);
        const errorEl = document.getElementById('ams-error-' + formId);

        if (!data.email) {
            if (errorEl) errorEl.textContent = 'Please enter your email address.';
            return;
        }
        if (errorEl) errorEl.textContent = '';

        const btn = document.querySelector('[data-ams-form="' + formId + '"] .ams-form-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Spinning…'; }

        // First submit the form (subscribe), then spin.
        api('/forms/' + formId + '/submit', { method: 'POST', body: JSON.stringify(data) })
            .then(subResult => {
                if (!subResult.success) {
                    if (errorEl) errorEl.textContent = subResult.message || 'An error occurred.';
                    if (btn) { btn.disabled = false; btn.textContent = 'Spin!'; }
                    return;
                }

                // Get spin result from server.
                return api('/forms/' + formId + '/spin', { method: 'POST', body: JSON.stringify({ email: data.email }) })
                    .then(spinResult => {
                        const formState = activeForms.find(f => f.id === formId);
                        const totalSegments = (formState && formState.spin_config && formState.spin_config.segments)
                            ? formState.spin_config.segments.length : 1;

                        animateWheel(formId, spinResult.segment_index, totalSegments, () => {
                            let msg = spinResult.label || 'Congratulations!';
                            if (spinResult.coupon_code) {
                                msg += '\nYour code: ' + spinResult.coupon_code;
                            }
                            setFreqCap(formId);
                            showSuccess(formId, msg);
                        });
                    });
            })
            .catch(() => {
                if (errorEl) errorEl.textContent = 'Network error. Please try again.';
                if (btn) { btn.disabled = false; btn.textContent = 'Spin!'; }
            });
    }

    /* ── Embedded Form Shortcode Support ── */

    function renderEmbeddedForms(forms) {
        forms.filter(f => f.type === 'embedded').forEach(form => {
            const targets = document.querySelectorAll('[data-ams-embed="' + form.id + '"]');
            targets.forEach(target => {
                const wrapper = buildForm(form);
                target.appendChild(wrapper);
                requestAnimationFrame(() => wrapper.classList.add('ams-visible'));
                api('/forms/' + form.id + '/view', { method: 'POST' });
                activeForms.push(form);
            });
        });
    }

    /* ── Init ── */

    function init() {
        injectGlobalStyles();

        api('/forms/active?page_id=' + PAGE_ID)
            .then(forms => {
                if (!forms || !forms.length) return;

                // Handle embedded forms first.
                renderEmbeddedForms(forms);

                // Handle triggered forms.
                forms.filter(f => f.type !== 'embedded').forEach(form => {
                    setupTriggers(form, form.triggers || {}, () => showForm(form));
                });
            })
            .catch(() => { /* Silently fail — no forms to show */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
