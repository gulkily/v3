// Run an isolated qutebrowser with --basedir=$QUTE_TEST_BASEDIR and
// QTWEBENGINE_REMOTE_DEBUGGING=$QUTE_DEBUG_PORT, plus the bookmarklet PHP fixture.
import assert from 'node:assert/strict';
import {spawnSync} from 'node:child_process';
const port=process.env.QUTE_DEBUG_PORT||'18766';
const basedir=process.env.QUTE_TEST_BASEDIR||'/tmp/bookmarklet-qute-check';
const base=process.env.BOOKMARKLET_BASE_URL||'http://127.0.0.1:18765';
const targets=()=>fetch(`http://127.0.0.1:${port}/json/list`).then(r=>r.json());
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function attach(target) {
  const ws=new WebSocket(target.webSocketDebuggerUrl); await new Promise((r,j)=>{ws.onopen=r;ws.onerror=j;});
  let id=0; const pending=new Map();
  ws.onclose=()=>{for(const p of pending.values())p.reject(Error('qutebrowser debugger disconnected'));pending.clear();};
  ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(JSON.stringify(m.error))):p.resolve(m.result);}};
  const send=(method,params={})=>new Promise((resolve,reject)=>{pending.set(++id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
  const evaluate=async expression=>{
    const out=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true,userGesture:true});
    if(out.exceptionDetails)throw Error(JSON.stringify(out.exceptionDetails));
    return out.result.value;
  };
  return {evaluate,send,close:()=>ws.close()};
}
function command(...commands) {
  const p=spawnSync('qutebrowser',['--basedir',basedir,...commands],{encoding:'utf8',timeout:15000,env:{...process.env,QTWEBENGINE_REMOTE_DEBUGGING:'',QTWEBENGINE_CHROMIUM_FLAGS:'--no-sandbox --disable-gpu'}});
  assert.equal(p.status,0,p.stderr);
}
async function wait(fn,label) {for(let n=0;n<100;n++){try{const v=await fn();if(v)return v;}catch{}await delay(100);}throw Error('Timed out: '+label);}
let page=await attach((await targets()).find(t=>t.type==='page'));
try {
  command(':open '+base+'/tools/');
  await wait(()=>page.evaluate('!!document.querySelector(\'a[href="/tools/bookmarklets/"]\')'),'Tools');
  await page.evaluate('document.querySelector(\'a[href="/tools/bookmarklets/"]\').click()');
  await wait(()=>page.evaluate('document.querySelectorAll("[data-bookmarklet-copy]:enabled").length===7'),'Bookmarklets');
  const entries=await page.evaluate('Array.from(document.querySelectorAll("[data-bookmarklet]")).map(a=>({kind:a.dataset.bookmarkletKind,mode:a.dataset.bookmarkletMode,href:a.getAttribute("href")}))');
  for(let i=0;i<entries.length;i++) {
    if(i) {command(':open '+base+'/tools/bookmarklets/'); await wait(()=>page.evaluate('document.querySelectorAll("[data-bookmarklet-copy]:enabled").length===7'),'copy page');}
    await page.evaluate(`document.querySelectorAll('[data-bookmarklet-copy]')[${i}].click()`);
    await wait(()=>page.evaluate(`document.querySelectorAll('[data-bookmarklet-feedback]')[${i}].textContent==='Copied'`),'copy success');
    const clipboard=spawnSync('/usr/bin/python3',['-c','from PyQt5.QtWidgets import QApplication; app=QApplication([]); print(app.clipboard().text(), end="")'],{encoding:'utf8',timeout:10000});
    assert.equal(clipboard.status,0,clipboard.stderr);
    assert.equal(clipboard.stdout,entries[i].href);
    command(':bind ,b jseval --url '+clipboard.stdout);
    command(':open '+base+'/test-source');
    await wait(()=>page.evaluate('!!document.querySelector("#selection")'),'source');
    if(entries[i].kind!=='tweet')await page.evaluate('var r=document.createRange();r.selectNodeContents(document.querySelector("#selection"));getSelection().removeAllRanges();getSelection().addRange(r)');
    const previous=await targets();
    command(':fake-key --global ,b');
    const target=await wait(async()=> (await targets()).find(t=>t.url.startsWith(base+'/compose/thread?') && (entries[i].mode==='same-window'||!previous.some(p=>p.id===t.id))), 'binding navigation '+i);
    const result=new URL(target.url);
    assert.equal(result.searchParams.get('board_tags'),'general');
    const body=result.searchParams.get('body');
    if(entries[i].kind==='url')assert.equal(body,base+'/test-source');
    else if(entries[i].kind==='tweet')assert.equal(body,'Tweet 50% 😀\nsecond line\n\n— Author (@author)\n'+base+'/status/123');
    else {
      assert(body.startsWith('Selected "quote" 50% 😀\nsecond line'));
      if(entries[i].kind==='clip')assert(body.endsWith('\n\nSource 100% cafe\n'+base+'/test-source'));
    }
    const compose=entries[i].mode==='same-window'?page:await attach(target);
    await wait(()=>compose.evaluate('!!document.querySelector("textarea[name=body]")'),'Compose form');
    // Existing Compose templates indent multiline values; client text normalization may replace em dashes.
    const formBody=await compose.evaluate('document.querySelector("textarea[name=body]").value');
    assert.equal(formBody.split('\n').map(line=>line.trim()).join('\n').replaceAll('—','-'),body.replaceAll('—','-'));
    if(compose!==page){page.close();page=compose;}
    console.log('PASS qutebrowser clipboard binding:',entries[i].kind,entries[i].mode);
    // Continue in the newly opened window; keep all windows in this isolated profile.
  }
} finally {page.close();}
