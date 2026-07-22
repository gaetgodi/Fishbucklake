/**
 * FBL Media Curator
 * Admin tool: folder-scoped title/caption editing + copy-to-WEB.
 */
(function () {
    'use strict';

    var cfg = window.FBL_CURATOR || {};
    var els = {};

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $all(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    document.addEventListener('DOMContentLoaded', function () {
        els.source     = $('#fbl-curator-source');
        els.count      = $('#fbl-curator-count');
        els.actions    = $('.fbl-curator-actions');
        els.table      = $('.fbl-curator-table');
        els.rows       = $('#fbl-curator-rows');
        els.empty      = $('#fbl-curator-empty');
        els.target     = $('#fbl-curator-target');
        els.status     = $('#fbl-curator-status');
        els.copycap    = $('#fbl-curator-copycaptions');
        els.batchcopy  = $('#fbl-curator-batchcopy');

        if (!els.source) return;

        els.source.addEventListener('change', onSourceChange);
        els.copycap.addEventListener('click', onCopyCaptions);
        els.batchcopy.addEventListener('click', onBatchCopy);
    });

    // Suggest a WEB target from the source folder name.
    // "Fishing_FBL" -> "WEB_Fishing"; anything else -> "WEB_" + name.
    function suggestTarget(name) {
        if (!name) return 'WEB_';
        var base = name;
        if (base.slice(-4) === '_FBL') base = base.slice(0, -4);
        return 'WEB_' + base;
    }

    function ajax(action, data) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', cfg.nonce);
        Object.keys(data || {}).forEach(function (k) {
            var v = data[k];
            if (Array.isArray(v)) {
                v.forEach(function (item) { body.append(k + '[]', item); });
            } else {
                body.append(k, v);
            }
        });
        return fetch(cfg.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    function onSourceChange() {
        var folder = els.source.value;
        els.status.textContent = '';
        if (!folder) {
            els.table.style.display = 'none';
            els.actions.style.display = 'none';
            els.empty.style.display = 'none';
            els.count.textContent = '';
            return;
        }
        els.target.value = suggestTarget(folder);
        els.count.textContent = 'loading…';
        els.rows.innerHTML = '';

        ajax('fbl_curator_load', { folder: folder }).then(function (res) {
            if (!res.success) {
                els.count.textContent = 'error: ' + (res.data || 'load failed');
                return;
            }
            var items = res.data.items || [];
            els.count.textContent = res.data.count + ' image' + (res.data.count === 1 ? '' : 's');
            if (!items.length) {
                els.table.style.display = 'none';
                els.actions.style.display = 'none';
                els.empty.style.display = '';
                return;
            }
            items.forEach(function (it) { els.rows.appendChild(buildRow(it)); });
            els.table.style.display = '';
            els.actions.style.display = '';
            els.empty.style.display = 'none';
        });
    }

    function buildRow(it) {
        var tr = document.createElement('tr');
        tr.dataset.id = it.id;

        // image
        var tdImg = document.createElement('td');
        var img = document.createElement('img');
        img.src = it.thumb || '';
        img.alt = '';
        img.className = 'fbl-curator-thumb';
        tdImg.appendChild(img);
        var fn = document.createElement('div');
        fn.className = 'fbl-curator-fn';
        fn.textContent = it.filename || '';
        tdImg.appendChild(fn);
        tr.appendChild(tdImg);

        // title
        tr.appendChild(fieldCell(it.id, 'title', it.title));
        // caption
        tr.appendChild(fieldCell(it.id, 'caption', it.caption));

        // flag (caption-copy)
        var tdFlag = document.createElement('td');
        tdFlag.style.textAlign = 'center';
        var flag = document.createElement('input');
        flag.type = 'checkbox';
        flag.className = 'fbl-curator-flag';
        tdFlag.appendChild(flag);
        tr.appendChild(tdFlag);

        // select (web-copy)
        var tdSel = document.createElement('td');
        tdSel.style.textAlign = 'center';
        var sel = document.createElement('input');
        sel.type = 'checkbox';
        sel.className = 'fbl-curator-select';
        tdSel.appendChild(sel);
        tr.appendChild(tdSel);

        // per-row copy button
        var tdCopy = document.createElement('td');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'button button-small fbl-curator-rowcopy';
        btn.textContent = 'Copy →';
        btn.addEventListener('click', function () { rowCopy(it.id, tr); });
        tdCopy.appendChild(btn);
        tr.appendChild(tdCopy);

        return tr;
    }

    function fieldCell(id, field, value) {
        var td = document.createElement('td');
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'fbl-curator-field fbl-curator-' + field;
        inp.value = value || '';
        inp.dataset.orig = value || '';

        var save = function () {
            if (inp.value === inp.dataset.orig) return;
            inp.classList.add('is-saving');
            ajax('fbl_curator_save', { id: id, field: field, value: inp.value }).then(function (res) {
                inp.classList.remove('is-saving');
                if (res.success) {
                    inp.dataset.orig = inp.value;
                    flash(inp);
                    if (field === 'title') resort();
                } else {
                    inp.classList.add('is-error');
                }
            });
        };

        inp.addEventListener('blur', save);
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
        });
        td.appendChild(inp);
        return td;
    }

    function flash(el) {
        el.classList.add('is-saved');
        setTimeout(function () { el.classList.remove('is-saved'); }, 900);
    }

    // Re-sort rows by title (case-insensitive), preserving inputs.
    function resort() {
        var rows = $all('tr', els.rows);
        rows.sort(function (a, b) {
            var ta = $('.fbl-curator-title', a).value.toLowerCase();
            var tb = $('.fbl-curator-title', b).value.toLowerCase();
            return ta < tb ? -1 : (ta > tb ? 1 : 0);
        });
        rows.forEach(function (r) { els.rows.appendChild(r); });
    }

    function onCopyCaptions() {
        var flagged = $all('tr', els.rows).filter(function (r) {
            return $('.fbl-curator-flag', r).checked;
        });
        if (!flagged.length) {
            els.status.textContent = 'No rows flagged.';
            return;
        }
        var ids = flagged.map(function (r) { return r.dataset.id; });
        els.status.textContent = 'copying titles to captions…';
        ajax('fbl_curator_copycaptions', { ids: ids }).then(function (res) {
            if (!res.success) { els.status.textContent = 'error: ' + (res.data || 'failed'); return; }
            // reflect new captions in the fields
            flagged.forEach(function (r) {
                var title = $('.fbl-curator-title', r).value;
                var cap = $('.fbl-curator-caption', r);
                cap.value = title;
                cap.dataset.orig = title;
                flash(cap);
                $('.fbl-curator-flag', r).checked = false;
            });
            els.status.textContent = 'Updated ' + res.data.updated + ' caption(s).';
        });
    }

    function rowCopy(id, tr) {
        var target = (els.target.value || '').trim();
        if (!validTarget(target)) return;
        var btn = $('.fbl-curator-rowcopy', tr);
        btn.disabled = true; btn.textContent = '…';
        ajax('fbl_curator_copyweb', { ids: [id], target: target }).then(function (res) {
            btn.disabled = false;
            if (!res.success) { btn.textContent = 'Copy →'; els.status.textContent = 'error: ' + (res.data || 'failed'); return; }
            btn.textContent = res.data.copied ? '✓ Copied' : '· In folder';
            setTimeout(function () { btn.textContent = 'Copy →'; }, 1500);
            els.status.textContent = 'Sent to ' + res.data.target +
                ' (' + res.data.copied + ' copied, ' + res.data.skipped + ' already there).';
        });
    }

    function onBatchCopy() {
        var target = (els.target.value || '').trim();
        if (!validTarget(target)) return;
        var selected = $all('tr', els.rows).filter(function (r) {
            return $('.fbl-curator-select', r).checked;
        });
        if (!selected.length) { els.status.textContent = 'No rows selected.'; return; }
        var ids = selected.map(function (r) { return r.dataset.id; });
        els.batchcopy.disabled = true;
        els.status.textContent = 'copying ' + ids.length + ' image(s) to ' + target + '…';
        ajax('fbl_curator_copyweb', { ids: ids, target: target }).then(function (res) {
            els.batchcopy.disabled = false;
            if (!res.success) { els.status.textContent = 'error: ' + (res.data || 'failed'); return; }
            selected.forEach(function (r) { $('.fbl-curator-select', r).checked = false; });
            els.status.textContent = 'Copied ' + res.data.copied + ' to ' + res.data.target +
                ' (' + res.data.skipped + ' already there).';
        });
    }

    function validTarget(target) {
        if (!target || target === 'WEB_') {
            els.status.textContent = 'Enter a target folder name (after WEB_).';
            return false;
        }
        return true;
    }
})();
