(function () {
  function parseTextResponse(text) {
    var result = {};
    text.split(/\n/).forEach(function (line) {
      var index = line.indexOf("=");
      if (index <= 0) {
        return;
      }
      result[line.slice(0, index)] = line.slice(index + 1);
    });
    return result;
  }

  function setPending(form, pending) {
    (form.closest("[data-feature-flag-row]") || form).querySelectorAll("button").forEach(function (control) {
      control.disabled = pending;
    });
  }

  function statusElementFor(form) {
    var row = form.closest("[data-feature-flag-row]");
    return form.querySelector("[data-role='feature-flag-status']")
      || (row && row.querySelector("[data-role='feature-flag-status']"));
  }

  function requestJson(path, fields) {
    return window.fetch(path, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(fields).toString()
    }).then(function (response) {
      return response.text().then(function (text) {
        var result;
        try {
          result = JSON.parse(text);
        } catch (error) {
          result = { status: "error", error: "Unable to complete signed feature flag change." };
        }
        if (!response.ok || result.status !== "ok") {
          throw new Error(result.error || "Unable to complete signed feature flag change.");
        }
        return result;
      });
    });
  }

  function updateRow(form, result) {
    var row = form.closest("[data-feature-flag-row]");
    if (!row || !result.effective_value) {
      return;
    }

    var effectiveValue = result.effective_value === "true";
    var isDefault = row.getAttribute("data-flag-default") === String(effectiveValue);
    var stateLabel = row.querySelector("[data-role='feature-flag-effective']");
    var source = row.querySelector("[data-role='feature-flag-source']");
    var switchButton = row.querySelector(".switch");
    var toggleForm = row.querySelector("[data-feature-flag-toggle]");
    var resetForm = row.querySelector(".feature-flag-reset-form");
    var badge = row.querySelector(".badge-overridden");

    if (stateLabel) {
      stateLabel.textContent = effectiveValue ? "enabled" : "disabled";
      stateLabel.classList.toggle("is-on", effectiveValue);
    }
    if (source && result.source) {
      source.textContent = result.source;
    }
    if (switchButton) {
      switchButton.setAttribute("aria-checked", String(effectiveValue));
    }
    if (toggleForm) {
      var valueInput = toggleForm.querySelector("input[name='value']");
      if (valueInput) {
        valueInput.value = String(!effectiveValue);
      }
    }
    if (resetForm) {
      resetForm.hidden = isDefault;
    }
    if (badge) {
      badge.hidden = isDefault;
    } else if (!isDefault) {
      var nameEl = row.querySelector(".feature-flag-name");
      if (nameEl) {
        var newBadge = document.createElement("span");
        newBadge.className = "badge badge-overridden";
        newBadge.textContent = "overridden";
        nameEl.appendChild(newBadge);
      }
    }
  }

  var navigationGeneration = 0;
  var pendingRows = new WeakSet();
  var scriptLoads = new Map();

  function identity() {
    try { return JSON.stringify(['username', 'public_key', 'private_key'].map(function (key) { return localStorage.getItem('forum_pki_' + key); })); }
    catch (_) { return ''; }
  }

  function loadScript(path) {
    var url = new URL(path, window.location.href);
    if (url.origin !== window.location.origin || !url.pathname.startsWith('/assets/')) return Promise.reject(new Error('Invalid asset'));
    if (scriptLoads.has(url.href)) return scriptLoads.get(url.href);
    if (Array.from(document.scripts).some(function (script) { return script.src === url.href; })) return Promise.resolve();
    var promise = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = url.href;
      script.onload = resolve;
      script.onerror = function () { script.remove(); scriptLoads.delete(url.href); reject(new Error('Asset unavailable')); };
      document.head.appendChild(script);
    });
    scriptLoads.set(url.href, promise);
    return promise;
  }

  async function startMessaging(state, link, current, savedIdentity) {
    if (!Array.isArray(state.scripts) || state.viewer !== link.dataset.viewer) throw new Error('Invalid messaging state');
    for (var path of state.scripts) {
      if (current !== navigationGeneration || identity() !== savedIdentity) throw new Error('Identity changed');
      await loadScript(path);
    }
    if (current !== navigationGeneration || identity() !== savedIdentity) throw new Error('Identity changed');
    if (!window.ForumPrivateMessageAvailability || !window.ForumPrivateMessageUnread) throw new Error('Messaging unavailable');
    window.ForumPrivateMessageAvailability.setEnabled(true);
    window.ForumPrivateMessageUnread.start(link);
    if (window.ForumPrivateMessageHistorySync) window.ForumPrivateMessageHistorySync.start(link);
  }

  function stopMessaging() {
    if (window.ForumPrivateMessageAvailability) window.ForumPrivateMessageAvailability.setEnabled(false);
    if (window.ForumPrivateMessageUnread) window.ForumPrivateMessageUnread.stop();
    if (window.ForumPrivateMessageHistorySync) window.ForumPrivateMessageHistorySync.stop();
    document.querySelectorAll('[data-private-message-unread]').forEach(function (link) { link.remove(); });
  }

  function refreshNavigation(result, status) {
    var current = ++navigationGeneration;
    var savedIdentity = identity();
    if (result.effective_value === 'false') stopMessaging();
    return window.fetch('/api/feature_flags/navigation', {
      credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
    }).then(function (response) {
      if (!response.ok) throw new Error('Navigation unavailable');
      return response.json();
    }).then(function (state) {
      if (current !== navigationGeneration) return;
      if (savedIdentity !== identity()) { stopMessaging(); throw new Error('Identity changed'); }
      if (state.status !== 'ok' || typeof state.html !== 'string' || typeof state.messaging_enabled !== 'boolean') {
        throw new Error('Invalid navigation');
      }
      var nav = document.querySelector('nav.nav');
      if (!nav) throw new Error('Navigation missing');
      var template = document.createElement('template');
      template.innerHTML = state.html;
      var link = template.content.querySelector('[data-private-message-unread]');
      if (state.messaging_enabled !== Boolean(link)) throw new Error('Invalid messaging navigation');
      if (!state.messaging_enabled) stopMessaging();
      nav.querySelectorAll('[data-private-message-unread]').forEach(function (old) { old.remove(); });
      if (link) {
        var following = link.nextElementSibling;
        var anchor = following && Array.from(nav.children).find(function (item) { return item.getAttribute('href') === following.getAttribute('href'); });
        nav.insertBefore(link, anchor || null);
        return startMessaging(state, link, current, savedIdentity);
      }
    }).catch(function () {
      if (current !== navigationGeneration || !status) return;
      if (savedIdentity !== identity()) stopMessaging();
      var link = document.querySelector('[data-private-message-unread]');
      if (link) {
        link.dataset.unreadState = 'unavailable';
        link.title = 'Unread status unavailable. Reload the page.';
        link.querySelector('#private-message-unread-status').textContent = link.title;
      }
      status.textContent = 'Saved. Navigation could not refresh. ';
      var reload = document.createElement('button');
      reload.type = 'button';
      reload.textContent = 'Reload page';
      reload.addEventListener('click', function () { window.location.reload(); });
      status.appendChild(reload);
    });
  }

  function bindForm(form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var row = form.closest("[data-feature-flag-row]") || form;
      if (pendingRows.has(row)) return;
      pendingRows.add(row);

      var status = statusElementFor(form);
      if (status) {
        status.textContent = "Saving...";
      }
      var fields = Object.fromEntries(new FormData(form).entries());
      var focused = form.contains(document.activeElement) ? document.activeElement : null;
      setPending(form, true);

      Promise.resolve().then(function () {
        if (!window.ForumBrowserSigning || !window.ForumBrowserSigning.ensureActionIdentity || !window.ForumBrowserSigning.signCanonicalRecord) {
          throw new Error("Browser signing is unavailable. Reload the page and try again.");
        }
        return window.ForumBrowserSigning.ensureActionIdentity(null, null);
      }).then(function () {
        return requestJson("/api/prepare_feature_flag_change", fields);
      }).then(function (prepared) {
        if (prepared.wrote_record === "no") {
          return prepared;
        }
        return window.ForumBrowserSigning.signCanonicalRecord(prepared.canonical_record).then(function (signature) {
          return requestJson("/api/finalize_feature_flag_change", {
            prepare_token: prepared.prepare_token,
            record_id: prepared.record_id,
            record_path: prepared.record_path,
            canonical_record: prepared.canonical_record,
            detached_signature: signature
          });
        });
      }).then(function (result) {
        updateRow(form, result);
        if (status) {
          status.textContent = "Saved.";
        }
        if (fields.key === 'FORUM_PRIVATE_MESSAGES_ENABLED') return refreshNavigation(result, status);
      }).catch(function (error) {
        if (status) {
          status.textContent = error.message;
        }
      }).finally(function () {
        pendingRows.delete(row);
        setPending(form, false);
        if (focused && document.activeElement === document.body) {
          var target = focused.closest('[hidden]') ? row.querySelector('.switch') : focused;
          if (target) target.focus({ preventScroll: true });
        }
      });
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-feature-flag-form]").forEach(bindForm);
  });
})();
