// Utilidades UI
document.addEventListener('DOMContentLoaded', () => {
    // Autocierre de alertas
    document.querySelectorAll('.alert-dismissible').forEach(a => {
        setTimeout(() => { try { bootstrap.Alert.getOrCreateInstance(a).close(); } catch (e) {} }, 5000);
    });

    // Desplegables del topnav: posición "fixed" para que no queden recortados
    // por el overflow-x:auto del contenedor de scroll horizontal.
    document.querySelectorAll('.tn-dropdown [data-bs-toggle="dropdown"]').forEach(el => {
        bootstrap.Dropdown.getOrCreateInstance(el, {
            popperConfig: (defaultConfig) => ({ ...defaultConfig, strategy: 'fixed' })
        });
    });

    // Selects de operador con filtro de búsqueda en vivo (escribir para filtrar).
    // Si el <select> tiene el atributo "multiple", permite elegir varios operadores (chips).
    document.querySelectorAll('select.js-op-select').forEach(select => {
        const isMulti = select.multiple;
        const wrap = document.createElement('div');
        wrap.className = 'op-select-wrap' + (isMulti ? ' op-select-multi' : '');
        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);
        select.classList.add('op-select-native');
        select.tabIndex = -1;

        let chips = null;
        if (isMulti) {
            chips = document.createElement('div');
            chips.className = 'op-select-chips';
            wrap.appendChild(chips);
        }

        const input = document.createElement('input');
        input.type = 'text';
        input.className = select.className.replace('op-select-native', '').trim() || 'form-control form-control-sm';
        input.placeholder = isMulti ? 'Buscar y agregar operadores...' : 'Buscar operador...';
        input.autocomplete = 'off';
        wrap.appendChild(input);

        const menu = document.createElement('div');
        menu.className = 'op-select-menu';
        wrap.appendChild(menu);

        const options = Array.from(select.options).filter(o => o.value).map(o => ({ value: o.value, text: o.textContent, opt: o }));
        let lastText = '';

        const renderChips = () => {
            if (!isMulti) return;
            chips.innerHTML = '';
            options.filter(o => o.opt.selected).forEach(o => {
                const chip = document.createElement('span');
                chip.className = 'op-chip';
                chip.textContent = o.text;
                const x = document.createElement('i');
                x.className = 'bi bi-x-lg';
                x.addEventListener('mousedown', e => {
                    e.preventDefault();
                    o.opt.selected = false;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    renderChips();
                });
                chip.appendChild(x);
                chips.appendChild(chip);
            });
        };

        if (isMulti) {
            renderChips();
        } else {
            const initSel = select.options[select.selectedIndex];
            lastText = (initSel && initSel.value) ? initSel.textContent : '';
            input.value = lastText;
        }

        const renderMenu = (filter) => {
            const q = filter.trim().toLowerCase();
            let pool = isMulti ? options.filter(o => !o.opt.selected) : options;
            const matches = q ? pool.filter(o => o.text.toLowerCase().includes(q)) : pool;
            menu.innerHTML = '';
            if (!matches.length) {
                menu.innerHTML = '<div class="op-select-empty">Sin resultados</div>';
            } else {
                matches.forEach(o => {
                    const item = document.createElement('div');
                    item.className = 'op-select-item';
                    item.textContent = o.text;
                    item.addEventListener('mousedown', e => {
                        e.preventDefault();
                        if (isMulti) {
                            o.opt.selected = true;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            renderChips();
                            input.value = '';
                            renderMenu('');
                            input.focus();
                        } else {
                            select.value = o.value;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            input.value = o.text;
                            lastText = o.text;
                            menu.classList.remove('show');
                        }
                    });
                    menu.appendChild(item);
                });
            }
        };

        input.addEventListener('focus', () => {
            renderMenu(isMulti ? input.value : (input.value === lastText ? '' : input.value));
            menu.classList.add('show');
        });
        input.addEventListener('input', () => {
            if (!isMulti) select.value = '';
            renderMenu(input.value);
            menu.classList.add('show');
        });
        input.addEventListener('blur', () => setTimeout(() => menu.classList.remove('show'), 150));
        input.addEventListener('keydown', e => { if (e.key === 'Escape') { menu.classList.remove('show'); input.blur(); } });

        // Sincroniza los chips/input visibles con el <select> real (usado tras form.reset())
        select.opSelectSync = () => {
            if (isMulti) {
                renderChips();
            } else {
                const sel = select.options[select.selectedIndex];
                lastText = (sel && sel.value) ? sel.textContent : '';
                input.value = lastText;
            }
            renderMenu('');
            menu.classList.remove('show');
        };

        select.addEventListener('change', () => {
            if (select.opSelectSync) select.opSelectSync();
        });
    });

    // Al cerrar cualquier modal (Cancelar, X, Escape) se descarta lo que se estaba editando:
    // el formulario vuelve a sus valores por defecto y los selects de operador (chips) se vacían.
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', () => {
            modal.querySelectorAll('form').forEach(f => f.reset());
            modal.querySelectorAll('select.js-op-select').forEach(sel => { if (sel.opSelectSync) sel.opSelectSync(); });
            modal.querySelectorAll('select.js-act-select, select.js-lugar-select, select.js-context-select').forEach(sel => { if (sel.otroSync) sel.otroSync(); });
        });
    });

    // Select "Actividad": si eligen OTRO, muestra un campo de texto + botones para escribir/guardar una nueva
    const addActivityOption = nombre => {
        document.querySelectorAll('select.js-act-select').forEach(otherSel => {
            if (Array.from(otherSel.options).some(o => o.value === nombre)) return;
            const otroOpt = Array.from(otherSel.options).find(o => o.value === 'OTROS');
            const opt = new Option(nombre, nombre);
            otherSel.insertBefore(opt, otroOpt || null);
        });
    };

    document.querySelectorAll('select.js-act-select').forEach(sel => {
        const form = sel.closest('form');
        const otroWrap = form.querySelector('.js-act-otro-wrap');
        const otroInput = otroWrap.querySelector('.js-act-otro');
        const otroOk = otroWrap.querySelector('.js-act-otro-ok');
        const otroCancel = otroWrap.querySelector('.js-act-otro-cancel');
        const fieldName = sel.dataset.fieldName || sel.name;
        const sync = () => {
            if (sel.value === 'OTROS') {
                otroWrap.classList.remove('d-none');
                otroInput.name = fieldName;
                sel.removeAttribute('name');
                otroInput.focus();
            } else {
                otroWrap.classList.add('d-none');
                otroInput.removeAttribute('name');
                otroInput.value = '';
                sel.name = fieldName;
            }
        };
        sel.addEventListener('change', sync);
        sync();
        sel.otroSync = sync;

        otroInput.addEventListener('input', () => {
            const pos = otroInput.selectionStart;
            otroInput.value = otroInput.value.toUpperCase();
            otroInput.setSelectionRange(pos, pos);
        });

        otroCancel.addEventListener('click', () => {
            sel.value = '';
            sync();
        });

        otroOk.addEventListener('click', () => {
            const nombre = otroInput.value.trim().toUpperCase();
            if (!nombre) { otroInput.focus(); return; }
            otroOk.disabled = true;
            fetch('?action=activity_add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'nombre=' + encodeURIComponent(nombre),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        addActivityOption(data.nombre);
                        sel.value = data.nombre;
                        sync();
                    }
                })
                .finally(() => { otroOk.disabled = false; });
        });
    });

    // Select "Lugar": si eligen OTRO, muestra un campo de texto + botones para escribir/guardar un nuevo lugar
    const addLugarOption = (nombre, area) => {
        document.querySelectorAll('select.js-lugar-select').forEach(otherSel => {
            if (otherSel.dataset.area !== area) return;
            if (Array.from(otherSel.options).some(o => o.value === nombre)) return;
            const otroOpt = Array.from(otherSel.options).find(o => o.value === 'OTROS');
            const opt = new Option(nombre, nombre);
            otherSel.insertBefore(opt, otroOpt || null);
        });
    };

    document.querySelectorAll('select.js-lugar-select').forEach(sel => {
        const form = sel.closest('form');
        const otroWrap = form.querySelector('.js-lugar-otro-wrap');
        const otroInput = otroWrap.querySelector('.js-lugar-otro');
        const otroOk = otroWrap.querySelector('.js-lugar-otro-ok');
        const otroCancel = otroWrap.querySelector('.js-lugar-otro-cancel');
        const fieldName = sel.dataset.fieldName || sel.name;
        const sync = () => {
            if (sel.value === 'OTROS') {
                otroWrap.classList.remove('d-none');
                otroInput.name = fieldName;
                sel.removeAttribute('name');
                otroInput.focus();
            } else {
                otroWrap.classList.add('d-none');
                otroInput.removeAttribute('name');
                otroInput.value = '';
                sel.name = fieldName;
            }
        };
        sel.addEventListener('change', sync);
        sync();
        sel.otroSync = sync;

        otroInput.addEventListener('input', () => {
            const pos = otroInput.selectionStart;
            otroInput.value = otroInput.value.toUpperCase();
            otroInput.setSelectionRange(pos, pos);
        });

        otroCancel.addEventListener('click', () => {
            sel.value = '';
            sync();
        });

        otroOk.addEventListener('click', () => {
            const lugar = otroInput.value.trim().toUpperCase();
            if (!lugar) { otroInput.focus(); return; }
            const area = sel.dataset.area || '';
            otroOk.disabled = true;
            fetch('?action=lugar_add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'nombre=' + encodeURIComponent(lugar) + '&area=' + encodeURIComponent(area),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        addLugarOption(data.nombre, data.area);
                        sel.value = data.nombre;
                        sync();
                    }
                })
                .finally(() => { otroOk.disabled = false; });
        });
    });

    // Select "Contexto": si eligen OTROS, muestra un campo de texto + botones para escribir/guardar un nuevo tipo de maniobra
    const addContextOption = (nombre, area) => {
        document.querySelectorAll('select.js-context-select').forEach(otherSel => {
            if (otherSel.dataset.area !== area) return;
            if (Array.from(otherSel.options).some(o => o.value === nombre)) return;
            const otroOpt = Array.from(otherSel.options).find(o => o.value === 'OTROS');
            const opt = new Option(nombre, nombre);
            otherSel.insertBefore(opt, otroOpt || null);
        });
    };

    document.querySelectorAll('select.js-context-select').forEach(sel => {
        const form = sel.closest('form');
        const otroWrap = form.querySelector('.js-context-otro-wrap');
        const otroInput = otroWrap.querySelector('.js-context-otro');
        const otroOk = otroWrap.querySelector('.js-context-otro-ok');
        const otroCancel = otroWrap.querySelector('.js-context-otro-cancel');
        const fieldName = sel.dataset.fieldName || sel.name;
        const sync = () => {
            if (sel.value === 'OTROS') {
                otroWrap.classList.remove('d-none');
                otroInput.name = fieldName;
                sel.removeAttribute('name');
                otroInput.focus();
            } else {
                otroWrap.classList.add('d-none');
                otroInput.removeAttribute('name');
                otroInput.value = '';
                sel.name = fieldName;
            }
        };
        sel.addEventListener('change', sync);
        sync();
        sel.otroSync = sync;

        otroInput.addEventListener('input', () => {
            const pos = otroInput.selectionStart;
            otroInput.value = otroInput.value.toUpperCase();
            otroInput.setSelectionRange(pos, pos);
        });

        otroCancel.addEventListener('click', () => {
            sel.value = '';
            sync();
        });

        otroOk.addEventListener('click', () => {
            const contexto = otroInput.value.trim().toUpperCase();
            if (!contexto) { otroInput.focus(); return; }
            const area = sel.dataset.area || '';
            const fetchAction = sel.dataset.action || 'context_add';
            otroOk.disabled = true;
            fetch('?action=' + fetchAction, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'nombre=' + encodeURIComponent(contexto) + '&area=' + encodeURIComponent(area),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        addContextOption(data.nombre, data.area);
                        sel.value = data.nombre;
                        sync();
                    }
                })
                .finally(() => { otroOk.disabled = false; });
        });
    });

    // Suma en vivo de campos de tiempo (horas => min, velocidad => seg)
    const liveSum = (selector, outSel, parser, fmt) => {
        const out = document.querySelector(outSel);
        if (!out) return;
        const inputs = document.querySelectorAll(selector);
        const recalc = () => {
            let t = 0;
            inputs.forEach(i => t += parser(i.value));
            out.textContent = fmt(t);
        };
        inputs.forEach(i => i.addEventListener('input', recalc));
        recalc();
    };
    const parseMin = v => {
        v = (v || '').trim();
        if (!v) return 0;
        if (v.includes(':')) { const p = v.split(':').map(Number); return (p[0] || 0) * 60 + (p[1] || 0); }
        return Math.round(parseFloat(v) || 0);
    };
    const parseSec = v => {
        v = (v || '').trim();
        if (!v) return 0;
        if (v.includes(':')) {
            const p = v.split(':').map(Number);
            if (p.length === 3) return p[0] * 3600 + p[1] * 60 + p[2];
            return (p[0] || 0) * 60 + (p[1] || 0);
        }
        return Math.round(parseFloat(v) || 0);
    };
    const fmtMin = t => Math.floor(t / 60) + ':' + String(t % 60).padStart(2, '0') + ' h';
    const fmtSec = t => {
        const m = Math.floor(t / 60), s = t % 60;
        return (t >= 3600 ? Math.floor(t / 3600) + ':' + String(Math.floor((t % 3600) / 60)).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0');
    };
    liveSum('.js-min-input', '#js-total-min', parseMin, fmtMin);
    liveSum('.js-sec-input', '#js-total-sec', parseSec, fmtSec);

    // Score en vivo del formulario de habilidades (escala 1-5)
    const scaleForm = document.querySelector('#skill-form[data-tipo="escala"], #skill-form[data-tipo="trinivel"]');
    if (scaleForm) {
        const out = document.getElementById('js-skill-score');
        const recalc = () => {
            let sum = 0;
            scaleForm.querySelectorAll('input[type=radio]:checked').forEach(r => { sum += +r.value; });
            const totalItems = scaleForm.querySelectorAll('.rate-group').length;
            const maxPoints = totalItems * (scaleForm.dataset.tipo === 'trinivel' ? 2 : 5);
            out.textContent = maxPoints ? (sum / maxPoints * 100).toFixed(1) + ' %' : '0.0 %';
        };
        scaleForm.querySelectorAll('input[type=radio]').forEach(r => r.addEventListener('change', recalc));
        recalc();
    }
    // Score en vivo (check ponderado)
    const checkForm = document.querySelector('#skill-form[data-tipo="check"]');
    if (checkForm) {
        const out = document.getElementById('js-skill-score');
        const recalc = () => {
            let score = 0;
            checkForm.querySelectorAll('[data-grupo]').forEach(g => {
                const peso = +g.dataset.peso;
                const boxes = g.querySelectorAll('input[type=checkbox]');
                const ok = g.querySelectorAll('input[type=checkbox]:checked').length;
                if (boxes.length) score += ok / boxes.length * peso;
            });
            out.textContent = score.toFixed(1) + ' %';
        };
        checkForm.querySelectorAll('input[type=checkbox]').forEach(c => c.addEventListener('change', recalc));
        recalc();
    }

    // Manejo de etiquetas Aprobado/Desaprobado en los switches de habilidades
    document.querySelectorAll('.js-skill-switch').forEach(sw => {
        const row = sw.closest('.d-flex');
        const statusSpan = row ? row.querySelector('.js-switch-status') : null;
        const updateStatus = () => {
            if (!statusSpan) return;
            if (sw.checked) {
                statusSpan.textContent = 'Aprobado';
                statusSpan.className = 'badge rounded-pill js-switch-status bg-success-subtle text-success border border-success-subtle';
            } else {
                statusSpan.textContent = 'Desaprobado';
                statusSpan.className = 'badge rounded-pill js-switch-status bg-danger-subtle text-danger border border-danger-subtle';
            }
        };
        sw.addEventListener('change', updateStatus);
        updateStatus();

        // Asegurar que el reset del formulario también las actualice
        const form = sw.closest('form');
        if (form) {
            form.addEventListener('reset', () => {
                setTimeout(updateStatus, 10);
            });
        }
    });

    // Validación del formulario de habilidades antes de guardar
    const skillFormObj = document.getElementById('skill-form');
    if (skillFormObj) {
        skillFormObj.addEventListener('submit', e => {
            // 1. Validar Operador
            const opSelect = skillFormObj.querySelector('[name="operator_id"]');
            if (opSelect && !opSelect.value) {
                e.preventDefault();
                alert('Debe seleccionar un operador.');
                return;
            }

            // 2. Validar Fecha
            const fechaInput = skillFormObj.querySelector('[name="fecha"]');
            if (fechaInput && !fechaInput.value) {
                e.preventDefault();
                alert('Debe ingresar la fecha.');
                fechaInput.focus();
                return;
            }

            // 3. Validar Lugar de instrucción (contexto)
            const contextoSelect = skillFormObj.querySelector('[name="contexto"]');
            if (contextoSelect && !contextoSelect.value) {
                e.preventDefault();
                alert('Debe seleccionar el lugar de instrucción.');
                return;
            }

            // 4. Validar que cada fila de habilidad tenga una calificación en escala
            if (skillFormObj.dataset.tipo === 'escala' || skillFormObj.dataset.tipo === 'trinivel') {
                const rateGroups = skillFormObj.querySelectorAll('.rate-group');
                let missing = false;
                rateGroups.forEach(group => {
                    const checked = group.querySelector('input[type=radio]:checked');
                    if (!checked) {
                        missing = true;
                        group.closest('tr').style.backgroundColor = 'rgba(220, 53, 69, 0.08)';
                    } else {
                        group.closest('tr').style.backgroundColor = '';
                    }
                });
                if (missing) {
                    e.preventDefault();
                    alert('Debe calificar todos los campos de habilidades antes de guardar.');
                    return;
                }
            }

            // 5. Validar Instructor
            const instructorSelect = skillFormObj.querySelector('[name="evaluador"]');
            if (instructorSelect && !instructorSelect.value) {
                e.preventDefault();
                alert('Debe seleccionar un instructor.');
                return;
            }
        });
    }

    // Filtro rápido de tablas
    const filtro = document.getElementById('js-table-filter');
    if (filtro) {
        filtro.addEventListener('input', () => {
            if (window.applyTableFilters) {
                window.applyTableFilters();
                return;
            }
            const q = filtro.value.toLowerCase();
            document.querySelectorAll('table.js-filterable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
            if (window.onTableFilterChange) window.onTableFilterChange();
        });
    }

    // Modal de edición genérico: rellena inputs desde data-json del botón
    document.querySelectorAll('[data-fill-form]').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.fillForm);
            const form = document.querySelector(btn.dataset.target + ' form') || document.querySelector(btn.dataset.target);
            Object.entries(data).forEach(([k, v]) => {
                const el = form.querySelector('[name="' + k + '"]');
                if (!el) return;
                if (el.type === 'checkbox') el.checked = !!+v;
                else {
                    el.value = v ?? '';
                    el.dispatchEvent(new Event('change'));
                }
            });
        });
    });

    // Auto-dismiss floating alerts after 4 seconds
    document.querySelectorAll('.alert-floating').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 4000);
    });
});
