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
        itemsRoot.appendChild(card);
      });
    }

    window.forumOutboxStorage.list().then(render).catch(function (error) {
      setStatus(error && error.message ? error.message : "Outbox storage is unavailable on this device.", "error");
    });
  });
})();
