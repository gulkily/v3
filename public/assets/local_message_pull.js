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

  function setStatus(message, kind) {
    statusNode.textContent = message;
    statusNode.dataset.kind = kind || 'info';
    statusNode.setAttribute('role', kind === 'error' ? 'alert' : 'status');
  }

  function stepText(name, text, done) {
    var item = root.querySelector('[data-local-pull-step="' + name + '"]');
    item.querySelector('[data-local-pull-step-text]').textContent = (done ? '✓ ' : '') + text;
    item.dataset.done = done ? 'true' : 'false';
    return item;
  }

  function relay(fields) {
    var body = new URLSearchParams(Object.assign({ token: root.dataset.token, source: source() }, fields));
    return fetch('/local/remote', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString()
    }).then(function (response) {
      return response.json().catch(function () { return { status: 'error', error: 'The local server sent an unexpected response.' }; });
    }).then(function (result) {
      if (result.status !== 'ok') throw new Error(result.error || 'The request failed.');
      return result;
    });
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
                return crypto.readOriginal(item.envelope, entry.keys, page.sender_keys[item.sender] || []).then(function () {
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
    }).catch(function (error) { setStatus(error.message, 'error'); });
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
      setStatus(error.message + ' Press Register to retry.', 'error');
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
        trustList.textContent = '';
        candidates.keys.forEach(function (key) {
          var item = document.createElement('li');
          item.textContent = key.username + (key.own ? ' (this browser)' : '') + ' · ' + key.identity_id.slice(8, 24) + '…';
          trustList.appendChild(item);
        });
        trustBox.hidden = false;
        setStatus('Pulled. Trust the listed keys to read your mail here.');
      });
    }).catch(function (error) {
      setStatus(/Import public content above/.test(error.message) ? error.message : error.message + ' Press Pull to resume.', 'error');
    }).then(function () { pullButton.removeAttribute('aria-disabled'); });
  });

  function finish(total) {
    setStatus('Checking pulled history keys…');
    return verifyPage('', { verified: [], failed: 0 }).then(function (tally) {
      return confirmVerified(tally.verified, 0).then(function (confirmed) {
        summaryNode.hidden = false;
        summaryNode.textContent = 'Pulled: ' + summaryText(total) + ' · ' + confirmed + ' confirmed on ' + host
          + (tally.failed ? ' · ' + tally.failed + ' not readable yet' : '');
        setStatus('Pull finished. Open Messages to read them.');
      });
    });
  }

  trustButton.addEventListener('click', function () {
    if (!pendingTrust || trustButton.getAttribute('aria-disabled') === 'true') return;
    trustButton.setAttribute('aria-disabled', 'true');
    setStatus('Trusting these keys in this clone…');
    var pending = pendingTrust;
    relay({ action: 'trust', identity_id: currentState.identity_id, keys: JSON.stringify(pending.keys.map(function (key) { return key.identity_id; })) })
      .then(function () {
        pendingTrust = null;
        trustBox.hidden = true;
        return finish(pending.total);
      }).catch(function (error) {
        setStatus(error.message + ' Press Trust to retry.', 'error');
      }).then(function () { trustButton.removeAttribute('aria-disabled'); });
  });

  root.querySelector('[data-local-pull-check]').addEventListener('click', function () {
    setStatus('Checking this browser\'s key…');
    refresh();
  });
  if (sourceField) sourceField.addEventListener('change', refresh);

  refresh();
})();
