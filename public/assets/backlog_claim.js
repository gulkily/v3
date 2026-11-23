// Backlog claims: the Claim form on the start page and the Release forms on the list. Each is an
// ordinary reply (to the claims thread, or to a claim) whose body is one line ("claim: PF-…" or
// "release: PF-…"), so this script only signs and sends it through the same prepare and finalize
// calls the Outbox and the pledge buttons use. The start page sends its Claim form without a tap
// when it was reached by pressing Start on the list, which leaves a one-time note in this tab; a
// reload, a pasted address or another tab never claims. The prompt is on the page before any of
// this runs and does not wait for it. On failure the button stays and says why; pressing again is
// safe, because a second claim by the same person on the same item changes nothing.
(function () {
  "use strict";

  var INTENT = "backlog-claim-intent";

  function feedback(form, message) {
    // A Release form is drawn apart from its row; its button and message name it by ID.
    var node = form.querySelector('[data-role="backlog-claim-feedback"]')
      || (form.id ? document.querySelector('[data-feedback-for="' + form.id + '"]') : null);
    if (!node) return;
    node.textContent = message;
    node.hidden = message === "";
  }

  function value(form, name) {
    var field = form.querySelector('input[name="' + name + '"]');
    return field ? field.value : "";
  }

  // The signing script loads after this one's first chance to run; wait for it briefly.
  function signing() {
    return new Promise(function (resolve) {
      var tries = 0;
      (function look() {
        var api = window.ForumBrowserSigning || null;
        if (api && typeof api.prepareOutboxPost === "function" && typeof api.finalizeOutboxPost === "function") return resolve(api);
        if (++tries > 50) return resolve(null);
        setTimeout(look, 100);
      })();
    });
  }

  function send(form) {
    return signing().then(function (api) {
      if (!api) return { ok: false, error: "Signing unavailable. Refresh and try again." };
      return api.prepareOutboxPost("reply", {
        threadId: value(form, "thread_id"),
        parentId: value(form, "parent_id"),
        boardTags: value(form, "board_tags"),
        body: value(form, "body"),
      }).then(function (result) {
        if (!result || !result.ok) return { ok: false, error: (result && result.error) || "" };
        return api.finalizeOutboxPost(result.fields, result.prepared);
      });
    }).catch(function (error) {
      return { ok: false, error: error && error.message ? error.message : "" };
    });
  }

  // Swaps in the server's fresh copy of the part that shows claims, so the page says what is now
  // true without a reload taking the prompt away mid-copy.
  function refresh(selector) {
    return fetch(location.href, { credentials: "same-origin", headers: { Accept: "text/html" } })
      .then(function (response) { return response.ok ? response.text() : Promise.reject(new Error("HTTP " + response.status)); })
      .then(function (html) {
        var fresh = new DOMParser().parseFromString(html, "text/html");
        selector.split(",").forEach(function (one) {
          var now = document.querySelector(one.trim());
          var next = fresh.querySelector(one.trim());
          if (now && next) now.replaceWith(document.importNode(next, true));
          else if (now && !next) now.remove();
        });
      });
  }

  function submit(form) {
    var button = form.querySelector('button[type="submit"]')
      || (form.id ? document.querySelector('button[form="' + form.id + '"]') : null);
    if (button && button.disabled) return Promise.resolve();
    if (button) button.disabled = true;
    feedback(form, "");
    return send(form).then(function (result) {
      if (result && result.ok) {
        var parts = form.getAttribute("data-refresh") || '[data-role="backlog-start-claims"]';
        return refresh(parts).catch(function () { window.location.reload(); });
      }
      if (button) button.disabled = false;
      feedback(form, (form.getAttribute("data-failure") || "Not claimed.") + (result && result.error ? " " + result.error : ""));
    });
  }

  document.addEventListener("submit", function (event) {
    var form = event.target;
    if (!form || !form.matches || !form.matches('[data-role="backlog-claim-form"], [data-role="backlog-release-form"]')) return;
    event.preventDefault();
    submit(form);
  });

  function start() {
    var form = document.querySelector('[data-role="backlog-claim-form"]');
    var intent = null;
    try {
      intent = sessionStorage.getItem(INTENT);
      sessionStorage.removeItem(INTENT);
    } catch (error) {}
    if (!form || !intent) return;
    // Only for the items Start was pressed for: every ID this form would claim must be among them.
    var wanted = intent.split(",");
    var ids = (form.getAttribute("data-claim-ids") || "").split(",");
    if (ids.every(function (id) { return wanted.indexOf(id) !== -1; })) submit(form);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
