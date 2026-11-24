(function () {
  'use strict';
  // Preferences → Notifications: turns web push on and off for this browser and sends a
  // test (PushApiController). Permission is asked only when the member taps Turn on.
  // preferences.php draws the last known state before paint; this checks it on load.
  var prefs = window.forumPreferences;
  var row = document.querySelector('[data-preference-row="notifications"]');
  if (!prefs || !row || row.hidden) return;
  var toggle = row.querySelector('[data-push-toggle]');
  var test = row.querySelector('[data-push-test]');
  var stateKey = prefs.keys.push;

  function remember(state) {
    try { localStorage.setItem(stateKey, state === 'on' ? 'on' : 'off'); } catch (error) {}
  }
  function show(state, note) {
    if (state !== 'working') remember(state);
    prefs.showNotifications(state, note);
  }

  function request(path, data) {
    var options = data === undefined
      ? { credentials: 'same-origin' }
      : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumPush' }, body: JSON.stringify(data) };
    return fetch(path, options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) {
          var error = new Error(body && body.error ? body.error : 'Request failed');
          error.status = response.status;
          throw error;
        }
        return body;
      });
    });
  }

  function keyBytes(text) {
    var base64 = text.replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(base64 + '==='.slice((base64.length + 3) % 4));
    var bytes = new Uint8Array(raw.length);
    for (var index = 0; index < raw.length; index++) bytes[index] = raw.charCodeAt(index);
    return bytes;
  }
  function sameKey(buffer, bytes) {
    if (!buffer) return false;
    var current = new Uint8Array(buffer);
    if (current.length !== bytes.length) return false;
    for (var index = 0; index < bytes.length; index++) if (current[index] !== bytes[index]) return false;
    return true;
  }

  // The worker is registered by pwa_registration.js; if it never becomes ready, give up.
  function registration() {
    return Promise.race([navigator.serviceWorker.ready, new Promise(function (resolve, reject) {
      setTimeout(function () { reject(new Error('No service worker')); }, 10000);
    })]);
  }
  function save(subscription) {
    return request('/api/push/subscribe', subscription.toJSON());
  }
  // Subscribes with the site's current key, replacing a subscription made with an older one.
  function subscribe(reg) {
    return request('/api/push/key').then(function (body) {
      var key = keyBytes(String(body.public_key || ''));
      return reg.pushManager.getSubscription().then(function (existing) {
        if (existing && sameKey(existing.options && existing.options.applicationServerKey, key)) return existing;
        return (existing ? existing.unsubscribe() : Promise.resolve()).then(function () {
          return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
        });
      });
    }).then(function (subscription) {
      return save(subscription).then(function () { return subscription; });
    });
  }
  function failure(error) {
    return error && error.status === 401 ? 'Sign in first' : error && error.status === 422 ? 'Not supported here' : 'Try again';
  }

  function turnOn() {
    // iPhone only asks from inside the tap, so permission comes before anything else.
    var asked = Notification.permission === 'granted' ? Promise.resolve('granted') : Notification.requestPermission();
    show('working');
    asked.then(function (permission) {
      if (permission !== 'granted') {
        show(permission === 'denied' ? 'blocked' : 'off');
        return;
      }
      return registration().then(subscribe).then(function () { show('on'); });
    }).catch(function (error) { show('off', failure(error)); });
  }

  function turnOff() {
    show('working');
    registration().then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (subscription) {
      if (!subscription) return;
      var endpoint = subscription.endpoint;
      return subscription.unsubscribe().then(function () {
        return request('/api/push/unsubscribe', { endpoint: endpoint }).catch(function () {});
      });
    }).then(function () { show('off'); }, function () { show('off', 'Try again'); });
  }

  function sendTest() {
    test.disabled = true;
    registration().then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (subscription) {
      if (!subscription) { show('off'); return; }
      return request('/api/push/test', { endpoint: subscription.endpoint }).then(function (body) {
        if (body.outcome === 'sent') {
          show('on', 'Test sent');
        } else if (body.outcome === 'gone') {
          // The server no longer sends here (signed out, or the push service dropped it).
          return subscription.unsubscribe().catch(function () {}).then(function () { show('off'); });
        } else {
          show('on', 'Refused' + (body.reason ? ': ' + body.reason : ''));
        }
      });
    }).catch(function (error) { show('on', failure(error)); }).then(function () { test.disabled = false; });
  }

  toggle.addEventListener('click', function () {
    if (row.getAttribute('data-push-state') === 'on') turnOff(); else turnOn();
  });
  test.addEventListener('click', sendTest);

  // On load: find the real state. A subscription the browser still holds is saved again, so it
  // follows the current sign-in and the current site key.
  if (Notification.permission === 'denied') { show('blocked'); return; }
  registration().then(function (reg) {
    return reg.pushManager.getSubscription().then(function (subscription) {
      if (!subscription || Notification.permission !== 'granted') { show('off'); return; }
      return subscribe(reg).then(function () { show('on'); }, function (error) {
        show(error && error.status === 401 ? 'off' : 'on', failure(error));
      });
    });
  }).catch(function () {});
})();
