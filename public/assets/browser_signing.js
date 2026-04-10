(function () {
  const storageKeys = {
    username: "forum_pki_username",
    publicKey: "forum_pki_public_key",
    privateKey: "forum_pki_private_key",
    fingerprint: "forum_pki_fingerprint",
    publishedFingerprint: "forum_pki_published_fingerprint",
    composePromptCancelled: "forum_pki_compose_prompt_cancelled",
  };

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

  async function syncIdentityHint(username) {
    const query = new URLSearchParams({ identity_hint: username }).toString();
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

    node.textContent = message;
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
    const publicKeyField = root.querySelector('[data-role="public-key-field"]');
    const usernameField = root.querySelector('[data-role="username-field"]');
    const privateKeyViewer = root.querySelector('[data-role="private-key-viewer"]');
    const publicKeyViewer = root.querySelector('[data-role="public-key-viewer"]');

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
    await syncIdentityHint(username);
    renderSavedState(root);

    return username;
  }

  async function publishPublicKey(root, bootstrapPostId) {
    const publicKey = localStorage.getItem(storageKeys.publicKey) || "";
    const username = localStorage.getItem(storageKeys.username) || "guest";
    const fingerprint = await ensureStoredFingerprint();
    if (!publicKey) {
      throw new Error("No browser public key is available to publish.");
    }

    const response = await fetch(`/api/link_identity?bootstrap_post_id=${encodeURIComponent(bootstrapPostId)}`, {
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
      await syncIdentityHint(username);
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
    const bootstrapPostId = root.getAttribute("data-bootstrap-post-id") || "root-001";
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
      await publishPublicKey(root, bootstrapPostId);
    } else {
      const username = localStorage.getItem(storageKeys.username) || "guest";
      await syncIdentityHint(username);
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
    const copyPublicButton = root.querySelector('[data-action="copy-public-key"]');
    const copyPrivateButton = root.querySelector('[data-action="copy-private-key"]');

    renderSavedState(root);

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
      clearButton.addEventListener("click", function () {
        localStorage.removeItem(storageKeys.username);
        localStorage.removeItem(storageKeys.publicKey);
        localStorage.removeItem(storageKeys.privateKey);
        localStorage.removeItem(storageKeys.fingerprint);
        localStorage.removeItem(storageKeys.publishedFingerprint);
        localStorage.removeItem(storageKeys.composePromptCancelled);
        renderSavedState(root);
        if (publicKeyField) {
          publicKeyField.value = "";
        }
        setStatus(statusNode, "Cleared the saved browser keypair from local storage.", "ok");
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
    if (!form) {
      return;
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
