(function () {
  const storageKeys = {
    username: "forum_pki_username",
    publicKey: "forum_pki_public_key",
    privateKey: "forum_pki_private_key",
    fingerprint: "forum_pki_fingerprint",
    publishedFingerprint: "forum_pki_published_fingerprint",
    composePromptCancelled: "forum_pki_compose_prompt_cancelled",
    composeDraftPrefix: "forum_compose_draft",
    recentlyClearedComposeDraft: "forum_recently_cleared_compose_draft",
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
  const LATIN_DIACRITIC_REPLACEMENTS = new Map([
    ["À", "A"], ["Á", "A"], ["Â", "A"], ["Ã", "A"], ["Ä", "A"], ["Å", "A"], ["Ā", "A"], ["Ă", "A"], ["Ą", "A"], ["Ǎ", "A"],
    ["à", "a"], ["á", "a"], ["â", "a"], ["ã", "a"], ["ä", "a"], ["å", "a"], ["ā", "a"], ["ă", "a"], ["ą", "a"], ["ǎ", "a"],
    ["Ç", "C"], ["Ć", "C"], ["Ĉ", "C"], ["Ċ", "C"], ["Č", "C"],
    ["ç", "c"], ["ć", "c"], ["ĉ", "c"], ["ċ", "c"], ["č", "c"],
    ["Ď", "D"], ["Đ", "D"],
    ["ď", "d"], ["đ", "d"],
    ["È", "E"], ["É", "E"], ["Ê", "E"], ["Ë", "E"], ["Ē", "E"], ["Ĕ", "E"], ["Ė", "E"], ["Ę", "E"], ["Ě", "E"],
    ["è", "e"], ["é", "e"], ["ê", "e"], ["ë", "e"], ["ē", "e"], ["ĕ", "e"], ["ė", "e"], ["ę", "e"], ["ě", "e"],
    ["Ĝ", "G"], ["Ğ", "G"], ["Ġ", "G"], ["Ģ", "G"],
    ["ĝ", "g"], ["ğ", "g"], ["ġ", "g"], ["ģ", "g"],
    ["Ĥ", "H"], ["Ħ", "H"],
    ["ĥ", "h"], ["ħ", "h"],
    ["Ì", "I"], ["Í", "I"], ["Î", "I"], ["Ï", "I"], ["Ĩ", "I"], ["Ī", "I"], ["Ĭ", "I"], ["Į", "I"], ["İ", "I"], ["Ǐ", "I"],
    ["ì", "i"], ["í", "i"], ["î", "i"], ["ï", "i"], ["ĩ", "i"], ["ī", "i"], ["ĭ", "i"], ["į", "i"], ["ǐ", "i"],
    ["Ĵ", "J"],
    ["ĵ", "j"],
    ["Ķ", "K"],
    ["ķ", "k"],
    ["Ĺ", "L"], ["Ļ", "L"], ["Ľ", "L"], ["Ŀ", "L"], ["Ł", "L"],
    ["ĺ", "l"], ["ļ", "l"], ["ľ", "l"], ["ŀ", "l"], ["ł", "l"],
    ["Ñ", "N"], ["Ń", "N"], ["Ņ", "N"], ["Ň", "N"],
    ["ñ", "n"], ["ń", "n"], ["ņ", "n"], ["ň", "n"],
    ["Ò", "O"], ["Ó", "O"], ["Ô", "O"], ["Õ", "O"], ["Ö", "O"], ["Ø", "O"], ["Ō", "O"], ["Ŏ", "O"], ["Ő", "O"], ["Ǒ", "O"],
    ["ò", "o"], ["ó", "o"], ["ô", "o"], ["õ", "o"], ["ö", "o"], ["ø", "o"], ["ō", "o"], ["ŏ", "o"], ["ő", "o"], ["ǒ", "o"],
    ["Ŕ", "R"], ["Ŗ", "R"], ["Ř", "R"],
    ["ŕ", "r"], ["ŗ", "r"], ["ř", "r"],
    ["Ś", "S"], ["Ŝ", "S"], ["Ş", "S"], ["Š", "S"],
    ["ś", "s"], ["ŝ", "s"], ["ş", "s"], ["š", "s"],
    ["Ţ", "T"], ["Ť", "T"], ["Ŧ", "T"],
    ["ţ", "t"], ["ť", "t"], ["ŧ", "t"],
    ["Ù", "U"], ["Ú", "U"], ["Û", "U"], ["Ü", "U"], ["Ũ", "U"], ["Ū", "U"], ["Ŭ", "U"], ["Ů", "U"], ["Ű", "U"], ["Ų", "U"], ["Ǔ", "U"],
    ["ù", "u"], ["ú", "u"], ["û", "u"], ["ü", "u"], ["ũ", "u"], ["ū", "u"], ["ŭ", "u"], ["ů", "u"], ["ű", "u"], ["ų", "u"], ["ǔ", "u"],
    ["Ŵ", "W"],
    ["ŵ", "w"],
    ["Ý", "Y"], ["Ŷ", "Y"], ["Ÿ", "Y"],
    ["ý", "y"], ["ŷ", "y"], ["ÿ", "y"],
    ["Ź", "Z"], ["Ż", "Z"], ["Ž", "Z"],
    ["ź", "z"], ["ż", "z"], ["ž", "z"],
    ["Æ", "AE"], ["æ", "ae"], ["Œ", "OE"], ["œ", "oe"], ["ẞ", "SS"], ["ß", "ss"],
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
      const punctuationReplacement = ASCII_COMPOSE_REPLACEMENTS.get(character);
      if (punctuationReplacement !== undefined) {
        normalized += punctuationReplacement;
        hadCorrections = true;
        continue;
      }

      const transliterationReplacement = LATIN_DIACRITIC_REPLACEMENTS.get(character);
      if (transliterationReplacement !== undefined) {
        normalized += transliterationReplacement;
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

  function composeDraftKey(form) {
    const kind = form.dataset.composeKind || "compose";
    if (kind === "reply") {
      const threadIdField = form.querySelector('input[name="thread_id"]');
      const parentIdField = form.querySelector('input[name="parent_id"]');
      const threadId = threadIdField ? threadIdField.value : "";
      const parentId = parentIdField ? parentIdField.value : "";
      return `${storageKeys.composeDraftPrefix}:${kind}:${threadId}:${parentId}`;
    }

    return `${storageKeys.composeDraftPrefix}:${kind}`;
  }

  function composeDraftFields(form) {
    return Array.from(form.querySelectorAll("input[name], textarea[name]")).filter(function (field) {
      if (!field.name || field.name === "author_identity_id") {
        return false;
      }

      if (field.tagName === "TEXTAREA") {
        return true;
      }

      if (field.tagName !== "INPUT") {
        return false;
      }

      const type = (field.getAttribute("type") || "text").toLowerCase();
      return type !== "hidden" || field.name === "thread_id" || field.name === "parent_id";
    });
  }

  function serializeComposeDraft(form) {
    const fields = {};
    composeDraftFields(form).forEach(function (field) {
      fields[field.name] = field.value;
    });

    return {
      fields: fields,
      savedAt: Date.now(),
    };
  }

  function saveComposeDraft(form) {
    try {
      localStorage.setItem(composeDraftKey(form), JSON.stringify(serializeComposeDraft(form)));
    } catch (error) {
    }
  }

  function loadComposeDraft(form) {
    try {
      const raw = localStorage.getItem(composeDraftKey(form));
      if (!raw) {
        return null;
      }

      const parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== "object" || !parsed.fields || typeof parsed.fields !== "object") {
        return null;
      }

      return parsed;
    } catch (error) {
      return null;
    }
  }

  function clearComposeDraft(form) {
    try {
      localStorage.removeItem(composeDraftKey(form));
    } catch (error) {
    }
  }

  function recentlyClearedComposeDraftKey() {
    try {
      return sessionStorage.getItem(storageKeys.recentlyClearedComposeDraft) || "";
    } catch (error) {
      return "";
    }
  }

  function clearRecentlyClearedComposeDraftKey() {
    try {
      sessionStorage.removeItem(storageKeys.recentlyClearedComposeDraft);
    } catch (error) {
    }
  }

  function restoreComposeDraft(form) {
    const draft = loadComposeDraft(form);
    if (!draft) {
      return false;
    }

    let restoredAny = false;
    composeDraftFields(form).forEach(function (field) {
      if (!Object.prototype.hasOwnProperty.call(draft.fields, field.name)) {
        return;
      }

      const nextValue = String(draft.fields[field.name] || "");
      if (field.value === nextValue) {
        return;
      }

      field.value = nextValue;
      restoredAny = true;
    });

    return restoredAny;
  }

  function hasExplicitComposePrefill(fields) {
    if (typeof window === "undefined" || !window.location || !window.location.search) {
      return false;
    }

    try {
      const params = new URLSearchParams(window.location.search);
      return fields.some(function (field) {
        return Boolean(field.name) && params.has(field.name);
      });
    } catch (error) {
      return false;
    }
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
    const technicalDetails = arguments.length > 3 && arguments[3] && typeof arguments[3].technicalDetails === "string"
      ? arguments[3].technicalDetails.trim()
      : "";
    if (!node) {
      return;
    }

    const messageNode = typeof node.querySelector === "function"
      ? node.querySelector('[data-role="browser-key-status-message"]')
      : null;
    if (messageNode) {
      messageNode.textContent = message;
    } else {
      node.textContent = message;
    }
    node.dataset.kind = kind || "info";
    renderTechnicalStatus(node, technicalDetails);
  }

  function createTechnicalStatusToggle(node) {
    if (!node || typeof node.appendChild !== "function" || typeof document === "undefined" || typeof document.createElement !== "function") {
      return null;
    }

    const spacer = document.createTextNode(" ");
    const toggle = document.createElement("a");
    toggle.href = "#";
    toggle.textContent = "details";
    toggle.setAttribute("data-role", "browser-key-status-technical-toggle");
    toggle.hidden = true;

    const details = document.createElement("code");
    details.setAttribute("data-role", "browser-key-status-technical-details");
    details.hidden = true;
    details.style.whiteSpace = "pre-wrap";

    toggle.addEventListener("click", function (event) {
      event.preventDefault();
      const expanded = !details.hidden;
      details.hidden = expanded;
      toggle.textContent = expanded ? "details" : "hide";
    });

    node.appendChild(spacer);
    node.appendChild(toggle);
    node.appendChild(document.createTextNode(" "));
    node.appendChild(details);

    return {
      toggle: toggle,
      details: details,
    };
  }

  function renderTechnicalStatus(node, technicalDetails) {
    if (!node || typeof node.querySelector !== "function") {
      return;
    }

    let toggle = node.querySelector('[data-role="browser-key-status-technical-toggle"]');
    let details = node.querySelector('[data-role="browser-key-status-technical-details"]');
    if ((!toggle || !details) && technicalDetails !== "") {
      const created = createTechnicalStatusToggle(node);
      if (created) {
        toggle = created.toggle;
        details = created.details;
      }
    }

    if (!toggle || !details) {
      return;
    }

    if (technicalDetails === "") {
      toggle.hidden = true;
      toggle.textContent = "details";
      details.hidden = true;
      details.textContent = "";
      return;
    }

    toggle.hidden = false;
    toggle.textContent = "details";
    details.hidden = true;
    details.textContent = technicalDetails;
  }

  function parseApiErrorResponse(text) {
    const lines = String(text || "").trim().split("\n");
    const errorLine = lines.find(function (line) {
      return line.indexOf("error=") === 0;
    });

    if (errorLine) {
      return errorLine.slice("error=".length).trim();
    }

    return String(text || "").trim();
  }

  function classifyIdentityBootstrapFailure(rawMessage) {
    const technicalDetails = String(rawMessage || "").trim();
    const fallback = "Could not prepare your browser identity automatically. Open /account/key/ to finish manually.";

    if (technicalDetails === "") {
      return {
        friendlyMessage: fallback,
        technicalDetails: "",
      };
    }

    if (technicalDetails === "Unable to inspect OpenPGP public key."
      || technicalDetails === "OpenPGP public key is missing a fingerprint or user ID."
      || technicalDetails === "public_key is required."
      || technicalDetails === "public_key must be ASCII only.") {
      return {
        friendlyMessage: "Could not verify the new browser key automatically. Open /account/key/ to finish manually.",
        technicalDetails: technicalDetails,
      };
    }

    if (technicalDetails.indexOf("Canonical write committed at ") === 0) {
      return {
        friendlyMessage: "Your browser identity may have been saved, but the forum could not finish refreshing it automatically. Open /account/key/ to finish manually.",
        technicalDetails: technicalDetails,
      };
    }

    if (technicalDetails === "Write APIs are disabled against the committed fixture repository. Initialize a local writable copy and set FORUM_REPOSITORY_ROOT."
      || technicalDetails === "Writable repository must be a git checkout before writes are allowed."
      || technicalDetails.indexOf("Unable to write canonical file: ") === 0
      || technicalDetails.indexOf("Unable to stage canonical write: ") === 0
      || technicalDetails.indexOf("Unable to commit canonical write: ") === 0
      || technicalDetails.indexOf("Unable to read commit SHA: ") === 0) {
      return {
        friendlyMessage: "This forum could not save your browser identity automatically. Open /account/key/ to finish manually.",
        technicalDetails: technicalDetails,
      };
    }

    return {
      friendlyMessage: fallback,
      technicalDetails: technicalDetails,
    };
  }

  function buildFriendlyError(message, technicalDetails) {
    const error = new Error(message);
    error.technicalDetails = technicalDetails;
    return error;
  }

  function statusFromError(error, fallbackMessage) {
    if (error instanceof Error) {
      return {
        message: error.message,
        technicalDetails: typeof error.technicalDetails === "string" ? error.technicalDetails.trim() : "",
      };
    }

    return {
      message: fallbackMessage,
      technicalDetails: "",
    };
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

  async function promptForUsername(promptMessage, cancellationMessage) {
    const prompted = typeof window.prompt === "function"
      ? window.prompt(
          promptMessage,
          localStorage.getItem(storageKeys.username) || "guest"
        )
      : "guest";

    if (prompted === null || String(prompted).trim() === "") {
      const continueAsGuest = typeof window.confirm === "function"
        ? window.confirm("Continue with the default username 'guest'?")
        : true;
      if (!continueAsGuest) {
        throw new Error(cancellationMessage);
      }

      return "guest";
    }

    return normalizeUsername(prompted);
  }

  async function promptForComposeUsername() {
    try {
      const username = await promptForUsername(
        "Choose a username for your first post:",
        "Posting paused until you choose a username."
      );
      localStorage.removeItem(storageKeys.composePromptCancelled);
      return username;
    } catch (error) {
      localStorage.setItem(storageKeys.composePromptCancelled, "1");
      throw error;
    }
  }

  async function promptForAccountUsername() {
    return promptForUsername(
      "Choose a username for this browser keypair:",
      "Key generation paused until you choose a username."
    );
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

    const failure = classifyIdentityBootstrapFailure(parseApiErrorResponse(text));
    throw buildFriendlyError(failure.friendlyMessage, failure.technicalDetails);
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

  async function ensureReadyIdentity(root, statusNode, options) {
    const config = options || {};
    const promptForUsername = typeof config.promptForUsername === "function"
      ? config.promptForUsername
      : promptForComposeUsername;
    const publishedFingerprint = localStorage.getItem(storageKeys.publishedFingerprint) || "";

    if (!hasBrowserKeypair()) {
      setStatus(statusNode, "Choose a username to prepare your browser keypair...", "info");
      const username = await promptForUsername();
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

  async function ensureComposeIdentity(root, statusNode) {
    await ensureReadyIdentity(root, statusNode, {
      promptForUsername: promptForComposeUsername,
    });
  }

  if (typeof window !== "undefined") {
    window.__forumBrowserIdentity = {
      currentAuthorIdentityId: currentAuthorIdentityId,
      ensureReadyIdentity: ensureReadyIdentity,
      hasBrowserKeypair: hasBrowserKeypair,
      classifyIdentityBootstrapFailure: classifyIdentityBootstrapFailure,
      statusFromError: statusFromError,
    };
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
    const submitButtons = form ? Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]')) : [];
    const textFields = form ? composeDraftFields(form).filter(function (field) {
      if (field.tagName === "TEXTAREA") {
        return true;
      }

      if (field.tagName !== "INPUT") {
        return false;
      }

      const type = (field.getAttribute("type") || "text").toLowerCase();
      return type === "text" || type === "search";
    }) : [];
    const fieldNormalizationStatusNodes = form ? Array.from(root.querySelectorAll('[data-role="compose-field-normalization-status"]')) : [];
    const removeUnsupportedButtons = form ? Array.from(root.querySelectorAll('[data-action="remove-unsupported-compose-characters"]')) : [];
    const normalizationStatusNode = root.querySelector('[data-role="compose-normalization-status"]');
    const normalizationMessageNode = root.querySelector('[data-role="compose-normalization-message"]');
    if (!form) {
      return;
    }
    const draftKey = composeDraftKey(form);

    function updateComposeNormalizationStatus(message, kind) {
      if (!normalizationStatusNode) {
        return;
      }

      if (!message) {
        normalizationStatusNode.hidden = true;
        normalizationStatusNode.dataset.kind = "";
        if (normalizationMessageNode) {
          normalizationMessageNode.textContent = "";
        } else {
          normalizationStatusNode.textContent = "";
        }
        return;
      }

      normalizationStatusNode.hidden = false;
      normalizationStatusNode.dataset.kind = kind || "";
      if (normalizationMessageNode) {
        normalizationMessageNode.textContent = message;
      } else {
        normalizationStatusNode.textContent = message;
      }
    }

    function fieldStatusNode(field) {
      return fieldNormalizationStatusNodes.find(function (node) {
        return node.dataset.composeFieldStatusFor === field.name;
      }) || null;
    }

    function fieldRemoveButton(field) {
      return removeUnsupportedButtons.find(function (button) {
        return button.dataset.composeFieldRemoveFor === field.name;
      }) || null;
    }

    function clearFieldNormalizationStatuses() {
      fieldNormalizationStatusNodes.forEach(function (node) {
        node.hidden = true;
        node.dataset.kind = "";
        const messageNode = node.querySelector('[data-role="compose-field-normalization-message"]');
        if (messageNode) {
          messageNode.textContent = "";
        } else {
          node.textContent = "";
        }
      });

      removeUnsupportedButtons.forEach(function (button) {
        button.hidden = true;
        button.disabled = true;
      });
    }

    function updateFieldNormalizationStatus(field, message, kind, allowRemoval) {
      const node = fieldStatusNode(field);
      const button = fieldRemoveButton(field);
      if (!node) {
        return;
      }

      if (!message) {
        node.hidden = true;
        node.dataset.kind = "";
        const emptyMessageNode = node.querySelector('[data-role="compose-field-normalization-message"]');
        if (emptyMessageNode) {
          emptyMessageNode.textContent = "";
        } else {
          node.textContent = "";
        }
        if (button) {
          button.hidden = true;
          button.disabled = true;
        }
        return;
      }

      node.hidden = false;
      node.dataset.kind = kind || "";
      const messageNode = node.querySelector('[data-role="compose-field-normalization-message"]');
      if (messageNode) {
        messageNode.textContent = message;
      } else {
        node.textContent = message;
      }
      if (button) {
        button.hidden = !allowRemoval;
        button.disabled = !allowRemoval;
      }
    }

    function setSubmitButtonsDisabled(disabled) {
      submitButtons.forEach(function (button) {
        button.disabled = disabled;
      });
    }

    function normalizeComposeField(field, options) {
      const result = normalizeComposeAscii(field.value, options);
      if (field.value !== result.text) {
        field.value = result.text;
      }

      return result;
    }

    function fieldLabel(field) {
      return field.dataset.composeFieldLabel || field.name || "field";
    }

    function fieldByName(name) {
      return textFields.find(function (field) {
        return field.name === name;
      }) || null;
    }

    function normalizeComposeFields(options) {
      const config = options || {};
      const removeUnsupported = config.removeUnsupported !== false;
      const persistDraft = config.persistDraft !== false;
      const unsupportedFields = [];

      clearFieldNormalizationStatuses();

      textFields.forEach(function (field) {
        const result = normalizeComposeField(field, { removeUnsupported: removeUnsupported });
        if (result.unsupportedCount > 0) {
          unsupportedFields.push(field);
          updateFieldNormalizationStatus(
            field,
            fieldLabel(field) + " contains unsupported characters.",
            "error",
            true
          );
        } else if (result.removedUnsupportedCount > 0) {
          updateFieldNormalizationStatus(
            field,
            "Removed "
              + result.removedUnsupportedCount
              + " unsupported character"
              + (result.removedUnsupportedCount === 1 ? "" : "s")
              + " from "
              + fieldLabel(field)
              + ".",
            "ok",
            false
          );
        }
      });

      if (unsupportedFields.length > 0) {
        updateComposeNormalizationStatus("Remove unsupported characters before submitting.", "error");
      } else {
        updateComposeNormalizationStatus("", "");
      }

      if (persistDraft) {
        saveComposeDraft(form);
      }

      return {
        hasUnsupported: unsupportedFields.length > 0,
      };
    }

    function removeUnsupportedFromField(field) {
      if (!field) {
        return;
      }

      const result = normalizeComposeField(field, { removeUnsupported: true });
      if (result.removedUnsupportedCount > 0) {
        updateFieldNormalizationStatus(
          field,
          "Removed "
            + result.removedUnsupportedCount
            + " unsupported character"
            + (result.removedUnsupportedCount === 1 ? "" : "s")
            + " from "
            + fieldLabel(field)
            + ".",
          "ok",
          false
        );
      }

      const summary = normalizeComposeFields({ removeUnsupported: false });
      if (!summary.hasUnsupported && result.removedUnsupportedCount > 0) {
        updateFieldNormalizationStatus(
          field,
          "Removed "
            + result.removedUnsupportedCount
            + " unsupported character"
            + (result.removedUnsupportedCount === 1 ? "" : "s")
            + " from "
            + fieldLabel(field)
            + ".",
          "ok",
          false
        );
      }
    }

    function restoreComposeUiState() {
      submitInFlight = false;
      setSubmitButtonsDisabled(false);
      normalizeComposeFields({ removeUnsupported: false });
    }

    function resetComposeFormToServerState() {
      form.reset();
      clearComposeDraft(form);
    }

    function shouldHonorRecentlyClearedDraft() {
      return recentlyClearedComposeDraftKey() === draftKey;
    }

    if (hasBrowserKeypair()) {
      void syncIdentityHint(preferredIdentityHint());
    }

    if (root.dataset.composeSubmitted === "1") {
      resetComposeFormToServerState();
      normalizeComposeFields({ removeUnsupported: false, persistDraft: false });
    } else if (shouldHonorRecentlyClearedDraft()) {
      resetComposeFormToServerState();
      clearRecentlyClearedComposeDraftKey();
      normalizeComposeFields({ removeUnsupported: false, persistDraft: false });
    } else if (hasExplicitComposePrefill(textFields)) {
      normalizeComposeFields({ removeUnsupported: false });
    } else {
      restoreComposeDraft(form);
      normalizeComposeFields({ removeUnsupported: false });
    }

    textFields.forEach(function (field) {
      field.addEventListener("input", function () {
        normalizeComposeFields({ removeUnsupported: false });
      });
      field.addEventListener("change", function () {
        saveComposeDraft(form);
      });
    });

    removeUnsupportedButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        removeUnsupportedFromField(fieldByName(button.dataset.composeFieldRemoveFor || ""));
      });
    });

    let submitInFlight = false;
    form.addEventListener("submit", async function (event) {
      if (submitInFlight) {
        return;
      }

      const submitter = event.submitter;
      event.preventDefault();
      submitInFlight = true;
      setSubmitButtonsDisabled(true);
      const normalizationResult = normalizeComposeFields({ removeUnsupported: false });
      if (normalizationResult.hasUnsupported) {
        submitInFlight = false;
        setSubmitButtonsDisabled(false);
        return;
      }

      try {
        await ensureComposeIdentity(root, statusNode);
        ensureComposeAuthorIdentity(form);
        setStatus(statusNode, "Identity ready. Sending post...", "ok");
        form.submit();
      } catch (error) {
        const status = statusFromError(error, "Unable to prepare your browser identity. Use /account/key/ manually.");
        setStatus(
          statusNode,
          status.message,
          "error",
          { technicalDetails: status.technicalDetails }
        );
        restoreComposeUiState();
      }
    });

    window.addEventListener("pageshow", function () {
      if (shouldHonorRecentlyClearedDraft()) {
        resetComposeFormToServerState();
        clearRecentlyClearedComposeDraftKey();
        normalizeComposeFields({ removeUnsupported: false, persistDraft: false });
        return;
      }

      restoreComposeUiState();
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
