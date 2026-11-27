// "Your messages": register this browser's key on the source, then pull (later stages).
(function () {
  var root = document.querySelector('[data-local-pull]');
  if (!root) return;
  var statusNode = root.querySelector('[data-local-pull-status]');
  var steps = root.querySelector('[data-local-pull-steps]');
  var registerButton = root.querySelector('[data-local-pull-register]');
  var pullButton = root.querySelector('[data-local-pull-start]');
  var summaryNode = root.querySelector('[data-local-pull-summary]');
  var currentState = null;
  var trustBox = root.querySelector('[data-local-pull-trust]');
  var trustList = root.querySelector('[data-local-pull-trust-list]');
  var trustButton = root.querySelector('[data-local-pull-trust-button]');
  var pendingTrust = null;
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

  function stepText(name, text, done) {
    var item = root.querySelector('[data-local-pull-step="' + name + '"]');
    item.querySelector('[data-local-pull-step-text]').textContent = (done ? '✓ ' : '') + text;
    item.dataset.done = done ? 'true' : 'false';
    return item;
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

  function render(state) {
    steps.hidden = false;
    if (state.source_approved_keys === 0) {
      setStatus(host + ' has no approved key named "' + state.username + '". This browser\'s key must use the same username as your account there. Change the username on Account, then reload.', 'error');
      steps.hidden = true;
      return;
    }
    // Both sides must agree on this key's name: the clone shows mail only for its own name.
    if (state.registered && state.source_username && state.local_username
        && state.source_username.toLowerCase() !== state.local_username.toLowerCase()) {
      steps.hidden = true;
      var mismatch = new Error('Names differ');
      mismatch.details = { step: 'local pull: key_status', error: 'username on the source differs from this clone', key: state.identity_id, source_username: state.source_username, local_username: state.local_username };
      setStatus(host + ' has this key as "' + state.source_username + '", this clone as "' + state.local_username
        + '". This clone shows mail only for its own name, so use a key named "' + state.source_username + '" here.', 'error', mismatch);
      return;
    }
    var registered = state.registered;
    stepText('register', registered ? 'This key is registered on ' + host + ' as ' + state.username + '.'
      : 'Register this key on ' + host + ' as ' + state.username + '.', registered);
    registerButton.hidden = registered;
    var approve = stepText('approve', state.approved ? 'Approved on ' + host + '.'
      : 'Approve it on ' + host + ' from a device that is already approved: ', state.approved);
    if (registered && !state.approved && !approve.querySelector('a')) {
      var link = document.createElement('a');
      link.href = state.source_key_url;
      link.textContent = 'open this key on ' + host;
      link.target = '_blank';
      link.rel = 'noopener';
      approve.querySelector('[data-local-pull-step-text]').appendChild(link);
    }
    stepText('import', 'Import public content above, so this clone has your correspondents\' public keys. Run it again when you write to someone new.', false);
    var ready = state.approved && state.locally_known;
    stepText('pull', ready ? 'Pull your messages. Pull again any time to fetch new ones.'
      : 'Pull your messages once the steps above are done.', false);
    pullButton.hidden = !ready;
    setStatus(ready ? 'This key is ready.' : 'Finish the steps below, then reload this page.');
  }

  function summaryText(total) {
    var parts = [total.added + ' new', total.existing + ' already here'];
    if (total.mismatched.length) parts.push(total.mismatched.length + ' kept local because they differ (' + total.mismatched.slice(0, 3).join(', ') + ')');
    parts.push(total.transfers_added + ' history keys');
    if (total.transfers_mismatched) parts.push(total.transfers_mismatched + ' history keys kept local');
    return parts.join(' · ');
  }

  // The history crypto module needs only the OpenPGP API; this page skips the full identity script.
  if (!window.__forumBrowserIdentity) {
    window.__forumBrowserIdentity = { ensureOpenPgpApi: function () { return window.__forumOpenPgpLoader.load(); } };
  }

  // Verify each pulled transfer to this key with the same checks Messages uses:
  // the donor's bundle, the envelope digest, and the original sender's signature.
  function verifyPage(after, tally) {
    var crypto = window.ForumPrivateMessageHistoryCrypto;
    var fields = { action: 'verify_list', identity_id: currentState.identity_id };
    if (after) fields.after = after;
    return relay(fields).then(function (page) {
      var privateKey = stored('forum_pki_private_key');
      return page.transfers.reduce(function (chain, transfer) {
        return chain.then(function () {
          if (!transfer.source_key) { tally.failed += transfer.items.length; return; }
          return crypto.unwrapHistoryKeys({ ciphertext: transfer.ciphertext, privateKey: privateKey, sourceKey: transfer.source_key,
            target: transfer.target, source: transfer.source, account: page.account, source_account: transfer.source_account
          }).then(function (entries) {
            var byId = {};
            entries.forEach(function (entry) { byId[entry.message_id] = entry; });
            return transfer.items.reduce(function (inner, item) {
              return inner.then(function () {
                var entry = byId[item.message_id];
                if (!entry || entry.digest !== item.digest || crypto.digest(item.envelope) !== item.digest) { tally.failed++; return; }
                if (tally.seen[item.message_id]) return;
                return crypto.readOriginal(item.envelope, entry.keys, page.sender_keys[item.sender] || []).then(function () {
                  if (tally.seen[item.message_id]) return;
                  tally.seen[item.message_id] = true;
                  tally.verified.push({ message_id: item.message_id, digest: item.digest, transfer_id: transfer.transfer_id });
                }, function () { tally.failed++; });
              });
            }, Promise.resolve());
          }, function () { tally.failed += transfer.items.length; });
        });
      }, Promise.resolve()).then(function () {
        return page.next ? verifyPage(page.next, tally) : tally;
      });
    });
  }

  function confirmVerified(items, confirmed) {
    if (!items.length) return Promise.resolve(confirmed);
    var batch = items.slice(0, 100);
    return relay({ action: 'challenge' }).then(function (challenge) {
      return signText(String(challenge.challenge)).then(function (signature) {
        return relay({ action: 'confirm', identity_id: currentState.identity_id, challenge: String(challenge.challenge),
          signature: signature, items: JSON.stringify(batch) });
      });
    }).then(function (result) {
      return confirmVerified(items.slice(100), confirmed + (result.confirmed || 0));
    });
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

  function refresh() {
    updateHost();
    var publicKey = stored('forum_pki_public_key');
    if (!publicKey || !stored('forum_pki_private_key')) {
      setStatus('This browser has no key yet. Create one on Account (/account/key/), then reload this page.');
      return Promise.resolve(null);
    }
    return relay({ action: 'key_status', public_key: publicKey }).then(function (state) {
      currentState = state;
      render(state);
      return state;
    }).catch(function (error) { setStatus(error.message, 'error', error); });
  }

  registerButton.addEventListener('click', function () {
    if (registerButton.getAttribute('aria-disabled') === 'true') return;
    registerButton.setAttribute('aria-disabled', 'true');
    setStatus('Registering this key on ' + host + '…');
    relay({ action: 'prepare_identity', public_key: stored('forum_pki_public_key') }).then(function (prepared) {
      if (prepared.exists || !prepared.canonical_record) return prepared;
      return signText(String(prepared.canonical_record)).then(function (signature) {
        return relay({ action: 'create_identity', prepare_token: String(prepared.prepare_token || ''),
          canonical_record: String(prepared.canonical_record), detached_signature: signature });
      });
    }).then(refresh).catch(function (error) {
      setStatus(error.message + ' Press Register to retry.', 'error', error);
    }).then(function () { registerButton.removeAttribute('aria-disabled'); });
  });

  pullButton.addEventListener('click', function () {
    if (pullButton.getAttribute('aria-disabled') === 'true' || !currentState) return;
    pullButton.setAttribute('aria-disabled', 'true');
    setStatus('Pulling your messages from ' + host + '… Keep this page open.');
    trustBox.hidden = true;
    pullOnce({ added: 0, existing: 0, mismatched: [], transfers_added: 0, transfers_mismatched: 0, read_marked: 0 }).then(function (total) {
      return relay({ action: 'trust_candidates', identity_id: currentState.identity_id }).then(function (candidates) {
        if (candidates.missing && candidates.missing.length) {
          summaryNode.hidden = false;
          summaryNode.textContent = 'Pulled: ' + summaryText(total);
          throw new Error('This clone is missing ' + candidates.missing.length + ' key(s) that signed your mail. Import public content above, then pull again.');
        }
        if (!candidates.keys.length) return finish(total);
        // Pause for one explicit click before this clone approves anyone.
        pendingTrust = { total: total, keys: candidates.keys };
        renderTrustList(candidates.keys);
        trustBox.hidden = false;
        setStatus('Pulled. Trust the listed keys to read your mail here.');
      });
    }).catch(function (error) {
      setStatus(/Import public content above|use a key named/.test(error.message) ? error.message : error.message + ' Press Pull to resume.', 'error', error);
    }).then(function () { pullButton.removeAttribute('aria-disabled'); });
  });

  // One row per person: "ilyag: 31 keys, including this browser", opening to short fingerprints.
  function renderTrustList(keys) {
    var groups = {}, order = [];
    keys.forEach(function (key) {
      if (!groups[key.username]) { groups[key.username] = []; order.push(key.username); }
      groups[key.username].push(key);
    });
    trustList.textContent = '';
    order.forEach(function (username) {
      var list = groups[username], own = list.some(function (key) { return key.own; });
      var item = document.createElement('li');
      var details = document.createElement('details');
      var summary = document.createElement('summary');
      var name = document.createElement('strong');
      name.textContent = username;
      summary.appendChild(name);
      summary.appendChild(document.createTextNode(': ' + list.length + (list.length === 1 ? ' key' : ' keys') + (own ? ', including this browser' : '')));
      details.appendChild(summary);
      var fingerprints = document.createElement('ul');
      list.forEach(function (key) {
        var row = document.createElement('li');
        var code = document.createElement('code');
        var fingerprint = key.identity_id.slice(8).toUpperCase();
        code.textContent = '…' + fingerprint.slice(-16).replace(/(.{4})(?=.)/g, '$1 ');
        code.title = fingerprint.replace(/(.{4})(?=.)/g, '$1 ');
        row.appendChild(code);
        if (key.own) {
          var chip = document.createElement('span');
          chip.className = 'chip';
          chip.textContent = 'this browser';
          row.appendChild(document.createTextNode(' '));
          row.appendChild(chip);
        }
        fingerprints.appendChild(row);
      });
      details.appendChild(fingerprints);
      item.appendChild(details);
      trustList.appendChild(item);
    });
  }

  function finish(total) {
    setStatus('Checking pulled history keys…');
    return verifyPage('', { verified: [], failed: 0, seen: {} }).then(function (tally) {
      return confirmVerified(tally.verified, 0).then(function (confirmed) {
        summaryNode.hidden = false;
        summaryNode.textContent = 'Pulled: ' + summaryText(total) + ' · ' + confirmed + ' confirmed on ' + host
          + (tally.failed ? ' · ' + tally.failed + ' not readable yet' : '');
        setStatus('Pull finished. Open Messages to read them.');
      });
    });
  }

  // One request per key, this browser's key first, so progress shows and a retry resumes.
  function trustNext(pending, done) {
    var count = pending.keys.length;
    trustButton.textContent = 'Trusting ' + Math.min(done + 1, count) + ' of ' + count + '…';
    setStatus('Trusting ' + Math.min(done + 1, count) + ' of ' + count + ' keys in this clone…');
    if (done >= count) return Promise.resolve();
    return relay({ action: 'trust', identity_id: currentState.identity_id, keys: JSON.stringify([pending.keys[done].identity_id]) })
      .then(function () { pending.done = done + 1; return trustNext(pending, done + 1); });
  }

  trustButton.addEventListener('click', function () {
    if (!pendingTrust || trustButton.getAttribute('aria-disabled') === 'true') return;
    var pending = pendingTrust;
    pending.keys.sort(function (a, b) { return (b.own ? 1 : 0) - (a.own ? 1 : 0); });
    trustButton.setAttribute('aria-disabled', 'true');
    trustButton.setAttribute('aria-busy', 'true');
    trustNext(pending, pending.done || 0).then(function () {
      pendingTrust = null;
      trustBox.hidden = true;
      return finish(pending.total);
    }).catch(function (error) {
      setStatus(error.message + ' Press Trust to continue.', 'error', error);
    }).then(function () {
      trustButton.removeAttribute('aria-disabled');
      trustButton.removeAttribute('aria-busy');
      trustButton.textContent = 'Trust these keys here';
    });
  });

  root.querySelector('[data-local-pull-check]').addEventListener('click', function () {
    setStatus('Checking this browser\'s key…');
    refresh();
  });
  if (sourceField) sourceField.addEventListener('change', refresh);

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

  connectButton.addEventListener('click', function () {
    if (connectButton.getAttribute('aria-disabled') === 'true') return;
    connectButton.setAttribute('aria-disabled', 'true');
    connectButton.setAttribute('aria-busy', 'true');
    connect().then(function () {
      root.dispatchEvent(new CustomEvent('local-pull:connected'));
      setStatus('Connected.');
      return refresh();
    }).catch(function (error) {
      fallback.hidden = true;
      setStatus(error.message, 'error', error);
    }).then(function () {
      connectButton.removeAttribute('aria-disabled');
      connectButton.removeAttribute('aria-busy');
    });
  });

  refresh();
})();
