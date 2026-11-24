(function () {
  "use strict";
  // Draws a QR code on a canvas in its computed text/background colors when they contrast
  // enough (7:1), else black on white. Shared by invitations and private-access confirms.
  function colors(canvas) {
    const fallback = ["#000", "#fff"];
    if (!window.getComputedStyle || (window.matchMedia && window.matchMedia("(forced-colors: active)").matches)) return fallback;
    const style = window.getComputedStyle(canvas);
    const resolved = [style.color, style.backgroundColor].map(function (color) {
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
    if (resolved.some(color => !color)) return fallback;
    resolved.sort((a, b) => a.luminance - b.luminance);
    return (resolved[1].luminance + 0.05) / (resolved[0].luminance + 0.05) >= 7
      ? resolved.map(color => color.color) : fallback;
  }

  function render(canvas, value) {
    const qr = window.qrcode(0, "M");
    qr.addData(value);
    qr.make();
    const modules = qr.getModuleCount();
    const cell = 4;
    const margin = 4;
    const [dark, light] = colors(canvas);
    canvas.width = canvas.height = (modules + margin * 2) * cell;
    const context = canvas.getContext("2d");
    context.fillStyle = light;
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = dark;
    for (let row = 0; row < modules; row += 1) {
      for (let col = 0; col < modules; col += 1) {
        if (qr.isDark(row, col)) context.fillRect((col + margin) * cell, (row + margin) * cell, cell, cell);
      }
    }
  }

  window.ForumQrCanvas = { render: render, colors: colors };
})();
