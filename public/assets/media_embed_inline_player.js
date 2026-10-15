(function () {
  const pending = new WeakSet();

  function themedBandcampSource(details, source) {
    if (details.dataset.provider !== "bandcamp") return source;
    // Resolve CSS variables through the active stylesheet, including alpha panels.
    const probe = document.createElement("span");
    probe.style.cssText = "position:absolute;visibility:hidden;pointer-events:none";
    details.appendChild(probe);
    const canvas = document.createElement("canvas");
    canvas.width = canvas.height = 1;
    const context = canvas.getContext("2d");
    if (!context) { probe.remove(); return source; }
    const paint = (color) => { context.fillStyle = color; context.fillRect(0, 0, 1, 1); };
    const pixels = () => Array.from(context.getImageData(0, 0, 1, 1).data).slice(0, 3);
    const variable = (name) => {
      probe.style.color = "var(" + name + ")";
      return getComputedStyle(probe).color;
    };
    paint("#ffffff");
    paint(variable("--page-bg"));
    paint(variable("--panel"));
    const panel = pixels();
    const link = details.previousElementSibling?.querySelector("a");
    const linkColor = link ? getComputedStyle(link).color : variable("--ink");
    const luminance = (rgb) => rgb.map(value => {
      const channel = value / 255;
      return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    }).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
    // Bandcamp supports standard light/dark surfaces, plus arbitrary link colors.
    const background = luminance(panel) < 0.179 ? [51, 51, 51] : [255, 255, 255];
    paint("rgb(" + background.join(",") + ")");
    paint(linkColor);
    let foreground = pixels();
    const contrast = (first, second) => {
      const a = luminance(first), b = luminance(second);
      return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    };
    if (contrast(background, foreground) < 4.5) {
      foreground = contrast(background, [0, 0, 0]) > contrast(background, [255, 255, 255])
        ? [0, 0, 0] : [255, 255, 255];
    }
    probe.remove();
    const hex = rgb => rgb.map(value => value.toString(16).padStart(2, "0")).join("");
    return source.replace(/\/bgcol=[0-9a-f]{6}\/linkcol=[0-9a-f]{6}\//i,
      "/bgcol=" + hex(background) + "/linkcol=" + hex(foreground) + "/");
  }
  document.addEventListener(
    "toggle",
    async (event) => {
      const details = event.target;
      if (!(details instanceof HTMLDetailsElement) || !details.open) {
        return;
      }
      const iframe = details.querySelector("iframe[data-embed-src]");
      if (!iframe || (iframe.getAttribute("src") && details.dataset.provider !== "bandcamp")) return;
      const load = () => {
        if (details.open) {
          const source = themedBandcampSource(details, iframe.getAttribute("data-embed-src"));
          // Recolor only when expanded; never reload an open player on theme changes.
          if (iframe.getAttribute("src") !== source) iframe.setAttribute("src", source);
          iframe.hidden = false;
        }
      };
      if (iframe.getAttribute("data-embed-src")) { load(); return; }
      const endpoint = iframe.getAttribute("data-resolve-src");
      if (!endpoint || pending.has(details)) return;
      const status = details.querySelector("[data-media-embed-status]");
      pending.add(details);
      status.textContent = "Loading player…";
      try {
        const response = await fetch(endpoint, { credentials: "same-origin", signal: AbortSignal.timeout(8000) });
        const data = await response.json();
        if (!response.ok || data.status !== "ready" || typeof data.embedSrc !== "string" ||
            !/^https:\/\/bandcamp\.com\/EmbeddedPlayer\/(?:album|track)=[1-9][0-9]{0,19}\/size=large\/bgcol=ffffff\/linkcol=0687f5\/tracklist=(?:true|false)\/artwork=small\/transparent=true\/$/.test(data.embedSrc)) {
          throw new Error("Unavailable player");
        }
        iframe.setAttribute("data-embed-src", data.embedSrc);
        status.textContent = "";
        load();
      } catch (_) {
        status.textContent = "Player unavailable. Use the source link.";
      } finally {
        pending.delete(details);
      }
    },
    true,
  );
})();
