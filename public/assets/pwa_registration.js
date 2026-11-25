(function () {
  "use strict";
  var runtime = window.forumBrowserRuntime || null;
  if (!runtime || !runtime.offlineDiagnosticKey) return;
  var diagnosticKey = runtime.offlineDiagnosticKey;

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
  if (!navigator.serviceWorker) {
    console.warn("[offline reading] registration unavailable", {
      pageUrl: window.location.href,
      secureContext: window.isSecureContext,
      serviceWorkerApi: false
    });
    return;
  }
  // Fast navigation: the layout says whether page requests may start while the worker
  // wakes up ("1" on, "0" off). Pages without the attribute leave the setting alone.
  function applyNavigationPreload() {
    var wanted = document.documentElement.getAttribute("data-fast-navigation");
    if (wanted !== "1" && wanted !== "0") return;
    navigator.serviceWorker.ready.then(function (registration) {
      var preload = registration.navigationPreload;
      if (!preload) return;
      return wanted === "1" ? preload.enable() : preload.disable();
    }).catch(function () {});
  }

  // Navbar pages from last copy: with the flag off, drop any copies kept while it was on.
  function dropNavCopiesWhenOff() {
    if (document.documentElement.getAttribute("data-nav-cached-copy") !== "0" || !window.caches || !runtime.offlineCachePrefix) return;
    window.caches.delete(runtime.offlineCachePrefix + "nav-copy").catch(function () {});
  }

  // Notification taps: when the worker can't move an open window itself, it asks the page.
  navigator.serviceWorker.addEventListener("message", function (event) {
    var data = event.data;
    if (!data || data.type !== "open-url" || typeof data.url !== "string" || data.url.charAt(0) !== "/" || data.url.charAt(1) === "/") return;
    var target;
    try { target = new URL(data.url, window.location.origin); } catch (error) { return; }
    if (target.origin !== window.location.origin || target.href === window.location.href) return;
    window.location.assign(target.href);
  });
  if (typeof navigator.serviceWorker.startMessages === "function") navigator.serviceWorker.startMessages();

  window.addEventListener("load", function () {
    applyNavigationPreload();
    dropNavCopiesWhenOff();
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
        if (!navigator.onLine) return registration;
        return registration.update().then(function () { return registration; });
      })
      // A first visit has no active worker until its quick install finishes.
      .then(function () { return navigator.serviceWorker.ready; })
      .then(function (registration) {
        clearRegistrationError();
        if (navigator.onLine && registration.active && !registration.installing && !registration.waiting) {
          registration.active.postMessage({ type: "refresh-offline-reader" });
        }
      })
      .catch(function (error) {
        saveRegistrationError(error);
      });
  });
})();
