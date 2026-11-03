import assert from 'node:assert/strict';
import {mkdtemp,readFile,rm,readdir} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join,resolve} from 'node:path';
import {runInThisContext} from 'node:vm';
import {spawn,spawnSync} from 'node:child_process';
import {createServer} from 'node:net';
import {once} from 'node:events';
import {chromium} from 'playwright-core';
const project=resolve(new URL('../..',import.meta.url).pathname);
runInThisContext(await readFile(join(project,'public/assets/openpgp.min.js'),'utf8'));
const pgp=globalThis.openpgp;
async function key(name){const k=await pgp.generateKey({type:'ecc',curve:'ed25519',userIDs:[{name}],format:'armored',date:new Date(Date.now()-60000)});return {...k,name,fp:(await pgp.readKey({armoredKey:k.publicKey})).getFingerprint()};}
const [operator,member,target,outsider,pending]=await Promise.all(['retirement-operator','retirement-browser','retirement-browser','retirement-other','retirement-browser'].map(key));
const root=await mkdtemp(join(tmpdir(),'key-retirement-browser-'));
let server,browser,logs='';
try {
 const seed=spawnSync('php',[join(project,'tests/Support/invite_browser_fixture.php'),root],{input:JSON.stringify({...operator,unapprovedPublicKeys:[member,target,outsider,pending].map(k=>k.publicKey)}),encoding:'utf8'});
 assert.equal(seed.status,0,seed.stderr);
 const socket=createServer().listen(0,'127.0.0.1');await once(socket,'listening');const port=socket.address().port;await new Promise(r=>socket.close(r));const base=`http://127.0.0.1:${port}`;
 server=spawn('php',['-d',`session.save_path=${root}/sessions`,'-S',`127.0.0.1:${port}`,join(project,'tests/Support/invite_browser_router.php')],{env:{...process.env,INVITE_TEST_ROOT:root,FORUM_SECRETS_PATH:join(root,'unused.php'),FORUM_APPROVED_MEMBERS_ONLY:'false',FORUM_USERNAME_CHANGES_ENABLED:'false',PRIVATE_MESSAGE_DATABASE_PATH:join(root,'messages.sqlite3'),PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH:join(root,'sync.sqlite3'),VISITOR_STATISTICS_DATABASE_PATH:join(root,'visitors.sqlite3')},stdio:['ignore','ignore','pipe']});
 server.stderr.on('data',d=>logs+=d);
 for(let i=0;i<60;i++){try{await fetch(base+'/api/version');break;}catch{await new Promise(r=>setTimeout(r,100));}}
 browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',args:['--no-sandbox']});
 async function context(k,js=true){const c=await browser.newContext({viewport:{width:390,height:844},javaScriptEnabled:js,serviceWorkers:'block'});if(k)await c.addInitScript(k=>{for(const [n,v]of Object.entries({username:k.name,public_key:k.publicKey,private_key:k.privateKey,fingerprint:k.fp,published_fingerprint:k.fp.toUpperCase(),approved_fingerprint:k.fp}))localStorage.setItem('forum_pki_'+n,v);},k);return c;}
 const op=await context(operator),page=await op.newPage();page.setDefaultTimeout(15000);
 await page.goto(base+'/account/key/');await page.waitForFunction(()=>document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId!=='');
 for(const k of [member,target,outsider]){
  const response=await op.request.post(base+'/api/prepare_approval',{form:{profile_slug:'openpgp-'+k.fp}});const prepared=await response.json();assert.equal(prepared.status,'ok',JSON.stringify(prepared));
  const signature=await pgp.sign({message:await pgp.createMessage({text:prepared.canonical_record}),signingKeys:await pgp.readPrivateKey({armoredKey:operator.privateKey}),detached:true});
  const result=await (await op.request.post(base+'/api/create_prepared_approval',{data:{...prepared,author_identity_id:'openpgp:'+operator.fp,detached_signature:signature}})).json();assert.equal(result.status,'ok',JSON.stringify(result));
 }
 const url=base+'/tools/user-access/profiles/openpgp-'+target.fp;
 // The same static control markup is used for all viewers; visibility is decided before it is parsed.
 for(const [k,visible] of [[null,false],[pending,false],[outsider,false],[target,false],[member,true],[operator,true]]){
  const c=await context(k);await c.addInitScript(()=>{window.retirementPaint=[];function frame(){const n=document.querySelector('[data-key-retirement]');if(n)window.retirementPaint.push(getComputedStyle(n).display!=='none');if(window.retirementPaint.length<4)requestAnimationFrame(frame);}requestAnimationFrame(frame);});const p=await c.newPage();await p.goto(url);assert.equal(await p.locator('[data-key-retirement]').isVisible(),visible,k?.name||'anonymous');await p.waitForFunction(()=>window.retirementPaint.length>0);assert.equal((await p.evaluate(()=>window.retirementPaint)).every(v=>v===visible),true,'No retirement control flash');await c.close();
 }
 const memberContext=await context(member),m=await memberContext.newPage();m.setDefaultTimeout(15000);
 await m.goto(base+'/account/key/');await m.waitForFunction(()=>document.querySelector('[data-private-site-auth-state]').dataset.authenticatedIdentityId!=='');
 await m.locator('[data-role="user-link"]').click();await m.locator(`a[href="/profiles/openpgp-${target.fp}"]`).click();await m.locator('.profile-approval-details').click();
 await m.locator('[data-retirement-review]').focus();await m.keyboard.press('Enter');await m.locator('[data-retirement-confirm]').waitFor({state:'visible'});
 assert.equal(await m.locator('[data-retirement-target]').textContent(),'openpgp:'+target.fp);
 assert.equal(await m.locator('[data-retirement-replacement]').textContent(),'openpgp:'+member.fp);
 const themes=(await readdir(join(project,'public/assets'))).map(f=>f.match(/^theme-([a-z0-9]+)\.css$/)?.[1]).filter(Boolean);
 for(const width of [390,1280])for(const theme of themes){
  await m.setViewportSize({width,height:900});await m.evaluate(theme=>{document.documentElement.dataset.theme=theme;document.querySelector('#theme-stylesheet').href='/assets/theme-'+theme+'.css';},theme);
  await m.waitForTimeout(60);assert.equal(await m.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${theme}/${width}`);
 }
 await m.screenshot({path:'/tmp/key-retirement-review.png',fullPage:true});
 await m.setViewportSize({width:390,height:844});await m.locator('[data-retirement-confirm]').scrollIntoViewIfNeeded();await m.screenshot({path:'/tmp/key-retirement-review-phone.png',fullPage:true});
 // A canceled review changes no authority and keeps browser keys intact.
 await m.locator('[data-retirement-cancel]').click();assert.equal((await (await fetch(base+'/api/key_retirement_status?target_identity_id=openpgp:'+target.fp)).json()).status,'not_retired');
 await m.locator('[data-retirement-review]').click();
 await m.route('**/api/finalize_key_retirement',async route=>{await route.fetch();await route.abort();},{times:1});
 await m.locator('[data-retirement-submit]').click();await m.getByRole('button',{name:'Retry retirement',exact:true}).waitFor();
 assert.equal((await (await fetch(base+'/api/key_retirement_status?target_identity_id=openpgp:'+target.fp)).json()).status,'retired');
 await m.getByRole('button',{name:'Retry retirement',exact:true}).click();await m.waitForFunction(()=>document.body.textContent.includes('This key is permanently retired.'));
 assert.equal((await (await fetch(base+'/api/key_retirement_status?target_identity_id=openpgp:'+target.fp)).json()).status,'retired');
 assert.equal(await m.evaluate(()=>localStorage.getItem('forum_pki_private_key')),member.privateKey);
 assert.equal(await m.locator('[data-signed-approval-form]').count(),0);
 await m.goto(base+'/tools/user-access/pending/');assert.equal(await m.locator(`a[href*="${target.fp}"]`).count(),0);
 await m.goto(base+'/user/'+member.name);assert.match(await m.locator('#account-keys').textContent(),/Retired/);
 await m.goto(base+'/forte/profiles/openpgp-'+target.fp);assert.match(await m.locator('body').textContent(),/Retired/);
 // Operator replaces the last unchanged key through the ordinary approval UI first.
 await page.goto(base+'/tools/user-access/profiles/openpgp-'+member.fp);await page.locator('[data-retirement-review]').click();await page.getByText('Approve an unchanged replacement key for this account before retiring this key.',{exact:true}).waitFor();
 page.on('dialog',dialog=>dialog.accept());
 await page.goto(base+'/tools/user-access/profiles/openpgp-'+pending.fp);await page.locator('[data-signed-approval-form] button').click();await page.waitForURL('**approval=success**');
 await page.goto(base+'/tools/user-access/profiles/openpgp-'+member.fp);await page.locator('[data-retirement-review]').click();await page.locator('[data-retirement-submit]').click();await page.waitForFunction(()=>document.body.textContent.includes('This key is permanently retired.'));
 const retired=await context(target),r=await retired.newPage();await r.goto(base+'/account/key/');await r.locator('[data-account-when="retired"]').waitFor({state:'visible'});
 const nojs=await context(null,false),n=await nojs.newPage();await n.goto(url);assert.match(await n.locator('body').textContent(),/permanently retired/);assert.equal(await n.locator('[data-retirement-submit]').count(),0);
 console.log(`PASS retirement: Account and operator replacement-first journeys, six roles, first paint, keyboard review, lost-response retry, retained history, retired Account recovery, JS disabled, ${themes.length} themes at phone/desktop widths.`);
} catch(error){console.error(logs.slice(-4000));throw error;}
finally{await browser?.close();server?.kill();await rm(root,{recursive:true,force:true});}
