(function () {
  "use strict";

  var actionTypes = ["reaction", "reply", "thread"];
  var states = ["draft", "queued", "waiting_for_connection", "sending", "accepted", "rejected", "conflicted", "cancelled", "needs_attention"];
  var transitions = {
    draft: ["queued", "cancelled"],
    queued: ["waiting_for_connection", "sending", "cancelled", "needs_attention"],
    waiting_for_connection: ["queued", "sending", "cancelled"],
    sending: ["queued", "accepted", "rejected", "conflicted", "needs_attention"],
    accepted: [],
    rejected: ["draft", "cancelled"],
    conflicted: ["draft", "cancelled"],
    cancelled: [],
    needs_attention: ["draft", "queued", "cancelled"]
  };

  function validValue(values, value) {
    return values.indexOf(value) !== -1;
  }

  function requireItem(item) {
    if (!item || typeof item !== "object" || typeof item.id !== "string" || item.id === "") {
      throw new Error("Outbox items require a stable id.");
    }
    if (!validValue(actionTypes, item.action)) {
      throw new Error("Outbox item action is unsupported.");
    }
    if (!validValue(states, item.state)) {
      throw new Error("Outbox item state is unsupported.");
    }
  }

  function createItem(input) {
    var item = {
      id: input && input.id,
      action: input && input.action,
      state: input && input.state || "draft",
      createdAt: input && input.createdAt || new Date().toISOString(),
      updatedAt: input && input.updatedAt || input && input.createdAt || new Date().toISOString(),
      target: input && input.target || {},
      summary: input && input.summary || "",
      payload: input && input.payload || {},
      actionAt: input && input.actionAt || null,
      integrationAt: input && input.integrationAt || null,
      intent: input && input.intent || null,
      delivery: input && input.delivery || null,
      outcome: input && input.outcome || null
    };
    requireItem(item);
    return item;
  }

  function createIntentId(action) {
    var suffix = window.crypto && typeof window.crypto.randomUUID === "function"
      ? window.crypto.randomUUID()
      : Date.now().toString(36) + "-" + Math.random().toString(36).slice(2);
    return "outbox-" + action + "-" + suffix;
  }

  function canTransition(item, nextState) {
    requireItem(item);
    return transitions[item.state].indexOf(nextState) !== -1;
  }

  function transition(item, nextState, outcome, updatedAt) {
    if (!canTransition(item, nextState)) {
      throw new Error("Outbox item cannot transition from " + item.state + " to " + nextState + ".");
    }
    return Object.assign({}, item, {
      state: nextState,
      updatedAt: updatedAt || new Date().toISOString(),
      outcome: outcome || null
    });
  }

  function pendingCount(items) {
    return (items || []).filter(function (item) {
      requireItem(item);
      return ["accepted", "rejected", "cancelled"].indexOf(item.state) === -1;
    }).length;
  }

  function safeSummary(item) {
    requireItem(item);
    return {
      id: item.id,
      action: item.action,
      state: item.state,
      createdAt: item.createdAt,
      updatedAt: item.updatedAt,
      target: item.target || {},
      summary: item.summary || "",
      outcome: item.outcome || null
    };
  }

  window.forumOutbox = {
    actionTypes: actionTypes.slice(),
    states: states.slice(),
    createIntentId: createIntentId,
    createItem: createItem,
    canTransition: canTransition,
    transition: transition,
    pendingCount: pendingCount,
    safeSummary: safeSummary
  };
})();
