(function () {
  const storageKeys = {
    username: "forum_pki_username",
    publicKey: "forum_pki_public_key",
    privateKey: "forum_pki_private_key",
  };

  function normalizeUsername(value) {
    const normalized = String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9._-]+/g, "-")
      .replace(/^-+|-+$/g, "");

    return normalized || "guest";
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

  async function generateBrowserKey(root) {
    if (!window.openpgp || !window.openpgp.generateKey) {
      throw new Error("OpenPGP.js failed to load.");
    }

    const prompted = typeof window.prompt === "function"
      ? window.prompt("Choose a username for this browser keypair:", localStorage.getItem(storageKeys.username) || "guest")
      : "guest";
    const username = normalizeUsername(prompted);
    const result = await window.openpgp.generateKey({
      type: "ecc",
      curve: "ed25519",
      userIDs: [{ name: username }],
      format: "armored",
    });

    localStorage.setItem(storageKeys.username, username);
    localStorage.setItem(storageKeys.publicKey, result.publicKey);
    localStorage.setItem(storageKeys.privateKey, result.privateKey);
    await syncIdentityHint(username);
    renderSavedState(root);

    return username;
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
        setStatus(statusNode, "Generating browser keypair...", "info");

        try {
          const username = await generateBrowserKey(root);
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

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-account-key-root]");
    if (!root) {
      return;
    }

    bindAccountKeyPage(root);
  });
})();
