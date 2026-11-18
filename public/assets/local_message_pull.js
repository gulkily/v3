// "Your messages": register this browser's key on the source, then pull (later stages).
(function () {
  var root = document.querySelector('[data-local-pull]');
  if (!root) return;
  var statusNode = root.querySelector('[data-local-pull-status]');
  var steps = root.querySelector('[data-local-pull-steps]');
  var registerButton = root.querySelector('[data-local-pull-register]');
  var host = '';
  try { host = new URL(root.dataset.source).host; } catch (error) { host = root.dataset.source; }
  root.querySelectorAll('[data-local-pull-host]').forEach(function (node) { node.textContent = host; });

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
    var body = new URLSearchParams(Object.assign({ token: root.dataset.token, source: root.dataset.source }, fields));
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
    stepText('import', state.locally_approved ? 'This clone knows the approval.'
      : 'After approving, run Import above so this clone learns the approval.', state.locally_approved);
    setStatus(state.approved && state.locally_approved ? 'This key is ready.' : 'Finish the steps below, then reload this page.');
  }

  function refresh() {
    var publicKey = stored('forum_pki_public_key');
    if (!publicKey || !stored('forum_pki_private_key')) {
      setStatus('This browser has no key yet. Create one on Account (/account/key/), then reload this page.');
      return Promise.resolve(null);
    }
    return relay({ action: 'key_status', public_key: publicKey }).then(function (state) {
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

  refresh();
})();
