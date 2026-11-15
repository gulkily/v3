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
  // Photo details: a bounded EXIF reader. Any problem yields no details; it never blocks attaching.
  function exifTiff(bytes, format) {
    const b = new Uint8Array(bytes), v = new DataView(bytes);
    const ascii = (at, n) => String.fromCharCode(...b.slice(at, at + n));
    if (format === 'jpeg') {
      for (let at = 2; at + 4 <= b.length;) {
        if (b[at++] !== 255) return null;
        while (at < b.length && b[at] === 255) at++;
        const marker = b[at++];
        if (marker === 0xda || marker === 0xd9 || at + 2 > b.length) return null;
        const size = v.getUint16(at);
        if (size < 2 || at + size > b.length) return null;
        if (marker === 0xe1 && size > 8 && ascii(at + 2, 6) === 'Exif\0\0') return new DataView(bytes, at + 8, size - 8);
        at += size;
      }
    } else if (format === 'png') {
      for (let at = 8; at + 12 <= b.length;) {
        const size = v.getUint32(at), type = ascii(at + 4, 4);
        if (size > b.length - at - 12 || type === 'IDAT' || type === 'IEND') return null;
        if (type === 'eXIf') return new DataView(bytes, at + 8, size);
        at += size + 12;
      }
    } else if (format === 'webp') {
      for (let at = 12; at + 8 <= b.length;) {
        const size = v.getUint32(at + 4, true), type = ascii(at, 4);
        if (size > b.length - at - 8) return null;
        if (type === 'EXIF') {
          const skip = size > 6 && ascii(at + 8, 6) === 'Exif\0\0' ? 6 : 0;
          return new DataView(bytes, at + 8 + skip, size - skip);
        }
        at += 8 + size + (size & 1);
      }
    }
    return null;
  }
  function exifTags(t) {
    if (t.byteLength < 8) return null;
    const order = t.getUint16(0);
    if (order !== 0x4949 && order !== 0x4d4d) return null;
    const le = order === 0x4949;
    const u16 = at => t.getUint16(at, le), u32 = at => t.getUint32(at, le);
    if (u16(2) !== 42) return null;
    const sizes = {1: 1, 2: 1, 3: 2, 4: 4, 5: 8, 7: 1, 9: 4, 10: 8};
    const seen = new Set();
    function ifd(offset) {
      const tags = {};
      if (seen.has(offset) || offset < 8 || offset + 2 > t.byteLength) return tags;
      seen.add(offset);
      const count = u16(offset);
      if (count > 512 || offset + 2 + count * 12 > t.byteLength) return tags;
      for (let i = 0; i < count; i++) {
        const entry = offset + 2 + i * 12, type = u16(entry + 2), n = u32(entry + 4), unit = sizes[type];
        if (!unit || n > 4096) continue;
        const at = n * unit <= 4 ? entry + 8 : u32(entry + 8);
        if (at + n * unit > t.byteLength) continue;
        tags[u16(entry)] = {type, n, at};
      }
      return tags;
    }
    function text(tag) {
      if (!tag || tag.type !== 2) return '';
      let s = '';
      for (let i = 0; i < tag.n; i++) { const c = t.getUint8(tag.at + i); if (c === 0) break; s += String.fromCharCode(c); }
      return s.replace(/[\x00-\x1f\x7f]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 100);
    }
    function num(tag, index = 0) {
      if (!tag || index >= tag.n) return NaN;
      if (tag.type === 3) return u16(tag.at + index * 2);
      if (tag.type === 4) return u32(tag.at + index * 4);
      if (tag.type === 5 || tag.type === 10) {
        const at = tag.at + index * 8, signed = tag.type === 10;
        const top = signed ? t.getInt32(at, le) : u32(at), bottom = signed ? t.getInt32(at + 4, le) : u32(at + 4);
        return bottom === 0 ? NaN : top / bottom;
      }
      return NaN;
    }
    const zero = ifd(u32(4));
    const exif = zero[0x8769] ? ifd(num(zero[0x8769])) : {};
    const gps = zero[0x8825] ? ifd(num(zero[0x8825])) : {};
    return {zero, exif, gps, text, num};
  }
  const round = (n, places) => String(Number(n.toFixed(places)));
  function photoDetails(bytes, format) {
    try {
      const tiff = exifTiff(bytes, format);
      const parsed = tiff && exifTags(tiff);
      if (!parsed) return [];
      const {zero, exif, gps, text, num} = parsed, details = [];
      const add = (key, label, value, display) => { if (value) details.push({key, label, value, display: display || value, line: 'Photo-' + label + ': ' + value}); };
      const make = text(zero[0x010f]), model = text(zero[0x0110]);
      add('camera', 'Camera', model.toLowerCase().startsWith(make.toLowerCase()) ? model : (make + ' ' + model).trim());
      const focal = num(exif[0x920a]), focal35 = num(exif[0xa405]);
      add('lens', 'Lens', text(exif[0xa434]) || (focal35 > 0 ? round(focal35, 0) + ' mm' : focal > 0 ? round(focal, 1) + ' mm' : ''));
      const time = num(exif[0x829a]), stop = num(exif[0x829d]), iso = num(exif[0x8827]);
      add('exposure', 'Exposure', [
        time > 0 ? (time < 1 ? '1/' + Math.round(1 / time) : round(time, 1)) + ' s' : '',
        stop > 0 ? 'f/' + round(stop, 1) : '',
        iso > 0 ? 'ISO ' + iso : ''
      ].filter(Boolean).join(' · '));
      const taken = /^(\d{4}):(\d\d):(\d\d) (\d\d):(\d\d):(\d\d)$/.exec(text(exif[0x9003]));
      if (taken) {
        const offset = /^[+-]\d\d:\d\d$/.test(text(exif[0x9011])) ? text(exif[0x9011]) : '';
        add('taken', 'Taken', taken[1] + '-' + taken[2] + '-' + taken[3] + 'T' + taken[4] + ':' + taken[5] + ':' + taken[6] + offset, formatTaken(taken.slice(1).map(Number)));
      }
      const degrees = tag => num(tag, 0) + num(tag, 1) / 60 + num(tag, 2) / 3600;
      let lat = degrees(gps[2]), lon = degrees(gps[4]);
      if (text(gps[1]) === 'S') lat = -lat;
      if (text(gps[3]) === 'W') lon = -lon;
      if (Number.isFinite(lat) && Number.isFinite(lon) && Math.abs(lat) <= 90 && Math.abs(lon) <= 180 && (lat !== 0 || lon !== 0)) {
        add('location', 'Location', lat.toFixed(6) + ' ' + lon.toFixed(6), formatLocation(lat, lon));
      }
      return details;
    } catch (error) { return []; }
  }
  function formatTaken(parts) {
    const [y, mo, d, h, mi] = parts;
    if (mo < 1 || mo > 12 || d < 1 || d > 31 || h > 23 || mi > 59) return '';
    return new Date(Date.UTC(y, mo - 1, d, h, mi)).toLocaleString(undefined, {timeZone: 'UTC', year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'});
  }
  function formatLocation(lat, lon) {
    return Math.abs(lat).toFixed(4) + '° ' + (lat < 0 ? 'S' : 'N') + ', ' + Math.abs(lon).toFixed(4) + '° ' + (lon < 0 ? 'W' : 'E');
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
    if (!file || !file.size || file.size > MAX_BYTES) throw new Error('Attach an image of at most 32 MiB.');
    const sourceBytes = await file.arrayBuffer();
    const [sourceWidth, sourceHeight, format] = dimensions(sourceBytes);
    const details = photoDetails(sourceBytes, format);
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
          return {file:new File([blob], name, {type}), width:canvas.width, height:canvas.height, details, note:'Prepared still image. Animated images use a single frame; only this preview will be uploaded.'};
        }
      }
      throw new Error('Unable to prepare this image within 5 MiB. Try a smaller copy.');
    } finally { if (bitmap.close) bitmap.close(); canvas.width = canvas.height = 0; }
  }
  window.ForumImagePrepare = {prepare, photoDetails};
})();
