(function () {
  const config = {
    v6Path: "/assets/openpgp.min.js",
    v5Path: "",
  };

  function selectedBundle() {
    if (window.isSecureContext === false && config.v5Path) {
      return { version: "v5", path: config.v5Path };
    }

    return { version: "v6", path: config.v6Path };
  }

  function loadScript(path) {
    return new Promise(function (resolve, reject) {
      const script = document.createElement("script");
      script.src = path;
      script.async = false;
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
  const ready = loadScript(bundle.path).then(function () {
    if (!window.openpgp) {
      throw new Error("OpenPGP bundle loaded without exposing window.openpgp.");
    }

    return window.openpgp;
  });

  ready.catch(function () {});

  window.__forumOpenPgpLoader = {
    selectedVersion: bundle.version,
    selectedPath: bundle.path,
    ready: ready,
  };
})();
