(function () {
  "use strict";

  const recentDestinationsStorageKey = "forum_recent_invitation_destinations";
  const maximumRecentDestinations = 8;

  function setFeedback(node, message, kind) {
    node.hidden = false;
    node.textContent = message;
    node.dataset.status = kind || "ok";
  }

  function randomToken() {
    const bytes = new Uint8Array(16);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, function (value) { return value.toString(16).padStart(2, "0"); }).join("");
  }

  async function verificationHash(token) {
    const bytes = new TextEncoder().encode(token);
    const digest = await crypto.subtle.digest("SHA-256", bytes);
    return "sha256:" + Array.from(new Uint8Array(digest), function (value) { return value.toString(16).padStart(2, "0"); }).join("");
  }

  async function post(endpoint, values) {
    const response = await fetch(endpoint, {
      method: "POST", credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: new URLSearchParams(values).toString(),
    });
    return response.json();
  }

  async function copyInvitationLink(link) {
    if (!link || !link.value) {
      throw new Error("Generate an invitation before copying it.");
    }

    link.focus();
    link.select();
    if (typeof navigator !== "undefined"
      && navigator.clipboard
      && typeof navigator.clipboard.writeText === "function") {
      await navigator.clipboard.writeText(link.value);
      return;
    }

    if (typeof document.execCommand === "function" && document.execCommand("copy")) {
      return;
    }

    throw new Error("The invite link is selected. Copy it with your browser's copy command.");
  }

  async function ensureInvitationIdentity(root, feedback) {
    const signing = window.ForumBrowserSigning || null;
    if (!signing || typeof signing.ensureActionIdentity !== "function" || typeof signing.signCanonicalRecord !== "function") {
      throw new Error("Browser signing is unavailable.");
    }

    await signing.ensureActionIdentity(root, feedback);
    return signing;
  }

  function resolveInvitationQrColors(canvas) {
    const fallback = ["#000", "#fff"];
    if (!window.getComputedStyle || (window.matchMedia && window.matchMedia("(forced-colors: active)").matches)) return fallback;
    const style = window.getComputedStyle(canvas);
    const colors = [style.color, style.backgroundColor].map(function (color) {
      // Computed sRGB only: reject transparent or unsupported color spaces conservatively.
      const match = /^rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(1(?:\.0+)?))?\s*\)$/.exec(color);
      if (!match) return null;
      const rgb = match.slice(1, 4).map(Number);
      if (rgb.some(value => value > 255)) return null;
      const linear = rgb.map(value => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : Math.pow((channel + 0.055) / 1.055, 2.4);
      });
      return { color, luminance: linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722 };
    });
    if (colors.some(color => !color)) return fallback;
    colors.sort((a, b) => a.luminance - b.luminance);
    return (colors[1].luminance + 0.05) / (colors[0].luminance + 0.05) >= 7
      ? colors.map(color => color.color) : fallback;
  }

  function renderInvitationQr(canvas, value) {
    const qr = window.qrcode(0, "M");
    qr.addData(value);
    qr.make();
    const modules = qr.getModuleCount();
    const cell = 4;
    const margin = 4;
    const colors = resolveInvitationQrColors(canvas);
    canvas.width = canvas.height = (modules + margin * 2) * cell;
    const context = canvas.getContext("2d");
    context.fillStyle = colors[1];
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = colors[0];
    for (let row = 0; row < modules; row += 1) {
      for (let col = 0; col < modules; col += 1) {
        if (qr.isDark(row, col)) context.fillRect((col + margin) * cell, (row + margin) * cell, cell, cell);
      }
    }
  }

  function validInvitationDestination(value) {
    const destination = String(value || "").trim();
    let decoded;
    try { decoded = decodeURIComponent(destination); } catch (error) { return ""; }
    if (destination === "" || destination.length > 500 || !/^\/(?!\/)/.test(decoded)
      || /[\u0000-\u0020\u007f\\]/.test(destination) || /[\u0000-\u001f\u007f\\]/.test(decoded)) {
      return "";
    }
    return destination;
  }

  function addDestinationOption(list, value) {
    const destination = validInvitationDestination(value);
    if (!list || destination === "") return;
    const options = list.querySelectorAll("[data-destination-value]");
    for (let index = 0; index < options.length; index += 1) {
      if (options[index].dataset.destinationValue === destination) return;
    }
    const option = document.createElement("button");
    option.type = "button";
    option.dataset.destinationValue = destination;
    option.textContent = destination;
    list.appendChild(option);
  }

  function recentInvitationDestinations() {
    try {
      const stored = JSON.parse(window.localStorage.getItem(recentDestinationsStorageKey) || "[]");
      if (!Array.isArray(stored)) return [];
      const destinations = [];
      stored.forEach(function (value) {
        const destination = validInvitationDestination(value);
        if (destination !== "" && destinations.indexOf(destination) === -1) destinations.push(destination);
      });
      return destinations.slice(0, maximumRecentDestinations);
    } catch (error) {
      return [];
    }
  }

  function rememberInvitationDestination(value) {
    const destination = validInvitationDestination(value);
    if (destination === "") return;
    const destinations = [destination].concat(recentInvitationDestinations().filter(function (item) {
      return item !== destination;
    })).slice(0, maximumRecentDestinations);
    try {
      window.localStorage.setItem(recentDestinationsStorageKey, JSON.stringify(destinations));
    } catch (error) {
      // Storage can be unavailable in private browsing contexts.
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    const root = document.querySelector("[data-invitation-page]");
    const form = root && root.querySelector("[data-invitation-issue-form]");
    if (!form) return;
    const feedback = root.querySelector("[data-role=invitation-feedback]");
    const result = root.querySelector("[data-role=invitation-result]");
    const link = root.querySelector("[data-role=invitation-link]");
    const qrResult = root.querySelector("[data-role=invitation-qr]");
    const qrCanvas = root.querySelector("[data-role=invitation-qr-code]");
    const includeDestination = form.elements.include_destination;
    const destination = form.elements.destination;
    const destinationFields = form.querySelector("[data-role=invitation-destination-fields]");
    const destinationMenu = form.querySelector("[data-role=invitation-destination-menu]");
    const destinationOptions = form.querySelector("[data-role=invitation-destination-options]");
    const copyButton = root.querySelector("[data-action=copy-invitation-link]");
    const issueButton = form.querySelector('button[type="submit"]');
    let issuing = false;
    const refreshQr = function () {
      if (issuing || result.hidden || qrResult.hidden || !link.value) return;
      try {
        renderInvitationQr(qrCanvas, link.value);
      } catch (error) {
        qrResult.hidden = true;
        setFeedback(feedback, "Invitation created. QR code unavailable; copy the invitation link instead.", "ok");
      }
    };
    if (window.MutationObserver) {
      new window.MutationObserver(refreshQr).observe(document.documentElement, {
        attributes: true, attributeFilter: ["data-theme", "data-resolved-theme", "data-lcd-palette"]
      });
    }
    // Initial and automatic theme changes can precede stylesheet readiness.
    document.addEventListener("load", function (event) {
      if (event.target.tagName === "LINK" && event.target.rel === "stylesheet") refreshQr();
    }, true);
    const forcedColors = window.matchMedia && window.matchMedia("(forced-colors: active)");
    if (forcedColors && forcedColors.addEventListener) forcedColors.addEventListener("change", refreshQr);
    addDestinationOption(destinationOptions, destination.dataset.sourceDestination);
    recentInvitationDestinations().forEach(function (recentDestination) {
      addDestinationOption(destinationOptions, recentDestination);
    });
    let destinationInitialized = false;
    const syncDestination = function () {
      const isIncluded = includeDestination.checked;
      if (isIncluded && !destinationInitialized) {
        if (!destination.value) destination.value = validInvitationDestination(destination.dataset.sourceDestination);
        destinationInitialized = true;
      }
      destination.disabled = !isIncluded;
      if (destinationFields) destinationFields.hidden = !isIncluded;
      if (!isIncluded && destinationMenu) destinationMenu.open = false;
      includeDestination.setAttribute("aria-expanded", String(isIncluded));
    };
    includeDestination.addEventListener("change", syncDestination);
    syncDestination();
    if (destinationOptions) destinationOptions.addEventListener("click", function (event) {
      const option = event.target && event.target.closest ? event.target.closest("[data-destination-value]") : null;
      if (!option) return;
      destination.value = option.dataset.destinationValue;
      if (destinationMenu) destinationMenu.open = false;
      destination.focus();
    });
    const selectInvitationLink = function () {
      link.select();
    };
    link.addEventListener("focus", selectInvitationLink);
    link.addEventListener("click", selectInvitationLink);
    if (copyButton) copyButton.addEventListener("click", async function () {
      try {
        await copyInvitationLink(link);
        setFeedback(feedback, "Invitation link copied.", "ok");
      } catch (error) {
        setFeedback(feedback, error && error.message || "Unable to copy invitation link.", "error");
      }
    });
    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      if (issuing) return;
      issuing = true;
      if (issueButton) issueButton.disabled = true;
      result.hidden = true;
      link.value = "";
      if (qrResult) qrResult.hidden = true;
      if (qrCanvas) qrCanvas.width = 0;
      setFeedback(feedback, "Creating invitation…", "ok");
      try {
        const signing = await ensureInvitationIdentity(root, feedback);
        const token = randomToken();
        const selectedDestination = includeDestination.checked ? String(destination.value || "") : "";
        const prepared = await post("/api/prepare_invitation", {
          action: "issue",
          verification_hash: await verificationHash(token),
          expires_at: new Date(Date.now() + 7 * 86400 * 1000).toISOString().replace(/\.\d{3}Z$/, "Z"),
          destination: selectedDestination,
        });
        if (!prepared || prepared.status !== "ok") throw new Error(prepared && prepared.error || "Unable to prepare invitation.");
        const signature = await signing.signCanonicalRecord(prepared.canonical_record);
        const finalized = await post("/api/create_prepared_invitation", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
          author_identity_id: (prepared.canonical_record.match(/^Author-Identity-ID: (.+)$/m) || ["", ""])[1],
          canonical_record: prepared.canonical_record, detached_signature: signature,
        });
        if (!finalized || finalized.status !== "ok") throw new Error(finalized && finalized.error || "Unable to create invitation.");
        rememberInvitationDestination(selectedDestination);
        addDestinationOption(destinationOptions, selectedDestination);
        link.value = window.location.origin + "/lobby/#invite=" + token;
        result.hidden = false;
        setFeedback(feedback, "Invitation created.", "ok");
        try {
          renderInvitationQr(qrCanvas, link.value);
          qrResult.hidden = false;
        } catch (error) {
          if (qrResult) qrResult.hidden = true;
          setFeedback(feedback, "Invitation created. QR code unavailable; copy the invitation link instead.", "ok");
        }
      } catch (error) {
        setFeedback(feedback, error && error.message || "Unable to create invitation.", "error");
      } finally {
        issuing = false;
        if (issueButton) issueButton.disabled = false;
      }
    });

    const revokeForm = root.querySelector("[data-invitation-revoke-form]");
    if (revokeForm) revokeForm.addEventListener("submit", async function (event) {
      event.preventDefault();
      try {
        const signing = await ensureInvitationIdentity(root, feedback);
        const prepared = await post("/api/prepare_invitation", {
          action: "revoke", invitation_id: String(revokeForm.elements.invitation_id.value || ""),
          verification_hash: String(revokeForm.elements.verification_hash.value || ""),
        });
        if (!prepared || prepared.status !== "ok") throw new Error(prepared && prepared.error || "Unable to prepare revocation.");
        const signature = await signing.signCanonicalRecord(prepared.canonical_record);
        const finalized = await post("/api/create_prepared_invitation", {
          prepare_token: prepared.prepare_token, post_id: prepared.post_id, record_path: prepared.record_path,
          author_identity_id: (prepared.canonical_record.match(/^Author-Identity-ID: (.+)$/m) || ["", ""])[1],
          canonical_record: prepared.canonical_record, detached_signature: signature,
        });
        if (!finalized || finalized.status !== "ok") throw new Error(finalized && finalized.error || "Unable to revoke invitation.");
        setFeedback(feedback, "Invitation revoked.", "ok");
      } catch (error) {
        setFeedback(feedback, error && error.message || "Unable to revoke invitation.", "error");
      }
    });
  });
})();
