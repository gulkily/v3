(function () {
  "use strict";
  // A public-only device: show the absolute confirm link and, on request, its QR for another
  // device of the account to scan. Without scripts the page still shows the code and the path.
  function bind(root) {
    if (!root) return;
    const path = root.dataset.confirmPath || "";
    const url = window.location.origin + path;
    const text = root.querySelector('[data-role="confirm-url"]');
    if (text) text.textContent = url;
    const row = root.querySelector('[data-role="show-confirm-qr-row"]');
    const button = root.querySelector('[data-action="show-confirm-qr"]');
    const canvas = root.querySelector('[data-role="confirm-qr"]');
    if (!row || !button || !canvas || !window.qrcode || !window.ForumQrCanvas) return;
    row.hidden = false;
    button.addEventListener("click", function () {
      try {
        window.ForumQrCanvas.render(canvas, url);
        canvas.hidden = false;
        row.hidden = true;
      } catch (error) {
        canvas.hidden = true;
      }
    });
  }
  document.addEventListener("DOMContentLoaded", function () { bind(document.querySelector("[data-private-access-device]")); });
})();
