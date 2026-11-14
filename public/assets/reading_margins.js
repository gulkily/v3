(function () {
  // Reading margins: one row of evenly spaced radios above a page. Click any
  // two radios and the text column goes between them, in either order. The
  // first click only marks its radio; the second sets the column, and the
  // click after that starts a new pair. Clicking the marked radio again
  // returns to the site's normal column. layout.php restores the saved edges
  // before paint and reading_margins.php checks the matching radios; this
  // script handles clicks.
  var runtime = window.forumBrowserRuntime || null;
  var ruler = document.querySelector("[data-role='reading-margins']");
  if (!ruler) {
    return;
  }

  var root = document.documentElement;
  var storageKey = runtime && runtime.readingMarginsStorageKey;
  var stops = Array.prototype.slice.call(ruler.querySelectorAll("[data-reading-margin-stop]"));
  var positions = stops.map(function (stop) { return stop.value; });

  // The column's edges as [left, right] strings, or null for the normal column.
  var edges = (function () {
    var saved = (root.getAttribute("data-reading-margins") || "").split(" ");
    return saved.length === 2 && positions.indexOf(saved[0]) !== -1 && positions.indexOf(saved[1]) !== -1 ? saved : null;
  })();
  // The first radio of a pair, waiting for the second click.
  var marked = null;

  function show(checkedValues) {
    stops.forEach(function (stop) {
      stop.checked = checkedValues.indexOf(stop.value) !== -1;
      stop.toggleAttribute("data-reading-margin-marked", stop.value === marked);
    });
  }

  function setColumn(next) {
    edges = next;
    marked = null;
    show(edges || []);
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

  // Radios check themselves before "click" fires; show() then sets every
  // radio from the state here.
  ruler.addEventListener("click", function (event) {
    var stop = event.target.closest && event.target.closest("[data-reading-margin-stop]");
    if (!stop) return;
    if (marked === null) {
      marked = stop.value;
      show([marked]);
    } else if (marked === stop.value) {
      setColumn(null);
    } else {
      var pair = [marked, stop.value].sort(function (a, b) { return a - b; });
      setColumn(pair);
    }
  });
})();
