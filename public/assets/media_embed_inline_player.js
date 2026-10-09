(function () {
  document.addEventListener(
    "toggle",
    (event) => {
      const details = event.target;
      if (!(details instanceof HTMLDetailsElement) || !details.open) {
        return;
      }

      const iframe = details.querySelector("iframe[data-embed-src]");
      if (iframe && !iframe.getAttribute("src")) {
        iframe.setAttribute("src", iframe.getAttribute("data-embed-src"));
      }
    },
    true,
  );
})();
