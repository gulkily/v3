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

  // A centered dialog over a dimmed page. The dimming does not block clicks,
  // so the header and the theme menu stay usable.
  function dialog(ctx, name, label) {
    var shade = ctx.el("div", { class: "engagify-shade", "aria-hidden": "true", "data-engagify": name + "-shade" });
    var box = ctx.el("div", { class: "engagify-nag engagify-card engagify-dialog", role: "dialog", "aria-label": label, "data-engagify": name });
    return {
      box: box,
      open: function () {
        ctx.add(shade);
        ctx.add(box);
        var first = box.querySelector("button, input, textarea");
        if (first) first.focus();
      },
      close: function () {
        ctx.remove(box);
        ctx.remove(shade);
      }
    };
  }

  // Newsletter popup with a guilt-trip decline. Nothing is sent or stored.
  NAGS.push(function newsletter(ctx) {
    ctx.later(function () {
      var d = dialog(ctx, "newsletter", "Newsletter");
      var form = ctx.el("form", { class: "engagify-form" });
      var input = ctx.el("input", { type: "email", id: "engagify-email", autocomplete: "off", placeholder: "you@example.com" });
      form.appendChild(ctx.el("label", { for: "engagify-email", text: "Email" }));
      form.appendChild(input);
      form.appendChild(ctx.el("button", { type: "submit", class: "engagify-button", text: "Subscribe" }));
      form.addEventListener("submit", function (event) {
        event.preventDefault();
        input.value = "";
        d.close();
        toast(ctx, "Check your inbox! (We didn't send anything.)");
        ctx.later(function () { rateUs(ctx); }, 2500);
      });
      d.box.appendChild(ctx.exitButton());
      d.box.appendChild(ctx.el("h2", { text: "Don't miss a single meme" }));
      d.box.appendChild(ctx.el("p", { text: "Join 0 subscribers who get the best of ZenMemes in their inbox every hour." }));
      d.box.appendChild(form);
      d.box.appendChild(ctx.el("button", { type: "button", class: "engagify-link", text: "No thanks, I prefer being uninformed", onclick: function () {
        d.close();
        ctx.later(function () { rateUs(ctx); }, 2500);
      } }));
      d.open();
    }, 6000);
  });

  // Rate us: happy raters are sent to public reviews, unhappy ones to a
  // private box, and "Remind me later" brings it back.
  function rateUs(ctx) {
    var d = dialog(ctx, "rate", "Rate us");
    function later() {
      d.close();
      toast(ctx, "Got it! We'll ask again in 20 seconds.");
      ctx.later(function () { rateUs(ctx); }, 20000);
    }
    function show(nodes) {
      while (d.box.firstChild) d.box.removeChild(d.box.firstChild);
      d.box.appendChild(ctx.exitButton());
      nodes.forEach(function (node) { d.box.appendChild(node); });
      var focus = d.box.querySelector("[data-engagify-focus]") || d.box.querySelector("button");
      if (focus && d.box.parentNode) focus.focus();
    }
    function privateBox() {
      var text = ctx.el("textarea", { id: "engagify-feedback", rows: "3", placeholder: "Please be gentle", "data-engagify-focus": "" });
      show([
        ctx.el("h2", { text: "Oh no! We're so sorry." }),
        ctx.el("p", { text: "Instead of posting that publicly, why not tell us privately? We read every message." }),
        ctx.el("label", { for: "engagify-feedback", text: "What went wrong?" }),
        text,
        ctx.el("button", { type: "button", class: "engagify-button", text: "Send to /dev/null", onclick: function () {
          text.value = "";
          later();
        } })
      ]);
    }
    function fiveStars() {
      show([
        ctx.el("h2", { text: "You're amazing!" }),
        ctx.el("p", { text: "Would you mind leaving that same review on a few more sites? It only takes 45 minutes." }),
        ctx.el("ul", { class: "engagify-sites" }, ["App Store", "Play Store", "Trustpilot", "G2", "Yelp", "Your group chat"].map(function (site) {
          return ctx.el("li", { text: site });
        })),
        ctx.el("button", { type: "button", class: "engagify-link", text: "Remind me later", "data-engagify-focus": "", onclick: later })
      ]);
    }
    function stars() {
      var row = ctx.el("div", { class: "engagify-stars" });
      var buttons = [1, 2, 3, 4, 5].map(function (n) {
        var star = ctx.el("button", { type: "button", class: "engagify-star", "aria-label": n + (n === 1 ? " star" : " stars"), text: "☆", onclick: function () {
          if (n === 5) fiveStars();
          else privateBox();
        } });
        function fill(count) {
          buttons.forEach(function (b, i) { b.textContent = i < count ? "★" : "☆"; });
        }
        star.addEventListener("mouseenter", function () { fill(n); });
        star.addEventListener("focus", function () { fill(n); });
        star.addEventListener("mouseleave", function () { fill(0); });
        row.appendChild(star);
        return star;
      });
      show([
        ctx.el("h2", { text: "Rate your experience" }),
        ctx.el("p", { text: "Your honest feedback helps us grow. (Five stars is the honest answer.)" }),
        row,
        ctx.el("button", { type: "button", class: "engagify-link", text: "Remind me later", onclick: later })
      ]);
    }
    show([
      ctx.el("div", { class: "engagify-app-icon engagify-app-icon--large", "aria-hidden": "true", text: "Z" }),
      ctx.el("h2", { text: "Enjoying ZenMemes?" }),
      ctx.el("p", { text: "You've been here for a few seconds. That's long enough to know how you feel, right?" }),
      ctx.el("button", { type: "button", class: "engagify-button", text: "Yes, I love it!", "data-engagify-focus": "", onclick: stars }),
      ctx.el("button", { type: "button", class: "engagify-link", text: "Not really", onclick: privateBox })
    ]);
    d.open();
  }

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
