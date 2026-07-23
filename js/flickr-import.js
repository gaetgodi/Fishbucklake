/**
 * FBL Flickr Import
 * Chunked zip upload (bypasses nginx body-size limits), then batched import.
 */
(function () {
    'use strict';

    var cfg = window.FBL_FI || {};
    var els = {};
    var session = '';
    var cancelled = false;

    var CHUNK_SIZE = 5 * 1024 * 1024; // 5 MB — well under any server limit

    function $(id) { return document.getElementById(id); }

    document.addEventListener('DOMContentLoaded', function () {
        els.file     = $('fbl-fi-file');
        els.maxedge  = $('fbl-fi-maxedge');
        els.quality  = $('fbl-fi-quality');
        els.upload   = $('fbl-fi-upload');
        els.status   = $('fbl-fi-status');
        els.stage2   = $('fbl-fi-stage2');
        els.summary  = $('fbl-fi-summary');
        els.folder   = $('fbl-fi-folder');
        els.start    = $('fbl-fi-start');
        els.cancel   = $('fbl-fi-cancel');
        els.progWrap = $('fbl-fi-progress-wrap');
        els.bar      = $('fbl-fi-bar');
        els.progText = $('fbl-fi-progress-text');
        els.log      = $('fbl-fi-log');

        if (!els.upload) return;

        // upload progress bar, injected under the button
        els.upBar = document.createElement('div');
        els.upBar.className = 'fbl-fi-bar';
        els.upBar.style.display = 'none';
        els.upBar.style.maxWidth = '480px';
        els.upBar.innerHTML = '<div class="fbl-fi-bar-fill" id="fbl-fi-upbar-fill"></div>';
        els.upload.parentNode.appendChild(els.upBar);
        els.upBarFill = $('fbl-fi-upbar-fill');

        els.upload.addEventListener('click', startUpload);
        els.start.addEventListener('click', startImport);
        els.cancel.addEventListener('click', function () {
            cancelled = true;
            els.stage2.style.display = 'none';
            els.status.textContent = 'Cancelled.';
        });
    });

    /* ---------- chunked upload ---------- */

    function startUpload() {
        if (!els.file.files || !els.file.files.length) {
            els.status.textContent = 'Choose a zip file first.';
            return;
        }
        var file  = els.file.files[0];
        var uid   = 'u' + Date.now() + Math.floor(Math.random() * 100000);
        var total = Math.ceil(file.size / CHUNK_SIZE);

        cancelled = false;
        els.upload.disabled = true;
        els.upBar.style.display = '';
        els.upBarFill.style.width = '0%';
        els.status.textContent = 'uploading… 0%';

        sendChunk(file, uid, 0, total, 0);
    }

    function sendChunk(file, uid, index, total, attempt) {
        if (cancelled) return;

        var start = index * CHUNK_SIZE;
        var blob  = file.slice(start, Math.min(start + CHUNK_SIZE, file.size));

        var fd = new FormData();
        fd.append('action', 'fbl_fi_chunk');
        fd.append('nonce', cfg.nonce);
        fd.append('uid', uid);
        fd.append('index', index);
        fd.append('total', total);
        fd.append('name', file.name);
        fd.append('chunk', blob);

        fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    els.upload.disabled = false;
                    els.status.textContent = 'Error: ' + (res.data || 'chunk failed');
                    return;
                }
                var pct = Math.round(((index + 1) / total) * 100);
                els.upBarFill.style.width = pct + '%';

                if (!res.data.complete) {
                    els.status.textContent = 'uploading… ' + pct + '%';
                    sendChunk(file, uid, index + 1, total, 0);
                    return;
                }

                // final chunk: the zip was assembled and extracted
                els.upload.disabled = false;
                els.status.textContent = '';
                els.upBar.style.display = 'none';
                showStage2(res.data);
            })
            .catch(function () {
                // retry a failed chunk up to 3 times before giving up
                if (attempt < 3) {
                    els.status.textContent = 'chunk ' + (index + 1) + ' failed — retrying…';
                    setTimeout(function () {
                        sendChunk(file, uid, index, total, attempt + 1);
                    }, 1000 * (attempt + 1));
                } else {
                    els.upload.disabled = false;
                    els.status.textContent = 'Upload failed at chunk ' + (index + 1) + '.';
                }
            });
    }

    function showStage2(d) {
        session = d.session;
        var msg = '<strong>' + d.count + '</strong> image' + (d.count === 1 ? '' : 's') + ' found';
        if (d.album)   msg += ' in album “' + escapeHtml(d.album) + '”';
        if (d.skipped) msg += ' — ' + d.skipped + ' non-image entr' +
                              (d.skipped === 1 ? 'y' : 'ies') + ' skipped';
        msg += '.';
        els.summary.innerHTML = msg;
        els.folder.value = d.folder;
        els.stage2.style.display = '';
    }

    /* ---------- batched import ---------- */

    function startImport() {
        var folder = (els.folder.value || '').trim();
        if (!folder) { els.status.textContent = 'Enter a destination folder name.'; return; }

        els.start.disabled = true;
        els.folder.disabled = true;
        els.progWrap.style.display = '';
        els.log.innerHTML = '';
        runBatch(0, folder, 0);
    }

    function runBatch(offset, folder, importedSoFar) {
        if (cancelled) return;

        var body = new URLSearchParams();
        body.append('action', 'fbl_fi_batch');
        body.append('nonce', cfg.nonce);
        body.append('session', session);
        body.append('folder', folder);
        body.append('offset', offset);
        body.append('maxedge', els.maxedge.value);
        body.append('quality', els.quality.value);

        fetch(cfg.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    els.progText.textContent = 'Error: ' + (res.data || 'batch failed');
                    els.start.disabled = false;
                    els.folder.disabled = false;
                    return;
                }
                var d = res.data;
                var imported = importedSoFar + d.done;

                (d.log || []).forEach(function (row) {
                    var line = document.createElement('div');
                    line.className = 'fbl-fi-log-line ' + (row.ok ? 'is-ok' : 'is-err');
                    line.textContent = (row.ok ? '✓ ' : '✕ ') + row.file +
                                       (row.ok ? '' : ' — ' + row.msg);
                    els.log.appendChild(line);
                });
                els.log.scrollTop = els.log.scrollHeight;

                var pct = d.total ? Math.round((d.offset / d.total) * 100) : 100;
                els.bar.style.width = pct + '%';
                els.progText.textContent = d.offset + ' / ' + d.total +
                    ' processed — ' + imported + ' imported';

                if (d.finished) {
                    els.progText.textContent = 'Done. ' + imported + ' image' +
                        (imported === 1 ? '' : 's') + ' imported into “' + d.folder + '”.';
                    els.start.disabled = false;
                    els.folder.disabled = false;
                    els.file.value = '';
                } else {
                    runBatch(d.offset, folder, imported);
                }
            })
            .catch(function () {
                els.progText.textContent = 'Batch request failed.';
                els.start.disabled = false;
                els.folder.disabled = false;
            });
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }
})();