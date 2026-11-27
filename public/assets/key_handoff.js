(function () {
  "use strict";

  // /account/key/share?to=http://127.0.0.1:8000 — sends this browser's key to the local page that
  // opened this window, after one click. Only loopback targets, only the opener, only that origin.
  var LOOPBACK = /^http:\/\/(127\.0\.0\.1|localhost|\[::1\]):[0-9]{1,5}$/;
  var root = document.querySelector('[data-key-handoff]');
  if (!root || !root.dataset.target) return;
  var target = root.dataset.target;
  var keyNode = root.querySelector('[data-key-handoff-key]');
  var send = root.querySelector('[data-key-handoff-send]');
  var status = root.querySelector('[data-key-handoff-status]');

  function stored(name) {
    try { return window.localStorage.getItem(name) || ''; } catch (error) { return ''; }
  }

  // Failures: a short message and a closed Details with the step and conditions (CONTRIBUTING).
  function fail(message, step, detail) {
    keyNode.textContent = message;
    keyNode.setAttribute('role', 'alert');
    send.hidden = true;
    var details = document.createElement('details');
    var summary = document.createElement('summary');
    summary.textContent = 'Details';
    var pre = document.createElement('pre');
    pre.textContent = ['step: key handoff: ' + step, 'error: ' + detail, 'target: ' + target,
      'opener: ' + Boolean(window.opener), 'browser: ' + navigator.userAgent, 'at: ' + new Date().toISOString()].join('\n');
    details.append(summary, pre);
    keyNode.after(details);
  }

  if (!LOOPBACK.test(target)) {
    fail('Keys can only go to a local copy on this computer.', 'target', 'not a loopback address');
    return;
  }
  if (!window.opener) {
    fail('Open this from your local copy\'s Connect button, not on its own.', 'opener', 'no opener window');
    return;
  }
  var privateKey = stored('forum_pki_private_key');
  var fingerprint = stored('forum_pki_fingerprint').toUpperCase();
  var username = stored('forum_pki_username');
  if (!privateKey || !fingerprint) {
    keyNode.textContent = 'This browser has no key. ';
    keyNode.setAttribute('role', 'alert');
    var link = document.createElement('a');
    link.href = '/account/key/';
    link.textContent = 'Open Account';
    keyNode.appendChild(link);
    return;
  }

  keyNode.textContent = 'Key: ' + (username || 'this browser') + ' · …' + fingerprint.slice(-16).replace(/(.{4})(?=.)/g, '$1 ');
  send.hidden = false;
  send.addEventListener('click', function () {
    if (!window.opener) {
      fail('Your local copy\'s window was closed. Press Connect there again.', 'send', 'opener closed');
      return;
    }
    window.opener.postMessage({ type: 'forum-key-handoff', version: 1, privateKey: privateKey, fingerprint: fingerprint, username: username }, target);
    send.hidden = true;
    status.textContent = 'Sent. You can close this window.';
    window.setTimeout(function () { window.close(); }, 600);
  });
})();
