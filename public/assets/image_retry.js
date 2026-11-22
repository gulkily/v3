(function () {
  "use strict";
  // An image whose request failed gets one more try after a short wait, so a
  // dropped request does not leave an empty box until the reader reloads.
  // Once per image; an image that loads is never touched.
  var DELAY_MS = 2000;

  function failed(img) {
    return img.complete && img.naturalWidth === 0 && !!(img.getAttribute("src") || img.getAttribute("srcset"));
  }

  function schedule(img) {
    if (!img || img.tagName !== "IMG" || img.dataset.imageRetried) return;
    var src = img.getAttribute("src") || "";
    if (src.indexOf("data:") === 0 || src.indexOf("blob:") === 0) return;
    img.dataset.imageRetried = "1";
    window.setTimeout(function () {
      if (!img.isConnected || !failed(img)) return;
      // Taking the addresses off and putting them back makes the browser ask again.
      var srcset = img.getAttribute("srcset");
      img.removeAttribute("srcset");
      img.removeAttribute("src");
      if (srcset !== null) img.setAttribute("srcset", srcset);
      if (src !== "") img.setAttribute("src", src);
    }, DELAY_MS);
  }

  // Error events from images don't bubble, so listen while they travel down.
  document.addEventListener("error", function (event) { schedule(event.target); }, true);

  // Images that failed before this script ran.
  function sweep() {
    Array.prototype.forEach.call(document.images, function (img) { if (failed(img)) schedule(img); });
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", sweep);
  else sweep();
})();
