(function () {
  "use strict";

  var root = document.querySelector("[data-offline-health]");
  if (!root) return;

  var summary = root.querySelector('[data-role="offline-health-summary"]');
  var checks = root.querySelector('[data-role="offline-health-checks"]');
  var guidance = root.querySelector('[data-role="offline-health-guidance"]');
  var recheck = root.querySelector('[data-action="recheck-offline-health"]');
  var readerUrl = root.getAttribute("data-reader-url") || "/offline/reader/";
  var snapshotUrl = root.getAttribute("data-snapshot-url") || "/offline/snapshot.sqlite3";
  var runtimeUrl = root.getAttribute("data-runtime-url") || "/assets/sql-wasm.wasm";
  var checking = false;
  var pendingCheck = false;

  function absoluteUrl(url) {
    return new URL(url, window.location.origin).href;
  }

  function setCheck(label, state, detail) {
    var item = document.createElement("li");
    item.setAttribute("data-state", state);
    item.textContent = label + ": " + detail;
    checks.appendChild(item);
  }

  async function cachedResponse(url) {
    return window.caches.match(absoluteUrl(url));
  }

  async function readerAssetUrls(response) {
    var html = await response.text();
    var documentFragment = new DOMParser().parseFromString(html, "text/html");
    var urls = Array.from(documentFragment.querySelectorAll("link[href], script[src]"))
      .map(function (node) { return node.getAttribute(node.tagName === "LINK" ? "href" : "src"); })
      .filter(Boolean)
      .map(function (url) { return new URL(url, window.location.origin); })
      .filter(function (url) { return url.origin === window.location.origin; })
      .map(function (url) { return url.href; });
    urls.push(absoluteUrl(runtimeUrl));
    return Array.from(new Set(urls));
  }

  async function localArtifacts() {
    if (!("caches" in window)) return { available: false, ready: false, assetsReady: false };
    try {
      var reader = await cachedResponse(readerUrl);
      var snapshot = await cachedResponse(snapshotUrl);
      if (!reader) return { available: true, ready: false, reader: false, snapshot: Boolean(snapshot), assetsReady: false };

      var assets = await readerAssetUrls(reader.clone());
      var responses = await Promise.all(assets.map(cachedResponse));
      var assetsReady = responses.every(Boolean);
      return {
        available: true,
        ready: Boolean(snapshot) && assetsReady,
        reader: true,
        snapshot: Boolean(snapshot),
        assetsReady: assetsReady,
      };
    } catch (error) {
      return { available: false, ready: false, assetsReady: false };
    }
  }

  async function serviceWorkerStatus() {
    if (!("serviceWorker" in navigator)) return { supported: false, ready: false };
    try {
      var registration = await navigator.serviceWorker.getRegistration();
      var rootScope = window.location.origin + "/";
      return {
        supported: true,
        ready: Boolean(registration && registration.active && registration.scope === rootScope),
      };
    } catch (error) {
      return { supported: false, ready: false };
    }
  }

  async function publishedSnapshotStatus(online) {
    if (!online) return { checked: false, ready: false };
    var controller = "AbortController" in window ? new AbortController() : null;
    var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 3000) : null;
    try {
      var response = await window.fetch(snapshotUrl, {
        method: "HEAD",
        credentials: "omit",
        cache: "no-store",
        signal: controller ? controller.signal : undefined,
      });
      return { checked: true, ready: response.ok };
    } catch (error) {
      return { checked: true, ready: false };
    } finally {
      if (timeout !== null) window.clearTimeout(timeout);
    }
  }

  function setGuidance(online, serviceWorker, artifacts, published) {
    guidance.hidden = false;
    if (!serviceWorker.supported || !artifacts.available) {
      guidance.textContent = "Use a browser that supports service workers and offline storage.";
      return;
    }
    if (!online) {
      guidance.textContent = artifacts.ready
        ? "This device has saved public content. Open the Board or a saved thread to read offline."
        : "Reconnect, reload a public page, wait for it to finish, then check again.";
      return;
    }
    if (!published.ready) {
      guidance.textContent = "The published public snapshot is unavailable. Wait for the site data update to finish, then check again.";
      return;
    }
    guidance.textContent = "Reload a public page and wait for it to finish, then check again.";
  }

  async function checkHealth() {
    if (checking) {
      pendingCheck = true;
      return;
    }
    checking = true;
    checks.textContent = "";
    guidance.hidden = true;
    summary.textContent = "Checking offline reading status…";

    try {
      var online = navigator.onLine;
      setCheck("Connection", online ? "ready" : "offline", online ? "Online" : "Offline");

      var serviceWorker = await serviceWorkerStatus();
      setCheck("Service worker", serviceWorker.ready ? "ready" : "missing", serviceWorker.ready ? "Ready" : serviceWorker.supported ? "Not ready" : "Not supported");

      var artifacts = await localArtifacts();
      setCheck("Saved reader shell", artifacts.reader ? "ready" : "missing", artifacts.reader ? "Available" : "Missing");
      setCheck("Saved reader assets", artifacts.assetsReady ? "ready" : "missing", artifacts.assetsReady ? "Available" : "Missing");
      setCheck("Saved public snapshot", artifacts.snapshot ? "ready" : "missing", artifacts.snapshot ? "Available" : "Missing");

      var published = await publishedSnapshotStatus(online);
      setCheck("Published public snapshot", published.checked ? (published.ready ? "ready" : "missing") : "unchecked", published.checked ? (published.ready ? "Reachable" : "Unavailable") : "Not checked while offline");

      if (serviceWorker.ready && artifacts.ready) {
        summary.textContent = "Offline reading is ready on this device.";
        if (!published.ready && published.checked) guidance.textContent = "Saved content remains ready, but the current published snapshot is unavailable.";
        else guidance.hidden = true;
        return;
      }

      summary.textContent = "Offline reading is not ready on this device.";
      setGuidance(online, serviceWorker, artifacts, published);
    } finally {
      checking = false;
      if (pendingCheck) {
        pendingCheck = false;
        checkHealth();
      }
    }
  }

  recheck.addEventListener("click", checkHealth);
  checkHealth();
  if ("serviceWorker" in navigator) navigator.serviceWorker.addEventListener("controllerchange", checkHealth);
})();
