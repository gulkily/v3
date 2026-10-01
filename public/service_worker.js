const CACHE_NAME = "zenmemes-offline-reader-v13";
const SNAPSHOT_URL = "/offline/snapshot.sqlite3";
const OFFLINE_HEALTH_URL = "/offline/";
const OFFLINE_READER_URL = "/offline/reader/";
const BOOTSTRAP_QUERY_PARAMETER = "__offline_bootstrap";

self.addEventListener("install", (event) => event.waitUntil((async () => {
  console.info("[offline reading] worker install started", workerDetails());
  try {
    await refreshOfflineReader([]);
    await self.skipWaiting();
    console.info("[offline reading] worker install completed", workerDetails());
  } catch (error) {
    console.error("[offline reading] worker install failed", Object.assign(workerDetails(), errorDetails(error)));
    throw error;
  }
})()));
self.addEventListener("activate", (event) => event.waitUntil((async () => {
  const names = await caches.keys();
  await Promise.all(names.filter((name) => name.startsWith("zenmemes-offline-reader-") && name !== CACHE_NAME).map((name) => caches.delete(name)));
  await self.clients.claim();
  console.info("[offline reading] worker activated", Object.assign(workerDetails(), { caches: await caches.keys() }));
})()));

function workerDetails() {
  return {
    cacheName: CACHE_NAME,
    workerScript: self.location.href,
    readerUrl: new URL(OFFLINE_READER_URL, self.location.origin).href,
    healthUrl: new URL(OFFLINE_HEALTH_URL, self.location.origin).href,
    snapshotUrl: new URL(SNAPSHOT_URL, self.location.origin).href,
    online: self.navigator.onLine
  };
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

async function refreshResources(urls) {
  const responses = await Promise.all([...new Set(urls)].map(async (url) => {
    const response = await fetchOfflineResource(url, "cache resource");
    return [url, response];
  }));
  const cache = await caches.open(CACHE_NAME);
  for (const [url, response] of responses) {
    await cache.put(cacheKey(url), response.clone());
  }
  console.info("[offline reading] cache refresh stored", Object.assign(workerDetails(), {
    cacheKeys: (await cache.keys()).map((request) => request.url)
  }));
}

async function refreshOfflineReader(extraUrls) {
  const shell = await fetchOfflineResource(OFFLINE_READER_URL, "offline reader shell");
  const health = await fetchOfflineResource(OFFLINE_HEALTH_URL, "offline health page");
  const assetUrls = await Promise.all([shell, health].map(async (response) => {
    const html = await response.clone().text();
    return [...html.matchAll(/(?:src|href|data-runtime-url)="([^"]+)"/g)]
      .map((match) => new URL(match[1], self.location.origin))
      .filter((url) => url.origin === self.location.origin)
      .map((url) => url.pathname);
  }));
  await refreshResources([
    OFFLINE_HEALTH_URL,
    OFFLINE_READER_URL,
    SNAPSHOT_URL,
    "/manifest.webmanifest",
    "/favicon.ico",
    ...assetUrls.flat(),
    ...extraUrls,
  ]);
}

function cacheableRequest(request) {
  if (request.method !== "GET") return false;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || url.search) return false;
  return url.pathname === OFFLINE_HEALTH_URL || url.pathname === OFFLINE_READER_URL || url.pathname === SNAPSHOT_URL
    || url.pathname === "/manifest.webmanifest" || url.pathname === "/favicon.ico"
    || url.pathname.startsWith("/assets/");
}

function supportsOfflineNavigation(url) {
  return url.pathname === "/" || url.pathname === "/threads" || url.pathname === "/threads/"
    || /^\/threads\/[^/]+\/?$/.test(url.pathname)
    || url.pathname === "/tags" || url.pathname === "/tags/"
    || /^\/tags\/[a-z0-9]+(?:-[a-z0-9]+)*\/?$/.test(url.pathname);
}

async function networkFirstNavigation(request) {
  try {
    return await fetch(request);
  } catch (error) {
    const url = new URL(request.url);
    const fallbackUrl = (url.pathname === "/offline" || url.pathname === OFFLINE_HEALTH_URL)
      ? OFFLINE_HEALTH_URL
      : supportsOfflineNavigation(url) ? OFFLINE_READER_URL : null;
    if (!fallbackUrl) throw error;
    const shell = await cachedResponse(await caches.open(CACHE_NAME), fallbackUrl);
    if (shell) return shell;
    throw error;
  }
}

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "refresh-offline-reader") {
    event.waitUntil((async () => {
      try {
        await refreshOfflineReader(Array.isArray(event.data.urls) ? event.data.urls : []);
        console.info("[offline reading] reader refresh completed", workerDetails());
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
    event.respondWith(networkFirstNavigation(event.request));
    return;
  }
  if (!cacheableRequest(event.request)) return;
  event.respondWith((async () => {
    const cached = await cachedResponse(await caches.open(CACHE_NAME), event.request.url);
    return cached || fetch(event.request);
  })());
});
