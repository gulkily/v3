(function () {
  "use strict";
  // Navbar pages from last copy (FORUM_NAV_CACHED_COPY_ENABLED). This page tells the offline
  // helper (service_worker.js) which paths are navbar destinations by writing them into the
  // helper's copy cache; the helper answers those pages from the copy kept last time and
  // fetches a fresh copy in the background. When the fresh copy of this page has another
  // version, the existing new-version notice is raised. This page is never changed.
  var runtime = window.forumBrowserRuntime || null;
  if (!runtime || !runtime.offlineCachePrefix || !window.caches || !navigator.serviceWorker) return;
  var CACHE = runtime.offlineCachePrefix + "nav-copy";
  var PATHS_KEY = "/__nav_copy_paths__";
  var LINKS = ".site-header .nav > a.nav-link[href], .site-header .account-menu__more a[href]";

  function paths() {
    var found = [];
    document.querySelectorAll(LINKS).forEach(function (link) {
      if (link.hasAttribute("data-invite-navigation")) return;
      var url;
      try { url = new URL(link.href, location.href); } catch (error) { return; }
      if (url.origin !== location.origin || url.search || found.indexOf(url.pathname) !== -1) return;
      found.push(url.pathname);
    });
    return found;
  }

  function writePaths() {
    var list = paths();
    if (!list.length) return Promise.resolve();
    return window.caches.open(CACHE).then(function (cache) {
      return cache.put(new URL(PATHS_KEY, location.origin).href, new Response(list.join("\n"), { headers: { "Content-Type": "text/plain" } }));
    }).catch(function () {});
  }

  // A copy made for another key or a signed-out visitor must not be shown.
  window.addEventListener("forum:identity-changed", function () {
    window.caches.delete(CACHE).then(writePaths, writePaths);
  });

  function askForFreshVersion() {
    var worker = navigator.serviceWorker.controller;
    if (!worker || typeof MessageChannel !== "function") return;
    var channel = new MessageChannel();
    channel.port1.onmessage = function (event) {
      var version = event.data && event.data.version;
      if (version && window.ForumAppVersion && window.ForumAppVersion.showPage) window.ForumAppVersion.showPage(version);
    };
    worker.postMessage({ type: "nav-copy-status", path: location.pathname }, [channel.port2]);
  }

  writePaths();
  askForFreshVersion();
  window.addEventListener("pageshow", function (event) { if (event.persisted) askForFreshVersion(); });
})();
