(function () {
  "use strict";
  function cacheUrls() {
    var urls = ["/offline/", "/offline/snapshot.sqlite3", "/manifest.webmanifest", "/favicon.ico", "/assets/sql-wasm.wasm"];
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
    navigator.serviceWorker.register("/offline/service_worker.js", { scope: "/offline/" })
      .then(function () { return navigator.serviceWorker.ready; })
      .then(function (registration) {
        if (registration.active) registration.active.postMessage({ type: "refresh-offline-reader", urls: cacheUrls() });
      })
      .catch(function () {});
  });
})();
