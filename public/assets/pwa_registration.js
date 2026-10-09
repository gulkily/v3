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
  window.addEventListener("load", function () {
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
