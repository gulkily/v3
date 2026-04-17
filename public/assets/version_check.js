(function () {
  var storage = null;
  try {
    storage = window.sessionStorage;
  } catch (error) {
    storage = null;
  }

  var pendingVersionStorageKey = 'forum_pending_app_version';
  var versionMeta = document.querySelector('meta[name="app-version"]');
  if (!versionMeta) {
    return;
  }

  var currentVersion = String(versionMeta.getAttribute('content') || '').trim();
  if (currentVersion === '') {
    return;
  }

  var endpointMeta = document.querySelector('meta[name="app-version-endpoint"]');
  var versionEndpoint = endpointMeta ? String(endpointMeta.getAttribute('content') || '').trim() : '/api/version';
  if (versionEndpoint === '' || typeof window.fetch !== 'function') {
    return;
  }

  var banner = document.querySelector('[data-role="app-version-banner"]');
  var reloadButton = banner ? banner.querySelector('[data-action="reload-for-new-version"]') : null;
  var nextVersion = null;
  var requestInFlight = false;

  function storedPendingVersion() {
    if (!storage) {
      return '';
    }

    try {
      return String(storage.getItem(pendingVersionStorageKey) || '').trim();
    } catch (error) {
      return '';
    }
  }

  function persistPendingVersion(version) {
    if (!storage) {
      return;
    }

    try {
      if (version === '') {
        storage.removeItem(pendingVersionStorageKey);
      } else {
        storage.setItem(pendingVersionStorageKey, version);
      }
    } catch (error) {
    }
  }

  function withVersionBypass(url, version) {
    var target = new URL(url, window.location.origin);
    target.searchParams.set('__v', version);
    return target.toString();
  }

  function showBanner(version) {
    nextVersion = version;
    persistPendingVersion(version);
    if (!banner) {
      return;
    }

    banner.hidden = false;
  }

  function reloadForNewVersion() {
    var version = nextVersion || currentVersion;
    persistPendingVersion(version);
    window.location.assign(withVersionBypass(window.location.href, version));
  }

  function ensurePendingVersionSatisfied() {
    var pendingVersion = storedPendingVersion();
    if (pendingVersion === '') {
      return false;
    }

    if (pendingVersion === currentVersion) {
      persistPendingVersion('');
      return false;
    }

    var currentUrl = new URL(window.location.href, window.location.origin);
    if (currentUrl.searchParams.get('__v') === pendingVersion) {
      showBanner(pendingVersion);
      return false;
    }

    window.location.replace(withVersionBypass(currentUrl.toString(), pendingVersion));
    return true;
  }

  if (ensurePendingVersionSatisfied()) {
    return;
  }

  function checkForNewVersion() {
    if (requestInFlight) {
      return;
    }

    requestInFlight = true;

    window.fetch(withVersionBypass(versionEndpoint, Date.now().toString()), {
      cache: 'no-store',
      headers: {
        Accept: 'text/plain'
      }
    }).then(function (response) {
      if (!response.ok) {
        return '';
      }

      return response.text();
    }).then(function (text) {
      var latestVersion = String(text || '').trim();
      if (latestVersion !== '' && latestVersion !== currentVersion) {
        showBanner(latestVersion);
      }
    }).catch(function () {
    }).finally(function () {
      requestInFlight = false;
    });
  }

  if (reloadButton) {
    reloadButton.addEventListener('click', reloadForNewVersion);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      checkForNewVersion();
    }
  });

  window.addEventListener('pageshow', function () {
    ensurePendingVersionSatisfied();
  });

  window.setTimeout(checkForNewVersion, 15000);
  window.setInterval(checkForNewVersion, 120000);
})();
