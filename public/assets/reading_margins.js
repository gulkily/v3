(function () {
  // Reading margins: one row of evenly spaced radios above a thread. The two
  // checked radios are the text column's edges. Choosing another radio moves
  // the nearer edge to it; choosing a checked radio returns to the site's
  // normal column. layout.php restores the saved edges before paint and
  // reading_margins.php checks the matching radios; this script handles clicks.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;
  var stops = Array.prototype.slice.call(ruler.querySelectorAll("[data-reading-margin-stop]"));
  var positions = stops.map(function (stop) { return Number(stop.value); });

  // The current edges as [left, right] in vw, or null for the normal column.
  var edges = (function () {
    var saved = (root.getAttribute("data-reading-margins") || "").split(" ").map(Number);
    return saved.length === 2 && positions.indexOf(saved[0]) !== -1 && positions.indexOf(saved[1]) !== -1 ? saved : null;
  })();

  // Where the normal column's edges are now, in vw.
  function columnEdges() {
    var main = document.querySelector(".main");
    var width = root.clientWidth || window.innerWidth;
    if (!main || !width) return [25, 75];
    var rect = main.getBoundingClientRect();
    return [(rect.left / width) * 100, (rect.right / width) * 100];
  }

  function nearest(target, candidates) {
    return candidates.reduce(function (best, position) {
      return best === null || Math.abs(position - target) < Math.abs(best - target) ? position : best;
    }, null);
  }

  // The edges after choosing an unchecked stop at `position`.
  function nextEdges(position) {
    if (edges) {
      var left = edges[0];
      var right = edges[1];
      // Move whichever edge is nearer; a stop exactly halfway moves the left edge.
      if (Math.abs(position - left) <= Math.abs(position - right)) {
        left = position;
      } else {
        right = position;
      }
      return [Math.min(left, right), Math.max(left, right)];
    }
    // Nothing chosen yet: the stop becomes the edge on its side of the column,
    // and the other edge goes to the stop nearest the column's other side.
    var current = columnEdges();
    if (position < (current[0] + current[1]) / 2) {
      return [position, nearest(current[1], positions.filter(function (each) { return each > position; }))];
    }
    return [nearest(current[0], positions.filter(function (each) { return each < position; })), position];
  }

  function apply(next) {
    edges = next && next[0] !== null && next[1] !== null ? next : null;
    stops.forEach(function (stop) {
      stop.checked = !!edges && edges.indexOf(Number(stop.value)) !== -1;
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
  // radio from the chosen edges, so a radio left unchecked here stays unchecked.
  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-stop]");
    if (!stop) return;
    var position = Number(stop.value);
    apply(edges && edges.indexOf(position) !== -1 ? null : nextEdges(position));
  });
})();
