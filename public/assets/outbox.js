(function () {
  "use strict";

  function clear(node) {
    while (node && node.firstChild) node.removeChild(node.firstChild);
  }

  function displayState(state) {
    return String(state || "unknown").replace(/_/g, " ");
  }

  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-outbox]");
    if (!root || !window.forumOutbox || !window.forumOutboxStorage) return;
    var status = root.querySelector('[data-role="outbox-status"]');
    var itemsRoot = root.querySelector('[data-role="outbox-items"]');

    function setStatus(message, kind) {
      status.textContent = message;
      status.dataset.kind = kind || "";
    }

    function render(items) {
      clear(itemsRoot);
      if (!items.length) {
        setStatus("No local drafts or queued actions on this device.", "empty");
        return;
      }
      var pending = window.forumOutbox.pendingCount(items);
      setStatus(items.length + " local item" + (items.length === 1 ? "" : "s") + "; " + pending + " still need attention.", "ready");
      items.forEach(function (item) {
        var summary = window.forumOutbox.safeSummary(item);
        var card = document.createElement("article");
        card.className = "card";
        var heading = document.createElement("h2");
        heading.textContent = summary.action + " — " + displayState(summary.state);
        var detail = document.createElement("p");
        detail.className = "meta";
        detail.textContent = summary.summary || "Local action";
        var created = document.createElement("p");
        created.className = "meta";
        created.textContent = "Created " + summary.createdAt;
        card.appendChild(heading);
        card.appendChild(detail);
        card.appendChild(created);
        if (summary.outcome && summary.outcome.message) {
          var outcome = document.createElement("p");
          outcome.className = "meta";
          outcome.textContent = summary.outcome.message;
          card.appendChild(outcome);
        }
        var actions = document.createElement("p");
        actions.className = "compose-form-actions";
        function addAction(label, handler) {
          var button = document.createElement("button");
          button.type = "button";
          button.textContent = label;
          button.addEventListener("click", function () {
            button.disabled = true;
            handler().then(reload).catch(function (error) {
              button.disabled = false;
              setStatus(error && error.message ? error.message : "Unable to update this Outbox item.", "error");
            });
          });
          actions.appendChild(button);
        }
        function saveTransition(nextState) {
          return window.forumOutboxStorage.save(window.forumOutbox.transition(item, nextState));
        }
        if (item.state === "draft") {
          addAction("Queue", function () { return saveTransition("queued"); });
        }
        if (item.state === "rejected" || item.state === "conflicted" || item.state === "needs_attention") {
          addAction("Retry", function () {
            var retryState = item.state === "rejected" || item.state === "conflicted" ? "draft" : "queued";
            return window.forumOutboxStorage.save(window.forumOutbox.transition(item, retryState)).then(function (retryItem) {
              return retryItem.state === "queued"
                ? window.forumOutboxSender.send(retryItem)
                : window.forumOutboxStorage.save(window.forumOutbox.transition(retryItem, "queued")).then(function (queued) {
                  return window.forumOutboxSender.send(queued);
                });
            });
          });
        }
        if (item.state === "queued" || item.state === "waiting_for_connection") {
          addAction(item.state === "waiting_for_connection" ? "Retry send" : "Send", function () {
            if (!window.forumOutboxSender) return Promise.reject(new Error("Outbox sending is unavailable. Reload while online and try again."));
            return window.forumOutboxSender.send(item);
          });
        }
        if (item.state !== "sending" && item.state !== "accepted" && item.state !== "cancelled") {
          addAction("Discard", function () { return window.forumOutboxStorage.remove(item.id); });
        }
        if (item.state === "accepted" || item.state === "cancelled") {
          addAction("Remove", function () { return window.forumOutboxStorage.remove(item.id); });
        }
        card.appendChild(actions);
        itemsRoot.appendChild(card);
      });
    }

    function reload() {
      return window.forumOutboxStorage.list().then(render);
    }

    reload().catch(function (error) {
      setStatus(error && error.message ? error.message : "Outbox storage is unavailable on this device.", "error");
    });
  });
})();
