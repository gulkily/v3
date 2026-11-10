// Text size for tables in Markdown posts on phones (site.css, .post-table).
// --table-zoom on <html> multiplies each table's fitted size; the minus, Fit and plus buttons
// and a pinch on a table change it for every table, and the value is saved for later pages
// (layout.php restores it before paint). The pinch changes font size rather than scaling, so
// text stays sharp and cells reflow.
(function () {
  "use strict";

  var KEY = "table-zoom";
  var MIN = 0.5;
  var MAX = 6;
  var root = document.documentElement;

  function clamp(value) {
    return Math.min(MAX, Math.max(MIN, value));
  }

  function current() {
    var value = parseFloat(root.style.getPropertyValue("--table-zoom"));
    return value > 0 ? value : 1;
  }

  function apply(value) {
    root.style.setProperty("--table-zoom", String(Math.round(clamp(value) * 1000) / 1000));
  }

  function save() {
    try { window.localStorage.setItem(KEY, String(current())); } catch (error) {}
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest && event.target.closest("[data-table-zoom]");
    if (!button) { return; }
    var action = button.getAttribute("data-table-zoom");
    apply(action === "fit" ? 1 : current() * (action === "in" ? 1.25 : 0.8));
    save();
  });

  var pinch = null;
  var frame = 0;

  function distance(touches) {
    return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY) || 1;
  }

  // Keep the point between the fingers over the same part of the table while it resizes.
  function zoomAround(value) {
    var scroller = pinch.scroller;
    var table = pinch.table;
    var before = table.getBoundingClientRect();
    var box = scroller.getBoundingClientRect();
    var fx = (scroller.scrollLeft + pinch.x - box.left) / (scroller.scrollWidth || 1);
    var fy = (pinch.y - before.top) / (before.height || 1);
    apply(value);
    var after = table.getBoundingClientRect();
    scroller.scrollLeft = fx * scroller.scrollWidth - (pinch.x - box.left);
    window.scrollBy(0, after.top + fy * after.height - pinch.y);
  }

  document.addEventListener("touchstart", function (event) {
    if (event.touches.length !== 2) { return; }
    var scroller = event.target.closest && event.target.closest(".post-table .docs-table-scroll");
    var table = scroller && scroller.querySelector("table");
    if (!table || window.getComputedStyle(scroller).touchAction.indexOf("pan") === -1) { return; }
    pinch = {
      scroller: scroller,
      table: table,
      start: distance(event.touches),
      zoom: current(),
      x: (event.touches[0].clientX + event.touches[1].clientX) / 2,
      y: (event.touches[0].clientY + event.touches[1].clientY) / 2,
      next: current()
    };
  }, { passive: true });

  document.addEventListener("touchmove", function (event) {
    if (!pinch || event.touches.length !== 2) { return; }
    event.preventDefault();
    pinch.next = clamp(pinch.zoom * distance(event.touches) / pinch.start);
    if (frame) { return; }
    frame = window.requestAnimationFrame(function () {
      frame = 0;
      if (pinch) { zoomAround(pinch.next); }
    });
  }, { passive: false });

  function endPinch(event) {
    if (!pinch || event.touches.length >= 2) { return; }
    if (frame) { window.cancelAnimationFrame(frame); frame = 0; }
    zoomAround(pinch.next);
    pinch = null;
    save();
  }
  document.addEventListener("touchend", endPinch, { passive: true });
  document.addEventListener("touchcancel", endPinch, { passive: true });

  // iOS Safari zooms the page on its gesture events even under touch-action; stop that only
  // while a table pinch is in progress.
  document.addEventListener("gesturestart", function (event) { if (pinch) { event.preventDefault(); } });
  document.addEventListener("gesturechange", function (event) { if (pinch) { event.preventDefault(); } });
})();
