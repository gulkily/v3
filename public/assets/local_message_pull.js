// "Your messages": Connect brings this browser's key from the source in a popup, then imports,
// pulls and trusts in one pass. Pull fetches new mail later.
(function () {
  var root = document.querySelector('[data-local-pull]');
  if (!root) return;
  var statusNode = root.querySelector('[data-local-pull-status]');
  var pullButton = root.querySelector('[data-local-pull-start]');
  var summaryNode = root.querySelector('[data-local-pull-summary]');
  var currentState = null;
  var host = '';
  var sourceField = document.querySelector('[data-local-import] input[name="source"]');
  // Follow the import form's source, so both cards always talk to the same instance.
  function source() { return sourceField && sourceField.value.trim() !== '' ? sourceField.value.trim() : root.dataset.source; }
  function updateHost() {
    try { host = new URL(source().indexOf('://') === -1 ? 'https://' + source() : source()).host; } catch (error) { host = source(); }
    root.querySelectorAll('[data-local-pull-host]').forEach(function (node) { node.textContent = host; });
  }
  updateHost();

  function stored(name) {
    try { return window.localStorage.getItem(name) || ''; } catch (error) { return ''; }
  }

  // Failures: a short message, then a closed Details disclosure with a Copy button (CONTRIBUTING:
  // "Error messages and diagnostics"). Details hold public facts only: never keys, cookies or message text.
  var detailsNode = null;
  function setStatus(message, kind, error) {
    statusNode.textContent = message;
    statusNode.dataset.kind = kind || 'info';
    statusNode.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    if (detailsNode) { detailsNode.remove(); detailsNode = null; }
    if (kind !== 'error') return;
    var fields = Object.assign({ step: 'local pull' }, (error && error.details) || {}, {
      page: location.pathname, source: source(), key: (currentState && currentState.identity_id) || (error && error.details && error.details.key) || '',
      online: navigator.onLine, browser: navigator.userAgent, at: new Date().toISOString()
    });
    if (error && !(error.details && error.details.error)) fields.error = (error.name || 'Error') + ': ' + error.message;
    var text = Object.keys(fields).filter(function (key) { return fields[key] !== null && fields[key] !== ''; })
      .map(function (key) { return key + ': ' + fields[key]; }).join('\n');
    detailsNode = document.createElement('details');
    detailsNode.className = 'local-pull-details';
    detailsNode.dataset.localPullDetails = '';
    var summary = document.createElement('summary');
    summary.textContent = 'Details';
    var pre = document.createElement('pre');
    pre.textContent = text;
    var copy = document.createElement('button');
    copy.type = 'button';
    copy.textContent = 'Copy';
    copy.addEventListener('click', function () {
      (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject(new Error('Clipboard unavailable'))).then(function () {
        copy.textContent = 'Copied';
        window.setTimeout(function () { copy.textContent = 'Copy'; }, 1500);
      }, function () { copy.textContent = 'Select the text to copy'; });
    });
    detailsNode.append(summary, pre, copy);
    statusNode.after(detailsNode);
  }

  // Every relayed step has a time limit and names itself when it fails.
  var RELAY_TIMEOUT_MS = 180000;
  function relay(fields) {
    var body = new URLSearchParams(Object.assign({ token: root.dataset.token, source: source() }, fields));
    var step = 'local pull: ' + fields.action, started = Date.now();
    var controller = window.AbortController ? new AbortController() : null;
    var timer = controller ? window.setTimeout(function () { controller.abort(); }, RELAY_TIMEOUT_MS) : null;
    function failure(message, details) {
      var error = new Error(message);
      error.details = Object.assign({ step: step, waited_ms: Date.now() - started }, details || {});
      return error;
    }
    return fetch('/local/remote', {
      method: 'POST', credentials: 'same-origin', signal: controller ? controller.signal : undefined,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
    }).then(function (response) {
      return response.json().catch(function () {
        throw failure('The local server sent an unexpected response.', { error: 'HTTP ' + response.status + ' without JSON' });
      });
    }, function (error) {
      throw failure(error.name === 'AbortError' ? 'This step took longer than ' + (RELAY_TIMEOUT_MS / 1000) + ' seconds.'
        : 'The local server could not be reached. Check that ./v3 start is still running.', { error: error.name + ': ' + error.message });
    }).then(function (result) {
      if (result.status !== 'ok') throw failure(result.error || 'The request failed.', result.details);
      return result;
    }).finally(function () { if (timer) window.clearTimeout(timer); });
  }

  function signText(text) {
    return window.__forumOpenPgpLoader.load().then(function (openpgp) {
      return openpgp.readPrivateKey({ armoredKey: stored('forum_pki_private_key') }).then(function (privateKey) {
        return openpgp.createMessage({ text: text }).then(function (message) {
          return openpgp.sign({ message: message, signingKeys: privateKey, detached: true, format: 'armored' });
        });
      });
    });
  }

  function summaryText(total) {
    var parts = [total.added + ' new', total.existing + ' already here'];
    if (total.mismatched.length) parts.push(total.mismatched.length + ' kept local because they differ (' + total.mismatched.slice(0, 3).join(', ') + ')');
    parts.push(total.transfers_added + ' history keys');
    if (total.transfers_mismatched) parts.push(total.transfers_mismatched + ' history keys kept local');
    return parts.join(' · ');
  }

  function pullOnce(total) {
    return relay({ action: 'challenge' }).then(function (challenge) {
      return signText(String(challenge.challenge)).then(function (signature) {
        return relay({ action: 'pull', identity_id: currentState.identity_id, challenge: String(challenge.challenge), signature: signature });
      });
    }).then(function (result) {
      ['added', 'existing', 'transfers_added', 'transfers_mismatched', 'read_marked'].forEach(function (key) { total[key] += result[key] || 0; });
      total.mismatched = total.mismatched.concat(result.mismatched || []);
      summaryNode.hidden = false;
      summaryNode.textContent = (result.done ? 'Pulled: ' : 'Pulling… ') + summaryText(total);
      return result.done ? total : pullOnce(total);
    });
  }

  // On load: a key that is connected already gets Pull; anything else gets Connect.
  function refresh() {
    updateHost();
    pullButton.hidden = true;
    var publicKey = stored('forum_pki_public_key');
    if (!publicKey || !stored('forum_pki_private_key')) {
      setStatus('Press Connect, then confirm on ' + host + ' in the window that opens.');
      return Promise.resolve(null);
    }
    return relay({ action: 'key_status', public_key: publicKey }).then(function (state) {
      currentState = state;
      var ready = state.approved && state.locally_approved;
      pullButton.hidden = !ready;
      setStatus(ready ? 'Connected as ' + state.username + '. Pull fetches new messages.'
        : 'Press Connect to read your messages from ' + host + ' here.');
      return state;
    }).catch(function (error) { setStatus(error.message, 'error', error); });
  }


  // Connect: zenmemes.com hands this browser's key over in a popup, after one click there
  // (key_handoff.js). Only a message from the source's origin, of the expected shape, is accepted.
  var HANDOFF_TIMEOUT_MS = window.__forumHandoffTimeoutMs || 120000; // tests may shorten it
  var connectButton = root.querySelector('[data-local-connect]');
  var fallback = root.querySelector('[data-local-connect-fallback]');
  var fallbackLink = root.querySelector('[data-local-connect-link]');
  var replaceBox = root.querySelector('[data-local-connect-replace]');
  function sourceOrigin() {
    var value = source();
    return new URL(value.indexOf('://') === -1 ? 'https://' + value : value).origin;
  }
  function stepError(message, step, detail) {
    var error = new Error(message);
    error.details = { step: step, error: detail };
    return error;
  }

  function receiveKey(origin, popup) {
    return new Promise(function (resolve, reject) {
      var timer = window.setTimeout(function () {
        window.removeEventListener('message', listen);
        reject(stepError('No answer from ' + host + ' within 2 minutes. Press Connect to try again.', 'local pull: connect handoff', 'timed out after ' + (HANDOFF_TIMEOUT_MS / 1000) + ' s'));
      }, HANDOFF_TIMEOUT_MS);
      function listen(event) {
        var data = event.data;
        if (event.origin !== origin || (popup && event.source !== popup)) return;
        if (!data || data.type !== 'forum-key-handoff' || data.version !== 1
            || typeof data.privateKey !== 'string' || typeof data.fingerprint !== 'string') return;
        window.clearTimeout(timer);
        window.removeEventListener('message', listen);
        resolve({ privateKey: data.privateKey, fingerprint: data.fingerprint.toLowerCase(), username: String(data.username || '') });
      }
      window.addEventListener('message', listen);
    });
  }

  // A different key already here: name both and wait for an explicit choice.
  function chooseReplace(received) {
    var current = stored('forum_pki_fingerprint').toLowerCase();
    if (!current || current === received.fingerprint) return Promise.resolve(false);
    var short = function (fingerprint) { return '…' + fingerprint.toUpperCase().slice(-8); };
    replaceBox.querySelector('[data-local-connect-replace-text]').textContent = 'This browser already has a key here: '
      + (stored('forum_pki_username') || 'unnamed') + ' ' + short(current) + '. Replace it with ' + (received.username || 'your key')
      + ' ' + short(received.fingerprint) + ' from ' + host + '?';
    replaceBox.hidden = false;
    return new Promise(function (resolve, reject) {
      replaceBox.querySelector('[data-local-connect-replace-yes]').onclick = function () { replaceBox.hidden = true; resolve(true); };
      replaceBox.querySelector('[data-local-connect-replace-no]').onclick = function () {
        replaceBox.hidden = true;
        reject(stepError('Kept this browser\'s key. Nothing changed.', 'local pull: connect replace', 'declined'));
      };
    });
  }

  function connect() {
    var origin;
    try { origin = sourceOrigin(); } catch (error) { return Promise.reject(stepError('The source address is not valid.', 'local pull: connect', error.message)); }
    var url = origin + '/account/key/share?to=' + encodeURIComponent(location.origin);
    var name = 'forum-key-handoff-' + Date.now();
    var popup = window.open(url, name, 'popup,width=520,height=640');
    fallback.hidden = Boolean(popup);
    fallbackLink.href = url;
    fallbackLink.target = name;
    setStatus(popup ? 'Waiting for you to confirm on ' + host + '…' : 'Your browser blocked the window. Use the link to open ' + host + '.');
    return receiveKey(origin, popup).then(function (received) {
      fallback.hidden = true;
      return chooseReplace(received).then(function (replace) {
        setStatus('Installing your key…');
        return window.__forumBrowserIdentity.importPrivateKey(received.privateKey, received.fingerprint, { replace: replace });
      });
    });
  }

  // After the key is here, Connect runs the rest in one pass: import public content when this
  // clone does not know the key yet, pull, trust the keys the source approves, turn on saved
  // copies. The stage is kept in sessionStorage, so a reload (import_control.js reloads when an
  // import ends) resumes it; every stage is safe to repeat.
  var CONNECT_KEY = 'forum-local-connect';
  var CONNECT_MAX_AGE_MS = 30 * 60 * 1000;
  var IMPORT_LIMIT_MS = window.__forumConnectImportLimitMs || 20 * 60 * 1000; // tests may shorten it
  function saveStage(stage, extra) {
    try { sessionStorage.setItem(CONNECT_KEY, JSON.stringify(Object.assign({ stage: stage, source: source(), at: Date.now() }, extra || {}))); } catch (_) {}
  }
  function savedStage() {
    try {
      var saved = JSON.parse(sessionStorage.getItem(CONNECT_KEY) || 'null');
      if (saved && saved.source === source() && Date.now() - saved.at < CONNECT_MAX_AGE_MS) return saved;
    } catch (_) {}
    clearStage();
    return null;
  }
  function clearStage() { try { sessionStorage.removeItem(CONNECT_KEY); } catch (_) {} }

  // The import card's own JSON endpoint, the same requests import_control.js sends.
  function importRequest(action) {
    return fetch('/local/', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'ForumImport' },
      body: JSON.stringify({ action: action, source: source(), token: root.dataset.token })
    }).then(function (response) {
      return response.json().catch(function () { return { status: 'error', error: 'HTTP ' + response.status + ' without JSON' }; });
    });
  }
  function importStepText(state) {
    var current = (state.steps || []).filter(function (step) { return step.status === 'current'; })[0];
    return current ? ' ' + current.label + '…' : '';
  }
  function waitForImport(started) {
    return new Promise(function (resolve) { window.setTimeout(resolve, 2000); }).then(function () {
      return importRequest('status');
    }).then(function (data) {
      if (data.status !== 'ok') {
        if (Date.now() - started > IMPORT_LIMIT_MS) throw stepError('The import did not finish. See the import card above.', 'local pull: connect import', data.error || 'status refused');
        return waitForImport(started);
      }
      var state = data.state;
      if (state.busy) {
        if (Date.now() - started > IMPORT_LIMIT_MS) throw stepError('The import is taking longer than 20 minutes. Keep this page open; Connect continues when it ends.', 'local pull: connect import', 'still running after ' + Math.round((Date.now() - started) / 1000) + ' s');
        setStatus('Importing public content from ' + host + '.' + importStepText(state) + ' Keep this page open.');
        return waitForImport(started);
      }
      if (state.task && state.task.status === 'failed') throw stepError('The import did not finish. See the import card above, then press Connect again.', 'local pull: connect import', 'task ' + state.task.status);
      return state;
    });
  }
  function runImport() {
    setStatus('Importing public content from ' + host + '… Keep this page open.');
    return importRequest('status').then(function (data) {
      if (data.status !== 'ok') throw stepError(data.error || 'The import could not start.', 'local pull: connect import', 'status refused');
      if (data.state.busy) return null;
      saveStage('import', { imported: true });
      return importRequest(data.state.pending ? 'resume' : 'import').then(function (queued) {
        if (queued.status !== 'ok') throw stepError(queued.error || 'The import could not start.', 'local pull: connect import', 'queue refused');
      });
    }).then(function () { return waitForImport(Date.now()); });
  }

  function trustAll(keys) {
    keys.sort(function (a, b) { return (b.own ? 1 : 0) - (a.own ? 1 : 0); });
    return keys.reduce(function (chain, key, index) {
      return chain.then(function () {
        setStatus('Trusting ' + (index + 1) + ' of ' + keys.length + ' keys that ' + host + ' approves…');
        return relay({ action: 'trust', identity_id: currentState.identity_id, keys: JSON.stringify([key.identity_id]) });
      });
    }, Promise.resolve());
  }

  function connectedState() {
    return relay({ action: 'key_status', public_key: stored('forum_pki_public_key') }).then(function (state) {
      currentState = state;
      if (!state.registered || !state.approved) {
        throw stepError(host + ' has not approved this key. Connect from a browser where you read your messages on ' + host + '.',
          'local pull: connect key_status', state.registered ? 'not approved on the source' : 'not registered on the source');
      }
      if (state.source_username && state.local_username && state.source_username.toLowerCase() !== state.local_username.toLowerCase()) {
        var mismatch = stepError(host + ' has this key as "' + state.source_username + '", this clone as "' + state.local_username
          + '". This clone shows mail only for its own name, so use a key named "' + state.source_username + '" here.',
          'local pull: connect key_status', 'username on the source differs from this clone');
        mismatch.details.source_username = state.source_username;
        mismatch.details.local_username = state.local_username;
        mismatch.final = true; // retrying cannot fix it
        throw mismatch;
      }
      return state;
    });
  }

  // stage: import → pull → trust → saved → done. "imported" stops a second import in one run.
  function runConnect(saved) {
    var imported = Boolean(saved && saved.imported);
    var total = { added: 0, existing: 0, mismatched: [], transfers_added: 0, transfers_mismatched: 0, read_marked: 0 };
    var resumeImport = saved && saved.stage === 'import' ? waitForImport(Date.now()) : Promise.resolve();
    return resumeImport.then(connectedState).then(function (state) {
      if (state.locally_known || imported) return state;
      imported = true;
      return runImport().then(connectedState);
    }).then(function (state) {
      if (!state.locally_known) throw stepError('The import finished, but this clone still does not know this key.', 'local pull: connect import', 'key not in the imported profiles');
      saveStage('pull', { imported: imported });
      setStatus('Pulling your messages from ' + host + '… Keep this page open.');
      return pullOnce(total);
    }).then(function () {
      saveStage('trust', { imported: imported });
      setStatus('Checking which keys signed your mail…');
      return relay({ action: 'trust_candidates', identity_id: currentState.identity_id }).then(function (candidates) {
        if (!candidates.missing.length || imported) return candidates;
        // Someone new wrote to you since the last import: import once more, then ask again.
        imported = true;
        return runImport().then(function () {
          return relay({ action: 'trust_candidates', identity_id: currentState.identity_id });
        });
      });
    }).then(function (candidates) {
      return trustAll(candidates.keys).then(function () { return candidates; });
    }).then(function (candidates) {
      saveStage('saved', { imported: imported });
      return relay({ action: 'saved_copies' }).then(function (saved) { return { candidates: candidates, saved: saved }; });
    }).then(function (result) {
      clearStage();
      var notes = [];
      if (result.candidates.missing.length) notes.push(result.candidates.missing.length + ' sender key(s) are not in this clone yet, so their messages stay unreadable');
      if (!result.saved.saved_copies) notes.push('saved copies are off by ' + (result.saved.overridden_by === 'private-config' ? 'private config' : 'the environment') + ', so Messages decrypts on every visit');
      summaryNode.hidden = false;
      summaryNode.textContent = 'Pulled: ' + summaryText(total);
      pullButton.hidden = false;
      setStatus('Connected. Open Messages to read your mail.' + (notes.length ? ' Note: ' + notes.join('; ') + '.' : ''));
      root.dispatchEvent(new CustomEvent('local-pull:done'));
      return result;
    });
  }

  function connectFailed(error) {
    fallback.hidden = true;
    summaryNode.hidden = true;
    setStatus(error.message + (error.final || /Press Connect|Nothing changed/.test(error.message) ? '' : ' Press Connect to continue.'), 'error', error);
  }

  connectButton.addEventListener('click', function () {
    if (connectButton.getAttribute('aria-disabled') === 'true') return;
    busy(connectButton, true);
    // A key already handed over and stage saved: continue without asking for the key again.
    var saved = savedStage();
    var keyReady = saved && stored('forum_pki_private_key') ? Promise.resolve() : connect().then(function () {
      root.dispatchEvent(new CustomEvent('local-pull:connected'));
      saveStage('start');
    });
    keyReady.then(function () { return runConnect(savedStage()); }).catch(connectFailed).then(function () { busy(connectButton, false); });
  });

  // Pull runs the same pass with the key already here: an import only if senders are missing.
  function busy(button, on) {
    [connectButton, pullButton].forEach(function (node) {
      if (on) node.setAttribute('aria-disabled', 'true'); else node.removeAttribute('aria-disabled');
    });
    if (on) button.setAttribute('aria-busy', 'true'); else button.removeAttribute('aria-busy');
  }
  pullButton.addEventListener('click', function () {
    if (pullButton.getAttribute('aria-disabled') === 'true') return;
    busy(pullButton, true);
    saveStage('start');
    runConnect(savedStage()).catch(connectFailed).then(function () { busy(pullButton, false); });
  });
  if (sourceField) sourceField.addEventListener('change', function () { clearStage(); refresh(); });

  var resumed = savedStage();
  if (resumed && stored('forum_pki_private_key')) {
    busy(connectButton, true);
    updateHost();
    setStatus('Continuing Connect…');
    runConnect(resumed).catch(connectFailed).then(function () { busy(connectButton, false); });
  } else {
    refresh();
  }
})();
