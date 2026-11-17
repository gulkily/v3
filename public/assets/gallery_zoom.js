// Gallery tile size (gallery.php, site.css .gallery-grid). --gallery-zoom on <html> multiplies
// the row height (in Word97 and Midnight it sets how many columns the figure table has).
// Smaller and Larger step it, and on phones a two-finger pinch on the grid changes it live.
// It is saved for later visits; layout.php restores it before paint with the same key and
// range. Built like table_zoom.js, so both kinds of zoom feel the same.
(function () {
  "use strict";

  var KEY = "gallery-zoom";
  var MIN = 0.5;
  var MAX = 2;
  // Below this the tiles show images only (captions visually hidden); layout.php uses the same value.
  var COMPACT = 0.7;
  var root = document.documentElement;

  function clamp(value) {
    return Math.min(MAX, Math.max(MIN, value));
  }

  function current() {
    var value = parseFloat(root.style.getPropertyValue("--gallery-zoom"));
    return value > 0 ? value : 1;
  }

  function apply(value) {
    value = Math.round(clamp(value) * 1000) / 1000;
    if (value === 1) {
      root.style.removeProperty("--gallery-zoom");
    } else {
      root.style.setProperty("--gallery-zoom", String(value));
    }
    root.toggleAttribute("data-gallery-compact", value < COMPACT);
  }

  function save() {
    try {
      var value = current();
      if (value === 1) {
        localStorage.removeItem(KEY);
      } else {
        localStorage.setItem(KEY, String(value));
      }
    } catch (error) {}
  }

  // Pinch: keep the point between the fingers over the same part of the grid.
  function zoomAround(value, grid, y) {
    var before = grid.getBoundingClientRect();
    var fy = (y - before.top) / (before.height || 1);
    apply(value);
    var after = grid.getBoundingClientRect();
    window.scrollBy(0, after.top + fy * after.height - y);
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest && event.target.closest("[data-gallery-zoom]");
    var grid = document.querySelector(".gallery-grid");
    if (!button || !grid) { return; }
    var step = button.getAttribute("data-gallery-zoom") === "in" ? 1.25 : 0.8;
    // Keep the tapped button where it is, so repeated taps don't chase it off screen.
    var before = button.getBoundingClientRect().top;
    apply(current() * step);
    window.scrollBy(0, button.getBoundingClientRect().top - before);
    save();
  });

  var pinch = null;
  var frame = 0;

  function distance(touches) {
    return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY) || 1;
  }

  document.addEventListener("touchstart", function (event) {
    if (event.touches.length !== 2) { return; }
    var grid = event.target.closest && event.target.closest(".gallery-grid");
    // Only where site.css hands pinching to the page (phones); elsewhere the browser zooms.
    if (!grid || window.getComputedStyle(grid).touchAction.indexOf("pan") === -1) { return; }
    pinch = {
      grid: grid,
      start: distance(event.touches),
      zoom: current(),
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
      if (pinch) { zoomAround(pinch.next, pinch.grid, pinch.y); }
    });
  }, { passive: false });

  function endPinch(event) {
    if (!pinch || event.touches.length >= 2) { return; }
    if (frame) { window.cancelAnimationFrame(frame); frame = 0; }
    zoomAround(pinch.next, pinch.grid, pinch.y);
    save();
    pinch = null;
  }
  document.addEventListener("touchend", endPinch, { passive: true });
  document.addEventListener("touchcancel", endPinch, { passive: true });

  // iOS Safari zooms the page on its gesture events even under touch-action; stop that only
  // while a gallery pinch is in progress.
  document.addEventListener("gesturestart", function (event) { if (pinch) { event.preventDefault(); } });
  document.addEventListener("gesturechange", function (event) { if (pinch) { event.preventDefault(); } });
})();
