(function () {
    'use strict';

    const cfg = window.SBA;
    if (!cfg) return;

    function field(form, name) {
        return form.elements.namedItem(name);
    }

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }

    /* ── API ── */

    async function api(action, data) {
        const body = data instanceof FormData ? data : new FormData();
        if (!(data instanceof FormData)) {
            for (const [key, value] of Object.entries(data || {})) body.append(key, value);
        }
        body.set('action', action);
        body.set('token', cfg.token);
        try {
            const res  = await fetch(cfg.api, { method: 'POST', body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = JSON.parse(await res.text());
            if (json && typeof json === 'object') return json;
        } catch (err) {
            // fall through: not JSON (session gone, PHP error page) or network down
        }
        return { ok: false, message: 'The server did not answer as expected. Reload the page and try again.' };
    }

    const tray = el('div', 'sba-toasts');
    tray.setAttribute('aria-live', 'polite');
    document.body.append(tray);

    function toast(message, kind) {
        const box   = el('div', 'sba-toast is-' + kind);
        const close = el('button', 'sba-x', '×');
        const list  = el('ul');
        close.type = 'button';
        close.setAttribute('aria-label', 'Close');
        close.addEventListener('click', () => box.remove());
        box.append(close, el('div', 'sba-toast-text', message), list);
        tray.append(box);
        const settle = (state) => {
            box.className = 'sba-toast is-' + state;
            setTimeout(() => box.remove(), state === 'ok' ? 6000 : 12000);
        };
        if (kind !== 'busy') settle(kind);
        return { box, list, settle };
    }

    function toastLine(list, line) {
        const item = el('li', 'is-' + line.state);
        item.append(el('span', 'sba-toast-label', line.label), el('span', 'sba-toast-state', line.message));
        list.append(item);
        return item;
    }

    function flash(entry) {
        try { sessionStorage.setItem('sba-flash', JSON.stringify(entry)); } catch (err) { /* storage off */ }
    }

    try {
        const saved = JSON.parse(sessionStorage.getItem('sba-flash') || 'null');
        sessionStorage.removeItem('sba-flash');
        if (saved) {
            const t = toast(saved.message, saved.kind);
            (saved.lines || []).forEach(line => toastLine(t.list, line));
        }
    } catch (err) { /* storage off */ }

    // Follow-ups on the game servers, in parallel; resolves to the toast state and lines
    async function runTasks(message, tasks) {
        const t = toast(message, tasks.length ? 'busy' : 'ok');
        const lines = [];
        await Promise.all(tasks.map(async task => {
            const line = { label: task.label, message: '…', state: 'busy' };
            const item = toastLine(t.list, line);
            const data = await api(task.action, { id: task.id || 0, sid: task.sid || 0 });
            line.message = data.message;
            line.state   = data.ok ? 'ok' : 'error';
            item.className = 'is-' + line.state;
            item.lastChild.textContent = line.message;
            lines.push(line);
        }));
        const kind = lines.some(line => line.state === 'error') ? 'warn' : 'ok';
        if (tasks.length) t.settle(kind);
        return { kind, lines };
    }

    async function finish(data, form) {
        form?.closest('dialog')?.close();
        const result = await runTasks(data.message, data.tasks || []);
        if (data.then || data.reload) {
            flash({ message: data.message, kind: result.kind, lines: result.lines });
            if (data.then) location.href = data.then; else location.reload();
            return;
        }
        if (data.reset && form) resetForm(form);
        // Banned or blocked from the players of a server: list them again (a banned player is kicked by now)
        if (form?.closest('#sba-player-ban, #sba-player-comm, #sba-amx-ban, #sba-amx-kick, #sba-amx-offline') && playersFrom?.isConnected) loadPlayers(playersFrom);
    }

    /* ── Forms ── */

    function clearErrors(form) {
        form.querySelectorAll('[data-error-for]').forEach(slot => { slot.textContent = ''; });
        form.querySelectorAll('.is-invalid').forEach(field => field.classList.remove('is-invalid'));
        const summary = form.querySelector('.sba-form-error');
        if (summary) summary.textContent = '';
    }

    function showErrors(form, data) {
        let first = null;
        for (const [name, message] of Object.entries(data.errors || {})) {
            const slot  = form.querySelector(`[data-error-for="${CSS.escape(name)}"]`);
            const input = field(form, name);
            if (slot) slot.textContent = message;
            if (input instanceof Element) {
                input.classList.add('is-invalid');
                first = first || input;
            }
        }
        const summary = form.querySelector('.sba-form-error');
        if (summary) summary.textContent = data.message || 'Something went wrong.';
        else toast(data.message || 'Something went wrong.', 'error');
        first?.focus();
    }

    // Presets (length, reason) follow the value of their field; "other" shows the amount and unit
    function syncPresets(form) {
        form.querySelectorAll('[data-sba-presets]').forEach(box => {
            const input  = field(form, box.dataset.sbaPresets);
            const value  = input ? input.value : '';
            const custom = box.parentElement.querySelector('.sba-custom');
            let matched  = false;
            box.querySelectorAll('.sba-preset').forEach(button => {
                const on = button.dataset.value === value && value !== '';
                button.classList.toggle('is-active', on);
                matched = matched || on;
            });
            if (!custom) return;
            const other = box.querySelector('[data-value="other"]');
            if (!matched && Number(value) > 0) {
                const unit = [43200, 10080, 1440, 60, 1].find(size => Number(value) % size === 0);
                custom.querySelector('[data-sba-unit]').value   = String(unit);
                custom.querySelector('[data-sba-amount]').value = Number(value) / unit;
                other.classList.add('is-active');
                custom.hidden = false;
            } else if (!other.classList.contains('is-active')) {
                custom.hidden = true;
            }
        });
    }

    function syncWhen(form) {
        form.querySelectorAll('[data-sba-when]').forEach(part => {
            const [name, value] = part.dataset.sbaWhen.split('=');
            part.hidden = field(form, name)?.value !== value;
        });
    }

    document.querySelectorAll('form[data-sba-action] input[type=hidden]').forEach(input => {
        input.dataset.sbaDefault = input.value;
    });

    function resetForm(form) {
        form.reset();
        form.querySelectorAll('input[type=hidden][data-sba-default]').forEach(input => {
            input.value = input.dataset.sbaDefault;
        });
        clearErrors(form);
        form.querySelectorAll('.sba-preset.is-active').forEach(button => button.classList.remove('is-active'));
        form.querySelectorAll('.sba-steam').forEach(box => { box.hidden = true; box.replaceChildren(); });
        form.querySelectorAll('[data-sba-auto]').forEach(field => { delete field.dataset.sbaAuto; });
        syncPresets(form);
        syncWhen(form);
    }

    function fill(form, values) {
        for (const [key, value] of Object.entries(values)) {
            if (key.startsWith('@')) {
                const slot = form.querySelector(`[data-sba-text="${CSS.escape(key.slice(1))}"]`);
                if (slot) slot.textContent = value;
                continue;
            }
            form.querySelectorAll(`[name="${CSS.escape(key)}"]`).forEach(field => {
                if (field.type === 'checkbox') {
                    field.checked = Array.isArray(value) ? value.map(String).includes(field.value) : ['1', 1, true].includes(value);
                } else if (field.type === 'radio') {
                    field.checked = field.value === String(value);
                } else {
                    field.value = value ?? '';
                }
            });
        }
        syncPresets(form);
        syncWhen(form);
    }

    function openDialog(dialog, values, title) {
        const form = dialog.querySelector('form');
        form.dataset.sbaAdd = form.dataset.sbaAdd || form.dataset.sbaAction;
        form.dataset.sbaAction = values?._action || form.dataset.sbaAdd;
        resetForm(form);
        if (title) dialog.querySelector('.sba-dialog-title').textContent = title;
        if (values) {
            fill(form, values);
            // A Steam ID given in advance gets its profile preview too (it tells admins and banned players)
            form.querySelectorAll('[data-sba-steam]').forEach(input => { if (input.value.trim()) lookup(input); });
        }
        dialog.showModal();
        form.querySelector('input:not([type=hidden]):not([disabled]), select, textarea')?.focus();
    }

    function confirmBox(message, label, danger) {
        return new Promise(resolve => {
            const dialog = el('dialog', 'sba-dialog is-confirm');
            const body   = el('div', 'sba-dialog-body hlstats-scrollbar');
            const foot   = el('div', 'sba-form-foot');
            const cancel = el('button', 'sba-btn', 'Cancel');
            const ok     = el('button', 'sba-btn ' + (danger ? 'is-danger is-solid' : 'is-primary'), label);
            cancel.type = ok.type = 'button';
            body.append(el('p', '', message));
            foot.append(cancel, ok);
            dialog.append(body, foot);
            document.body.append(dialog);
            let answer = false;
            cancel.addEventListener('click', () => dialog.close());
            ok.addEventListener('click', () => { answer = true; dialog.close(); });
            dialog.addEventListener('close', () => { dialog.remove(); resolve(answer); });
            dialog.showModal();
            ok.focus();
        });
    }

    async function doAction(button) {
        const danger = button.classList.contains('is-danger');
        if (button.dataset.sbaConfirm && !(await confirmBox(button.dataset.sbaConfirm, button.textContent.trim(), danger))) return;
        button.disabled = true;
        const data = await api(button.dataset.sbaDo, { id: button.dataset.sbaId || 0 });
        button.disabled = false;
        if (!data.ok) {
            toast(data.message, 'error');
            return;
        }
        if (button.dataset.sbaThen) data.then = button.dataset.sbaThen;
        await finish(data, null);
    }

    /* ── Steam profile preview ── */

    async function lookup(input) {
        const box   = input.closest('.sba-field').nextElementSibling;
        const value = input.value.trim();
        if (!value) {
            box.hidden = true;
            box.replaceChildren();
            return;
        }
        box.hidden = false;
        box.className = 'sba-steam is-loading';
        box.textContent = 'Looking up the Steam profile…';
        const data = await api('steam.lookup', { steam: value });
        if (input.value.trim() !== value) return;   // typed on since
        box.replaceChildren();
        if (!data.ok) {
            box.className = 'sba-steam is-error';
            box.textContent = data.message;
            return;
        }
        box.className = 'sba-steam';
        if (/^https:\/\//.test(data.avatar || '')) {
            const img = el('img', 'sba-avatar');
            img.src = data.avatar;
            img.alt = '';
            box.append(img);
        }
        const info = el('div', 'sba-steam-info');
        info.append(el('div', 'sba-steam-name', data.name || 'Steam profile not available'), el('div', 'sba-muted', data.ids.steam2 + ' · ' + data.ids.steam3));
        const flags = el('div', 'sba-steam-flags');
        if (data.admin) flags.append(el('span', 'sba-badge is-warn', 'Admin: ' + data.admin));
        if (data.banned) flags.append(el('span', 'sba-badge is-danger', 'Banned now'));
        const link = el('a', 'sba-steam-link', 'Steam profile ↗');
        link.href = 'https://steamcommunity.com/profiles/' + data.ids.id64;
        link.target = '_blank';
        link.rel = 'noopener';
        box.append(info, flags, link);

        // Offer the Steam name unless one was typed
        const target = field(input.form, input.dataset.sbaSteam);
        let name = data.name || '';
        if (input.form.dataset.sbaAction?.startsWith('admin.')) name = name.replaceAll("'", '');
        if (target && name && (!target.value || target.value === target.dataset.sbaAuto)) {
            target.value = name;
            target.dataset.sbaAuto = name;
        }
    }

    /* ── Lists: searched on the server, a page at a time ── */

    function search(input) {
        const url   = new URL(location.href);
        const query = input.value.trim();
        if (query) url.searchParams.set('q', query); else url.searchParams.delete('q');
        url.searchParams.delete(input.dataset.sbaPage);
        url.searchParams.set('ajax', input.dataset.sbaSearch);
        history.replaceState({ fetchUrl: url.href, fetchTarget: input.dataset.sbaSearch }, '', url.href);
        Fetch.run(url.href, input.dataset.sbaSearch, false);
    }

    document.addEventListener('fetch:loaded', event => {
        const list  = event.target;
        const count = list.querySelector('[data-sba-count]');
        const slot  = list.id ? document.querySelector(`[data-sba-count-of="${CSS.escape(list.id)}"]`) : null;
        if (count && slot) slot.textContent = count.dataset.sbaCount;
    });

    /* ── Servers list: the players of a server under its row, from RCON "status" ── */

    let playersRow  = null;   // server row whose players are shown
    let playersFrom = null;   // server row a ban or block dialog was opened from

    async function loadPlayers(row) {
        const box = row.nextElementSibling.querySelector('.sba-players');
        box.classList.add('is-loading');
        if (!box.firstChild) box.textContent = 'Asking the server who is playing…';
        // AMXBans servers have their own action (data-sba-players-action)
        const data = await api(row.dataset.sbaPlayersAction || 'server.players', { sid: row.dataset.sbaPlayers });
        box.classList.remove('is-loading');
        if (data.ok) box.innerHTML = data.html;
        else box.replaceChildren(el('p', 'sba-players-error', data.message));
    }

    function showPlayers(row, open) {
        let panel = row.nextElementSibling;
        if (open && !panel?.classList.contains('sb-details-row')) {
            panel = el('tr', 'sb-details-row');
            const cell = panel.insertCell();
            cell.colSpan = row.cells.length;
            cell.append(el('div', 'sba sba-players'));
            row.after(panel);
        }
        if (panel?.classList.contains('sb-details-row')) panel.hidden = !open;
        row.classList.toggle('is-open', open);
        if (open) loadPlayers(row);   // who is on changes: ask again at each opening
    }

    // One server open at a time; the paging of the list replaces the rows, so rows are compared, not kept
    function togglePlayers(row) {
        const open = row !== playersRow;
        if (playersRow) showPlayers(playersRow, false);
        playersRow = open ? row : null;
        if (open) showPlayers(row, true);
    }

    /* ── Events ── */

    document.addEventListener('click', event => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const opener = target.closest('[data-sba-open], [data-sba-edit]');
        if (opener) {
            playersFrom = opener.closest('.sb-details-row')?.previousElementSibling || null;
            const dialog = document.getElementById(opener.dataset.sbaOpen || opener.dataset.sbaEdit);
            if (dialog) openDialog(dialog, opener.dataset.sbaValues ? JSON.parse(opener.dataset.sbaValues) : null, opener.dataset.sbaTitle);
            return;
        }
        if (target.closest('[data-sba-close]')) {
            target.closest('dialog')?.close();
            return;
        }
        if (target.matches('dialog.sba-dialog') && !target.classList.contains('is-confirm')) {
            target.close();   // click on the backdrop
            return;
        }
        if (target.closest('[data-sba-players-refresh]')) {
            loadPlayers(target.closest('.sb-details-row').previousElementSibling);
            return;
        }
        const server = target.closest('tr[data-sba-players]');
        if (server && !target.closest('a')) {   // the server and address links keep working
            togglePlayers(server);
            return;
        }
        const action = target.closest('[data-sba-do]');
        if (action) {
            doAction(action);
            return;
        }
        const all = target.closest('[data-sba-all]');
        if (all) {
            const boxes = [...all.closest('fieldset').querySelectorAll('input[type=checkbox]:not(:disabled)')];
            const on = !boxes.every(box => box.checked);
            boxes.forEach(box => { box.checked = on; });
            return;
        }
        const preset = target.closest('.sba-preset');
        if (preset) {
            const form   = preset.closest('form');
            const box    = preset.closest('[data-sba-presets]');
            const input  = field(form, box.dataset.sbaPresets);
            const custom = box.parentElement.querySelector('.sba-custom');
            box.querySelectorAll('.sba-preset').forEach(button => button.classList.toggle('is-active', button === preset));
            if (preset.dataset.value === 'other') {
                custom.hidden = false;
                const amount = custom.querySelector('[data-sba-amount]');
                input.value = String(Math.max(1, Number(amount.value) || 1) * Number(custom.querySelector('[data-sba-unit]').value));
                amount.focus();
            } else {
                input.value = preset.dataset.value;
                if (custom) custom.hidden = true;
            }
            form.querySelector(`[data-error-for="${box.dataset.sbaPresets}"]`)?.replaceChildren();
        }
    });

    document.addEventListener('input', event => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        if (target.matches('[data-sba-steam]')) {
            clearTimeout(target.sbaTimer);
            target.sbaTimer = setTimeout(() => lookup(target), 400);
        }
        if (target.matches('[data-sba-search]')) {
            clearTimeout(target.sbaTimer);
            target.sbaTimer = setTimeout(() => search(target), 300);
        }
        if (target.matches('[data-sba-amount], [data-sba-unit]')) {
            const custom = target.closest('.sba-custom');
            field(target.form, 'length').value = String(Math.max(1, Number(custom.querySelector('[data-sba-amount]').value) || 1) * Number(custom.querySelector('[data-sba-unit]').value));
        }
        if (target.name === 'reason' && target.form) syncPresets(target.form);
    });

    document.addEventListener('change', event => {
        const form = event.target.form;
        if (form && form.matches('form[data-sba-action]')) syncWhen(form);
    });

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('form[data-sba-action]')) return;
        event.preventDefault();
        if (form.classList.contains('is-busy')) return;
        clearErrors(form);
        form.classList.add('is-busy');
        const button = form.querySelector('[type=submit]');
        if (button) button.disabled = true;
        const data = await api(form.dataset.sbaAction, new FormData(form));
        form.classList.remove('is-busy');
        if (button) button.disabled = false;
        if (!data.ok) {
            showErrors(form, data);
            return;
        }
        await finish(data, form);
    });
}());
