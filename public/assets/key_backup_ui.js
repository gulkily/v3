(function () {
  'use strict';
  async function backupText(bytes) {
    const png = bytes[0] === 137 && bytes[1] === 80 && bytes[2] === 78 && bytes[3] === 71;
    if (png) bytes = await window.ForumStarfield.decodeStarfield(bytes);
    if (bytes.length > window.ForumKeyBackup.MAX_BYTES) throw new Error('This backup file is too large.');
    try { return new TextDecoder('utf-8', { fatal: true }).decode(bytes); }
    catch (_) { throw new Error('Use an original backup text file or Starfield PNG. Screenshots and JPEG images cannot restore your key.'); }
  }
  function bind(root, identity) {
    const area = root.querySelector('[data-key-backup]');
    if (!area || area.dataset.bound) return;
    area.dataset.bound = '1';
    const find = selector => area.querySelector(selector);
    const status = find('[data-backup-status]');
    let busy = false;
    // One dialog in two modes: save a copy of this key, or use a copy from another device.
    function show(mode) {
      if (busy) return;
      let shown = null;
      area.querySelectorAll('[data-backup-panel]').forEach(panel => {
        panel.hidden = panel.dataset.backupPanel !== mode;
        if (!panel.hidden) shown = panel;
      });
      status.textContent = '';
      if (!area.open) {
        if (typeof area.showModal === 'function') area.showModal();
        else area.setAttribute('open', '');
      }
      const title = shown && shown.querySelector('h2[id]');
      if (title) area.setAttribute('aria-labelledby', title.id);
      const first = shown && shown.querySelector('input');
      if (first) first.focus();
    }
    function defaultMode() {
      try { return identity.snapshot().forum_pki_private_key ? 'backup' : 'restore'; } catch (_) { return 'restore'; }
    }
    function openLinkedBackup() {
      if (window.location.hash === '#key-backup') show(defaultMode());
    }
    openLinkedBackup();
    window.addEventListener('hashchange', openLinkedBackup);
    root.querySelectorAll('[data-backup-open]').forEach(link => link.addEventListener('click', event => {
      event.preventDefault();
      show(link.dataset.backupOpen);
    }));
    area.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    area.addEventListener('close', () => {
      area.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
      showStrength();
      if (window.location.hash === '#key-backup' && window.history && history.replaceState) {
        history.replaceState(null, '', window.location.pathname + window.location.search);
      }
    });

    // A rough, local strength hint: length and variety, no word lists.
    const meter = find('[data-role="passphrase-meter"]');
    const meterLabel = find('[data-role="passphrase-strength-label"]');
    function showStrength() {
      const value = find('[data-backup-password]').value;
      const kinds = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/].filter(pattern => pattern.test(value)).length;
      const words = value.trim().split(/\s+/).filter(Boolean).length;
      const score = value === '' ? 0 : Math.min(4, (value.length >= 8 ? 1 : 0) + (value.length >= 12 ? 1 : 0)
        + (value.length >= 16 ? 1 : 0) + (kinds >= 3 || words >= 4 ? 1 : 0));
      if (meter) meter.value = score;
      if (meterLabel) meterLabel.textContent = value === '' ? '' : ['Too short', 'Weak', 'Fair', 'Good', 'Strong'][score];
    }
    find('[data-backup-password]').addEventListener('input', showStrength);
    showStrength();
    const formatNote = find('[data-role="format-note"]');
    function selectedFormat() {
      const checked = area.querySelector('[data-backup-format]:checked');
      return checked ? checked.value : 'text';
    }
    area.querySelectorAll('[data-backup-format]').forEach(input => input.addEventListener('change', () => {
      if (formatNote) formatNote.textContent = selectedFormat() === 'png'
        ? 'A picture that holds the copy. Keep the original file; screenshots or edited images won’t work.'
        : 'A small text file.';
    }));

    root.addEventListener('submit', event => { if (busy) event.preventDefault(); }, true);
    root.addEventListener('click', event => { if (busy) { event.preventDefault(); event.stopImmediatePropagation(); } }, true);
    async function run(action) {
      if (busy) return;
      busy = true;
      const controls = Array.from(root.querySelectorAll('button, input, textarea'));
      const disabled = controls.map(control => control.disabled);
      controls.forEach(control => { control.disabled = true; });
      status.textContent = 'Working locally…';
      try { await action(); }
      catch (error) { status.textContent = error.message || 'Unable to complete the backup operation.'; }
      finally {
        area.querySelectorAll('input[type="password"]').forEach(input => { input.value = ''; });
        showStrength();
        controls.forEach((control, i) => { control.disabled = disabled[i]; });
        busy = false;
      }
    }
    find('[data-backup-download]').addEventListener('click', () => run(async () => {
      const before = identity.snapshot();
      const original = before.forum_pki_private_key;
      if (!original) throw new Error('Set up this browser before making a backup.');
      const passphrase = find('[data-backup-password]').value;
      if (passphrase !== find('[data-backup-confirm]').value) throw new Error('The passphrases do not match.');
      const backup = await window.ForumKeyBackup.createBackup(original, passphrase);
      if (backup.fingerprint !== String(before.forum_pki_fingerprint || '').toLowerCase()) throw new Error('The browser key and account fingerprint do not match.');
      const isPng = selectedFormat() === 'png';
      const content = isPng ? window.ForumStarfield.encodeStarfield(new TextEncoder().encode(backup.text)) : backup.text;
      const blob = new Blob([content], { type: isPng ? 'image/png' : 'text/plain;charset=utf-8' });
      const check = await window.ForumKeyBackup.readBackup(await backupText(new Uint8Array(await blob.arrayBuffer())), passphrase);
      if (check.privateKeyText !== original || check.fingerprint !== backup.fingerprint) throw new Error('Backup verification failed.');
      if (JSON.stringify(before) !== JSON.stringify(identity.snapshot())) throw new Error('The browser identity changed. Try backing up again.');
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = 'key-backup-' + backup.fingerprint + (isPng ? '.png' : '.txt');
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      const recorded = window.ForumKeyBackupStatus.record(backup.fingerprint);
      setTimeout(() => URL.revokeObjectURL(url), 30000);
      status.textContent = (isPng ? 'Starfield image' : 'Text file') + ' download started.';
      if (!recorded) {
        status.append(document.createElement('br'), document.createTextNode('Backup reminder could not be saved.'));
      }
    }));
    find('[data-backup-restore]').addEventListener('click', () => run(async () => {
      const file = find('[data-backup-file]').files[0];
      if (!file) throw new Error('Choose a backup file first.');
      if (file.size > window.ForumStarfield.MAX_FILE_BYTES) throw new Error('This backup file is too large.');
      const before = JSON.stringify(identity.snapshot());
      const backup = await window.ForumKeyBackup.readBackup(await backupText(new Uint8Array(await file.arrayBuffer())), find('[data-restore-password]').value);
      if (before !== JSON.stringify(identity.snapshot())) throw new Error('The browser identity changed. Try restoring again.');
      await identity.restore(backup.privateKeyText, backup.fingerprint);
      find('[data-backup-file]').value = '';
      status.textContent = 'Backup restored. Your public key is linked and this browser is ready.';
    }));
  }
  window.ForumKeyBackupUI = { bind };
})();
