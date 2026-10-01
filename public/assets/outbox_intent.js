(function () {
  "use strict";

  function stableValue(value) {
    if (Array.isArray(value)) return value.map(stableValue);
    if (value && typeof value === "object") {
      return Object.keys(value).sort().reduce(function (result, key) {
        result[key] = stableValue(value[key]);
        return result;
      }, {});
    }
    return value;
  }

  function currentAuthorIdentityId() {
    var identity = window.__forumBrowserIdentity;
    return identity && typeof identity.currentAuthorIdentityId === "function"
      ? String(identity.currentAuthorIdentityId() || "")
      : "";
  }

  function canonicalRecord(input) {
    return [
      "Schema: Forum Offline Intent v1",
      "Intent-ID: " + input.id,
      "Action: " + input.action,
      "Target-Kind: " + String((input.target || {}).kind || ""),
      "Target-ID: " + String((input.target || {}).id || ""),
      "Action-At: " + input.actionAt,
      "Author-Identity-ID: " + input.authorIdentityId,
      "Payload: " + JSON.stringify(stableValue(input.payload || {})),
      ""
    ].join("\n");
  }

  function createSignedIntent(input) {
    if (!window.forumOutbox || !window.ForumBrowserSigning || typeof window.ForumBrowserSigning.signCanonicalRecord !== "function") {
      return Promise.reject(new Error("Offline signing is unavailable on this device."));
    }
    var authorIdentityId = String(input && input.authorIdentityId || currentAuthorIdentityId() || "");
    if (!authorIdentityId) return Promise.reject(new Error("Set up a browser identity while online before signing offline work."));
    var actionAt = String(input && input.actionAt || new Date().toISOString());
    var itemInput = Object.assign({}, input, {
      id: input && input.id || window.forumOutbox.createIntentId(input && input.action || "reaction"),
      actionAt: actionAt,
      authorIdentityId: authorIdentityId
    });
    var record = canonicalRecord(itemInput);
    return window.ForumBrowserSigning.signCanonicalRecord(record).then(function (signature) {
      return window.forumOutbox.createItem(Object.assign({}, itemInput, {
        intent: {
          authorIdentityId: authorIdentityId,
          canonicalRecord: record,
          detachedSignature: String(signature || "")
        }
      }));
    });
  }

  window.forumOutboxIntent = {
    canonicalRecord: canonicalRecord,
    createSignedIntent: createSignedIntent
  };
})();
