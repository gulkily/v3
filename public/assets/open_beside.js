(function () {
  // Open beside (FORUM_OPEN_BESIDE_ENABLED): with the mode on, a plain click on
  // a link in the page's content opens that page in one pane to the right of
  // the content column, when there is room for it. The pane is a frame named
  // forum-open-beside under a bar with the page's title, Open alone and Close;
  // layout.php hides the site header, ruler and status bar in a page shown
  // there. Focus stays on the clicked link; the opening is announced. The mode is switched with the Beside toggle on the
  // reading-margins ruler (reading_margins.php); layout.php restores it before
  // paint as data-open-beside="on".
  var root = document.documentElement;
  // A page inside the pane never opens a pane of its own. Its links load in
  // the pane, except links to other sites, which take the whole tab: most
  // sites refuse to be shown in a frame.
  if (root.hasAttribute("data-open-beside-framed")) {
    window.addEventListener("click", function (event) {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      var link = event.target && event.target.closest ? event.target.closest("a[href]") : null;
      if (!link || link.hasAttribute("download") || link.getAttribute("target")) return;
      var url;
      try {
        url = new URL(link.href, location.href);
      } catch (error) {
        return;
      }
      if (url.origin === location.origin || (url.protocol !== "http:" && url.protocol !== "https:")) return;
      event.preventDefault();
      window.top.location.href = url.href;
    });
    // A click on a field gives it focus even when the click also had to move
    // focus into this frame. Firefox under test automation stops at the frame
    // when something in the main page was focused, and the main page's
    // clicked link always is.
    window.addEventListener("click", function (event) {
      var field = event.target && event.target.closest ? event.target.closest("textarea, input, select, [contenteditable='true']") : null;
      if (field && !field.disabled && document.activeElement !== field && typeof field.focus === "function") {
        field.focus();
      }
    });
    return;
  }

  var runtime = window.forumBrowserRuntime || {};
  var modeKey = runtime.openBesideStorageKey;
  var paneKey = runtime.openBesidePaneStorageKey;
  var FRAME_NAME = "forum-open-beside";
  var GUTTER = 16;
  var pane = null;
  var frame = null;
  var title = null;
  var alone = null;
  var status = null;
  // Set when a click opens a page, so the next load is announced once.
  var announceNext = false;

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
    // The bar: the page's title, Open alone (the pane's page becomes the main
    // page) and Close.
    var bar = document.createElement("div");
    bar.className = "open-beside__bar";
    title = document.createElement("span");
    title.className = "open-beside__title";
    alone = document.createElement("a");
    alone.className = "open-beside__action";
    alone.setAttribute("data-open-beside-alone", "");
    alone.textContent = "Open alone";
    alone.addEventListener("click", function () {
      store(paneKey, null);
    });
    var closer = document.createElement("button");
    closer.type = "button";
    closer.className = "open-beside__action";
    closer.setAttribute("data-open-beside-close", "");
    closer.textContent = "Close";
    closer.addEventListener("click", close);
    bar.appendChild(title);
    bar.appendChild(alone);
    bar.appendChild(closer);
    frame = document.createElement("iframe");
    frame.className = "open-beside__frame";
    frame.name = FRAME_NAME;
    frame.title = "Page opened beside";
    frame.addEventListener("load", loaded);
    pane.appendChild(bar);
    pane.appendChild(frame);
    document.body.appendChild(pane);
    if (!status) {
      status = document.createElement("p");
      status.className = "visually-hidden";
      status.setAttribute("role", "status");
      status.setAttribute("data-open-beside-status", "");
      document.body.appendChild(status);
    }
    place();
  }

  // The pane's page changed: by a click here, a link followed inside the pane,
  // or a form posted there. The bar and the saved address follow it.
  function loaded() {
    if (!frame) return;
    var address = null;
    var name = "";
    try {
      var at = frame.contentWindow.location;
      if (at.href === "about:blank") return;
      address = at.pathname + at.search + at.hash;
      name = frame.contentDocument.title || "";
    } catch (error) {
      // Another site's page: nothing of it can be read.
    }
    title.textContent = name || address || "";
    frame.title = name ? "Beside: " + name : "Page opened beside";
    if (address !== null) {
      alone.href = address;
      store(paneKey, address);
    }
    if (announceNext) {
      announceNext = false;
      status.textContent = "Opened beside: " + (name || address || "page");
    }
  }

  function open(url) {
    if (room() === null) return false;
    if (!pane) build();
    announceNext = true;
    title.textContent = "";
    alone.href = url;
    frame.src = url;
    store(paneKey, url);
    place();
    return true;
  }

  function close() {
    if (pane) pane.remove();
    pane = null;
    frame = null;
    title = null;
    alone = null;
    if (status) status.textContent = "";
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

  // On window, so every page script listening on the document has had its say
  // first: a link another script already handles (an inline reply form, say)
  // arrives here with its default prevented and is left alone.
  window.addEventListener("click", function (event) {
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
