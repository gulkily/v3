(function () {
  const composeRoots = Array.from(document.querySelectorAll("[data-compose-root]"));
  if (composeRoots.length === 0) {
    return;
  }

  const intentSelector = 'textarea[name="body"], input[name="subject"]';
  let loadPromise = null;

  function scriptAlreadyPresent(path) {
    return Boolean(document.querySelector(`script[src*="${path}"]`));
  }

  function appendScript(path) {
    return new Promise(function (resolve, reject) {
      if (scriptAlreadyPresent(path)) {
        resolve();
        return;
      }

      const script = document.createElement("script");
      script.src = path;
      script.defer = true;
      script.onload = function () {
        resolve();
      };
      script.onerror = function () {
        reject(new Error(`Unable to load ${path}`));
      };
      document.head.appendChild(script);
    });
  }

  function initializeSigning() {
    if (window.ForumBrowserSigning && typeof window.ForumBrowserSigning.init === "function") {
      composeRoots.forEach(function (composeRoot) {
        window.ForumBrowserSigning.init(composeRoot);
      });
    }
  }

  function loadSigningAssets() {
    if (!loadPromise) {
      const assetPaths = window.__forumAssetPaths || {};
      const loaderPath = typeof assetPaths.openpgpLoader === "string" ? assetPaths.openpgpLoader : "";
      const signingPath = typeof assetPaths.browserSigning === "string" ? assetPaths.browserSigning : "";
      if (!loaderPath || !signingPath) {
        return Promise.reject(new Error("Browser signing asset URLs are missing from the page asset configuration."));
      }

      loadPromise = appendScript(loaderPath)
        .then(function () {
          return appendScript(signingPath);
        })
        .then(initializeSigning)
        .catch(function (error) {
          loadPromise = null;
          throw error;
        });
    }

    return loadPromise;
  }

  function handleIntent() {
    void loadSigningAssets();
  }

  composeRoots.forEach(function (composeRoot) {
    composeRoot.addEventListener("focusin", function (event) {
      if (event.target && event.target.matches && event.target.matches(intentSelector)) {
        handleIntent();
      }
    });
    composeRoot.addEventListener("pointerdown", function (event) {
      if (event.target && event.target.matches && event.target.matches(intentSelector)) {
        handleIntent();
      }
    });
    composeRoot.addEventListener("input", function (event) {
      if (event.target && event.target.matches && event.target.matches(intentSelector)) {
        handleIntent();
      }
    });
  });

  window.ForumLazyComposeSigning = {
    load: loadSigningAssets,
  };
})();
