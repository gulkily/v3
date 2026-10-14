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
    function openLinkedBackup() {
      if (window.location.hash === '#key-backup') area.open = true;
    }
    openLinkedBackup();
    window.addEventListener('hashchange', openLinkedBackup);
    const find = selector => area.querySelector(selector);
    const status = find('[data-backup-status]');
    let busy = false;
    const tabs = Array.from(area.querySelectorAll('[data-backup-tab]'));
    function selectTab(name) {
      if (busy) return;
      tabs.forEach(tab => {
        const selected = tab.dataset.backupTab === name;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
      });
      area.querySelectorAll('[data-backup-panel]').forEach(panel => {
        panel.hidden = panel.dataset.backupPanel !== name;
      });
      status.textContent = '';
    }
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => selectTab(tab.dataset.backupTab));
      tab.addEventListener('keydown', event => {
        if (busy || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
          : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        selectTab(tabs[next].dataset.backupTab);
        tabs[next].focus();
      });
    });
    let hasPrivateKey = false;
    try { hasPrivateKey = Boolean(identity.snapshot().forum_pki_private_key); } catch (_) {}
    selectTab(hasPrivateKey ? 'backup' : 'restore');

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
        controls.forEach((control, i) => { control.disabled = disabled[i]; });
        busy = false;
      }
    }
    area.querySelectorAll('[data-backup-download]').forEach(button => button.addEventListener('click', () => run(async () => {
      const before = identity.snapshot();
      const original = before.forum_pki_private_key;
      if (!original) throw new Error('Set up this browser before making a backup.');
      const passphrase = find('[data-backup-password]').value;
      if (passphrase !== find('[data-backup-confirm]').value) throw new Error('The passphrases do not match.');
      const backup = await window.ForumKeyBackup.createBackup(original, passphrase);
      if (backup.fingerprint !== String(before.forum_pki_fingerprint || '').toLowerCase()) throw new Error('The browser key and account fingerprint do not match.');
      const isPng = button.dataset.backupDownload === 'png';
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
      status.textContent = 'Verified ' + (isPng ? 'PNG' : 'text') + ' backup download started. Keep the original file and passphrase somewhere safe.' + (recorded ? '' : ' The reminder could not be saved in this browser.');
    })));
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
