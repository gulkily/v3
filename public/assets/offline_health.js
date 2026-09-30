(function () {
  "use strict";

  var root = document.querySelector("[data-offline-health]");
  if (!root) return;

  var summary = root.querySelector('[data-role="offline-health-summary"]');
  var status = root.querySelector('[data-role="offline-health-status"]');
  var deviceChecks = root.querySelector('[data-role="offline-health-checks"]');
  var workerChecks = root.querySelector('[data-role="offline-worker-checks"]');
  var archiveChecks = root.querySelector('[data-role="offline-archive-checks"]');
  var publicationChecks = root.querySelector('[data-role="offline-publication-checks"]');
  var guidance = root.querySelector('[data-role="offline-health-guidance"]');
  var recheck = root.querySelector('[data-action="recheck-offline-health"]');
  var openSavedArchive = root.querySelector('[data-role="open-saved-archive"]');
  var readerUrl = root.getAttribute("data-reader-url") || "/offline/reader/";
  var snapshotUrl = root.getAttribute("data-snapshot-url") || "/offline/snapshot.sqlite3";
  var runtimeUrl = root.getAttribute("data-runtime-url") || "/assets/sql-wasm.wasm";
  var diagnosticKey = "forum-offline-registration-error";
  var checking = false;
  var pendingCheck = false;

  function absoluteUrl(url) {
    return new URL(url, window.location.origin).href;
  }

  function setCheck(section, label, state, detail, source) {
    var row = document.createElement("tr");
    row.setAttribute("data-state", state);
    var heading = document.createElement("th");
    heading.setAttribute("scope", "row");
    heading.textContent = label;
    var value = document.createElement("td");
    value.textContent = detail;
    if (source) {
      var sourceLine = document.createElement("div");
      sourceLine.className = "codebase-source";
      var sourceCode = document.createElement("code");
      sourceCode.textContent = source;
      sourceLine.appendChild(sourceCode);
      value.appendChild(sourceLine);
    }
    row.appendChild(heading);
    row.appendChild(value);
    section.appendChild(row);
  }

  function clearChecks() {
    [deviceChecks, workerChecks, archiveChecks, publicationChecks].forEach(function (section) {
      section.textContent = "";
    });
  }

  function setHealthStatus(state, text) {
    status.setAttribute("data-status", state);
    status.textContent = text;
  }

  function diagnosticValue(value) {
    return value === null || value === undefined || value === "" ? "none" : String(value);
  }

  function savedRegistrationError() {
    try {
      var saved = window.localStorage.getItem(diagnosticKey);
      if (!saved) return null;
      var decoded = JSON.parse(saved);
      return {
        at: diagnosticValue(decoded.at),
        name: diagnosticValue(decoded.name),
        message: diagnosticValue(decoded.message)
      };
    } catch (error) {
      return { at: "unavailable", name: error && error.name ? error.name : "Error", message: error && error.message ? error.message : "Unable to read saved registration error" };
    }
  }

  async function offlineCache() {
    var names = await window.caches.keys();
    var matching = names.filter(function (name) { return /^zenmemes-offline-reader-v\d+$/.test(name); });
    matching.sort(function (left, right) {
      return Number(right.slice(right.lastIndexOf("v") + 1)) - Number(left.slice(left.lastIndexOf("v") + 1));
    });
    if (!matching.length) return null;
    return { name: matching[0], cache: await window.caches.open(matching[0]) };
  }

  async function cachedResponse(cache, url) {
    return cache.match(absoluteUrl(url), { ignoreVary: true });
  }

  function metadataValue(database, key) {
    var result = database.exec("SELECT value FROM metadata WHERE key = ?", [key])[0];
    return result && result.values && result.values[0] ? String(result.values[0][0] || "") : "";
  }

  function positiveInteger(value) {
    var number = Number(value);
    return Number.isFinite(number) && number >= 0 ? Math.floor(number) : null;
  }

  function formatBytes(bytes) {
    if (!Number.isFinite(bytes) || bytes < 0) return "Unknown";
    if (bytes < 1024) return bytes + " bytes";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(bytes % 1024 === 0 ? 0 : 1) + " KiB";
    return (bytes / (1024 * 1024)).toFixed(bytes % (1024 * 1024) === 0 ? 0 : 1) + " MiB";
  }

  async function savedArchiveStats(cache) {
    var unavailable = {
      available: false,
      sizeBytes: null,
      generatedAt: null,
      threadCount: null,
      postCount: null,
      maxBytes: null,
      error: null,
    };
    if (!cache) return unavailable;

    try {
      var response = await cachedResponse(cache, snapshotUrl);
      if (!response) return unavailable;

      var bytes = new Uint8Array(await response.arrayBuffer());
      var stats = Object.assign({}, unavailable, { available: true, sizeBytes: bytes.byteLength });
      if (typeof window.initSqlJs !== "function") {
        stats.error = "Browser SQLite runtime is unavailable.";
        return stats;
      }

      var SQL = await window.initSqlJs({
        locateFile: function (fileName) {
          return fileName === "sql-wasm.wasm" ? runtimeUrl : "/assets/" + fileName;
        }
      });
      var database = new SQL.Database(bytes);
      try {
        stats.generatedAt = metadataValue(database, "generated_at") || null;
        stats.threadCount = positiveInteger(metadataValue(database, "thread_count"));
        stats.postCount = positiveInteger(metadataValue(database, "post_count"));
        stats.maxBytes = positiveInteger(metadataValue(database, "max_bytes"));
      } finally {
        database.close();
      }
      return stats;
    } catch (error) {
      return Object.assign({}, unavailable, {
        error: error && error.message ? error.message : String(error),
      });
    }
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
    var reader = documentFragment.querySelector("[data-offline-reader]");
    var readerRuntimeUrl = reader ? reader.getAttribute("data-runtime-url") : null;
    urls.push(absoluteUrl(readerRuntimeUrl || "/assets/sql-wasm.wasm"));
    return Array.from(new Set(urls));
  }

  async function localArtifacts() {
    if (!("caches" in window)) return { available: false, ready: false, assetsReady: false, cacheName: null, cache: null, error: null };
    try {
      var cacheInfo = await offlineCache();
      if (!cacheInfo) return { available: true, ready: false, reader: false, snapshot: false, assetsReady: false, cacheName: null, cache: null, error: null };
      var reader = await cachedResponse(cacheInfo.cache, readerUrl);
      var snapshot = await cachedResponse(cacheInfo.cache, snapshotUrl);
      if (!reader) return { available: true, ready: false, reader: false, snapshot: Boolean(snapshot), assetsReady: false, cacheName: cacheInfo.name, cache: cacheInfo.cache, error: null };

      var assets = await readerAssetUrls(reader.clone());
      var responses = await Promise.all(assets.map(function (url) { return cachedResponse(cacheInfo.cache, url); }));
      var assetsReady = responses.every(Boolean);
      return {
        available: true,
        ready: Boolean(snapshot) && assetsReady,
        reader: true,
        snapshot: Boolean(snapshot),
        assetsReady: assetsReady,
        cacheName: cacheInfo.name,
        cache: cacheInfo.cache,
        error: null,
      };
    } catch (error) {
      return { available: false, ready: false, assetsReady: false, cacheName: null, cache: null, error: error && error.message ? error.message : String(error) };
    }
  }

  async function serviceWorkerStatus() {
    var rootScope = window.location.origin + "/";
    var result = {
      supported: "serviceWorker" in navigator,
      secure: window.isSecureContext === true,
      ready: false,
      registrationCount: null,
      registration: null,
      controller: null,
      error: null,
      savedError: savedRegistrationError(),
    };
    if (!result.supported) return result;
    try {
      var registrations = await navigator.serviceWorker.getRegistrations();
      var registration = registrations.find(function (candidate) { return candidate.scope === rootScope; }) || null;
      result.registrationCount = registrations.length;
      result.registration = registration;
      result.controller = navigator.serviceWorker.controller || null;
      result.ready = Boolean(registration && registration.active);
      return result;
    } catch (error) {
      result.error = error && error.message ? error.message : String(error);
      return result;
    }
  }

  async function publishedSnapshotStatus(online) {
    if (!online) return { checked: false, ready: false, status: null, error: null };
    var controller = "AbortController" in window ? new AbortController() : null;
    var timeout = controller ? window.setTimeout(function () { controller.abort(); }, 3000) : null;
    try {
      var response = await window.fetch(snapshotUrl, {
        method: "HEAD",
        credentials: "omit",
        cache: "no-store",
        signal: controller ? controller.signal : undefined,
      });
      return { checked: true, ready: response.ok, status: response.status, error: null };
    } catch (error) {
      return { checked: true, ready: false, status: null, error: error && error.message ? error.message : String(error) };
    } finally {
      if (timeout !== null) window.clearTimeout(timeout);
    }
  }

  function setGuidance(online, serviceWorker, artifacts, published) {
    guidance.hidden = false;
    if (!serviceWorker.secure) {
      guidance.textContent = "Open this page over HTTPS. Service workers require a secure context (except localhost).";
      return;
    }
    if (!serviceWorker.supported || !artifacts.available) {
      guidance.textContent = "Use the diagnostic values above to resolve browser storage or service-worker availability.";
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
    clearChecks();
    guidance.hidden = true;
    openSavedArchive.hidden = true;
    setHealthStatus("checking", "CHECKING");
    summary.textContent = "Checking offline reading status…";

    try {
      var online = navigator.onLine;
      setCheck(deviceChecks, "Connection", online ? "ready" : "offline", online ? "Online" : "Offline", "navigator.onLine");
      setCheck(deviceChecks, "Page URL", "ready", window.location.href, "window.location.href");
      setCheck(deviceChecks, "Secure context", window.isSecureContext === true ? "ready" : "missing", window.isSecureContext === true ? "true" : "false", "window.isSecureContext");
      setCheck(deviceChecks, "Service-worker API", "serviceWorker" in navigator ? "ready" : "missing", "serviceWorker" in navigator ? "present" : "missing", "\"serviceWorker\" in navigator");
      setCheck(deviceChecks, "Cache Storage API", "caches" in window ? "ready" : "missing", "caches" in window ? "present" : "missing", "\"caches\" in window");

      var serviceWorker = await serviceWorkerStatus();
      setCheck(workerChecks, "Service worker", serviceWorker.ready ? "ready" : "missing", serviceWorker.ready ? "Ready" : serviceWorker.supported ? "Not ready" : "Not supported", "navigator.serviceWorker.getRegistrations()");
      setCheck(workerChecks, "Worker registrations", serviceWorker.registrationCount === 0 ? "missing" : "ready", diagnosticValue(serviceWorker.registrationCount), "navigator.serviceWorker.getRegistrations()");
      setCheck(workerChecks, "Root registration scope", serviceWorker.registration ? "ready" : "missing", serviceWorker.registration ? serviceWorker.registration.scope : "none", "registration.scope");
      setCheck(workerChecks, "Active worker script", serviceWorker.registration && serviceWorker.registration.active ? "ready" : "missing", serviceWorker.registration && serviceWorker.registration.active ? serviceWorker.registration.active.scriptURL : "none", "registration.active.scriptURL");
      setCheck(workerChecks, "Installing worker state", serviceWorker.registration && serviceWorker.registration.installing ? "missing" : "ready", serviceWorker.registration && serviceWorker.registration.installing ? serviceWorker.registration.installing.state : "none", "registration.installing.state");
      setCheck(workerChecks, "Page controller", serviceWorker.controller ? "ready" : "missing", serviceWorker.controller ? serviceWorker.controller.scriptURL : "none", "navigator.serviceWorker.controller.scriptURL");
      if (serviceWorker.error) setCheck(workerChecks, "Registration inspection error", "missing", serviceWorker.error, "navigator.serviceWorker.getRegistrations()");
      if (serviceWorker.savedError) setCheck(workerChecks, "Last registration error", "missing", serviceWorker.savedError.at + " | " + serviceWorker.savedError.name + ": " + serviceWorker.savedError.message, "localStorage.getItem(\"forum-offline-registration-error\")");

      if (!serviceWorker.secure) {
        setCheck(archiveChecks, "Saved archive", "missing", "Not checked — HTTPS is required.", "Cache Storage is unavailable without a secure context.");
        setCheck(publicationChecks, "Published archive", "missing", "Not checked — HTTPS is required.", "fetch(\"/offline/snapshot.sqlite3\", { method: \"HEAD\" })");
        setHealthStatus("not-ready", "NOT READY");
        summary.textContent = "Offline reading requires HTTPS.";
        guidance.hidden = false;
        guidance.textContent = "Open https://" + window.location.host + "/offline/. This page is currently " + window.location.protocol + ", so the browser will not expose service workers or Cache Storage.";
        return;
      }

      var artifacts = await localArtifacts();
      setCheck(archiveChecks, "Offline reader cache", artifacts.cacheName ? "ready" : "missing", diagnosticValue(artifacts.cacheName), "caches.keys(); caches.open(cacheName)");
      setCheck(archiveChecks, "Saved reader shell", artifacts.reader ? "ready" : "missing", artifacts.reader ? "Available" : "Missing", "cache.match(\"" + readerUrl + "\", { ignoreVary: true })");
      setCheck(archiveChecks, "Saved reader assets", artifacts.assetsReady ? "ready" : "missing", artifacts.assetsReady ? "Available" : "Missing", "DOMParser(reader shell); cache.match(assetUrl, { ignoreVary: true })");
      setCheck(archiveChecks, "Saved public snapshot", artifacts.snapshot ? "ready" : "missing", artifacts.snapshot ? "Available" : "Missing", "cache.match(\"" + snapshotUrl + "\", { ignoreVary: true })");
      var archive = await savedArchiveStats(artifacts.cache);
      setCheck(archiveChecks, "Saved archive size", archive.available ? "ready" : "missing", archive.available ? formatBytes(archive.sizeBytes) : "Missing", "cached /offline/snapshot.sqlite3 byte length");
      setCheck(archiveChecks, "Archive generated", archive.generatedAt ? "ready" : "missing", archive.generatedAt || "Unknown", "SQLite metadata: generated_at");
      setCheck(archiveChecks, "Archive contents", archive.threadCount !== null && archive.postCount !== null ? "ready" : "missing", archive.threadCount !== null && archive.postCount !== null ? archive.threadCount + " threads; " + archive.postCount + " posts" : "Unknown", "SQLite metadata: thread_count, post_count");
      setCheck(archiveChecks, "Archive capacity", archive.maxBytes !== null ? "ready" : "missing", archive.maxBytes !== null ? formatBytes(archive.maxBytes) + " configured maximum" : "Unknown", "SQLite metadata: max_bytes");
      if (archive.error) setCheck(archiveChecks, "Archive inspection error", "missing", archive.error, "window.initSqlJs(); new SQL.Database(bytes)");
      if (artifacts.error) setCheck(archiveChecks, "Cache inspection error", "missing", artifacts.error, "Cache Storage API");

      var published = await publishedSnapshotStatus(online);
      setCheck(publicationChecks, "Published public snapshot", published.checked ? (published.ready ? "ready" : "missing") : "unchecked", published.checked ? (published.ready ? "HTTP " + published.status : published.status ? "HTTP " + published.status : "Unavailable") : "Not checked while offline", "fetch(\"" + snapshotUrl + "\", { method: \"HEAD\", cache: \"no-store\" })");
      if (published.error) setCheck(publicationChecks, "Published snapshot error", "missing", published.error, "fetch(\"" + snapshotUrl + "\", { method: \"HEAD\" })");

      if (serviceWorker.ready && artifacts.ready) {
        setHealthStatus("ready", "READY");
        openSavedArchive.hidden = false;
        summary.textContent = "Offline reading is ready on this device.";
        if (!published.ready && published.checked) guidance.textContent = "Saved content remains ready, but the current published snapshot is unavailable.";
        else guidance.hidden = true;
        return;
      }

      setHealthStatus(serviceWorker.ready || artifacts.snapshot ? "partial" : "not-ready", serviceWorker.ready || artifacts.snapshot ? "PARTIAL" : "NOT READY");
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
