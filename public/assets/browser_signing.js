(function () {
  const storageKeys = {
    username: "forum_pki_username",
    publicKey: "forum_pki_public_key",
    privateKey: "forum_pki_private_key",
    fingerprint: "forum_pki_fingerprint",
    publishedFingerprint: "forum_pki_published_fingerprint",
    composePromptCancelled: "forum_pki_compose_prompt_cancelled",
  };
  let clearedKeypairBackup = null;
  const ASCII_COMPOSE_REPLACEMENTS = new Map([
    ["\u2018", "'"],
    ["\u2019", "'"],
    ["\u201C", '"'],
    ["\u201D", '"'],
    ["\u2013", "-"],
    ["\u2014", "-"],
    ["\u2026", "..."],
    ["\u00A0", " "],
  ]);

  function normalizeNewlines(text) {
    return String(text || "").replace(/\r\n?/g, "\n");
  }

  function unsupportedComposeCharacters(text) {
    const characters = [];
    for (const character of text) {
      if (!/^[\x00-\x7F]$/.test(character)) {
        characters.push(character);
      }
    }
    return characters;
  }

  function normalizeComposeAscii(text, options) {
    const config = options || {};
    const removeUnsupported = config.removeUnsupported === true;
    let normalized = "";
    let hadCorrections = false;

    for (const character of normalizeNewlines(text)) {
      const replacement = ASCII_COMPOSE_REPLACEMENTS.get(character);
      if (replacement !== undefined) {
        normalized += replacement;
        hadCorrections = true;
        continue;
      }

      normalized += character;
    }

    const unsupportedBeforeRemoval = unsupportedComposeCharacters(normalized);
    if (removeUnsupported && unsupportedBeforeRemoval.length > 0) {
      normalized = Array.from(normalized)
        .filter(function (character) {
          return /^[\x00-\x7F]$/.test(character);
        })
        .join("");
    }

    return {
      text: normalized,
      hadCorrections: hadCorrections,
      unsupportedCount: removeUnsupported ? 0 : unsupportedBeforeRemoval.length,
      removedUnsupportedCount: removeUnsupported ? unsupportedBeforeRemoval.length : 0,
    };
  }

  if (typeof window !== "undefined") {
    window.__forumComposeNormalization = {
      normalizeComposeAscii: normalizeComposeAscii,
    };
  }

  function loadClearedKeypairBackup() {
    return clearedKeypairBackup;
  }

  function saveClearedKeypairBackup() {
    const backup = {
      username: localStorage.getItem(storageKeys.username) || "",
      publicKey: localStorage.getItem(storageKeys.publicKey) || "",
      privateKey: localStorage.getItem(storageKeys.privateKey) || "",
      fingerprint: localStorage.getItem(storageKeys.fingerprint) || "",
      publishedFingerprint: localStorage.getItem(storageKeys.publishedFingerprint) || "",
      composePromptCancelled: localStorage.getItem(storageKeys.composePromptCancelled) || "",
    };

    if (!backup.publicKey && !backup.privateKey) {
      clearedKeypairBackup = null;
      return false;
    }

    clearedKeypairBackup = backup;
    return true;
  }

  function clearClearedKeypairBackup() {
    clearedKeypairBackup = null;
  }

  function restoreClearedKeypairBackup() {
    const backup = loadClearedKeypairBackup();
    if (!backup || (!backup.publicKey && !backup.privateKey)) {
      return false;
    }

    localStorage.setItem(storageKeys.username, backup.username || "guest");
    localStorage.setItem(storageKeys.publicKey, backup.publicKey);
    localStorage.setItem(storageKeys.privateKey, backup.privateKey);

    if (backup.fingerprint) {
      localStorage.setItem(storageKeys.fingerprint, backup.fingerprint);
    } else {
      localStorage.removeItem(storageKeys.fingerprint);
    }

    if (backup.publishedFingerprint) {
      localStorage.setItem(storageKeys.publishedFingerprint, backup.publishedFingerprint);
    } else {
      localStorage.removeItem(storageKeys.publishedFingerprint);
    }

    if (backup.composePromptCancelled) {
      localStorage.setItem(storageKeys.composePromptCancelled, backup.composePromptCancelled);
    } else {
      localStorage.removeItem(storageKeys.composePromptCancelled);
    }

    clearClearedKeypairBackup();
    return true;
  }

  function renderUndoState(root) {
    const undoLink = root.querySelector('[data-action="undo-clear-browser-key"]');
    if (!undoLink) {
      return;
    }

    undoLink.hidden = !loadClearedKeypairBackup();
  }

  function normalizeUsername(value) {
    const normalized = String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9._-]+/g, "-")
      .replace(/^-+|-+$/g, "");

    return normalized || "guest";
  }

  function currentAuthorIdentityId() {
    const fingerprint = (localStorage.getItem(storageKeys.fingerprint) || "")
      .trim()
      .toLowerCase();
    if (!fingerprint) {
      return "";
    }

    return `openpgp:${fingerprint}`;
  }

  function preferredIdentityHint() {
    const identityId = currentAuthorIdentityId();
    if (identityId) {
      return identityId;
    }

    return normalizeUsername(localStorage.getItem(storageKeys.username) || "guest");
  }

  async function syncIdentityHint(value) {
    const query = new URLSearchParams({ identity_hint: value }).toString();
    await fetch(`/api/set_identity_hint?${query}`, {
      method: "POST",
      credentials: "same-origin",
    });
  }

  async function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(text);
      return;
    }

    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.setAttribute("readonly", "readonly");
    textarea.style.position = "absolute";
    textarea.style.left = "-9999px";
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand("copy");
    document.body.removeChild(textarea);
  }

  function setStatus(node, message, kind) {
    if (!node) {
      return;
    }

    const messageNode = node.querySelector('[data-role="browser-key-status-message"]');
    if (messageNode) {
      messageNode.textContent = message;
    } else {
      node.textContent = message;
    }
    node.dataset.kind = kind || "info";
  }

  function hasBrowserKeypair() {
    return Boolean(
      localStorage.getItem(storageKeys.publicKey) &&
      localStorage.getItem(storageKeys.privateKey)
    );
  }

  function renderSavedState(root) {
    const publicKey = localStorage.getItem(storageKeys.publicKey) || "";
    const privateKey = localStorage.getItem(storageKeys.privateKey) || "";
    const username = localStorage.getItem(storageKeys.username) || "guest";
    const fingerprint = (localStorage.getItem(storageKeys.fingerprint) || "")
      .trim()
      .toLowerCase();
    const publicKeyField = root.querySelector('[data-role="public-key-field"]');
    const usernameField = root.querySelector('[data-role="username-field"]');
    const privateKeyViewer = root.querySelector('[data-role="private-key-viewer"]');
    const publicKeyViewer = root.querySelector('[data-role="public-key-viewer"]');
    const identityIdField = root.querySelector('[data-role="identity-id-field"]');
    const profileLink = root.querySelector('[data-role="profile-link"]');
    const profileLinkWrap = root.querySelector('[data-role="profile-link-wrap"]');

    if (publicKeyField && !publicKeyField.value) {
      publicKeyField.value = publicKey;
    }

    if (usernameField) {
      usernameField.textContent = username;
    }

    if (privateKeyViewer) {
      privateKeyViewer.textContent = privateKey || "No browser private key saved yet.";
    }

    if (publicKeyViewer) {
      publicKeyViewer.textContent = publicKey || "No browser public key saved yet.";
    }

    if (identityIdField) {
      identityIdField.textContent = fingerprint ? `openpgp:${fingerprint}` : "none";
    }

    if (profileLink && profileLinkWrap) {
      if (fingerprint) {
        profileLink.href = `/profiles/openpgp-${fingerprint}`;
        profileLinkWrap.hidden = false;
      } else {
        profileLink.href = "/account/key/";
        profileLinkWrap.hidden = true;
      }
    }

    renderUndoState(root);
  }

  async function promptForComposeUsername() {
    const prompted = typeof window.prompt === "function"
      ? window.prompt(
          "Choose a username for your first post:",
          localStorage.getItem(storageKeys.username) || "guest"
        )
      : "guest";

    if (prompted === null || String(prompted).trim() === "") {
      const continueAsGuest = typeof window.confirm === "function"
        ? window.confirm("Continue with the default username 'guest'?")
        : true;
      if (!continueAsGuest) {
        localStorage.setItem(storageKeys.composePromptCancelled, "1");
        throw new Error("Posting paused until you choose a username.");
      }

      localStorage.removeItem(storageKeys.composePromptCancelled);
      return "guest";
    }

    localStorage.removeItem(storageKeys.composePromptCancelled);
    return normalizeUsername(prompted);
  }

  async function promptForAccountUsername() {
    const prompted = typeof window.prompt === "function"
      ? window.prompt(
          "Choose a username for this browser keypair:",
          localStorage.getItem(storageKeys.username) || "guest"
        )
      : "guest";

    if (prompted === null || String(prompted).trim() === "") {
      const continueAsGuest = typeof window.confirm === "function"
        ? window.confirm("Continue with the default username 'guest'?")
        : true;
      if (!continueAsGuest) {
        throw new Error("Key generation paused until you choose a username.");
      }

      return "guest";
    }

    return normalizeUsername(prompted);
  }

  async function generateBrowserKey(root, preferredUsername) {
    if (!window.openpgp || !window.openpgp.generateKey) {
      throw new Error("OpenPGP.js failed to load.");
    }

    const username = normalizeUsername(preferredUsername || localStorage.getItem(storageKeys.username) || "guest");
    const result = await window.openpgp.generateKey({
      type: "ecc",
      curve: "ed25519",
      userIDs: [{ name: username }],
      format: "armored",
    });
    const key = await window.openpgp.readKey({ armoredKey: result.publicKey });
    const fingerprint = String(key.getFingerprint()).toUpperCase();

    localStorage.setItem(storageKeys.username, username);
    localStorage.setItem(storageKeys.publicKey, result.publicKey);
    localStorage.setItem(storageKeys.privateKey, result.privateKey);
    localStorage.setItem(storageKeys.fingerprint, fingerprint);
    clearClearedKeypairBackup();
    await syncIdentityHint(preferredIdentityHint());
    renderSavedState(root);

    return username;
  }

  async function publishPublicKey(root) {
    const publicKey = localStorage.getItem(storageKeys.publicKey) || "";
    const username = localStorage.getItem(storageKeys.username) || "guest";
    const fingerprint = await ensureStoredFingerprint();
    if (!publicKey) {
      throw new Error("No browser public key is available to publish.");
    }

    const response = await fetch(`/api/link_identity`, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: new URLSearchParams({ public_key: publicKey }).toString(),
    });
    const text = await response.text();

    if (text.includes("status=ok") || text.includes("Identity already exists for this fingerprint.")) {
      if (fingerprint) {
        localStorage.setItem(storageKeys.publishedFingerprint, fingerprint);
      }
      await syncIdentityHint(preferredIdentityHint());
      renderSavedState(root);
      return;
    }

    throw new Error("Automatic identity bootstrap failed. Open /account/key/ to finish manually.");
  }

  async function ensureStoredFingerprint() {
    const existing = localStorage.getItem(storageKeys.fingerprint) || "";
    if (existing) {
      return existing;
    }

    const publicKey = localStorage.getItem(storageKeys.publicKey) || "";
    if (!publicKey || !window.openpgp || !window.openpgp.readKey) {
      return "";
    }

    const key = await window.openpgp.readKey({ armoredKey: publicKey });
    const fingerprint = String(key.getFingerprint()).toUpperCase();
    localStorage.setItem(storageKeys.fingerprint, fingerprint);

    return fingerprint;
  }

  async function ensureComposeIdentity(root, statusNode) {
    const publishedFingerprint = localStorage.getItem(storageKeys.publishedFingerprint) || "";

    if (!hasBrowserKeypair()) {
      setStatus(statusNode, "Choose a username to prepare your browser keypair...", "info");
      const username = await promptForComposeUsername();
      setStatus(statusNode, "Generating browser keypair...", "info");
      await generateBrowserKey(root, username);
    }

    const fingerprint = await ensureStoredFingerprint();
    if (fingerprint === "" || publishedFingerprint !== fingerprint) {
      setStatus(statusNode, "Publishing your public key in the background...", "info");
      await publishPublicKey(root);
    } else {
      await syncIdentityHint(preferredIdentityHint());
    }
  }

  function ensureComposeAuthorIdentity(form) {
    let field = form.querySelector('input[name="author_identity_id"]');
    if (!field) {
      field = document.createElement("input");
      field.type = "hidden";
      field.name = "author_identity_id";
      form.appendChild(field);
    }

    field.value = currentAuthorIdentityId();
  }

  function bindAccountKeyPage(root) {
    const statusNode = root.querySelector('[data-role="browser-key-status"]');
    const publicKeyField = root.querySelector('[data-role="public-key-field"]');
    const generateButton = root.querySelector('[data-action="generate-browser-key"]');
    const loadButton = root.querySelector('[data-action="load-browser-key"]');
    const clearButton = root.querySelector('[data-action="clear-browser-key"]');
    const undoClearLink = root.querySelector('[data-action="undo-clear-browser-key"]');
    const copyPublicButton = root.querySelector('[data-action="copy-public-key"]');
    const copyPrivateButton = root.querySelector('[data-action="copy-private-key"]');

    renderSavedState(root);
    if (hasBrowserKeypair()) {
      void syncIdentityHint(preferredIdentityHint());
    }

    if (generateButton) {
      generateButton.addEventListener("click", async function () {
        generateButton.disabled = true;

        try {
          const username = await promptForAccountUsername();
          setStatus(statusNode, "Generating browser keypair...", "info");
          await generateBrowserKey(root, username);
          if (publicKeyField) {
            publicKeyField.value = localStorage.getItem(storageKeys.publicKey) || "";
          }
          setStatus(statusNode, `Browser keypair ready for ${username}. Submit the form to link it.`, "ok");
        } catch (error) {
          setStatus(statusNode, error instanceof Error ? error.message : "Unable to generate browser keypair.", "error");
        } finally {
          generateButton.disabled = false;
        }
      });
    }

    if (loadButton) {
      loadButton.addEventListener("click", function () {
        renderSavedState(root);
        if (publicKeyField) {
          publicKeyField.value = localStorage.getItem(storageKeys.publicKey) || "";
        }
        setStatus(statusNode, "Loaded the saved browser public key into the form.", "ok");
      });
    }

    if (clearButton) {
      clearButton.addEventListener("click", async function () {
        try {
          const savedBackup = saveClearedKeypairBackup();
          localStorage.removeItem(storageKeys.username);
          localStorage.removeItem(storageKeys.publicKey);
          localStorage.removeItem(storageKeys.privateKey);
          localStorage.removeItem(storageKeys.fingerprint);
          localStorage.removeItem(storageKeys.publishedFingerprint);
          localStorage.removeItem(storageKeys.composePromptCancelled);
          await syncIdentityHint(preferredIdentityHint());
          renderSavedState(root);
          if (publicKeyField) {
            publicKeyField.value = "";
          }

          if (savedBackup) {
            setStatus(statusNode, "Cleared the saved browser keypair from local storage.", "ok");
          } else {
            setStatus(statusNode, "Cleared the saved browser keypair from local storage.", "ok");
          }
        } catch (error) {
          setStatus(statusNode, error instanceof Error ? error.message : "Unable to clear the saved browser keypair.", "error");
        }
      });
    }

    if (undoClearLink) {
      undoClearLink.addEventListener("click", async function (event) {
        event.preventDefault();

        try {
          const restored = restoreClearedKeypairBackup();
          if (!restored) {
            setStatus(statusNode, "No recently cleared browser keypair is available to restore.", "error");
            renderSavedState(root);
            return;
          }

          await syncIdentityHint(preferredIdentityHint());
          renderSavedState(root);
          if (publicKeyField) {
            publicKeyField.value = localStorage.getItem(storageKeys.publicKey) || "";
          }
          setStatus(statusNode, "Restored the recently cleared browser keypair.", "ok");
        } catch (error) {
          setStatus(statusNode, error instanceof Error ? error.message : "Unable to restore the cleared browser keypair.", "error");
        }
      });
    }

    if (copyPublicButton) {
      copyPublicButton.addEventListener("click", async function () {
        const publicKey = localStorage.getItem(storageKeys.publicKey) || "";
        if (!publicKey) {
          setStatus(statusNode, "No saved public key is available to copy.", "error");
          return;
        }

        await copyText(publicKey);
        setStatus(statusNode, "Copied the saved public key.", "ok");
      });
    }

    if (copyPrivateButton) {
      copyPrivateButton.addEventListener("click", async function () {
        const privateKey = localStorage.getItem(storageKeys.privateKey) || "";
        if (!privateKey) {
          setStatus(statusNode, "No saved private key is available to copy.", "error");
          return;
        }

        await copyText(privateKey);
        setStatus(statusNode, "Copied the saved private key.", "ok");
      });
    }
  }

  function bindComposePage(root) {
    const form = root.querySelector("[data-compose-form]");
    const statusNode = root.querySelector('[data-role="compose-identity-status"]');
    const bodyField = form ? form.querySelector('textarea[name="body"]') : null;
    const normalizationStatusNode = root.querySelector('[data-role="compose-normalization-status"]');
    const normalizationActions = root.querySelector('[data-role="compose-normalization-actions"]');
    const removeUnsupportedButton = root.querySelector('[data-action="remove-unsupported-compose-characters"]');
    if (!form) {
      return;
    }

    function updateComposeNormalizationStatus(message, kind) {
      if (!normalizationStatusNode) {
        return;
      }

      normalizationStatusNode.textContent = message || "";
      normalizationStatusNode.dataset.kind = kind || "";
    }

    function normalizeBodyInput(options) {
      if (!bodyField) {
        return {
          text: "",
          hadCorrections: false,
          unsupportedCount: 0,
          removedUnsupportedCount: 0,
        };
      }

      const result = normalizeComposeAscii(bodyField.value, options);
      if (bodyField.value !== result.text) {
        bodyField.value = result.text;
      }

      if (result.removedUnsupportedCount > 0) {
        updateComposeNormalizationStatus(
          "Removed " + result.removedUnsupportedCount + " unsupported character" + (result.removedUnsupportedCount === 1 ? "" : "s") + ".",
          "ok"
        );
      } else if (result.unsupportedCount > 0) {
        updateComposeNormalizationStatus("Unsupported characters remain in the body. Remove them before submitting.", "error");
      } else if (result.hadCorrections) {
        updateComposeNormalizationStatus("Converted common non-ASCII punctuation to ASCII.", "ok");
      } else {
        updateComposeNormalizationStatus("", "");
      }

      if (normalizationActions) {
        normalizationActions.hidden = result.unsupportedCount === 0;
      }
      if (removeUnsupportedButton) {
        removeUnsupportedButton.disabled = result.unsupportedCount === 0;
      }

      return result;
    }

    if (hasBrowserKeypair()) {
      void syncIdentityHint(preferredIdentityHint());
    }

    if (bodyField) {
      normalizeBodyInput();
      bodyField.addEventListener("input", function () {
        normalizeBodyInput();
      });
    }

    if (removeUnsupportedButton) {
      removeUnsupportedButton.addEventListener("click", function () {
        normalizeBodyInput({ removeUnsupported: true });
      });
    }

    let submitInFlight = false;
    form.addEventListener("submit", async function (event) {
      if (submitInFlight) {
        return;
      }

      const submitter = event.submitter;
      event.preventDefault();
      submitInFlight = true;
      if (submitter && typeof submitter.disabled === "boolean") {
        submitter.disabled = true;
      }

      try {
        const normalizationResult = normalizeBodyInput();
        if (normalizationResult.unsupportedCount > 0) {
          throw new Error("Unsupported characters remain in the body. Remove them before submitting.");
        }

        await ensureComposeIdentity(root, statusNode);
        ensureComposeAuthorIdentity(form);
        setStatus(statusNode, "Identity ready. Sending post...", "ok");
        form.submit();
      } catch (error) {
        setStatus(
          statusNode,
          error instanceof Error ? error.message : "Unable to prepare your browser identity. Use /account/key/ manually.",
          "error"
        );
        submitInFlight = false;
        if (submitter && typeof submitter.disabled === "boolean") {
          submitter.disabled = false;
        }
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    const accountRoot = document.querySelector("[data-account-key-root]");
    if (accountRoot) {
      bindAccountKeyPage(accountRoot);
    }

    const composeRoot = document.querySelector("[data-compose-root]");
    if (composeRoot) {
      bindComposePage(composeRoot);
    }
  });
})();
