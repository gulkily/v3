(function () {
  // Reading margins: one row of evenly spaced radios above a page. The text
  // column spans the last two radios clicked. Each click on an unchecked radio
  // moves the column at once by replacing the edge clicked longer ago, which
  // is outlined so the reader can see which edge moves next. Clicking a
  // checked radio returns to the site's normal column. layout.php restores the
  // saved pair before paint and reading_margins.php checks and outlines the
  // radios; this script handles clicks.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;
  var stops = Array.prototype.slice.call(ruler.querySelectorAll("[data-reading-margin-stop]"));
  var positions = stops.map(function (stop) { return stop.value; });

  // The column's edges in click order, [older, newer], or null for the normal column.
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

  function apply(next) {
    order = next;
    stops.forEach(function (stop) {
      stop.checked = !!order && order.indexOf(stop.value) !== -1;
      stop.toggleAttribute("data-reading-margin-next", !!order && stop.value === order[0]);
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

  // Radios check themselves before "click" fires; apply() then sets every
  // radio and the outline from the state here.
  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-stop]");
    if (!stop) return;
    var position = stop.value;
    if (order && order.indexOf(position) !== -1) {
      apply(null);
    } else if (order) {
      apply([order[1], position]);
    } else {
      var other = oppositeStop(position);
      apply(other === null ? null : [other, position]);
    }
  });
})();
