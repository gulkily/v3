(function () {
  const pending = new WeakSet();
  document.addEventListener(
    "toggle",
    async (event) => {
      const details = event.target;
      if (!(details instanceof HTMLDetailsElement) || !details.open) {
        return;
      }
      const iframe = details.querySelector("iframe[data-embed-src]");
      if (!iframe || iframe.getAttribute("src")) return;
      const load = () => {
        if (details.open) {
          iframe.setAttribute("src", iframe.getAttribute("data-embed-src"));
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
