(function () {
  'use strict';
  const prefix = 'forum_pki_backup_verified_';
  function refresh() {
    const prompts = document.querySelectorAll('[data-key-backup-reminder]');
    let show = true;
    try {
      const fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
      show = Boolean(fingerprint && localStorage.getItem('forum_pki_private_key')) && !localStorage.getItem(prefix + fingerprint);
    } catch (_) { /* Keep a useful link when browser storage is unavailable. */ }
    prompts.forEach(prompt => { prompt.hidden = !show; });
  }
  function record(fingerprint) {
    try {
      if (!/^[a-f0-9]{40,64}$/.test(fingerprint)) return false;
      // The date of the last saved copy for this key (older records hold '1').
      localStorage.setItem(prefix + fingerprint, new Date().toISOString().slice(0, 10));
      refresh();
      window.dispatchEvent(new Event('forum:key-backup-recorded'));
      return true;
    } catch (_) { return false; }
  }
  document.addEventListener('DOMContentLoaded', refresh);
  window.addEventListener('pageshow', refresh);
  window.addEventListener('storage', refresh);
  window.addEventListener('forum:identity-changed', refresh);
  window.ForumKeyBackupStatus = { record, refresh };
})();
