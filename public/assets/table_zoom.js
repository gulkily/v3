// Text size for zoom tables on phones (ZoomTable.php, site.css .zoom-table, --table-min):
// post tables and Tools tables. Each kind of table has its own size: --table-zoom-<kind> on
// <html> multiplies that kind's fitted size, and Actual size instead sets
// data-table-size-<kind>="page" on <html>, which puts that kind at the page's own text size.
// The Smaller, Fit, Actual size and Larger buttons and a pinch on a table change the size for
// every table of its kind, and it is saved for later pages (layout.php restores it before
// paint, with the same keys). The pinch changes font size rather than scaling, so text stays
// sharp and cells reflow.
(function () {
  "use strict";

  // Kind => localStorage key; matches ZoomTable::KINDS and layout.php.
  var KEYS = { post: "table-zoom", tools: "table-zoom-tools" };
  var MIN = 0.5;
  var MAX = 8;
  var root = document.documentElement;

  function clamp(value) {
    return Math.min(MAX, Math.max(MIN, value));
  }

  function kindOf(element) {
    var wrapper = element && element.closest && element.closest(".zoom-table");
    var kind = wrapper && wrapper.getAttribute("data-zoom-kind");
    return kind && Object.prototype.hasOwnProperty.call(KEYS, kind) ? kind : null;
  }

  function pageSize(kind) {
    return root.getAttribute("data-table-size-" + kind) === "page";
  }

  function current(kind) {
    var value = parseFloat(root.style.getPropertyValue("--table-zoom-" + kind));
    return value > 0 ? value : 1;
  }

  // The zoom factor that would show this table at its current size. At Actual size that is
  // page text over the table's fitted size (measured with Actual size briefly off), so
  // Smaller, Larger and pinch carry on from the size on screen.
  function factorFor(kind, table) {
    if (!pageSize(kind) || !table) { return current(kind); }
    var saved = root.style.getPropertyValue("--table-zoom-" + kind);
    root.removeAttribute("data-table-size-" + kind);
    root.style.setProperty("--table-zoom-" + kind, "1");
    var fitPx = parseFloat(window.getComputedStyle(table).fontSize);
    root.setAttribute("data-table-size-" + kind, "page");
    if (saved) { root.style.setProperty("--table-zoom-" + kind, saved); } else { root.style.removeProperty("--table-zoom-" + kind); }
    var pagePx = parseFloat(window.getComputedStyle(table.closest(".zoom-table")).fontSize);
    return fitPx > 0 ? pagePx / fitPx : 1;
  }

  function apply(kind, value) {
    root.removeAttribute("data-table-size-" + kind);
    root.style.setProperty("--table-zoom-" + kind, String(Math.round(clamp(value) * 1000) / 1000));
  }

  function save(kind) {
    try { window.localStorage.setItem(KEYS[kind], pageSize(kind) ? "page" : String(current(kind))); } catch (error) {}
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest && event.target.closest("[data-table-zoom]");
    var kind = kindOf(button);
    if (!kind) { return; }
    var action = button.getAttribute("data-table-zoom");
    if (action === "page") {
      root.setAttribute("data-table-size-" + kind, "page");
    } else if (action === "fit") {
      apply(kind, 1);
    } else {
      var table = button.closest(".zoom-table").querySelector(".zoom-table-scroll table");
      apply(kind, factorFor(kind, table) * (action === "in" ? 1.25 : 0.8));
    }
    save(kind);
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
    apply(pinch.kind, value);
    var after = table.getBoundingClientRect();
    scroller.scrollLeft = fx * scroller.scrollWidth - (pinch.x - box.left);
    window.scrollBy(0, after.top + fy * after.height - pinch.y);
  }

  document.addEventListener("touchstart", function (event) {
    if (event.touches.length !== 2) { return; }
    var scroller = event.target.closest && event.target.closest(".zoom-table-scroll");
    var table = scroller && scroller.querySelector("table");
    var kind = kindOf(scroller);
    if (!table || !kind || window.getComputedStyle(scroller).touchAction.indexOf("pan") === -1) { return; }
    var start = factorFor(kind, table);
    pinch = {
      kind: kind,
      scroller: scroller,
      table: table,
      start: distance(event.touches),
      zoom: start,
      x: (event.touches[0].clientX + event.touches[1].clientX) / 2,
      y: (event.touches[0].clientY + event.touches[1].clientY) / 2,
      next: start
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
    save(pinch.kind);
    pinch = null;
  }
  document.addEventListener("touchend", endPinch, { passive: true });
  document.addEventListener("touchcancel", endPinch, { passive: true });

  // iOS Safari zooms the page on its gesture events even under touch-action; stop that only
  // while a table pinch is in progress.
  document.addEventListener("gesturestart", function (event) { if (pinch) { event.preventDefault(); } });
  document.addEventListener("gesturechange", function (event) { if (pinch) { event.preventDefault(); } });
})();
