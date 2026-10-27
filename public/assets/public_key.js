(function () {
  'use strict';
  document.querySelectorAll('[data-public-key-details]').forEach(function (details) {
    var button = details.querySelector('[data-copy-public-key]');
    if (!button) return;
    var key = details.querySelector('[data-role="public-key-viewer"]');
    var status = details.querySelector('[data-key-copy-status]');
    button.hidden = false;
    button.addEventListener('click', async function () {
      status.hidden = true;
      try {
        await navigator.clipboard.writeText(key.textContent);
        button.textContent = 'Copied';
        window.setTimeout(function () { button.textContent = 'Copy'; }, 1500);
      } catch (error) {
        status.textContent = 'Select the public key text and copy it manually.';
        status.hidden = false;
      }
    });
  });
}());
