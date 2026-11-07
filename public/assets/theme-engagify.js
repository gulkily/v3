/*
 * Engagify theme script: a parody of the engagement-optimized web.
 * Registered with theme_toggle.js; runs only while Engagify is the active
 * theme. Each nag is a function in NAGS that receives a context and adds its
 * elements and timers through it, so stop() removes everything at once.
 */
(function () {
  var registry = window.forumThemeScripts;
  if (!registry) return;

  var NAGS = [];
  var context = null;

  function createContext() {
    var nodes = [];
    var timers = [];
    var stopped = false;

    function el(tag, attributes, children) {
      var node = document.createElement(tag);
      Object.keys(attributes || {}).forEach(function (name) {
        if (name === "text") node.textContent = attributes[name];
        else if (name === "onclick") node.addEventListener("click", attributes[name]);
        else node.setAttribute(name, attributes[name]);
      });
      (children || []).forEach(function (child) {
        if (child) node.appendChild(child);
      });
      return node;
    }

    return {
      el: el,
      add: function (node) {
        if (stopped) return node;
        nodes.push(node);
        // Inside the app shell, nags inherit its phone tab bar offset.
        (document.querySelector("[data-shell]") || document.body).appendChild(node);
        return node;
      },
      remove: function (node) {
        if (node && node.parentNode) node.parentNode.removeChild(node);
      },
      later: function (callback, delay) {
        if (stopped) return;
        timers.push(window.setTimeout(function () {
          if (!stopped) callback();
        }, delay));
      },
      exit: function () {
        if (typeof window.forumExitTheme === "function") window.forumExitTheme();
      },
      exitButton: function (label) {
        return el("button", { type: "button", class: "engagify-link", text: label || "Exit Engagify", onclick: function () {
          if (typeof window.forumExitTheme === "function") window.forumExitTheme();
        } });
      },
      stop: function () {
        stopped = true;
        timers.forEach(function (timer) { window.clearTimeout(timer); });
        nodes.forEach(function (node) {
          if (node.parentNode) node.parentNode.removeChild(node);
        });
        nodes = [];
        timers = [];
      }
    };
  }

  // Exit: always on screen while Engagify is active.
  NAGS.push(function exitPill(ctx) {
    ctx.add(ctx.el("button", {
      type: "button",
      class: "engagify-exit",
      "data-engagify": "exit",
      text: "Exit Engagify",
      onclick: ctx.exit
    }));
  });

  // A small toast, used by nags to answer clicks.
  function toast(ctx, text) {
    var node = ctx.add(ctx.el("div", { class: "engagify-nag engagify-toast", role: "status", "data-engagify": "toast", text: text }));
    ctx.later(function () { ctx.remove(node); }, 4000);
  }

  // "Open in app": a bar at the top that pushes the page down, for an app
  // that does not exist.
  NAGS.push(function appBar(ctx) {
    var bar = ctx.el("div", { class: "engagify-nag engagify-app-bar", role: "region", "aria-label": "Get the app", "data-engagify": "app-bar" });
    bar.appendChild(ctx.exitButton("Exit"));
    bar.appendChild(ctx.el("div", { class: "engagify-app-icon", "aria-hidden": "true", text: "Z" }));
    bar.appendChild(ctx.el("p", {}, [
      ctx.el("strong", { text: "ZenMemes is better in the app" }),
      document.createTextNode(" 4.9 stars from 0 reviews")
    ]));
    bar.appendChild(ctx.el("button", { type: "button", class: "engagify-button", text: "Open", onclick: function () {
      toast(ctx, "App not found. Have you tried installing the app?");
    } }));
    bar.appendChild(ctx.el("button", { type: "button", class: "engagify-close", "aria-label": "Close", text: "×", onclick: function () {
      ctx.remove(bar);
    } }));
    var shell = document.querySelector("[data-shell]") || document.body;
    ctx.add(bar);
    shell.insertBefore(bar, shell.firstChild);
  });

  // Cookie banner: one big button, one small one.
  NAGS.push(function cookieBanner(ctx) {
    ctx.later(function () {
      var banner = ctx.el("div", { class: "engagify-nag engagify-card engagify-cookie", role: "region", "aria-label": "Cookie notice", "data-engagify": "cookie" });
      var body = ctx.el("div", { class: "engagify-cookie-body" }, [
        ctx.el("h2", { text: "We value your privacy" }),
        ctx.el("p", { text: "We use one cookie, to keep you signed in. Our 847 trusted partners would like to use the rest." })
      ]);
      var accept = ctx.el("button", { type: "button", class: "engagify-button", text: "Accept all", onclick: function () {
        ctx.remove(banner);
      } });
      var manage = ctx.el("button", { type: "button", class: "engagify-link", text: "Manage preferences", onclick: function () {
        body.lastChild.textContent = "Your preferences are saved when you select Accept all.";
        accept.focus();
      } });
      banner.appendChild(ctx.exitButton());
      banner.appendChild(body);
      banner.appendChild(ctx.el("div", { class: "engagify-actions" }, [accept, manage]));
      ctx.add(banner);
    }, 800);
  });

  // A fake browser notification prompt.
  NAGS.push(function notificationPrompt(ctx) {
    ctx.later(function () {
      var prompt = ctx.el("div", { class: "engagify-nag engagify-card engagify-permission", role: "dialog", "aria-label": "Notifications", "data-engagify": "notifications" });
      var close = function (message) {
        ctx.remove(prompt);
        if (message) toast(ctx, message);
      };
      prompt.appendChild(ctx.exitButton());
      prompt.appendChild(ctx.el("p", {}, [
        ctx.el("strong", { text: location.host || "This site" }),
        document.createTextNode(" wants to show notifications")
      ]));
      prompt.appendChild(ctx.el("div", { class: "engagify-actions" }, [
        ctx.el("button", { type: "button", class: "engagify-link", text: "Block", onclick: function () { close("We'll ask again tomorrow."); } }),
        ctx.el("button", { type: "button", class: "engagify-button", text: "Allow", onclick: function () { close("Notifications enabled. (They weren't.)"); } })
      ]));
      ctx.add(prompt);
    }, 3000);
  });

  registry.register("engagify", {
    start: function () {
      if (context) context.stop();
      context = createContext();
      NAGS.forEach(function (nag) {
        try {
          nag(context);
        } catch (error) {
          if (window.console) window.console.error(error);
        }
      });
    },
    stop: function () {
      if (context) context.stop();
      context = null;
    }
  });

})();
