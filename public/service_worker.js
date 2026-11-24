const RUNTIME = self.__forumBrowserRuntime || {
  namespace: "zenmemes",
  offlineCachePrefix: "zenmemes-offline-reader-",
  offlineCacheName: "zenmemes-offline-reader-v16"
};
const CACHE_NAME = RUNTIME.offlineCacheName;
const CACHE_PREFIX = RUNTIME.offlineCachePrefix;
const SNAPSHOT_URL = "/offline/snapshot.sqlite3";
const UPDATE_URL = "/offline/update.sqlite3";
const OFFLINE_HEALTH_URL = "/offline/";
const OFFLINE_READER_URL = "/offline/reader/";
const OFFLINE_OUTBOX_URL = "/tools/outbox/";
// The compose pages are cached as they render with no target, and served offline at their usual addresses.
const COMPOSE_THREAD_URL = "/compose/thread";
const COMPOSE_REPLY_URL = "/compose/reply";
const BOOTSTRAP_QUERY_PARAMETER = "__offline_bootstrap";
const REVISION_KEY = "/__offline_reader_revision__";

self.addEventListener("install", (event) => event.waitUntil((async () => {
  try {
    await refreshOfflineReader([]);
    await self.skipWaiting();
  } catch (error) {
    console.error("[offline reading] worker install failed", Object.assign(workerDetails(), errorDetails(error)));
    throw error;
  }
})()));
self.addEventListener("activate", (event) => event.waitUntil((async () => {
  const names = await caches.keys();
  await Promise.all(names.filter((name) => isOwnedCacheName(name) && name !== CACHE_NAME).map((name) => caches.delete(name)));
  await self.clients.claim();
})()));

function workerDetails() {
  return {
    namespace: RUNTIME.namespace,
    cacheName: CACHE_NAME,
    workerScript: self.location.href,
    readerUrl: new URL(OFFLINE_READER_URL, self.location.origin).href,
    healthUrl: new URL(OFFLINE_HEALTH_URL, self.location.origin).href,
    outboxUrl: new URL(OFFLINE_OUTBOX_URL, self.location.origin).href,
    snapshotUrl: new URL(SNAPSHOT_URL, self.location.origin).href,
    online: self.navigator.onLine
  };
}

function isOwnedCacheName(name) {
  return typeof name === "string" && name.startsWith(CACHE_PREFIX);
}

function errorDetails(error) {
  return {
    errorName: error && error.name ? error.name : "Error",
    errorMessage: error && error.message ? error.message : String(error)
  };
}

function cacheKey(url) {
  return new URL(url, self.location.origin).href;
}

async function cachedResponse(cache, url) {
  return cache.match(cacheKey(url), { ignoreVary: true });
}

async function fetchOfflineResource(url, purpose) {
  const cacheKeyUrl = new URL(url, self.location.origin).href;
  const requestUrl = new URL(cacheKeyUrl);
  requestUrl.searchParams.set(BOOTSTRAP_QUERY_PARAMETER, CACHE_NAME);
  let response;
  try {
    response = await fetch(new Request(requestUrl.href, { credentials: "omit", cache: "no-store" }));
  } catch (error) {
    console.error("[offline reading] fetch failed", Object.assign(workerDetails(), {
      purpose,
      cacheKeyUrl,
      requestUrl: requestUrl.href
    }, errorDetails(error)));
    throw error;
  }
  if (!response.ok) {
    const error = new Error("Unable to cache " + cacheKeyUrl + " (HTTP " + response.status + ")");
    console.error("[offline reading] fetch returned an error response", Object.assign(workerDetails(), {
      purpose,
      cacheKeyUrl,
      requestUrl: requestUrl.href,
      status: response.status,
      statusText: response.statusText
    }, errorDetails(error)));
    throw error;
  }
  return response;
}

async function refreshResources(urls, alreadyFetched = []) {
  const responses = await Promise.all([...new Set(urls)].map(async (url) => {
    const response = await fetchOfflineResource(url, "cache resource");
    return [url, response];
  }));
  const cache = await caches.open(CACHE_NAME);
  for (const [url, response] of [...alreadyFetched, ...responses]) {
    await cache.put(cacheKey(url), response.clone());
  }
}

async function refreshOfflineReader(extraUrls) {
  const shell = await fetchOfflineResource(OFFLINE_READER_URL, "offline reader shell");
  const health = await fetchOfflineResource(OFFLINE_HEALTH_URL, "offline health page");
  const outbox = await fetchOfflineResource(OFFLINE_OUTBOX_URL, "offline outbox page");
  const composeThread = await fetchOfflineResource(COMPOSE_THREAD_URL, "offline new post page");
  const composeReply = await fetchOfflineResource(COMPOSE_REPLY_URL, "offline reply page");
  const pages = [shell, health, outbox, composeThread, composeReply];
  const shellBodies = await Promise.all(pages.map((response) => response.clone().text()));
  const assetUrls = await Promise.all(pages.map(async (response) => {
    const html = await response.clone().text();
    return [...html.matchAll(/(?:<script[^>]*\ssrc|<link[^>]*\shref|data-runtime-url)="([^"]+)"/g)]
      .map((match) => new URL(match[1], self.location.origin))
      .filter((url) => url.origin === self.location.origin)
      .filter((url) => url.pathname.startsWith("/assets/"))
      .map((url) => url.pathname);
  }));
  await refreshResources([
    "/manifest.webmanifest",
    "/favicon.ico",
    ...assetUrls.flat(),
    ...extraUrls,
  ], [
    [OFFLINE_READER_URL, shell],
    [OFFLINE_HEALTH_URL, health],
    [OFFLINE_OUTBOX_URL, outbox],
    [COMPOSE_THREAD_URL, composeThread],
    [COMPOSE_REPLY_URL, composeReply],
  ]);
  await refreshSnapshot();
  await storeRevision(await revisionFromBodies(shellBodies));
}

async function refreshSnapshot() {
  try {
    const update = await fetchOfflineResource(UPDATE_URL, "offline update");
    await (await caches.open(CACHE_NAME)).put(cacheKey(UPDATE_URL), update.clone());
  } catch (error) {
    // A compact update is optional; retain the last saved database.
  }
  try {
    const response = await fetchOfflineResource(SNAPSHOT_URL, "offline snapshot");
    await (await caches.open(CACHE_NAME)).put(cacheKey(SNAPSHOT_URL), response.clone());
  } catch (error) {
    // A snapshot is optional: retain the cached reader shell and assets when it is unavailable.
  }
}

// The shell pages embed every asset URL and the published snapshot revision,
// so hashing them captures a deploy or a snapshot publish.
async function revisionFromBodies(bodies) {
  const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(bodies.join("\u0000")));
  return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function currentRevision() {
  const bodies = await Promise.all([OFFLINE_READER_URL, OFFLINE_HEALTH_URL, OFFLINE_OUTBOX_URL, COMPOSE_THREAD_URL, COMPOSE_REPLY_URL].map(async (url) => {
    const response = await fetchOfflineResource(url, "revision shell");
    return response.text();
  }));
  return revisionFromBodies(bodies);
}

async function storeRevision(revision) {
  await (await caches.open(CACHE_NAME)).put(cacheKey(REVISION_KEY), new Response(revision));
}

async function storedRevision() {
  const response = await cachedResponse(await caches.open(CACHE_NAME), REVISION_KEY);
  return response ? response.text() : null;
}

function cacheableRequest(request) {
  if (request.method !== "GET") return false;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || url.search) return false;
  return url.pathname === OFFLINE_HEALTH_URL || url.pathname === OFFLINE_READER_URL || url.pathname === SNAPSHOT_URL || url.pathname === UPDATE_URL
    || url.pathname === OFFLINE_OUTBOX_URL
    || url.pathname === "/manifest.webmanifest" || url.pathname === "/favicon.ico"
    || url.pathname.startsWith("/assets/");
}

function supportsOfflineNavigation(url) {
  return url.pathname === "/" || url.pathname === "/threads" || url.pathname === "/threads/"
    || /^\/threads\/[^/]+\/?$/.test(url.pathname)
    || url.pathname === "/tags" || url.pathname === "/tags/"
    || /^\/tags\/[a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*\/?$/.test(url.pathname);
}

function composePageFor(url) {
  const path = url.pathname.replace(/\/$/, "");
  return path === COMPOSE_THREAD_URL || path === COMPOSE_REPLY_URL ? path : null;
}

function escapeAttribute(value) {
  return String(value).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

// The cached reply page has an empty target; fill it from the address, as the server would.
async function offlineComposePage(url) {
  const page = composePageFor(url);
  const cached = await cachedResponse(await caches.open(CACHE_NAME), page);
  if (!cached) return cached;
  // Marked so page scripts know this copy came from the device, not the server.
  let html = (await cached.text()).replace("data-compose-root", 'data-compose-root data-offline-copy="1"');
  if (page !== COMPOSE_REPLY_URL) return new Response(html, { status: cached.status, statusText: cached.statusText, headers: cached.headers });
  for (const name of ["thread_id", "parent_id"]) {
    const value = url.searchParams.get(name) || "";
    html = html.replace(`name="${name}" value=""`, `name="${name}" value="${escapeAttribute(value)}"`);
  }
  return new Response(html, { status: cached.status, statusText: cached.statusText, headers: cached.headers });
}

// Fast navigation: a page asks for a navbar destination as a finger or pointer goes down
// on it, and the navigation that follows within EARLY_FETCH_MS is answered with that
// response. Pages send nothing while the "Fast navigation" flag is off.
const EARLY_FETCH_MS = 10000;
const earlyFetches = new Map();

function startEarlyFetch(href) {
  let url;
  try { url = new URL(href, self.location.origin); } catch (error) { return; }
  if (url.origin !== self.location.origin) return;
  url.hash = "";
  const known = earlyFetches.get(url.href);
  if (known && Date.now() - known.at < EARLY_FETCH_MS) return;
  for (const [key, entry] of earlyFetches) if (Date.now() - entry.at >= EARLY_FETCH_MS) earlyFetches.delete(key);
  const response = fetch(url.href, { credentials: "same-origin", headers: { Accept: "text/html" } }).catch(() => null);
  earlyFetches.set(url.href, { at: Date.now(), response });
  return response;
}

// A redirected or failed response cannot answer the navigation; the caller falls back.
async function takeEarlyFetch(request) {
  if (request.method !== "GET") return null;
  const key = new URL(request.url);
  key.hash = "";
  const entry = earlyFetches.get(key.href);
  if (!entry) return null;
  earlyFetches.delete(key.href);
  if (Date.now() - entry.at >= EARLY_FETCH_MS) return null;
  const response = await entry.response;
  return response && response.ok && !response.redirected && response.type === "basic" ? response : null;
}

// Navbar pages from last copy: pages with the flag on write the navbar paths into this
// cache; pages with it off delete the cache. A listed page opened before is answered from
// its copy, and a fresh copy is fetched in the background for the next visit. The page on
// screen is never changed; it learns the fresh copy's version through "nav-copy-status".
const NAV_COPY_CACHE = CACHE_PREFIX + "nav-copy";
const NAV_COPY_PATHS_KEY = "/__nav_copy_paths__";
const navCopyRefreshes = new Map();

async function navCopyTarget(request) {
  if (request.method !== "GET" || self.navigator.onLine === false) return null;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || url.search) return null;
  if (!(await caches.has(NAV_COPY_CACHE))) return null;
  const cache = await caches.open(NAV_COPY_CACHE);
  const listed = await cache.match(cacheKey(NAV_COPY_PATHS_KEY));
  if (!listed) return null;
  const paths = (await listed.text()).split("\n");
  if (!paths.includes(url.pathname)) return null;
  url.hash = "";
  return { cache, key: url.href, path: url.pathname };
}

function keepable(response) {
  return Boolean(response) && response.ok && !response.redirected && response.type === "basic"
    && !/no-store/i.test(response.headers.get("Cache-Control") || "");
}

async function pageVersion(response) {
  const html = await response.clone().text();
  const match = html.match(/<meta name="page-version" content="([^"]*)">/)
    || html.match(/<meta name="app-version" content="([^"]*)">/);
  return match ? match[1] : null;
}

async function networkFirstNavigation(event) {
  const request = event.request;
  // Settle the preload even when another response answers, so the browser does not cancel it noisily.
  const preload = event.preloadResponse ? Promise.resolve(event.preloadResponse).catch(() => undefined) : Promise.resolve(undefined);
  event.waitUntil(preload);
  try {
    const earlyFetch = takeEarlyFetch(request);
    const target = await navCopyTarget(request).catch(() => null);
    const kept = target ? await target.cache.match(target.key) : null;
    if (kept) {
      const refresh = (async () => {
        let fresh = (await earlyFetch) || (await preload);
        if (!fresh) fresh = await fetch(target.key, { credentials: "same-origin", headers: { Accept: "text/html" } });
        if (!keepable(fresh)) return null;
        await target.cache.put(target.key, fresh.clone());
        return pageVersion(fresh);
      })().catch(() => null);
      navCopyRefreshes.set(target.path, refresh);
      event.waitUntil(refresh.then(() => new Promise((done) => setTimeout(done, 30000))).then(() => {
        if (navCopyRefreshes.get(target.path) === refresh) navCopyRefreshes.delete(target.path);
      }));
      return kept;
    }
    // This page comes from the network, so an older background refresh says nothing about it.
    if (target) navCopyRefreshes.delete(target.path);
    const response = (await earlyFetch) || (await preload) || (await fetch(request));
    if (target && keepable(response)) event.waitUntil(target.cache.put(target.key, response.clone()).catch(() => {}));
    return response;
  } catch (error) {
    const url = new URL(request.url);
    if (request.method === "GET" && composePageFor(url)) {
      const page = await offlineComposePage(url);
      if (page) return page;
      throw error;
    }
    const fallbackUrl = (url.pathname === "/offline" || url.pathname === OFFLINE_HEALTH_URL)
      ? OFFLINE_HEALTH_URL
      : url.pathname === OFFLINE_OUTBOX_URL || url.pathname === "/tools/outbox"
        ? OFFLINE_OUTBOX_URL
      : supportsOfflineNavigation(url) ? OFFLINE_READER_URL : null;
    if (!fallbackUrl) throw error;
    const shell = await cachedResponse(await caches.open(CACHE_NAME), fallbackUrl);
    if (shell) return shell;
    throw error;
  }
}

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "nav-copy-status" && typeof event.data.path === "string" && event.ports[0]) {
    const port = event.ports[0];
    const refresh = navCopyRefreshes.get(event.data.path);
    if (!refresh) { port.postMessage({ type: "nav-copy-status", version: null }); return; }
    event.waitUntil(refresh.then((version) => port.postMessage({ type: "nav-copy-status", version: version || null })));
    return;
  }
  if (event.data && event.data.type === "early-fetch" && typeof event.data.url === "string") {
    const started = startEarlyFetch(event.data.url);
    // Keep the worker alive until the response arrives; the navigation picks it up.
    if (started) event.waitUntil(started);
    return;
  }
  if (event.data && event.data.type === "refresh-offline-reader") {
    event.waitUntil((async () => {
      try {
        if (!event.data.force && await currentRevision() === await storedRevision()) {
          if (event.ports[0]) event.ports[0].postMessage({ type: "offline-reader-refreshed", status: "unchanged", cacheName: CACHE_NAME });
          return;
        }
        await refreshOfflineReader(Array.isArray(event.data.urls) ? event.data.urls : []);
        if (event.ports[0]) event.ports[0].postMessage({ type: "offline-reader-refreshed", status: "ready", cacheName: CACHE_NAME });
      } catch (error) {
        console.error("[offline reading] reader refresh failed", Object.assign(workerDetails(), errorDetails(error)));
        if (event.ports[0]) event.ports[0].postMessage(Object.assign({ type: "offline-reader-refreshed", status: "error" }, errorDetails(error)));
        throw error;
      }
    })());
  }
});
self.addEventListener("fetch", (event) => {
  if (event.request.mode === "navigate") {
    event.respondWith(networkFirstNavigation(event));
    return;
  }
  if (!cacheableRequest(event.request)) return;
  event.respondWith((async () => {
    const cached = await cachedResponse(await caches.open(CACHE_NAME), event.request.url);
    return cached || fetch(event.request);
  })());
});

// Notifications (push_subscribe): show every push, because iPhone stops delivering to a site
// that receives a push without showing one. The payload is the server's JSON (title, body,
// url, tag); anything unreadable still shows a plain notice.
function pushNotice(event) {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (error) { data = {}; }
  if (!data || typeof data !== "object") data = {};
  let url = "/";
  try {
    const target = new URL(typeof data.url === "string" ? data.url : "/", self.location.origin);
    if (target.origin === self.location.origin) url = target.pathname + target.search + target.hash;
  } catch (error) { url = "/"; }
  const options = {
    body: typeof data.body === "string" ? data.body : "Something new",
    icon: "/assets/app-icon-192.png",
    data: { url }
  };
  if (typeof data.tag === "string" && data.tag) options.tag = data.tag;
  return { title: typeof data.title === "string" && data.title ? data.title : "ZenMemes", options };
}
self.addEventListener("push", (event) => {
  const notice = pushNotice(event);
  event.waitUntil(self.registration.showNotification(notice.title, notice.options));
});
// A tap focuses an open app window if there is one, else opens the notice's page.
self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = new URL((event.notification.data && event.notification.data.url) || "/", self.location.origin).href;
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    const open = windows.find((client) => new URL(client.url).origin === self.location.origin && "focus" in client);
    if (open) return open.focus();
    return self.clients.openWindow(url);
  })());
});
