// Attach image eligibility (image_upload.js): a key confirmed allowed before is offered Attach image
// before the profile check answers; a "no" answer hides it and forgets the key.
// Run: node tests/browser/image_upload_eligibility.mjs
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {chromium} from 'playwright-core';

const partial = readFileSync('templates/partials/image_compose_fields.php', 'utf8').replace(/<\?php[\s\S]*?\?>/g, '');
const mine = 'openpgp:' + 'a'.repeat(40), other = 'openpgp:' + 'b'.repeat(40);
const browser = await chromium.launch({executablePath:process.env.BROWSER_PATH || '/usr/bin/google-chrome', headless:true, args:['--no-sandbox']});
try {
  const page = await browser.newPage({viewport:{width:390, height:844}});
  let approved = 'yes', release;
  await page.route('https://eligibility.test/', route => route.fulfill({contentType:'text/html', body:'<!doctype html><body data-image-content-state="1"></body>'}));
  await page.route('https://eligibility.test/api/get_profile**', async route => {
    const id = new URL(route.request().url()).searchParams.get('profile_slug').replace('-', ':');
    await new Promise(resolve => { release = resolve; });
    await route.fulfill({contentType:'text/plain', body:`Identity-ID: ${id}\nApproved: ${approved}\nImage-Upload-Allowed: ${approved}\n`});
  });
  await page.goto('https://eligibility.test/');
  await page.addScriptTag({path:'public/assets/image_prepare.js'});
  await page.addScriptTag({path:'public/assets/image_upload.js'});

  // Binds a fresh composer as identity `id`, then reports whether Attach image is offered
  // while the profile request is still waiting, and again after it answers.
  const open = async id => {
    await page.evaluate(([html, id]) => {
      document.body.innerHTML = `<form>${html}</form>`;
      ForumImageUpload.bind(document.querySelector('form'), async () => id);
    }, [partial, id]);
    await new Promise(resolve => { const wait = () => release ? resolve() : setTimeout(wait, 10); wait(); });
    const before = await page.evaluate(() => !document.querySelector('.image-upload-choose').hidden);
    const done = release; release = null; done();
    await page.waitForTimeout(100);
    const after = await page.evaluate(() => !document.querySelector('.image-upload-choose').hidden);
    const stored = await page.evaluate(() => localStorage.getItem('forum_image_eligible_identity'));
    return {before, after, stored};
  };

  assert.deepEqual(await open(mine), {before:false, after:true, stored:mine}, 'first visit waits for the check');
  assert.deepEqual(await open(mine), {before:true, after:true, stored:mine}, 'a confirmed key is offered before the check answers');
  assert.deepEqual(await open(other), {before:false, after:true, stored:other}, 'another key is not offered early');
  approved = 'no';
  assert.deepEqual(await open(other), {before:true, after:false, stored:null}, 'a "no" answer hides it and forgets the key');
  approved = 'yes';
  assert.deepEqual(await open(other), {before:false, after:true, stored:other}, 'after a "no", the next visit waits again');
  console.log('image_upload_eligibility: ok');
} finally {
  await browser.close();
}
