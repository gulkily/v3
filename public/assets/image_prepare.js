(function () {
  'use strict';
  const MAX_BYTES = 32 * 1024 * 1024, MAX_PIXELS = 40 * 1000 * 1000, MAX_SIDE = 16384;
  const OUTPUT_BYTES = 5 * 1024 * 1024, OUTPUT_SIDE = 2048;
  function dimensions(bytes) {
    const v = new DataView(bytes), b = new Uint8Array(bytes);
    const ascii = (at, n) => String.fromCharCode(...b.slice(at, at + n));
    if (b.length >= 24 && v.getUint32(0) === 0x89504e47 && ascii(12, 4) === 'IHDR') return [v.getUint32(16), v.getUint32(20), 'png'];
    if (b.length >= 10 && /^GIF8[79]a$/.test(ascii(0, 6))) return [v.getUint16(6, true), v.getUint16(8, true), 'gif'];
    if (b.length >= 30 && ascii(0, 4) === 'RIFF' && ascii(8, 4) === 'WEBP') {
      const type = ascii(12, 4);
      const uint24 = at => b[at] + (b[at + 1] << 8) + (b[at + 2] << 16);
      if (type === 'VP8X') return [uint24(24) + 1, uint24(27) + 1, 'webp'];
      if (type === 'VP8 ') return [v.getUint16(26, true) & 16383, v.getUint16(28, true) & 16383, 'webp'];
      if (type === 'VP8L' && b[20] === 0x2f) return [1 + (v.getUint32(21, true) & 16383), 1 + ((v.getUint32(21, true) >>> 14) & 16383), 'webp'];
    }
    if (b.length >= 54 && ascii(0, 2) === 'BM' && v.getUint32(14, true) >= 40) return [v.getInt32(18, true), Math.abs(v.getInt32(22, true)), 'bmp'];
    if (b.length >= 4 && b[0] === 255 && b[1] === 216) {
      for (let at = 2; at + 4 <= b.length;) {
        if (b[at++] !== 255) break;
        while (at < b.length && b[at] === 255) at++;
        const marker = b[at++];
        if (marker === 0xda || marker === 0xd9) break;
        if (at + 2 > b.length) break;
        const size = v.getUint16(at);
        if (size < 2 || at + size > b.length) break;
        if ([0xc0,0xc1,0xc2,0xc3,0xc5,0xc6,0xc7,0xc9,0xca,0xcb,0xcd,0xce,0xcf].includes(marker) && size >= 7) return [v.getUint16(at + 5), v.getUint16(at + 3), 'jpeg'];
        at += size;
      }
    }
    throw new Error('This image cannot be prepared here. Choose JPEG, PNG, WebP, GIF or BMP, or export your image to one of those formats.');
  }
  function bound(width, height) {
    if (!(width > 0 && height > 0) || Math.max(width, height) > MAX_SIDE || width * height > MAX_PIXELS) throw new Error('This image is too large to prepare safely. Export a copy below 40 megapixels and 16384 pixels per side.');
  }
  async function decode(file) {
    if (typeof createImageBitmap === 'function') return createImageBitmap(file, {imageOrientation:'from-image'});
    const url = URL.createObjectURL(file), img = new Image();
    try { img.src = url; await img.decode(); return img; } finally { URL.revokeObjectURL(url); }
  }
  async function prepare(file) {
    if (!file || !file.size || file.size > MAX_BYTES) throw new Error('Choose an image of at most 32 MiB for local preparation.');
    const [sourceWidth, sourceHeight, format] = dimensions(await file.arrayBuffer());
    bound(sourceWidth, sourceHeight);
    let bitmap, timer;
    const decoding = decode(file);
    try {
      bitmap = await Promise.race([decoding, new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('Image preparation timed out. Try a smaller copy.')), 15000); })]);
    } catch (error) {
      decoding.then(late => { if (late.close) late.close(); }).catch(() => {});
      throw new Error(error.message || 'Unable to decode this image; choose another file.');
    } finally { clearTimeout(timer); }
    const canvas = document.createElement('canvas');
    try {
      const width = bitmap.width || bitmap.naturalWidth, height = bitmap.height || bitmap.naturalHeight;
      bound(width, height);
      let ratio = Math.min(1, OUTPUT_SIDE / Math.max(width, height));
      const type = format === 'jpeg' ? 'image/jpeg' : 'image/png';
      for (let attempt = 0; attempt < 8; attempt++, ratio *= 0.8) {
        canvas.width = Math.max(1, Math.floor(width * ratio)); canvas.height = Math.max(1, Math.floor(height * ratio));
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Image preparation is unavailable on this device.');
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, type, 0.85));
        if (blob && blob.type === type && blob.size <= OUTPUT_BYTES) {
          const name = 'image.' + (type === 'image/jpeg' ? 'jpg' : 'png');
          return {file:new File([blob], name, {type}), width:canvas.width, height:canvas.height, note:'Prepared still image. Animated images use a single frame; only this preview will be uploaded.'};
        }
      }
      throw new Error('Unable to prepare this image within 5 MiB. Try a smaller copy.');
    } finally { if (bitmap.close) bitmap.close(); canvas.width = canvas.height = 0; }
  }
  window.ForumImagePrepare = {prepare};
})();
