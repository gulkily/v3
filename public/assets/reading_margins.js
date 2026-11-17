(function () {
  // Reading margins: one row of evenly spaced radios above a page; two checked
  // radios are the text column's edges. Clicking an unchecked radio moves the
  // nearer edge to it at once, so a click outside the column moves the edge on
  // that side and the edges never cross. A click exactly midway between the
  // edges moves the one clicked longer ago; when such a midway stop exists,
  // that edge is outlined. Clicking a checked radio returns to the site's
  // normal column. layout.php restores the saved pair before paint and
  // reading_margins.php checks and outlines the radios; this script handles
  // clicks.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;
  var stops = Array.prototype.slice.call(ruler.querySelectorAll("[data-reading-margin-stop]"));
  var positions = stops.map(function (stop) { return stop.value; });

  // The column's edges in click order, [older, newer], or null for the normal
  // column. Click order only decides ties.
  var order = (function () {
    var saved = (root.getAttribute("data-reading-margins") || "").split(" ");
    return saved.length === 2 && saved[0] !== saved[1]
      && positions.indexOf(saved[0]) !== -1 && positions.indexOf(saved[1]) !== -1 ? saved : null;
  })();

  // The stop nearest the normal column's edge on the side away from `position`.
  function oppositeStop(position) {
    var main = document.querySelector(".main");
    var width = root.clientWidth || window.innerWidth;
    var rect = main ? main.getBoundingClientRect() : null;
    var left = rect && width ? (rect.left / width) * 100 : 25;
    var right = rect && width ? (rect.right / width) * 100 : 75;
    var clickedIsLeft = Number(position) < (left + right) / 2;
    var target = clickedIsLeft ? right : left;
    return positions.filter(function (each) {
      return clickedIsLeft ? Number(each) > Number(position) : Number(each) < Number(position);
    }).reduce(function (best, each) {
      return best === null || Math.abs(each - target) < Math.abs(best - target) ? each : best;
    }, null);
  }

  function index(position) {
    return positions.indexOf(position);
  }

  // A tie is possible only when a stop sits exactly midway between the edges.
  function tiePossible(pair) {
    return (index(pair[1]) - index(pair[0])) % 2 === 0;
  }

  function apply(next) {
    order = next;
    var outlined = order && tiePossible(order) ? order[0] : null;
    stops.forEach(function (stop) {
      stop.checked = !!order && order.indexOf(stop.value) !== -1;
      stop.toggleAttribute("data-reading-margin-next", stop.value === outlined);
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
  window.forumResetReadingMargins = function () { apply(null); };

  // Radios check themselves before "click" fires; apply() then sets every
  // radio and the outline from the state here.
  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-stop]");
    if (!stop) return;
    var position = stop.value;
    if (order && order.indexOf(position) !== -1) {
      apply(null);
    } else if (order) {
      var toOlder = Math.abs(index(position) - index(order[0]));
      var toNewer = Math.abs(index(position) - index(order[1]));
      var kept = toNewer < toOlder ? order[0] : order[1];
      apply([kept, position]);
    } else {
      var other = oppositeStop(position);
      apply(other === null ? null : [other, position]);
    }
  });
})();
