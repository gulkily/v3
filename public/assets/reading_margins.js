(function () {
  // Reading margins: one row of evenly spaced radios above a page; the two
  // checked radios are the text column's edges. Stops count fifths of the
  // site's normal column from its left edge, so 0 and 5 (the home stops) are
  // the normal column, checked until the reader chooses another. Clicking an
  // unchecked radio moves the nearer edge to it at once, so a click outside the
  // column moves the edge on that side and the edges never cross. A click
  // exactly midway between the edges moves the one clicked longer ago; when
  // such a midway stop exists, that edge is ringed. Clicking a checked radio
  // returns to the normal column. layout.php restores the saved pair before
  // paint and reading_margins.php fits the stops to the window, checks the
  // radios and draws the ring; this script handles clicks and refits the stops
  // when the window or the theme changes.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;
  var stops = Array.prototype.slice.call(ruler.querySelectorAll("[data-reading-margin-stop]"));
  var positions = stops.map(function (stop) { return stop.value; });
  var home = ["0", "5"];

  // A chosen column: two different stops that are not the normal column.
  function chosen(pair) {
    return pair.length === 2 && pair[0] !== pair[1]
      && positions.indexOf(pair[0]) !== -1 && positions.indexOf(pair[1]) !== -1
      && !(home.indexOf(pair[0]) !== -1 && home.indexOf(pair[1]) !== -1) ? pair : null;
  }

  // The chosen column's edges in click order, [older, newer], or null for the
  // normal column. Click order only decides ties.
  var order = chosen((root.getAttribute("data-reading-margins") || "").split(" "));

  // A tie is possible only when a stop sits exactly midway between the edges.
  function tiePossible(pair) {
    return (pair[1] - pair[0]) % 2 === 0;
  }

  function apply(next, save) {
    order = next;
    var edges = order || home;
    var ringed = order && tiePossible(order) ? order[0] : null;
    stops.forEach(function (stop) {
      stop.checked = edges.indexOf(stop.value) !== -1;
      stop.toggleAttribute("data-reading-margin-next", stop.value === ringed);
    });
    if (order) {
      root.setAttribute("data-reading-margins", order.join(" "));
      root.style.setProperty("--reading-left", Math.min(order[0], order[1]));
      root.style.setProperty("--reading-right", Math.max(order[0], order[1]));
    } else {
      root.removeAttribute("data-reading-margins");
      root.style.removeProperty("--reading-left");
      root.style.removeProperty("--reading-right");
    }
    if (!save) return;
    try {
      if (!storageKey) return;
      if (order) {
        localStorage.setItem(storageKey, order.join(" "));
      } else {
        localStorage.removeItem(storageKey);
      }
    } catch (error) {
    }
  }

  // Preferences (preferences.js) returns to the normal column through here, so
  // this script's own state stays in step.
  window.forumResetReadingMargins = function () { apply(null, true); };

  // Radios check themselves before "click" fires; apply() then sets every
  // radio and the ring from the state here.
  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-stop]");
    if (!stop) return;
    var position = stop.value;
    var edges = order || home;
    if (edges.indexOf(position) !== -1) {
      apply(null, true);
      return;
    }
    var toOlder = Math.abs(position - edges[0]);
    var toNewer = Math.abs(position - edges[1]);
    var kept = toNewer < toOlder ? edges[0] : edges[1];
    apply(chosen([kept, position]), true);
  });

  // A resized window fits a different number of stops, and so does a theme
  // with another column width (the ruler is as wide as the normal column). The
  // saved column applies whenever both of its stops fit; otherwise this window
  // shows the normal column and the saved choice stays as it is. The observer
  // also runs once at the start, after the stylesheets the first fit may have
  // missed.
  var fitting = false;
  function fit() {
    if (fitting || typeof window.forumReadingMarginsFit !== "function") return;
    fitting = true;
    requestAnimationFrame(function () {
      fitting = false;
      var fits = window.forumReadingMarginsFit();
      var saved = null;
      try {
        saved = storageKey ? chosen((localStorage.getItem(storageKey) || "").split(" ")) : null;
      } catch (error) {
      }
      apply(saved && fits[saved[0]] && fits[saved[1]] ? saved : null, false);
    });
  }
  window.addEventListener("resize", fit);
  if (window.ResizeObserver) {
    new ResizeObserver(fit).observe(ruler);
  }
})();
