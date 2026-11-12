(function () {
  "use strict";

  // Messages this browser has already read and verified, kept so conversations and the Messages list can show them without
  // decrypting again. Entries are encrypted with an AES-GCM key this browser generated and cannot export,
  // and are bound to the browser identity's fingerprint. Every failure is a miss: the reader then reads live.
  const databaseName = "forum-private-message-cache-v1";
  const keyStore = "keys";
  const messageStore = "messages";
  let opening = null;
  let cacheKey = null;

  function fingerprint() {
    try {
      return String(window.localStorage.getItem("forum_pki_fingerprint") || "").trim().toLowerCase();
    } catch (error) {
      return "";
    }
  }

  // Saved copies are only used while this browser could still decrypt them live.
  function hasPrivateKey() {
    try {
      return String(window.localStorage.getItem("forum_pki_private_key") || "").trim() !== "";
    } catch (error) {
      return false;
    }
  }

  function enabled() {
    return Boolean(document.querySelector('[data-private-message-cache="1"]'))
      && Boolean(window.indexedDB && window.crypto && window.crypto.subtle)
      && fingerprint() !== "" && hasPrivateKey();
  }

  function request(store, action) {
    return new Promise(function (resolve, reject) {
      const call = action(store);
      call.onsuccess = function () { resolve(call.result); };
      call.onerror = function () { reject(call.error); };
    });
  }

  function openDatabase() {
    if (!opening) {
      opening = new Promise(function (resolve, reject) {
        const call = window.indexedDB.open(databaseName, 1);
        call.onupgradeneeded = function () {
          const database = call.result;
          if (!database.objectStoreNames.contains(keyStore)) database.createObjectStore(keyStore);
          if (!database.objectStoreNames.contains(messageStore)) database.createObjectStore(messageStore);
        };
        call.onsuccess = function () {
          const database = call.result;
          // Let clearAll delete the database while this page still holds it open.
          database.onversionchange = function () { database.close(); opening = null; cacheKey = null; };
          resolve(database);
        };
        call.onerror = function () { reject(call.error); };
        call.onblocked = function () { reject(new Error("Message cache is blocked.")); };
      });
      opening.catch(function () { opening = null; });
    }
    return opening;
  }

  function store(database, name, mode) {
    return database.transaction(name, mode).objectStore(name);
  }

  async function key(database) {
    if (!cacheKey) {
      cacheKey = (async function () {
        const saved = await request(store(database, keyStore, "readonly"), function (s) { return s.get("key"); });
        if (saved) return saved;
        const created = await window.crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, false, ["encrypt", "decrypt"]);
        try {
          await request(store(database, keyStore, "readwrite"), function (s) { return s.add(created, "key"); });
          return created;
        } catch (error) {
          // Another tab saved its key first; use that one so both tabs read the same entries.
          return request(store(database, keyStore, "readonly"), function (s) { return s.get("key"); });
        }
      })();
      cacheKey.catch(function () { cacheKey = null; });
    }
    return cacheKey;
  }

  function entryId(owner, messageId) {
    return owner + ":" + messageId;
  }

  // Lookups already started for this page, by message id. The script runs as soon as the page is parsed,
  // before the reader and the rest of the page's scripts, so saved copies start loading at once.
  const early = new Map();

  async function readMany(ids) {
    const owner = fingerprint();
    const database = await openDatabase();
    const entries = store(database, messageStore, "readonly");
    const found = await Promise.all(ids.map(function (id) {
      return request(entries, function (s) { return s.get(entryId(owner, id)); }).catch(function () { return null; });
    }));
    const secret = found.some(Boolean) ? await key(database) : null;
    return Promise.all(found.map(function (entry, index) {
      if (!entry || !entry.iv || !entry.data) return null;
      return window.crypto.subtle.decrypt(
        { name: "AES-GCM", iv: entry.iv, additionalData: new TextEncoder().encode(entryId(owner, ids[index])) }, secret, entry.data)
        .then(function (plain) { return owner === fingerprint() ? new TextDecoder().decode(plain) : null; })
        .catch(function () { return null; });
    }));
  }

  function prefetch(messageIds) {
    const ids = Array.from(new Set(messageIds.map(String).filter(Boolean))).filter(function (id) { return !early.has(id); });
    if (!ids.length || !enabled()) return;
    const batch = readMany(ids).catch(function () { return ids.map(function () { return null; }); });
    ids.forEach(function (id, index) {
      early.set(id, batch.then(function (texts) { return texts[index]; }));
    });
  }

  async function get(messageId) {
    const started = early.get(String(messageId));
    if (started) {
      early.delete(String(messageId));
      const text = await started;
      return text !== null && enabled() ? text : null;
    }
    try {
      const owner = fingerprint();
      if (!enabled() || !messageId) return null;
      const database = await openDatabase();
      const id = entryId(owner, String(messageId));
      const entry = await request(store(database, messageStore, "readonly"), function (s) { return s.get(id); });
      if (!entry || !entry.iv || !entry.data) return null;
      const plain = await window.crypto.subtle.decrypt(
        { name: "AES-GCM", iv: entry.iv, additionalData: new TextEncoder().encode(id) }, await key(database), entry.data);
      // The identity may have changed while this read was in flight.
      return owner === fingerprint() ? new TextDecoder().decode(plain) : null;
    } catch (error) {
      return null;
    }
  }

  async function put(messageId, plaintext) {
    try {
      const owner = fingerprint();
      if (!enabled() || !messageId) return false;
      const database = await openDatabase();
      const id = entryId(owner, String(messageId));
      const iv = window.crypto.getRandomValues(new Uint8Array(12));
      const data = await window.crypto.subtle.encrypt(
        { name: "AES-GCM", iv: iv, additionalData: new TextEncoder().encode(id) }, await key(database), new TextEncoder().encode(String(plaintext)));
      if (owner !== fingerprint()) return false;
      await request(store(database, messageStore, "readwrite"), function (s) { return s.put({ iv: iv, data: data }, id); });
      return true;
    } catch (error) {
      return false;
    }
  }

  function clearAll() {
    opening = null;
    cacheKey = null;
    return new Promise(function (resolve) {
      try {
        const call = window.indexedDB.deleteDatabase(databaseName);
        // "blocked" only means another page still has it open; that page closes it on versionchange.
        call.onsuccess = call.onerror = function () { resolve(); };
        setTimeout(resolve, 3000);
      } catch (error) {
        resolve();
      }
    });
  }

  window.ForumPrivateMessageCache = { databaseName: databaseName, enabled: enabled, get: get, put: put, clearAll: clearAll, prefetch: prefetch };

  try {
    prefetch(Array.from(document.querySelectorAll("[data-private-message-id], [data-conversation-row][data-message-id]")).map(function (node) {
      return node.dataset.privateMessageId || node.dataset.messageId;
    }));
  } catch (error) {
    // A miss: the reader reads live.
  }
})();
