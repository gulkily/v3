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

  function start() {
    if (hasKey()) {
      recordDevice().catch(function () { /* the next page load tries again */ });
    }
  }

  window.ForumKeyBootstrap = { loadIdentityScripts: loadIdentityScripts };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
