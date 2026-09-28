(function () {
  "use strict";
  if (!navigator.serviceWorker) return;
  navigator.serviceWorker.getRegistration().then(function (registration) {
    if (registration && registration.scope === window.location.origin + "/") {
      return registration.unregister();
    }
  }).catch(function () {});
})();
