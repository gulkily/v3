(function () {
  "use strict";
  var diagnosticKey = "forum-offline-registration-error";

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
  if (!navigator.serviceWorker) return;
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
      .then(function () { return navigator.serviceWorker.ready; })
      .then(function (registration) {
        clearRegistrationError();
        if (navigator.onLine && registration.active) registration.active.postMessage({ type: "refresh-offline-reader", urls: cacheUrls() });
      })
      .catch(saveRegistrationError);
  });
})();
