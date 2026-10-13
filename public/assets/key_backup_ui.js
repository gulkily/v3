(function () {
  'use strict';
  function bind(root, identity) {
    const area = root.querySelector('[data-key-backup]');
    if (!area || area.dataset.bound) return;
    area.dataset.bound = '1';
    const find = selector => area.querySelector(selector);
    const status = find('[data-backup-status]');
    let busy = false;
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
      const blob = new Blob([backup.text], { type: 'text/plain;charset=utf-8' });
      const check = await window.ForumKeyBackup.readBackup(await blob.text(), passphrase);
      if (check.privateKeyText !== original || check.fingerprint !== backup.fingerprint) throw new Error('Backup verification failed.');
      if (JSON.stringify(before) !== JSON.stringify(identity.snapshot())) throw new Error('The browser identity changed. Try backing up again.');
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = 'key-backup-' + backup.fingerprint + '.txt';
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      setTimeout(() => URL.revokeObjectURL(url), 30000);
      status.textContent = 'Verified text backup download started. Keep the file and passphrase somewhere safe.';
    })));
    find('[data-backup-restore]').addEventListener('click', () => run(async () => {
      const file = find('[data-backup-file]').files[0];
      if (!file) throw new Error('Choose a backup file first.');
      if (file.size > window.ForumKeyBackup.MAX_BYTES) throw new Error('This backup file is too large.');
      const before = JSON.stringify(identity.snapshot());
      const backup = await window.ForumKeyBackup.readBackup(await file.text(), find('[data-restore-password]').value);
      if (before !== JSON.stringify(identity.snapshot())) throw new Error('The browser identity changed. Try restoring again.');
      await identity.restore(backup.privateKeyText, backup.fingerprint);
      find('[data-backup-file]').value = '';
      status.textContent = 'Backup restored. Your public key is linked and this browser is ready.';
    }));
  }
  window.ForumKeyBackupUI = { bind };
})();
