(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    var dialog = document.querySelector("[data-query-dialog]");
    if (!dialog) { return; }
    var root = dialog.querySelector("[data-sqlite-viewer]");
    var returnFocus = null;
    document.addEventListener("click", function (event) {
      var action = event.target.closest("[data-view-query]");
      if (!action) { return; }
      returnFocus = action;
      dialog.querySelector("h2").textContent = action.dataset.queryLabel;
      if (!dialog.open) { dialog.showModal(); }
      // Let the shell paint before database initialization or query execution.
      requestAnimationFrame(function () {
        setTimeout(function () {
          root.sqliteViewer.open({ key: action.dataset.queryKey, sql: action.dataset.querySql });
        }, 0);
      });
    });
    dialog.querySelector("[data-close-query]").addEventListener("click", function () { dialog.close(); });
    dialog.addEventListener("close", function () {
      if (returnFocus) { returnFocus.focus({ preventScroll: true }); }
    });
  });
})();
