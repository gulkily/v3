(function () {
  const composeRoot = document.querySelector("[data-compose-root]");
  if (!composeRoot) {
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
      window.ForumBrowserSigning.init(composeRoot);
    }
  }

  function loadSigningAssets() {
    if (!loadPromise) {
      loadPromise = appendScript("/assets/openpgp_loader.js")
        .then(function () {
          return appendScript("/assets/browser_signing.js");
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

  window.ForumLazyComposeSigning = {
    load: loadSigningAssets,
  };
})();
