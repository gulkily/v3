(function () {
  "use strict";

  function responseValue(text, name) {
    var match = String(text || "").match(new RegExp("^" + name + "=(.*)$", "m"));
    return match ? match[1].trim() : "";
  }

  function isOffline(error) {
    var message = String(error && error.message ? error.message : error || "").toLowerCase();
    return (typeof navigator !== "undefined" && navigator.onLine === false)
      || message.indexOf("failed to fetch") !== -1
      || message.indexOf("networkerror") !== -1;
  }

  function failureState(error) {
    var message = String(error && error.message ? error.message : error || "Unable to send this Outbox item.");
    var lower = message.toLowerCase();
    if (isOffline(error)) return "waiting_for_connection";
    if (lower.indexOf("not found") !== -1 || lower.indexOf("target") !== -1 || lower.indexOf("parent") !== -1) return "conflicted";
    if (lower.indexOf("identity") !== -1 || lower.indexOf("browser key") !== -1 || lower.indexOf("signature") !== -1) return "needs_attention";
    return "rejected";
  }

  function outcome(message, extra) {
    return Object.assign({ message: message }, extra || {});
  }

  function samePreparedDelivery(item) {
    return item.delivery && item.delivery.fields && item.delivery.prepared
      ? item.delivery
      : null;
  }

  function prepareDelivery(item) {
    if (!window.ForumBrowserSigning || typeof window.ForumBrowserSigning.prepareOutboxPost !== "function") {
      return Promise.reject(new Error("Signing tools are unavailable. Reload while online and try again."));
    }
    return window.ForumBrowserSigning.prepareOutboxPost(item.action, item.payload).then(function (result) {
      if (!result || !result.ok) throw new Error(result && result.error ? result.error : "Unable to prepare this Outbox post.");
      return { fields: result.fields, prepared: result.prepared };
    });
  }

  function finalizeDelivery(delivery) {
    if (!window.ForumBrowserSigning || typeof window.ForumBrowserSigning.finalizeOutboxPost !== "function") {
      return Promise.reject(new Error("Signing tools are unavailable. Reload while online and try again."));
    }
    return window.ForumBrowserSigning.finalizeOutboxPost(delivery.fields, delivery.prepared).then(function (result) {
      if (!result || !result.ok) throw new Error(result && result.error ? result.error : "Unable to finish this signed post.");
      return result;
    });
  }

  function confirmPreparedDelivery(delivery) {
    var postId = delivery && delivery.prepared && delivery.prepared.postId;
    if (!postId || typeof fetch !== "function") return Promise.resolve(false);
    return fetch("/api/get_post?post_id=" + encodeURIComponent(postId), { credentials: "same-origin" })
      .then(function (response) { return response.ok; })
      .catch(function () { return false; });
  }

  function sendReaction(item) {
    if (item.intent && item.intent.canonicalRecord && item.intent.detachedSignature) {
      return fetch("/api/apply_signed_reaction", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({
          canonical_record: item.intent.canonicalRecord,
          detached_signature: item.intent.detachedSignature
        }).toString()
      }).then(function (response) {
        return response.text();
      }).then(function (text) {
        if (responseValue(text, "status") !== "ok") throw new Error(responseValue(text, "error") || "Unable to apply this signed reaction.");
        return {
          threadId: responseValue(text, "thread_id"),
          postId: responseValue(text, "post_id"),
          commitSha: responseValue(text, "commit_sha"),
          actionAt: responseValue(text, "action_at"),
          integrationAt: responseValue(text, "integration_at")
        };
      });
    }
    if (!window.ForumBrowserSigning || typeof window.ForumBrowserSigning.ensureActionIdentity !== "function") {
      return Promise.reject(new Error("Signing tools are unavailable. Reload while online and try again."));
    }
    var payload = item.payload || {};
    if (payload.kind !== "thread_tag" || !payload.threadId || !payload.tag) {
      return Promise.reject(new Error("This reaction is incomplete and cannot be sent."));
    }
    return window.ForumBrowserSigning.ensureActionIdentity(null, null).then(function () {
      return fetch("/api/apply_thread_tag", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: new URLSearchParams({ thread_id: payload.threadId, tag: payload.tag }).toString()
      });
    }).then(function (response) {
      return response.text();
    }).then(function (text) {
      if (responseValue(text, "status") !== "ok") throw new Error(responseValue(text, "error") || "Unable to apply this reaction.");
        return { threadId: responseValue(text, "thread_id"), commitSha: responseValue(text, "commit_sha") };
    });
  }

  function transitionAndSave(item, state, result) {
    var next = window.forumOutbox.transition(item, state, result);
    return window.forumOutboxStorage.save(next).then(function () { return next; });
  }

  function send(item) {
    if (!item || ["queued", "waiting_for_connection"].indexOf(item.state) === -1) {
      return Promise.reject(new Error("Queue this Outbox item before sending it."));
    }
    var sendingItem = null;
    return transitionAndSave(item, "sending").then(function (sending) {
      sendingItem = sending;
      if (sending.action === "reaction") {
        return sendReaction(sending).then(function (result) {
          var integrated = Object.assign({}, sending, { integrationAt: result.integrationAt || sending.integrationAt || new Date().toISOString() });
          return transitionAndSave(integrated, "accepted", outcome("Reaction accepted by the server.", result));
        });
      }

      var delivery = samePreparedDelivery(sending);
      return (delivery ? Promise.resolve(delivery) : prepareDelivery(sending).then(function (prepared) {
        var retained = Object.assign({}, sending, { delivery: prepared });
        return window.forumOutboxStorage.save(retained).then(function () {
          sendingItem = retained;
          return prepared;
        });
      })).then(function (prepared) {
        return finalizeDelivery(prepared).then(function (result) {
          return transitionAndSave(Object.assign({}, sending, { delivery: prepared }), "accepted", outcome("Post accepted by the server.", {
            postId: result.postId || prepared.prepared.postId,
            threadId: result.threadId || prepared.prepared.threadId,
            commitSha: result.commitSha || ""
          }));
        }).catch(function (error) {
          if (String(error && error.message ? error.message : error).toLowerCase().indexOf("prepared post not found") === -1) throw error;
          return confirmPreparedDelivery(prepared).then(function (confirmed) {
            if (!confirmed) throw error;
            return transitionAndSave(Object.assign({}, sending, { delivery: prepared }), "accepted", outcome("Post was accepted by the server; delivery was confirmed after an interrupted response.", {
              postId: prepared.prepared.postId,
              threadId: prepared.prepared.threadId
            }));
          });
        });
      });
    }).catch(function (error) {
      if (!sendingItem) throw error;
      var failure = window.forumOutbox.transition(
        sendingItem,
        failureState(error),
        outcome(String(error && error.message ? error.message : error || "Unable to send this Outbox item."))
      );
      return window.forumOutboxStorage.save(failure).then(function () { return failure; });
    });
  }

  var processingPromise = null;

  function processQueuedOutbox() {
    if (typeof navigator !== "undefined" && navigator.onLine === false) return Promise.resolve([]);
    if (processingPromise) return processingPromise;
    var process = function () {
      return window.forumOutboxStorage.list().then(function (items) {
        var eligible = items.filter(function (item) {
          return item.state === "queued" || item.state === "waiting_for_connection";
        });
        return eligible.reduce(function (chain, item) {
          return chain.then(function (results) {
            return send(item).then(function (result) {
              results.push(result);
              return results;
            });
          });
        }, Promise.resolve([]));
      });
    };
    var locks = typeof navigator !== "undefined" && navigator.locks && typeof navigator.locks.request === "function";
    processingPromise = (locks
      ? navigator.locks.request("forum-outbox-delivery", { ifAvailable: true }, function (lock) { return lock ? process() : []; })
      : process()
    ).finally(function () { processingPromise = null; });
    return processingPromise;
  }

  window.forumOutboxSender = { send: send, failureState: failureState, processQueuedOutbox: processQueuedOutbox };
})();
