(function () {
  const config = {
    v6Path: "/assets/openpgp.min.js",
    v5Path: "/assets/openpgp.v5.11.3.min.js",
  };

  function selectedBundle() {
    if (window.isSecureContext === false && config.v5Path) {
      return { version: "v5", path: config.v5Path };
    }

    return { version: "v6", path: config.v6Path };
  }

  function preloadScript(path) {
    const link = document.createElement("link");
    link.rel = "preload";
    link.as = "script";
    link.href = path;
    link.fetchPriority = "low";
    document.head.appendChild(link);
  }

  function loadScript(path) {
    return new Promise(function (resolve, reject) {
      const script = document.createElement("script");
      script.src = path;
      script.async = true;
      script.onload = function () {
        resolve();
      };
      script.onerror = function () {
        reject(new Error("Unable to load OpenPGP bundle: " + path));
      };
      document.head.appendChild(script);
    });
  }

  const bundle = selectedBundle();
  let ready = null;

  function load() {
    if (ready === null) {
      ready = loadScript(bundle.path).then(function () {
        if (!window.openpgp) {
          throw new Error("OpenPGP bundle loaded without exposing window.openpgp.");
        }

        return window.openpgp;
      });
      ready.catch(function () {});
    }

    return ready;
  }

  preloadScript(bundle.path);

  window.__forumOpenPgpLoader = {
    selectedVersion: bundle.version,
    selectedPath: bundle.path,
    load: load,
  };
})();
