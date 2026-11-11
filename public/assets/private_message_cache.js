(function () {
  "use strict";

  // Messages this browser has already read and verified, kept so a conversation can show them without
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

  function enabled() {
    return Boolean(document.querySelector('[data-private-message-cache="1"]'))
      && Boolean(window.indexedDB && window.crypto && window.crypto.subtle)
      && fingerprint() !== "";
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

  async function get(messageId) {
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

  window.ForumPrivateMessageCache = { databaseName: databaseName, enabled: enabled, get: get, put: put, clearAll: clearAll };
})();
