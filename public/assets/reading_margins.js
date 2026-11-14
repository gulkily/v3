(function () {
  // Reading margins: one row of evenly spaced radios above a page. The left
  // half sets where the text column starts and the right half where it ends.
  // Choosing a checked radio returns to the site's normal column. layout.php
  // restores the saved edges before paint and reading_margins.php checks the
  // matching radios; this script handles clicks.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;
  var sides = ["left", "right"];

  function stops(edge) {
    return Array.prototype.slice.call(ruler.querySelectorAll("[data-reading-margin-edge='" + edge + "']"));
  }

  function valid(edge, value) {
    return stops(edge).some(function (stop) { return stop.value === value; });
  }

  // The current edges as [left, right] strings, or null for the normal column.
  var edges = (function () {
    var saved = (root.getAttribute("data-reading-margins") || "").split(" ");
    return saved.length === 2 && valid("left", saved[0]) && valid("right", saved[1]) ? saved : null;
  })();

  // The stop on `edge` nearest where the normal column's edge is now.
  function nearestStop(edge) {
    var main = document.querySelector(".main");
    var width = root.clientWidth || window.innerWidth;
    var rect = main ? main.getBoundingClientRect() : null;
    var current = rect && width ? ((edge === "left" ? rect.left : rect.right) / width) * 100 : 50;
    return stops(edge).reduce(function (best, stop) {
      return !best || Math.abs(stop.value - current) < Math.abs(best.value - current) ? stop : best;
    }, null).value;
  }

  function apply(next) {
    edges = next;
    sides.forEach(function (edge, index) {
      stops(edge).forEach(function (stop) {
        stop.checked = !!edges && stop.value === edges[index];
      });
    });
    if (edges) {
      root.setAttribute("data-reading-margins", edges.join(" "));
      root.style.setProperty("--reading-left", edges[0]);
      root.style.setProperty("--reading-right", edges[1]);
    } else {
      root.removeAttribute("data-reading-margins");
      root.style.removeProperty("--reading-left");
      root.style.removeProperty("--reading-right");
    }
    try {
      if (!storageKey) return;
      if (edges) {
        localStorage.setItem(storageKey, edges.join(" "));
      } else {
        localStorage.removeItem(storageKey);
      }
    } catch (error) {
    }
  }

  // Radios check themselves before "click" fires; apply() then sets every
  // radio from the chosen edges.
  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-edge]");
    if (!stop) return;
    var index = sides.indexOf(stop.getAttribute("data-reading-margin-edge"));
    if (edges && edges[index] === stop.value) {
      apply(null);
      return;
    }
    var next = edges ? edges.slice() : [nearestStop("left"), nearestStop("right")];
    next[index] = stop.value;
    apply(next);
  });
})();
