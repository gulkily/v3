(function () {
  'use strict';
  const base='/api/private_messages/history_sync/';
  let nav, account='', original='', generation=0, active=null, aborter=null, lastRun=0;
  const stats={state:'waiting',verified:0,contributed:0,unavailable:0};
  const verifiedIds=new Set(), candidateRequests=new Map();
  function identity() {
    try { return JSON.stringify(['username','public_key','private_key'].map(k=>localStorage.getItem('forum_pki_'+k)||'')); }
    catch (_) { return ''; }
  }
  function valid(run) { return generation===run && original===identity() && !document.hidden; }
  function publish(state) {
    stats.state=state;
    document.dispatchEvent(new CustomEvent('private-message-history-sync-state',{detail:{...stats}}));
  }
  async function request(action, input, run) {
    if (!valid(run)) throw Error('History identity changed.');
    const response=await fetch(base+action,{credentials:'same-origin',cache:'no-store',signal:aborter.signal,
      headers:{Accept:'application/json',...(input?{'Content-Type':'application/json','X-Requested-With':'ForumPrivateMessages'}:{})},
      ...(input?{method:'POST',body:JSON.stringify(input)}:{})});
    const result=await response.json();
    if (!valid(run)) throw Error('History identity changed.');
    if(!response.ok || result.status!=='ok') throw Error('History synchronization unavailable. Retry later.');
    return result;
  }
  async function candidates(messageId, envelope) {
    if(!nav || original!==identity() || document.hidden) return [];
    const crypto=window.ForumPrivateMessageHistoryCrypto, digest=crypto.digest(envelope), run=generation;
    const cacheKey=messageId+':'+digest;
    if(candidateRequests.has(cacheKey)) return candidateRequests.get(cacheKey);
    const promise=(async()=>{
      if(!aborter || aborter.signal.aborted) aborter=new AbortController();
      const privateKey=localStorage.getItem('forum_pki_private_key')||'';
      const pgp=await window.__forumBrowserIdentity.ensureOpenPgpApi(['readPrivateKey']);
      const target=(await pgp.readPrivateKey({armoredKey:privateKey})).getFingerprint().toLowerCase();
      const result=[];let after='';
      do {
        const page=await request('transfers?message_id='+encodeURIComponent(messageId)+'&after='+encodeURIComponent(after),null,run);
        if(page.account!==account || page.target!==target || page.digest!==digest || page.message_id!==messageId) throw Error('History binding changed.');
        for(const transfer of page.transfers) {
          try {
            if(transfer.account!==account || transfer.target!==target) continue;
            const entries=await crypto.unwrapHistoryKeys({account,source:transfer.source,target,privateKey,sourceKey:transfer.source_key,ciphertext:transfer.ciphertext});
            const entry=entries.find(row=>row.message_id===messageId && row.digest===digest);
            if(entry) result.push({keys:entry.keys,transfer_id:transfer.transfer_id});
          } catch (_) { /* One invalid donor must not block another candidate. */ }
        }
        after=page.next;
      } while(after && valid(run));
      if(!valid(run)) throw Error('History identity changed.');
      return result;
    })().finally(()=>candidateRequests.delete(cacheKey));
    candidateRequests.set(cacheKey,promise);return promise;
  }
  function confirm(messageId,envelope,transferId='') {
    if(!nav || original!==identity() || document.hidden) return;
    const digest=window.ForumPrivateMessageHistoryCrypto.digest(envelope);
    if(!aborter || aborter.signal.aborted) aborter=new AbortController();
    request('acknowledge',{items:[{message_id:messageId,digest,transfer_id:transferId}]},generation).then(()=>{
      const key=messageId+':'+digest;
      if(!verifiedIds.has(key)) {verifiedIds.add(key);stats.verified++;publish(stats.state);}
    }).catch(()=>{ /* Reading remains valid if a scheduling receipt is lost. */ });
  }
  async function ownKeys(row, privateKey) {
    const crypto=window.ForumPrivateMessageHistoryCrypto;
    if(crypto.digest(row.encrypted_envelope)!==row.digest) throw Error('Envelope changed.');
    try {
      const keys=await crypto.extractKeys(row.encrypted_envelope,privateKey);
      await crypto.readOriginal(row.encrypted_envelope,keys,row.sender_keys);
      return {keys,transfer_id:''};
    } catch (_) {
      for(const candidate of await candidates(row.message_id,row.encrypted_envelope)) {
        try {await crypto.readOriginal(row.encrypted_envelope,candidate.keys,row.sender_keys);return candidate;} catch (_) {}
      }
      throw Error('History not currently recoverable.');
    }
  }
  async function run() {
    const current=generation, crypto=window.ForumPrivateMessageHistoryCrypto;
    if(!crypto || !valid(current)) return;
    aborter=new AbortController();
    const deadline=Date.now()+10000;
    const timer=setTimeout(()=>aborter.abort(),15000);
    publish('working');
    try {
      const privateKey=localStorage.getItem('forum_pki_private_key')||'';
      if(!privateKey) { publish('waiting'); return; }
      const pgp=await window.__forumBrowserIdentity.ensureOpenPgpApi(['readPrivateKey']);
      const fingerprint=(await pgp.readPrivateKey({armoredKey:privateKey})).getFingerprint().toLowerCase();
      for(let batch=0;batch<8 && Date.now()<deadline;batch++) {
        const work=await request('work',null,current);
        if(work.account!==account || work.source!==fingerprint) throw Error('Saved key does not match authenticated identity.');
        const entries=[], receipts=[];
        let checkpoint=work.checkpoint;
        for(const row of work.messages) {
          if(!valid(current)) throw Error('History identity changed.');
          try {
            const result=await ownKeys(row,privateKey);
            receipts.push({message_id:row.message_id,digest:row.digest,transfer_id:result.transfer_id});
            if(!verifiedIds.has(row.message_id+':'+row.digest)) {verifiedIds.add(row.message_id+':'+row.digest);stats.verified++;}
            if(work.target!==fingerprint) entries.push({message_id:row.message_id,digest:row.digest,keys:result.keys});
          } catch (_) { stats.unavailable++; }
          if(Date.now()>=deadline) { checkpoint={target:work.target,after_id:row.message_id};break; }
        }
        if(entries.length) {
          const ciphertext=await crypto.wrapHistoryKeys({account,source:fingerprint,target:work.target,privateKey,targetKey:work.target_key,entries});
          await request('transfers',{target:work.target,ciphertext,items:entries.map(({message_id,digest})=>({message_id,digest}))},current);
          stats.contributed+=entries.length;
        }
        // Confirm our own verified access even while contributing to another target.
        await request('acknowledge',{items:receipts,checkpoint},current);
        publish('working');
        if(work.cycle_end) break;
      }
      publish(stats.unavailable?'waiting':'ready');
    } catch (_) { if(valid(current)) publish('error'); }
    finally { clearTimeout(timer); }
  }
  function refresh(force=false) {
    if(!nav || document.hidden) return Promise.resolve();
    if(original!==identity()) { invalidate();return Promise.resolve(); }
    if(active) return active;
    if(!force && Date.now()-lastRun<5000) return Promise.resolve();
    lastRun=Date.now();
    active=run().finally(()=>{active=null;});
    return active;
  }
  function invalidate() { generation++; if(aborter) aborter.abort();verifiedIds.clear();candidateRequests.clear();stats.verified=0;publish('identity-changed'); }
  window.ForumPrivateMessageHistorySync={refresh:()=>refresh(true),state:()=>({...stats}),candidates,confirm};
  document.addEventListener('DOMContentLoaded',function () {
    nav=document.querySelector('[data-private-message-unread]');
    if(!nav) return;
    account=nav.dataset.viewer;original=identity();
    window.addEventListener('pagehide',()=>{generation++;if(aborter)aborter.abort();});
    window.addEventListener('pageshow',()=>refresh());
    window.addEventListener('focus',()=>refresh());
    window.addEventListener('storage',event=>{if(event.key===null || ['forum_pki_username','forum_pki_public_key','forum_pki_private_key'].includes(event.key)) invalidate();});
    document.addEventListener('visibilitychange',()=>{if(document.hidden){generation++;if(aborter)aborter.abort();}else refresh();});
    refresh();
  });
})();
