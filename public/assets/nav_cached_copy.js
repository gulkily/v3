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
  // Installed app opens instantly (FORUM_OWA_INSTANT_LAUNCH_ENABLED): the installed app
  // writes this marker, which makes the helper treat every reader route like a navbar page.
  var INSTANT_KEY = "/__instant_launch__";
  var root = document.documentElement;
  var instantFlag = root.getAttribute("data-owa-instant-launch") === "1";
  var standalone = navigator.standalone === true
    || (typeof window.matchMedia === "function" && window.matchMedia("(display-mode: standalone)").matches);
  // In the installed app the layout turns this on before paint when the instant flag is on.
  var navCopies = root.getAttribute("data-nav-cached-copy") === "1";
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
    var list = navCopies ? paths() : [];
    var marker = instantFlag && standalone;
    var url = function (key) { return new URL(key, location.origin).href; };
    // Only clean-up left: never create the cache for it.
    var opened = list.length || marker ? window.caches.open(CACHE)
      : window.caches.has(CACHE).then(function (has) { return has ? window.caches.open(CACHE) : null; });
    return opened.then(function (cache) {
      if (!cache) return null;
      var writes = [];
      if (list.length) writes.push(cache.put(url(PATHS_KEY), new Response(list.join("\n"), { headers: { "Content-Type": "text/plain" } })));
      if (!navCopies) writes.push(cache.delete(url(PATHS_KEY)));
      if (marker) writes.push(cache.put(url(INSTANT_KEY), new Response("1", { headers: { "Content-Type": "text/plain" } })));
      // A browser tab sharing storage with the app leaves the app's marker alone.
      if (!instantFlag) writes.push(cache.delete(url(INSTANT_KEY)));
      return Promise.all(writes);
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
  if (!navCopies) return;
  askForFreshVersion();
  window.addEventListener("pageshow", function (event) { if (event.persisted) askForFreshVersion(); });
})();
