// Session key bootstrap (docs/plans/session_key_bootstrap/).
//
// A browser can lose its stored key while keeping its session cookie: iPhone Safari
// deletes storage that scripts write after seven days of use without a visit, but
// keeps cookies the server set. The session can then vouch for a new key.
//
// This script does nothing on most page loads; it reads local storage and one cookie,
// then stops. It loads the signing scripts only when it has work to do:
// - a browser that holds a key re-signs once, so its session records which browser it is;
// - a browser with no key whose identity hint names a member asks the session for a new key.
(function () {
  "use strict";

  var script = document.currentScript;
  var identityScripts = String((script && script.dataset.identityScripts) || "").split(" ").filter(Boolean);

  function stored(name) {
    try {
      return window.localStorage.getItem(name) || "";
    } catch (error) {
      return "";
    }
  }

  function storedFingerprint() {
    return stored("forum_pki_fingerprint").trim().toLowerCase().replace(/^openpgp:/, "");
  }

  function hasKey() {
    return stored("forum_pki_private_key") !== "" && /^[a-f0-9]{40}$/.test(storedFingerprint());
  }

  // "/assets/browser_signing.71c3d69646e6.js" -> "browser_signing.js", so a page's own copy counts.
  function scriptName(src) {
    return String(src || "").split("?")[0].split("/").pop().replace(/\.[0-9a-f]{8,}\.js$/, ".js");
  }

  // Loads the signing scripts this page lacks, in order.
  function loadIdentityScripts() {
    return identityScripts.reduce(function (ready, src) {
      return ready.then(function () {
        var name = scriptName(src);
        var present = Array.prototype.some.call(document.scripts, function (node) {
          return scriptName(node.src) === name;
        });
        if (present) return undefined;
        return new Promise(function (resolve, reject) {
          var node = document.createElement("script");
          node.src = src;
          node.onload = resolve;
          node.onerror = function () { reject(new Error("Could not load " + name)); };
          document.head.appendChild(node);
        });
      });
    }, Promise.resolve());
  }

  // Sessions made before this feature describe no browser. A browser with a key signs
  // in again once; signing in as the same identity keeps the session and records it.
  function recordDevice() {
    var fingerprint = storedFingerprint();
    if (stored("forum_session_device_recorded") === fingerprint) return Promise.resolve();
    if (stored("forum_pki_published_fingerprint").trim().toLowerCase() !== fingerprint) return Promise.resolve();
    return loadIdentityScripts().then(function () {
      if (!window.PrivateSiteAuth) return undefined;
      return window.PrivateSiteAuth.authenticateInPlace({ force: true });
    });
  }

  // The identity hint is a cookie the server set, so it outlives Safari's storage wipe.
  function hintedIdentityId() {
    var match = /(?:^|;\s*)identity_hint=([^;]*)/.exec(document.cookie || "");
    var value = match ? decodeURIComponent(match[1]).trim().toLowerCase() : "";
    return /^openpgp:[a-f0-9]{40}$/.test(value) ? value : "";
  }

  function remember(name, value) {
    try { window.localStorage.setItem(name, value); } catch (error) { /* storage unavailable */ }
  }

  async function readJson(response) {
    try { return await response.json(); } catch (error) { return {}; }
  }

  async function post(path, fields) {
    var body = new URLSearchParams();
    Object.keys(fields).forEach(function (key) { body.set(key, fields[key]); });
    return readJson(await fetch(path, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded" }, body: body.toString()
    }));
  }

  // One notice, in the app banner's style, after the key really changed.
  function showNotice(approved) {
    if (!document.body || document.querySelector('[data-role="key-bootstrap-notice"]')) return;
    var banner = document.createElement("div");
    banner.className = "app-version-banner key-bootstrap-notice";
    banner.setAttribute("data-role", "key-bootstrap-notice");
    banner.setAttribute("role", "status");
    var inner = document.createElement("div");
    inner.className = "app-version-banner__inner";
    var text = document.createElement("span");
    text.className = "app-version-banner__text";
    text.textContent = approved
      ? "This browser lost its key and has a new one."
      : "This browser lost its key. Its new key waits for approval.";
    var save = document.createElement("button");
    save.type = "button";
    save.className = "app-version-banner__reload";
    save.textContent = approved ? "Save a backup" : "Account";
    save.addEventListener("click", function () { window.location.assign("/account/key/"); });
    var close = document.createElement("button");
    close.type = "button";
    close.className = "app-version-banner__reload";
    close.textContent = "OK";
    close.addEventListener("click", function () { banner.remove(); });
    inner.appendChild(text);
    inner.appendChild(save);
    inner.appendChild(close);
    banner.appendChild(inner);
    document.body.insertBefore(banner, document.body.firstChild);
  }

  // A browser with no key whose hint names a member asks its session for a new key under that name.
  async function bootstrapKey(hint) {
    // Once per hinted identity, so a refusal does not repeat on every page.
    if (stored("forum_key_bootstrap_tried") === hint) return;
    remember("forum_key_bootstrap_tried", hint);
    var status = await readJson(await fetch("/api/key_bootstrap_status", { credentials: "same-origin" }));
    if (status.status !== "eligible" || status.from_identity_id !== hint) return;

    await loadIdentityScripts();
    var identity = window.__forumBrowserIdentity;
    if (!identity || typeof identity.ensureReadyIdentity !== "function" || hasKey()) return;
    await identity.ensureReadyIdentity(null, null, { temporaryName: status.username });
    var identityId = "openpgp:" + storedFingerprint();

    var prepared = await post("/api/prepare_key_bootstrap", { identity_id: identityId });
    if (prepared.status === "pending") { showNotice(false); return; }
    if (prepared.status !== "ok") return;
    var signature = await identity.signCanonicalRecord(prepared.canonical_record, identityId);
    var result = await post("/api/finalize_key_bootstrap", {
      prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
      author_identity_id: identityId, canonical_record: prepared.canonical_record, detached_signature: signature
    });
    if (result.status !== "ok") return;
    // The session now signs in as the new key, which records this browser too.
    if (window.PrivateSiteAuth) await window.PrivateSiteAuth.authenticateInPlace({ force: true });
    showNotice(true);
  }

  function start() {
    if (hasKey()) {
      recordDevice().catch(function () { /* the next page load tries again */ });
      return;
    }
    var hint = hintedIdentityId();
    if (hint !== "") {
      bootstrapKey(hint).catch(function () { /* the member can still restore a backup or ask for approval */ });
    }
  }

  window.ForumKeyBootstrap = { loadIdentityScripts: loadIdentityScripts };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
