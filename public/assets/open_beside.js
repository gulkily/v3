(function () {
  // Open beside (FORUM_OPEN_BESIDE_ENABLED): with the mode on, a plain click on
  // a link in the page's content opens that page in one pane to the right of
  // the content column, when there is room for it. The pane is a frame named
  // forum-open-beside; layout.php hides the site header, ruler and status bar
  // in a page shown there. The mode is switched with the Beside toggle on the
  // reading-margins ruler (reading_margins.php); layout.php restores it before
  // paint as data-open-beside="on".
  var root = document.documentElement;
  // A page inside the pane never opens a pane of its own.
  if (root.hasAttribute("data-open-beside-framed")) {
    return;
  }

  var runtime = window.forumBrowserRuntime || {};
  var modeKey = runtime.openBesideStorageKey;
  var paneKey = runtime.openBesidePaneStorageKey;
  var FRAME_NAME = "forum-open-beside";
  var GUTTER = 16;
  var pane = null;
  var frame = null;

  function modeOn() {
    return root.getAttribute("data-open-beside") === "on";
  }

  function store(key, value) {
    try {
      if (!key) return;
      if (value === null) {
        localStorage.removeItem(key);
      } else {
        localStorage.setItem(key, value);
      }
    } catch (error) {
    }
  }

  // Where a pane fits: from the content column's right edge to the window's,
  // no wider than the site's normal page, and at least 20rem (the width every
  // page is built to fit). Returns null when there is no room.
  function room() {
    var main = document.querySelector(".main");
    var shell = document.querySelector(".shell");
    if (!main || !shell || !window.matchMedia("(min-width: 640px)").matches) return null;
    var left = main.getBoundingClientRect().right + GUTTER;
    var space = root.clientWidth - left - GUTTER;
    var smallest = 20 * parseFloat(getComputedStyle(root).fontSize);
    if (space < smallest) return null;
    var ruler = document.querySelector("[data-role='reading-margins']");
    var rulerBottom = ruler ? ruler.getBoundingClientRect().bottom : 0;
    return {
      left: Math.round(left),
      width: Math.round(Math.min(space, Math.max(smallest, shell.getBoundingClientRect().width))),
      // Below the ruler while it is on screen, so its stops and the toggle stay usable.
      top: Math.round(Math.max(GUTTER / 2, rulerBottom + 4)),
    };
  }

  function place() {
    if (!pane) return;
    var fit = room();
    pane.hidden = fit === null;
    if (fit === null) return;
    pane.style.left = fit.left + "px";
    pane.style.width = fit.width + "px";
    pane.style.top = fit.top + "px";
  }

  var placing = false;
  function placeSoon() {
    if (placing || !pane) return;
    placing = true;
    requestAnimationFrame(function () {
      placing = false;
      place();
    });
  }

  function build() {
    pane = document.createElement("aside");
    pane.className = "open-beside";
    pane.setAttribute("data-open-beside-pane", "");
    pane.setAttribute("aria-label", "Beside");
    frame = document.createElement("iframe");
    frame.className = "open-beside__frame";
    frame.name = FRAME_NAME;
    frame.title = "Page opened beside";
    pane.appendChild(frame);
    document.body.appendChild(pane);
    place();
  }

  function open(url) {
    if (room() === null) return false;
    if (!pane) build();
    frame.src = url;
    store(paneKey, url);
    place();
    return true;
  }

  function close() {
    if (pane) pane.remove();
    pane = null;
    frame = null;
    store(paneKey, null);
  }

  // A link the pane may take: a page of this site, reached by a plain left
  // click, that asks for nothing else (a new tab, a download, a spot on this
  // page).
  function paneAddress(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return null;
    var link = event.target && event.target.closest ? event.target.closest("a[href]") : null;
    if (!link || !link.closest(".main") || link.closest("[data-open-beside-pane]")) return null;
    if (link.hasAttribute("download")) return null;
    var target = link.getAttribute("target");
    if (target && target !== "_self") return null;
    var url;
    try {
      url = new URL(link.href, location.href);
    } catch (error) {
      return null;
    }
    if (url.origin !== location.origin || (url.protocol !== "http:" && url.protocol !== "https:")) return null;
    if (url.pathname === location.pathname && url.search === location.search && url.hash !== "") return null;
    return url.pathname + url.search + url.hash;
  }

  document.addEventListener("click", function (event) {
    if (!modeOn()) return;
    var address = paneAddress(event);
    if (address === null || !open(address)) return;
    event.preventDefault();
  });

  function setMode(on) {
    if (on) {
      root.setAttribute("data-open-beside", "on");
    } else {
      root.removeAttribute("data-open-beside");
      close();
    }
    store(modeKey, on ? "on" : null);
    Array.prototype.forEach.call(document.querySelectorAll("[data-open-beside-toggle]"), function (toggle) {
      toggle.setAttribute("aria-pressed", on ? "true" : "false");
    });
  }

  document.addEventListener("click", function (event) {
    var toggle = event.target && event.target.closest ? event.target.closest("[data-open-beside-toggle]") : null;
    if (toggle) setMode(!modeOn());
  });

  window.addEventListener("resize", placeSoon);
  window.addEventListener("scroll", placeSoon, { passive: true });
  if (window.ResizeObserver) {
    var main = document.querySelector(".main");
    if (main) new ResizeObserver(placeSoon).observe(main);
  }
})();
