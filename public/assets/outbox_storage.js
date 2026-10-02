(function () {
  "use strict";

  var databaseName = "forum-offline-outbox-v1";
  var storeName = "items";

  function storageError(error) {
    var name = error && error.name ? error.name : "";
    if (name === "QuotaExceededError") return "Outbox storage is full. Export or discard local work before adding more.";
    if (name === "SecurityError") return "This browser does not allow local Outbox storage.";
    return "Outbox storage is unavailable on this device.";
  }

  function openDatabase() {
    if (!window.indexedDB) return Promise.reject(new Error(storageError()));
    return new Promise(function (resolve, reject) {
      var request = window.indexedDB.open(databaseName, 1);
      request.onupgradeneeded = function () {
        var database = request.result;
        if (!database.objectStoreNames.contains(storeName)) database.createObjectStore(storeName, { keyPath: "id" });
      };
      request.onerror = function () { reject(new Error(storageError(request.error))); };
      request.onsuccess = function () { resolve(request.result); };
    });
  }

  function useStore(mode, action) {
    return openDatabase().then(function (database) {
      return new Promise(function (resolve, reject) {
        var transaction = database.transaction(storeName, mode);
        var result;
        transaction.oncomplete = function () {
          database.close();
          resolve(result);
        };
        transaction.onerror = transaction.onabort = function () {
          database.close();
          reject(new Error(storageError(transaction.error)));
        };
        try {
          var request = action(transaction.objectStore(storeName));
          request.onerror = function () { reject(new Error(storageError(request.error))); };
          request.onsuccess = function () { result = request.result; };
        } catch (error) {
          database.close();
          reject(error);
        }
      });
    });
  }

  function validate(item) {
    if (!window.forumOutbox || typeof window.forumOutbox.createItem !== "function") {
      throw new Error("Outbox state is unavailable.");
    }
    return window.forumOutbox.createItem(item);
  }

  function list() {
    return useStore("readonly", function (store) { return store.getAll(); }).then(function (items) {
      return (items || []).sort(function (left, right) { return right.createdAt.localeCompare(left.createdAt); });
    });
  }

  window.forumOutboxStorage = {
    list: list,
    load: function (id) { return useStore("readonly", function (store) { return store.get(id); }); },
    save: function (item) { return useStore("readwrite", function (store) { return store.put(validate(item)); }); },
    remove: function (id) { return useStore("readwrite", function (store) { return store.delete(id); }); },
    clear: function () { return useStore("readwrite", function (store) { return store.clear(); }); },
    errorMessage: storageError
  };
})();
