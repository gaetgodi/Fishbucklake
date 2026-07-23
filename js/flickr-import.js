/**
 * FBL Flickr Import
 * Upload a zip, inspect it, then import in batches with progress.
 */
(function () {
    'use strict';

    var cfg = window.FBL_FI || {};
    var els = {};
    var session = '';
    var total = 0;
    var cancelled = false;

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

        els.upload.addEventListener('click', doUpload);
        els.start.addEventListener('click', startImport);
        els.cancel.addEventListener('click', function () {
            cancelled = true;
            els.stage2.style.display = 'none';
            els.status.textContent = 'Cancelled.';
        });
    });

    function doUpload() {
        if (!els.file.files || !els.file.files.length) {
            els.status.textContent = 'Choose a zip file first.';
            return;
        }
        var fd = new FormData();
        fd.append('action', 'fbl_fi_upload');
        fd.append('nonce', cfg.nonce);
        fd.append('zipfile', els.file.files[0]);

        els.upload.disabled = true;
        els.status.textContent = 'uploading and extracting… (large albums take a while)';

        fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                els.upload.disabled = false;
                if (!res.success) {
                    els.status.textContent = 'Error: ' + (res.data || 'upload failed');
                    return;
                }
                session = res.data.session;
                total   = res.data.count;
                els.status.textContent = '';
                var msg = '<strong>' + res.data.count + '</strong> image' +
                          (res.data.count === 1 ? '' : 's') + ' found';
                if (res.data.album) msg += ' in album “' + escapeHtml(res.data.album) + '”';
                if (res.data.skipped) msg += ' — ' + res.data.skipped + ' non-image entr' +
                          (res.data.skipped === 1 ? 'y' : 'ies') + ' skipped';
                msg += '.';
                els.summary.innerHTML = msg;
                els.folder.value = res.data.folder;
                els.stage2.style.display = '';
                cancelled = false;
            })
            .catch(function () {
                els.upload.disabled = false;
                els.status.textContent = 'Upload request failed (file may exceed the server limit).';
            });
    }

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
