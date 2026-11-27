(function () {
  'use strict';
  // Preferences → Notifications: turns web push on and off for this browser and sends a
  // test (PushApiController). Permission is asked only when the member taps Turn on.
  // preferences.php draws the last known state before paint; this checks it on load.
  // Every step that can stall has a time limit; a failure shows a short note plus Details
  // naming the step, and is reported to operators (CONTRIBUTING: error messages).
  var prefs = window.forumPreferences;
  var row = document.querySelector('[data-preference-row="notifications"]');
  if (!prefs || !row || row.hidden) return;
  var toggle = row.querySelector('[data-push-toggle]');
  var test = row.querySelector('[data-push-test]');
  var details = row.querySelector('[data-push-details]');
  var detailsText = row.querySelector('[data-push-details-text]');
  var copy = row.querySelector('[data-push-details-copy]');
  var stateKey = prefs.keys.push;
  var script = document.currentScript ? String(document.currentScript.src).replace(/^.*\//, '') : '';
  var STEP_MS = window.__forumPushStepMs || 15000;
  var WORKER_MS = window.__forumPushWorkerMs || 10000;

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
          var error = new Error(body && body.error ? body.error : 'HTTP ' + response.status);
          error.status = response.status;
          throw error;
        }
        return body;
      });
    });
  }

  // Runs one named step; it fails with the step's name when it rejects or runs out of time.
  function within(step, work, ms) {
    var begun = Date.now();
    var timer;
    var limit = new Promise(function (resolve, reject) {
      timer = setTimeout(function () {
        reject({ timedOut: true, name: 'Timeout', message: 'No answer after ' + Math.round(ms / 1000) + ' s' });
      }, ms);
    });
    return Promise.race([Promise.resolve().then(work), limit]).then(function (value) {
      clearTimeout(timer);
      return value;
    }, function (error) {
      clearTimeout(timer);
      if (error && error.failedStep) throw error;
      throw {
        failedStep: step,
        timedOut: !!(error && error.timedOut),
        name: error && error.name ? String(error.name) : 'Error',
        message: error && error.message ? String(error.message) : String(error),
        status: error && error.status ? error.status : null,
        waited: Date.now() - begun,
      };
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
    return within('service worker', function () { return navigator.serviceWorker.ready; }, WORKER_MS);
  }
  // Subscribes with the site's current key, replacing a subscription made with an older one.
  function subscribe(reg) {
    var key;
    return within('push key', function () { return request('/api/push/key'); }, STEP_MS).then(function (body) {
      key = keyBytes(String(body.public_key || ''));
      return within('existing subscription', function () { return reg.pushManager.getSubscription(); }, STEP_MS);
    }).then(function (existing) {
      if (existing && sameKey(existing.options && existing.options.applicationServerKey, key)) return existing;
      var drop = existing ? within('drop old subscription', function () { return existing.unsubscribe(); }, STEP_MS) : Promise.resolve();
      return drop.then(function () {
        return within('push subscribe', function () {
          return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
        }, STEP_MS);
      });
    }).then(function (subscription) {
      return within('save subscription', function () { return request('/api/push/subscribe', subscription.toJSON()); }, STEP_MS)
        .then(function () { return subscription; });
    });
  }

  function note(failure) {
    if (failure && failure.status === 401) return 'Sign in first';
    if (failure && failure.status === 422) return 'Not supported here';
    if (failure && failure.timedOut) return 'Didn’t respond';
    return 'Try again';
  }
  function yesNo(value) { return value ? 'yes' : 'no'; }
  function installed() {
    try { if (window.matchMedia('(display-mode: standalone)').matches) return true; } catch (error) {}
    return navigator.standalone === true;
  }
  // Key: value text, safe to copy and to send: no endpoint, key, cookie or token.
  function detailText(action, failure) {
    var lines = [
      'Action: ' + action,
      'Step: ' + (failure.failedStep || 'unknown'),
      'Error: ' + failure.name + ': ' + failure.message,
    ];
    if (failure.status) lines.push('HTTP status: ' + failure.status);
    if (typeof failure.waited === 'number') lines.push('Waited: ' + (failure.waited / 1000).toFixed(1) + ' s');
    lines.push(
      'Permission: ' + (window.Notification ? Notification.permission : 'unavailable'),
      'Installed app: ' + yesNo(installed()),
      'Online: ' + yesNo(navigator.onLine !== false),
      'Browser: ' + navigator.userAgent,
      'Script: ' + (script || 'unknown'),
      'Page: ' + location.pathname,
      'Time: ' + new Date().toISOString()
    );
    return lines.join('\n') + '\n';
  }
  function clearDetails() {
    if (!details) return;
    details.hidden = true;
    details.open = false;
    detailsText.textContent = '';
  }
  // Shows the diagnosis under the row and sends it to operators; a 401 can't be sent.
  function fail(action, error, state) {
    var failure = error && error.failedStep ? error : { failedStep: 'unknown', name: error && error.name ? String(error.name) : 'Error', message: error && error.message ? String(error.message) : String(error), status: error && error.status ? error.status : null };
    show(state, note(failure));
    var text = detailText(action, failure);
    if (details) {
      detailsText.textContent = text;
      details.hidden = false;
    }
    if (failure.status !== 401) request('/api/push/report', { report: text }).catch(function () {});
  }

  function turnOn() {
    // iPhone only asks from inside the tap, so permission comes before anything else.
    var asked = Notification.permission === 'granted' ? Promise.resolve('granted') : Notification.requestPermission();
    clearDetails();
    show('working');
    Promise.resolve(asked).then(function (permission) {
      if (permission !== 'granted') {
        show(permission === 'denied' ? 'blocked' : 'off');
        return;
      }
      return registration().then(subscribe).then(function () { show('on'); });
    }, function (error) {
      throw { failedStep: 'permission', name: error && error.name ? String(error.name) : 'Error', message: error && error.message ? String(error.message) : String(error) };
    }).catch(function (error) { fail('Turn on notifications', error, 'off'); });
  }

  function turnOff() {
    clearDetails();
    show('working');
    registration().then(function (reg) {
      return within('existing subscription', function () { return reg.pushManager.getSubscription(); }, STEP_MS);
    }).then(function (subscription) {
      if (!subscription) return;
      var endpoint = subscription.endpoint;
      return within('unsubscribe', function () { return subscription.unsubscribe(); }, STEP_MS).then(function () {
        return request('/api/push/unsubscribe', { endpoint: endpoint }).catch(function () {});
      });
    }).then(function () { show('off'); }, function (error) { fail('Turn off notifications', error, 'off'); });
  }

  function sendTest() {
    test.disabled = true;
    clearDetails();
    registration().then(function (reg) {
      return within('existing subscription', function () { return reg.pushManager.getSubscription(); }, STEP_MS);
    }).then(function (subscription) {
      if (!subscription) { show('off'); return; }
      return within('send test', function () { return request('/api/push/test', { endpoint: subscription.endpoint }); }, STEP_MS).then(function (body) {
        if (body.outcome === 'sent') {
          show('on', 'Test sent');
        } else if (body.outcome === 'gone') {
          // The server no longer sends here (signed out, or the push service dropped it).
          return subscription.unsubscribe().catch(function () {}).then(function () { show('off'); });
        } else {
          show('on', 'Refused' + (body.reason ? ': ' + body.reason : ''));
        }
      });
    }).catch(function (error) { fail('Send a test notification', error, 'on'); }).then(function () { test.disabled = false; });
  }

  toggle.addEventListener('click', function () {
    if (row.getAttribute('data-push-state') === 'on') turnOff(); else turnOn();
  });
  test.addEventListener('click', sendTest);
  if (copy) {
    copy.addEventListener('click', function () {
      var text = detailsText.textContent;
      Promise.resolve().then(function () { return navigator.clipboard.writeText(text); }).then(function () {
        copy.textContent = 'Copied';
        setTimeout(function () { copy.textContent = 'Copy'; }, 1500);
      }, function () {
        // Clipboard refused: select the text so it can be copied by hand.
        var range = document.createRange();
        range.selectNodeContents(detailsText);
        var selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
      });
    });
  }

  // On load: find the real state. A subscription the browser still holds is saved again, so it
  // follows the current sign-in and the current site key.
  if (Notification.permission === 'denied') { show('blocked'); return; }
  registration().then(function (reg) {
    return within('existing subscription', function () { return reg.pushManager.getSubscription(); }, STEP_MS).then(function (subscription) {
      if (!subscription || Notification.permission !== 'granted') { show('off'); return; }
      return subscribe(reg).then(function () { show('on'); }, function (error) {
        if (error && error.status === 401) { show('off', note(error)); return; }
        fail('Check notifications on load', error, 'on');
      });
    });
  }).catch(function () {});
})();
