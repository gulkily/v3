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
    // Phone text size (sqlite.css scales the dialog by --query-zoom).
    var savedZoom = NaN;
    try { savedZoom = parseFloat(window.localStorage.getItem("query-zoom")); } catch (error) {}
    if (savedZoom >= 0.6 && savedZoom <= 2.5) { dialog.style.setProperty("--query-zoom", String(savedZoom)); }
    dialog.addEventListener("click", function (event) {
      var button = event.target.closest("[data-query-zoom]");
      if (!button) { return; }
      var current = parseFloat(dialog.style.getPropertyValue("--query-zoom")) || 1;
      var next = Math.round(Math.min(2.5, Math.max(0.6, current + 0.1 * Number(button.getAttribute("data-query-zoom")))) * 10) / 10;
      dialog.style.setProperty("--query-zoom", String(next));
      try { window.localStorage.setItem("query-zoom", String(next)); } catch (error) {}
    });
    // iOS Safari zooms the page into any field under 16px when it gains focus and
    // stays zoomed after, which would undo the dense text. maximum-scale stops that
    // auto-zoom while the dialog is open; pinch zoom still works on iOS.
    var viewport = document.querySelector('meta[name="viewport"]');
    var viewportContent = viewport ? viewport.getAttribute("content") : null;
    function lockFocusZoom(on) {
      if (!viewport || viewportContent === null || !/iPhone|iPad|iPod/.test(navigator.userAgent)) { return; }
      viewport.setAttribute("content", on ? viewportContent + ", maximum-scale=1" : viewportContent);
    }
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
        lockFocusZoom(true);
        dialog.showModal();
        dialog.scrollTop = dialogScroll;
      }
      var version = ++openVersion;
      // Let the shell paint before database initialization or query execution.
      requestAnimationFrame(function () {
        setTimeout(function () {
          if (version !== openVersion || !dialog.open || !root || !root.sqliteViewer) { return; }
          root.sqliteViewer.open({ key: action.dataset.queryKey, sql: action.dataset.querySql });
        }, 0);
      });
    });
    dialog.querySelector("[data-close-query]").addEventListener("click", function () { dialogScroll = dialog.scrollTop; dialog.close(); });
    dialog.addEventListener("cancel", function () { dialogScroll = dialog.scrollTop; });
    dialog.addEventListener("close", function () {
      openVersion++;
      lockFocusZoom(false);
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
