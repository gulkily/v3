(function () {
  'use strict';
  var stamp = document.querySelector('meta[name="v3-generated-at"]');
  if (!stamp) return;
  var generated = Date.parse(stamp.content);
  var threshold = document.querySelector('meta[name="v3-refresh-after"]');
  var age = Math.max(1, Number(threshold && threshold.content) || 60) * 1000;
  if (!Number.isFinite(generated)) return;
  // A single hint; no polling and no dependency on identity/session initialization.
  window.setTimeout(function () {
    var key = 'v3_refresh_hint:' + location.pathname;
    try {
      if (Date.now() - Number(sessionStorage.getItem(key) || 0) < 60000) return;
      sessionStorage.setItem(key, String(Date.now()));
    } catch (ignored) {}
    fetch('/internal/high-traffic/refresh', {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ route: location.pathname }).toString()
    }).catch(function () {});
  }, Math.max(0, generated + age - Date.now()) + Math.random() * 3000);
}());
