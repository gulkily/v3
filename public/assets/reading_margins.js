(function () {
  // Reading margins: the radio row above a thread sets the text column's edges.
  // layout.php restores the saved edges before paint and reading_margins.php
  // checks the matching radios; this script handles changes. Choosing a radio
  // that is already checked returns to the site's normal column.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;

  function stops(edge) {
    return Array.prototype.slice.call(
      ruler.querySelectorAll("[data-reading-margin-edge='" + edge + "']")
    );
  }

  function checkedValue(edge) {
    var checked = stops(edge).filter(function (stop) { return stop.checked; })[0];
    return checked ? checked.value : null;
  }

  // The stop nearest where the column edge is now, for the edge not yet chosen.
  function nearestStop(edge) {
    var main = document.querySelector(".main");
    var rect = main ? main.getBoundingClientRect() : null;
    var width = root.clientWidth || window.innerWidth;
    var current = rect && width ? ((edge === "left" ? rect.left : rect.right) / width) * 100 : 50;
    return stops(edge).reduce(function (best, stop) {
      return !best || Math.abs(stop.value - current) < Math.abs(best.value - current) ? stop : best;
    }, null);
  }

  function apply(left, right) {
    if (left && right) {
      root.setAttribute("data-reading-margins", left + " " + right);
      root.style.setProperty("--reading-left", left);
      root.style.setProperty("--reading-right", right);
    } else {
      root.removeAttribute("data-reading-margins");
      root.style.removeProperty("--reading-left");
      root.style.removeProperty("--reading-right");
    }
    try {
      if (!storageKey) return;
      if (left && right) {
        localStorage.setItem(storageKey, left + " " + right);
      } else {
        localStorage.removeItem(storageKey);
      }
    } catch (error) {
    }
  }

  // A radio that was checked before the click is being chosen again: reset.
  var wasChecked = null;
  function remember(event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-edge]");
    wasChecked = stop && stop.checked ? stop : null;
  }
  ruler.addEventListener("pointerdown", remember);
  ruler.addEventListener("keydown", function (event) {
    if (event.key === " " || event.key === "Spacebar") remember(event);
  });

  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-edge]");
    if (!stop || stop !== wasChecked) {
      wasChecked = null;
      return;
    }
    wasChecked = null;
    ruler.querySelectorAll("[data-reading-margin-edge]").forEach(function (each) {
      each.checked = false;
    });
    apply(null, null);
  });

  ruler.addEventListener("change", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-edge]");
    if (!stop) return;
    var otherEdge = stop.getAttribute("data-reading-margin-edge") === "left" ? "right" : "left";
    if (!checkedValue(otherEdge)) {
      var nearest = nearestStop(otherEdge);
      if (nearest) nearest.checked = true;
    }
    apply(checkedValue("left"), checkedValue("right"));
  });
})();
