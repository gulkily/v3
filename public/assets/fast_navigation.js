(function () {
  "use strict";
  // Fast navigation (FORUM_FAST_NAVIGATION_ENABLED): when a finger or pointer goes down on a
  // navbar link, ask the offline helper (service_worker.js) to fetch that page, so the
  // navigation that follows is answered sooner. When the click lands, the tapped
  // destination takes the existing is-active highlight before the next page arrives.
  // Without the worker, offline, or with modifier keys, links behave as plain links.
  var LINKS = ".site-header a.nav-link[href], .site-header a.new-post-button[href]";
  var ACTIVE = ".site-header .nav-link.is-active, .site-header .new-post-button.is-active";

  function destination(event) {
    var target = event.target instanceof Element ? event.target.closest(LINKS) : null;
    if (!target || target.hasAttribute("data-invite-navigation") || target.hasAttribute("download")) return null;
    if (target.target && target.target !== "_self") return null;
    var url;
    try { url = new URL(target.href, location.href); } catch (error) { return null; }
    if (url.origin !== location.origin) return null;
    return { link: target, url: url };
  }

  function plain(event) {
    return !(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) && (event.button === undefined || event.button === 0);
  }

  document.addEventListener("pointerdown", function (event) {
    if (!plain(event) || !navigator.onLine) return;
    var found = destination(event);
    var worker = navigator.serviceWorker && navigator.serviceWorker.controller;
    if (!found || !worker) return;
    var href = found.url.href.split("#")[0];
    // The worker ignores a repeat of an address it is already fetching.
    if (href === location.href.split("#")[0]) return;
    worker.postMessage({ type: "early-fetch", url: href });
  }, { capture: true, passive: true });

  // The page's own active item, restored if this page comes back from the back/forward cache.
  var original = Array.prototype.slice.call(document.querySelectorAll(ACTIVE));

  function mark(links) {
    document.querySelectorAll(ACTIVE).forEach(function (element) { element.classList.remove("is-active"); });
    links.forEach(function (element) { element.classList.add("is-active"); });
  }

  document.addEventListener("click", function (event) {
    if (event.defaultPrevented || !plain(event)) return;
    var found = destination(event);
    if (!found) return;
    var path = found.url.pathname + found.url.search;
    if (path === location.pathname + location.search) return;
    // The same destination can sit in the bar and in the More menu; mark every copy.
    var copies = Array.prototype.filter.call(document.querySelectorAll(LINKS), function (element) {
      try {
        var other = new URL(element.href, location.href);
        return other.origin === location.origin && other.pathname + other.search === path;
      } catch (error) { return false; }
    });
    mark(copies);
  });

  window.addEventListener("pageshow", function (event) {
    if (!event.persisted) return;
    mark(original);
  });
})();
