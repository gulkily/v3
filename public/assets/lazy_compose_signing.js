(function () {
  const composeRoots = Array.from(document.querySelectorAll("[data-compose-root]"));
  const intentSelector = 'textarea[name="body"], input[name="subject"]';
  const reactionSelector = "[data-thread-reactions-root], .post-card[data-post-id]";
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
      window.ForumBrowserSigning.init(document);
    }
  }

  function hasStoredBrowserKeypair() {
    try {
      return Boolean(
        window.localStorage
        && window.localStorage.getItem("forum_pki_public_key")
        && window.localStorage.getItem("forum_pki_private_key")
      );
    } catch (error) {
      return false;
    }
  }

  function pageHasReactionSurfaces() {
    return Boolean(document.querySelector(reactionSelector));
  }

  function requestIdle(callback) {
    if (typeof window.requestIdleCallback === "function") {
      window.requestIdleCallback(callback, { timeout: 2000 });
      return;
    }

    window.setTimeout(callback, 0);
  }

  function scheduleStoredReactionIdentityPrewarm() {
    if (!pageHasReactionSurfaces() || !hasStoredBrowserKeypair()) {
      return;
    }

    requestIdle(function () {
      void loadSigningAssets().catch(function () {});
    });
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

  scheduleStoredReactionIdentityPrewarm();
})();
