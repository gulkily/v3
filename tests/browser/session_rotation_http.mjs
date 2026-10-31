// Run with: node tests/browser/session_rotation_http.mjs
// Requests that still carry the session ID a sign-in just replaced must not sign the browser out.
import assert from 'node:assert/strict';
import { mkdtemp, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';

const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const pgp = globalThis.openpgp;
const root = await mkdtemp(join(tmpdir(), 'session-rotation-'));
const alice = await pgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'alice' }], format: 'armored' });
const fingerprint = (await pgp.readKey({ armoredKey: alice.publicKey })).getFingerprint().toLowerCase();
const envelope = await pgp.encrypt({ message: await pgp.createMessage({ text: 'hello' }), encryptionKeys: await pgp.readKey({ armoredKey: alice.publicKey }), format: 'armored' });
const profiles = [{ name: 'alice', fingerprint, publicKey: alice.publicKey }];
const seeded = spawnSync('php', [join(project, 'tests/Support/private_message_browser_fixture.php'), root], { input: JSON.stringify({ profiles, incoming: envelope, outgoing: envelope, canonical_identity: profiles[0] }), encoding: 'utf8' });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/private_message_browser_router.php')], { env: { ...process.env, PRIVATE_MESSAGE_TEST_ROOT: root, FORUM_SECRETS_PATH: join(root, 'unused-secrets.php'), PRIVATE_MESSAGE_DATABASE_PATH: join(root, 'state/private/messages.sqlite3'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3'), FORUM_APPROVED_MEMBERS_ONLY: 'false' }, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });

const sessionCookie = response => (response.headers.getSetCookie().map(c => c.match(/^PHPSESSID=([^;]*)/)).find(Boolean) || [])[1];
const request = (path, id, options = {}) => fetch(base + path, { redirect: 'manual', ...options, headers: { ...(options.headers || {}), Origin: base, ...(id ? { Cookie: 'PHPSESSID=' + id } : {}) } });
const identity = 'openpgp:' + fingerprint;
async function signIn(id) {
  const challengeResponse = await request('/api/auth_challenge', id);
  id = sessionCookie(challengeResponse) || id;
  const challenge = (await challengeResponse.text()).match(/^challenge=([a-f0-9]+)$/m)[1];
  const signature = await pgp.sign({ message: await pgp.createMessage({ text: challenge }), signingKeys: await pgp.readPrivateKey({ armoredKey: alice.privateKey }), detached: true, format: 'armored' });
  const response = await request('/api/authenticate_identity', id, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ identity_id: identity, challenge, detached_signature: signature }).toString() });
  assert.equal(response.status, 200, await response.text());
  return { before: id, after: sessionCookie(response) };
}
const signedInAs = async id => (await (await request('/account/key/', id)).text()).match(/data-authenticated-identity-id="([^"]*)"/)[1];

try {
  for (let i = 0; i < 80; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  const { before: old, after: current } = await signIn(null);
  assert.ok(old && current && old !== current, 'sign-in moves the session to a new ID');
  assert.equal(await signedInAs(current), identity);

  // A page load still carrying the old ID stays signed in and is handed the current cookie, not a deletion.
  const page = await request('/account/key/', old);
  assert.match(await page.text(), new RegExp(`data-authenticated-identity-id="${identity}"`));
  assert.equal(sessionCookie(page), current, 'the old ID is redirected to the signed-in session');

  // An API request on the old ID (Messages here) acts in the signed-in session, without a new empty one.
  const unread = await request('/api/private_messages/unread', old);
  assert.equal(unread.status, 200, await unread.text());
  assert.notEqual(sessionCookie(unread) ?? current, '', 'never a cookie deletion');
  assert.ok([undefined, current].includes(sessionCookie(unread)), 'no replacement session');

  // Signing in again from the same browser forwards through both replacements.
  const again = await signIn(current);
  assert.equal(await signedInAs(old), identity);
  assert.equal(await signedInAs(again.before), identity);

  // Remove sent with an old ID signs out the session the browser actually holds.
  const removed = await request('/api/clear_identity', old, { method: 'POST' });
  assert.equal(removed.status, 200);
  assert.equal(await signedInAs(again.after), '', 'Remove on the old ID signed out the current session');

  // An unknown ID still gets nothing: no forwarding without a replaced session.
  assert.equal(await signedInAs('a'.repeat(26)), '');
  console.log('PASS session rotation: old ID forwards on page loads and API requests, chains across sign-ins, Remove signs out the current session, unknown IDs get nothing');
} catch (error) { console.error(serverLog.slice(-3000)); throw error; }
finally { server.kill(); await rm(root, { recursive: true, force: true }); }
