// Photo details: the composer checklist (image_upload.js + compose_disclosure.js) and reply retry
// keeping the chosen lines (reply_continuation.js). Run: node tests/browser/image_photo_details.mjs
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {chromium} from 'playwright-core';

const partial = readFileSync('templates/partials/image_compose_fields.php', 'utf8').replace(/<\?php[\s\S]*?\?>/g, '');
const browser = await chromium.launch({executablePath:process.env.BROWSER_PATH || '/usr/bin/google-chrome', headless:true, args:['--no-sandbox']});
try {
  const page = await browser.newPage({viewport:{width:390, height:844}, hasTouch:true});
  // Reply continuation needs navigator.locks, which needs a secure context.
  await page.route('https://photo-details.test/', route => route.fulfill({contentType:'text/html', body:'<!doctype html><body></body>'}));
  await page.goto('https://photo-details.test/');
  await page.setContent(`<form data-phone-reply><textarea name="body"></textarea><input name="thread_id" value="t1"><input name="parent_id" value="p1">${partial}</form>
    <div id="root"><div data-reply-panel></div></div>`);
  for (const path of ['image_prepare.js', 'image_upload.js', 'compose_disclosure.js', 'reply_continuation.js']) await page.addScriptTag({path:'public/assets/' + path});

  const result = await page.evaluate(async () => {
    // Minimal big-endian EXIF: IFD0 with Make, Model and a GPS pointer; GPS IFD with N/W coordinates.
    const be16 = n => [n >> 8 & 255, n & 255], be32 = n => [n >>> 24 & 255, n >> 16 & 255, n >> 8 & 255, n & 255];
    const ascii = s => [...s].map(c => c.charCodeAt(0)).concat([0]);
    const make = ascii('Apple'), model = ascii('iPhone 15 Pro');
    const ifd0 = 8, gpsAt = ifd0 + 2 + 3 * 12 + 4, dataAt = gpsAt + 2 + 4 * 12 + 4;
    const lat = [42, 1, 21, 1, 1296, 100], lon = [71, 1, 7, 1, 12, 100];
    const extra = [...make, ...model, ...lat.flatMap(be32), ...lon.flatMap(be32)];
    const at = {make:dataAt, model:dataAt + make.length, lat:dataAt + make.length + model.length, lon:dataAt + make.length + model.length + 24};
    const tiff = new Uint8Array([0x4d, 0x4d, ...be16(42), ...be32(ifd0),
      ...be16(3), ...be16(0x010f), ...be16(2), ...be32(make.length), ...be32(at.make), ...be16(0x0110), ...be16(2), ...be32(model.length), ...be32(at.model),
      ...be16(0x8825), ...be16(4), ...be32(1), ...be32(gpsAt), ...be32(0),
      ...be16(4), ...be16(1), ...be16(2), ...be32(2), 0x4e, 0, 0, 0, ...be16(2), ...be16(5), ...be32(3), ...be32(at.lat),
      ...be16(3), ...be16(2), ...be32(2), 0x57, 0, 0, 0, ...be16(4), ...be16(5), ...be32(3), ...be32(at.lon), ...be32(0), ...extra]);
    const canvas = document.createElement('canvas'); canvas.width = 8; canvas.height = 4;
    const jpeg = new Uint8Array(await (await new Promise(r => canvas.toBlob(r, 'image/jpeg'))).arrayBuffer());
    const app1 = [0xff, 0xe1, ...be16(tiff.length + 8), 0x45, 0x78, 0x69, 0x66, 0, 0];
    const photo = new File([jpeg.slice(0, 2), new Uint8Array(app1), tiff, jpeg.slice(2)], 'photo.jpg', {type:'image/jpeg'});
    const plain = new File([jpeg], 'plain.jpg', {type:'image/jpeg'});

    const form = document.querySelector('form'), details = form.querySelector('[data-compose-disclosure="photo"]');
    const summary = () => details.querySelector('[data-role="compose-disclosure-summary"]').textContent;
    ForumImageUpload.bind(form);
    form.__imageEligible = true;
    const out = {};
    ForumImageUpload.select(form, photo); await ForumImageUpload.ready(form); await new Promise(r => setTimeout(r, 0));
    out.shown = !details.hidden; out.open = details.open;
    out.labels = [...details.querySelectorAll('[data-photo-detail]')].map(box => box.dataset.label);
    out.ticked = details.querySelectorAll('[data-photo-detail]:checked').length;
    out.warning = details.querySelector('.image-photo-detail-warning')?.textContent;
    out.noneChosen = ForumImageUpload.chosenLines(form);
    out.emptySummary = summary();
    details.querySelector('[data-photo-detail="camera"]').click();
    out.cameraSummary = summary();
    out.cameraLines = ForumImageUpload.chosenLines(form);
    ForumImageUpload.select(form, plain); await ForumImageUpload.ready(form); await new Promise(r => setTimeout(r, 0));
    out.plainHidden = details.hidden; out.plainLines = ForumImageUpload.chosenLines(form); out.plainSummary = summary();

    // Reply retry: the first prepare fails; Retry must reload the item and send the same lines.
    const seen = [];
    let fail = true;
    const continuation = ForumReplyContinuation.bind(form, document.getElementById('root'), {
      card: () => { const card = document.createElement('article'); card.innerHTML = '<p data-role="pending-reply-status"></p>'; return card; },
      clearImage() {}, clearDraft() {}, bindPost() {}, renderPost: async () => { throw new Error('offline'); },
      confirm: async () => false,
      prepare: async (fields, attachment, description, photoLines) => {
        seen.push(photoLines);
        if (fail) { fail = false; throw new Error('network'); }
        return {fields:Object.assign({}, fields), prepared:{postId:'x'}};
      },
      finalize: async () => ({ok:true, postId:'x'})
    });
    out.bound = Boolean(continuation);
    if (continuation) {
      continuation.send({body:'hi', thread_id:'t1', parent_id:'p1'}, plain, '', ['Photo-Camera: Apple iPhone 15 Pro']);
      await new Promise(r => setTimeout(r, 200));
      document.querySelector('[data-reply-operation] button').click();
      await new Promise(r => setTimeout(r, 200));
      out.retryLines = seen;
    }
    return out;
  });

  assert.equal(result.shown, true);
  assert.equal(result.open, false, 'Photo details starts collapsed');
  assert.deepEqual(result.labels, ['Camera', 'Location']);
  assert.equal(result.ticked, 0, 'nothing is ticked by default');
  assert.equal(result.warning, 'Shows where this photo was taken.');
  assert.deepEqual(result.noneChosen, []);
  assert.equal(result.emptySummary, '');
  assert.equal(result.cameraSummary, 'Camera');
  assert.deepEqual(result.cameraLines, ['Photo-Camera: Apple iPhone 15 Pro']);
  assert.equal(result.plainHidden, true, 'a photo without metadata shows no Photo details row');
  assert.deepEqual(result.plainLines, []);
  assert.equal(result.plainSummary, '');
  assert.equal(result.bound, true);
  assert.deepEqual(result.retryLines, [['Photo-Camera: Apple iPhone 15 Pro'], ['Photo-Camera: Apple iPhone 15 Pro']]);
  console.log('PASS photo details: collapsed and unticked by default, location warning, summary, chosen lines, no-metadata photo, reply retry keeps lines');
} finally { await browser.close(); }
