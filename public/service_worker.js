const CACHE_NAME = "zenmemes-offline-reader-v1";
const SNAPSHOT_URL = "/offline/snapshot.sqlite3";
const OFFLINE_READER_URL = "/offline/";

self.addEventListener("install", (event) => event.waitUntil((async () => {
  await refreshOfflineReader([]);
  await self.skipWaiting();
})()));
self.addEventListener("activate", (event) => event.waitUntil((async () => {
  const names = await caches.keys();
  await Promise.all(names.filter((name) => name.startsWith("zenmemes-offline-reader-") && name !== CACHE_NAME).map((name) => caches.delete(name)));
  await self.clients.claim();
})()));

async function refreshResources(urls) {
  const responses = await Promise.all([...new Set(urls)].map(async (url) => {
    const response = await fetch(new Request(url, { credentials: "omit", cache: "no-store" }));
    if (!response.ok) throw new Error("Unable to cache " + url);
    return [url, response];
  }));
  const cache = await caches.open(CACHE_NAME);
  for (const [url, response] of responses) if (url !== SNAPSHOT_URL) await cache.put(url, response.clone());
  const snapshot = responses.find(([url]) => url === SNAPSHOT_URL);
  if (snapshot) await cache.put(SNAPSHOT_URL, snapshot[1].clone());
}

async function refreshOfflineReader(extraUrls) {
  const shell = await fetch(new Request(OFFLINE_READER_URL, { credentials: "omit", cache: "no-store" }));
  if (!shell.ok) throw new Error("Unable to cache offline reader shell");
  const html = await shell.clone().text();
  const assetUrls = [...html.matchAll(/(?:src|href)="([^"]+)"/g)]
    .map((match) => new URL(match[1], self.location.origin))
    .filter((url) => url.origin === self.location.origin)
    .map((url) => url.pathname);
  await refreshResources([
    OFFLINE_READER_URL,
    SNAPSHOT_URL,
    "/manifest.webmanifest",
    "/favicon.ico",
    "/assets/sql-wasm.wasm",
    ...assetUrls,
    ...extraUrls,
  ]);
}

function cacheableRequest(request) {
  if (request.method !== "GET") return false;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin || url.search) return false;
  return url.pathname === OFFLINE_READER_URL || url.pathname === SNAPSHOT_URL
    || url.pathname === "/manifest.webmanifest" || url.pathname === "/favicon.ico"
    || url.pathname.startsWith("/assets/");
}

self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "refresh-offline-reader") {
    event.waitUntil(refreshOfflineReader(Array.isArray(event.data.urls) ? event.data.urls : []));
  }
});
self.addEventListener("fetch", (event) => {
  if (!cacheableRequest(event.request)) return;
  event.respondWith((async () => (await (await caches.open(CACHE_NAME)).match(event.request)) || fetch(event.request))());
});
