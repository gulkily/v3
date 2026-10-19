(function () {
  "use strict";
  document.addEventListener("DOMContentLoaded", function () {
    var dialog = document.querySelector("[data-query-dialog]");
    if (!dialog) { return; }
    var root = dialog.querySelector("[data-sqlite-viewer]");
    var returnFocus = null;
    var background = null;
    var dialogScroll = 0;
    var openVersion = 0;
    var pendingRanges = null;
    function captureRanges() {
      var selection = window.getSelection();
      return selection ? Array.from({ length: selection.rangeCount }, function (_, index) { return selection.getRangeAt(index).cloneRange(); }) : [];
    }
    document.addEventListener("pointerdown", function (event) {
      pendingRanges = event.target.closest("[data-view-query]") ? captureRanges() : null;
    }, true);
    document.addEventListener("click", function (event) {
      var action = event.target.closest("[data-view-query]");
      if (!action) { return; }
      returnFocus = action;
      if (!dialog.open) {
        background = { x: window.scrollX, y: window.scrollY, overflow: document.body.style.overflow,
          ranges: pendingRanges || captureRanges() };
        pendingRanges = null;
        document.body.style.overflow = "hidden";
        dialog.showModal();
        dialog.scrollTop = dialogScroll;
      }
      var version = ++openVersion;
      // Let the shell paint before database initialization or query execution.
      requestAnimationFrame(function () {
        setTimeout(function () {
          if (version !== openVersion || !dialog.open) { return; }
          root.sqliteViewer.open({ key: action.dataset.queryKey, sql: action.dataset.querySql });
        }, 0);
      });
    });
    dialog.querySelector("[data-close-query]").addEventListener("click", function () { dialogScroll = dialog.scrollTop; dialog.close(); });
    dialog.addEventListener("cancel", function () { dialogScroll = dialog.scrollTop; });
    dialog.addEventListener("close", function () {
      openVersion++;
      if (background) {
        document.body.style.overflow = background.overflow;
        var selection = window.getSelection();
        if (selection && background.ranges.length) {
          selection.removeAllRanges();
          background.ranges.forEach(function (range) { selection.addRange(range); });
        }
      }
      if (returnFocus) { returnFocus.focus({ preventScroll: true }); }
      if (background) { window.scrollTo(background.x, background.y); }
    });
  });
})();
