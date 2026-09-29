(function () {
  "use strict";
  var diagnosticKey = "forum-offline-registration-error";
  var registrationScriptUrl = document.currentScript && document.currentScript.src
    ? document.currentScript.src
    : "(unknown)";

  function cacheNames() {
    if (!window.caches || typeof window.caches.keys !== "function") return Promise.resolve(["(Cache Storage unavailable)"]);
    return window.caches.keys().catch(function (error) {
      return ["(unable to list caches: " + String(error && error.message || error) + ")"];
    });
  }

  function workerDetails(worker) {
    if (!worker) return null;
    return { scriptURL: worker.scriptURL, state: worker.state };
  }

  function logOfflineState(event, registration, error) {
    return cacheNames().then(function (names) {
      var versionMeta = document.querySelector('meta[name="app-version"]');
      console.info("[offline reading] " + event, {
        pageUrl: window.location.href,
        appVersion: versionMeta ? versionMeta.getAttribute("content") : "(not rendered)",
        pwaRegistrationScript: registrationScriptUrl,
        online: navigator.onLine,
        secureContext: window.isSecureContext,
        registrationScope: registration ? registration.scope : null,
        activeWorker: workerDetails(registration && registration.active),
        installingWorker: workerDetails(registration && registration.installing),
        waitingWorker: workerDetails(registration && registration.waiting),
        pageController: workerDetails(navigator.serviceWorker && navigator.serviceWorker.controller),
        offlineCaches: names.filter(function (name) { return name.indexOf("zenmemes-offline-reader-") === 0; }),
        error: error ? { name: error.name || "Error", message: error.message || String(error) } : null
      });
    });
  }

  function saveRegistrationError(error) {
    try {
      window.localStorage.setItem(diagnosticKey, JSON.stringify({
        at: new Date().toISOString(),
        name: error && error.name ? String(error.name) : "Error",
        message: error && error.message ? String(error.message) : String(error || "Unknown registration error")
      }));
    } catch (ignored) {}
  }

  function clearRegistrationError() {
    try { window.localStorage.removeItem(diagnosticKey); } catch (ignored) {}
  }
  function cacheUrls() {
    var urls = ["/offline/reader/", "/offline/snapshot.sqlite3", "/manifest.webmanifest", "/favicon.ico"];
    document.querySelectorAll('link[href], script[src]').forEach(function (node) {
      var value = node.getAttribute(node.tagName === "LINK" ? "href" : "src");
      if (!value) return;
      var url = new URL(value, window.location.href);
      if (url.origin === window.location.origin) urls.push(url.pathname);
    });
    return Array.from(new Set(urls));
  }
  if (!navigator.serviceWorker) {
    console.warn("[offline reading] registration unavailable", {
      pageUrl: window.location.href,
      secureContext: window.isSecureContext,
      serviceWorkerApi: false
    });
    return;
  }
  window.addEventListener("load", function () {
    logOfflineState("page loaded", null);
    navigator.serviceWorker.getRegistrations().then(function (registrations) {
      return Promise.all(registrations.map(function (registration) {
        return registration.scope === window.location.origin + "/offline/"
          ? registration.unregister()
          : false;
      }));
    }).then(function () {
      return navigator.serviceWorker.register("/service_worker.js", { scope: "/" });
    })
      .then(function (registration) {
        return logOfflineState("registered", registration).then(function () { return registration; });
      })
      .then(function (registration) {
        if (!navigator.onLine) return registration;
        return registration.update().then(function () {
          return logOfflineState("update check completed", registration).then(function () { return registration; });
        });
      })
      .then(function (registration) {
        clearRegistrationError();
        if (navigator.onLine && registration.active && !registration.installing && !registration.waiting) {
          registration.active.postMessage({ type: "refresh-offline-reader", urls: cacheUrls() });
          logOfflineState("requested reader refresh", registration);
        } else {
          logOfflineState("reader refresh deferred", registration);
        }
      })
      .catch(function (error) {
        saveRegistrationError(error);
        logOfflineState("registration failed", null, error);
      });
  });
})();
