(function () {
  'use strict';
  const base='/api/private_messages/history_sync/';
  let nextMode='account', combinedSupported=true;
  let nav, account='', original='', generation=0, active=null, aborter=null, lastRun=0, listening=false;
  const stats={state:'waiting',verified:0,contributed:0,unavailable:0,pending:false};
  const verifiedIds=new Set(), candidateRequests=new Map(), originals=new Map(), confirmed=new Set(), confirming=new Set();
  let workRevision='', confirmationTimer=null;
  const confirmationQueue=new Map();
  function clearConfirmations(){clearTimeout(confirmationTimer);confirmationTimer=null;confirmationQueue.clear();confirming.clear();}
  function clearRecovery() {originals.clear();candidateRequests.clear();}
  function remember(set,key) {if(set.size>=256)set.delete(set.values().next().value);set.add(key);}
  let requestsInFlight=0;const requestWaiters=[];
  let turn=null, continuationTimer=null, retryAt=0;
  const completedWork=new Set(), candidateCursors=new Map();
  function budgetError(){const error=Error('History turn exhausted.');error.budget=true;return error;}
  function cancelContinuation(){clearTimeout(continuationTimer);continuationTimer=null;stats.pending=false;}
  function continueLater(run){
    cancelContinuation();
    if(!valid(run))return;
    stats.pending=true;
    continuationTimer=setTimeout(()=>{continuationTimer=null;if(valid(run))refresh(true,true);},Math.max(1000,retryAt-Date.now()));
  }

  function identity() {
    try { return JSON.stringify(['username','public_key','private_key'].map(k=>localStorage.getItem('forum_pki_'+k)||'')); }
    catch (_) { return ''; }
  }
  function valid(run) { return !!nav && !window.ForumPrivateMessageAvailability.isDisabled() && generation===run && original===identity() && !document.hidden; }
  function publish(state) {
    stats.state=state;
    document.dispatchEvent(new CustomEvent('private-message-history-sync-state',{detail:{...stats}}));
  }
  async function request(action, input, run) {
    const budget=turn;
    if(requestsInFlight>=3) await new Promise(resolve=>requestWaiters.push(resolve));
    else requestsInFlight++;
    try {
      for(let attempt=0;attempt<2;attempt++) {
        if(!valid(run) || !aborter || aborter.signal.aborted) throw Error('History identity changed.');
        if(budget && (budget!==turn || budget.attempts>=16 || Date.now()>=budget.deadline))throw budgetError();
        if(budget)budget.attempts++;
        const lifecycle=aborter, controller=new AbortController();
        const cancel=()=>controller.abort();lifecycle.signal.addEventListener('abort',cancel,{once:true});
        const timeout=setTimeout(cancel,5000);
        try {
          const response=await window.ForumPrivateMessageAvailability.request(base+action,{credentials:'same-origin',cache:'no-store',signal:controller.signal,
            headers:{Accept:'application/json',...(input?{'Content-Type':'application/json','X-Requested-With':'ForumPrivateMessages'}:{})},
            ...(input?{method:'POST',body:JSON.stringify(input)}:{})});
          const result=await response.json();
          if(!valid(run)) throw Error('History identity changed.');
          if(!response.ok || result.status!=='ok') {
            if(response.status===503 && attempt===0) continue;
            const error=Error('History synchronization unavailable. Retry later.');
            error.status=response.status;
            if(response.status===429){
              const header=response.headers?.get('Retry-After'), seconds=Number(header);
              retryAt=Date.now()+(header && Number.isFinite(seconds)?Math.max(1,seconds)*1000:10000);
            }
            throw error;
          }
          return result;
        } catch(error) { if(error.budget || attempt!==0 || !valid(run) || lifecycle.signal.aborted || (error.status && error.status!==503)) throw error; }
        finally {clearTimeout(timeout);lifecycle.signal.removeEventListener('abort',cancel);}
      }
      throw Error('History synchronization unavailable.');
    } finally {const next=requestWaiters.shift();if(next)next();else requestsInFlight--;}
  }
  async function candidates(messageId, envelope, run=generation, visit=null) {
    if(!valid(run)) return [];
    const crypto=window.ForumPrivateMessageHistoryCrypto, digest=crypto.digest(envelope);
    const cacheKey=messageId+':'+digest+(visit?':work':'');
    if(candidateRequests.has(cacheKey)) return candidateRequests.get(cacheKey);
    if(!visit)candidateCursors.delete(cacheKey);
    const promise=(async()=>{
      if(!aborter || (aborter.signal.aborted && !active)) aborter=new AbortController();
      const privateKey=localStorage.getItem('forum_pki_private_key')||'';
      const pgp=await window.__forumBrowserIdentity.ensureOpenPgpApi(['readPrivateKey']);
      const target=(await pgp.readPrivateKey({armoredKey:privateKey})).getFingerprint().toLowerCase();
      const result=[];let after=candidateCursors.get(cacheKey)||'';
      do {
        const page=await request('transfers?message_id='+encodeURIComponent(messageId)+'&after='+encodeURIComponent(after),null,run);
        if(page.account!==account || page.target!==target || page.digest!==digest || page.message_id!==messageId) throw Error('History binding changed.');
        for(const transfer of page.transfers) {
          try {
            if(transfer.account!==account || transfer.target!==target) continue;
            const entries=await crypto.unwrapHistoryKeys({account,source_account:transfer.source_account,source:transfer.source,target,privateKey,sourceKey:transfer.source_key,ciphertext:transfer.ciphertext});
            const entry=entries.find(row=>row.message_id===messageId && row.digest===digest);
            if(entry) {
              const candidate={keys:entry.keys,transfer_id:transfer.transfer_id};
              if(visit) {if(await visit(candidate))return [candidate];}
              else result.push(candidate);
            }
          } catch (_) { /* One invalid donor must not block another candidate. */ }
        }
        after=page.next;
        if(after){if(candidateCursors.size>=128)candidateCursors.delete(candidateCursors.keys().next().value);candidateCursors.set(cacheKey,after);}else candidateCursors.delete(cacheKey);
      } while(after && valid(run));
      if(!valid(run)) throw Error('History identity changed.');
      return result;
    })().finally(()=>{if(candidateRequests.get(cacheKey)===promise)candidateRequests.delete(cacheKey);});
    candidateRequests.set(cacheKey,promise);return promise;
  }
  function confirm(messageId,envelope,transferId='') {
    if(window.ForumPrivateMessageAvailability.isDisabled() || !nav || original!==identity() || document.hidden) return;
    const digest=window.ForumPrivateMessageHistoryCrypto.digest(envelope), key=messageId+':'+digest;
    if(confirmed.has(key) || confirming.has(key))return;
    if(confirmationQueue.size>=256)return;
    confirming.add(key);confirmationQueue.set(key,{message_id:messageId,digest,transfer_id:transferId});
    if(confirmationTimer===null)confirmationTimer=setTimeout(flushConfirmations,50);
  }
  async function flushConfirmations() {
    confirmationTimer=null;
    const run=generation,batch=Array.from(confirmationQueue).slice(0,10);
    for(const [key] of batch)confirmationQueue.delete(key);
    try {
      if(!valid(run))return;
      if(!aborter || (aborter.signal.aborted && !active))aborter=new AbortController();
      await request('acknowledge',{items:batch.map(([,item])=>item)},run);
      if(!valid(run))return;
      for(const [key] of batch){remember(confirmed,key);if(!verifiedIds.has(key)){remember(verifiedIds,key);stats.verified++;}}
      publish(stats.state);
    } catch (_) { /* A subsequent verified read can retry a lost receipt. */ }
    finally {
      for(const [key] of batch)confirming.delete(key);
      if(confirmationQueue.size && valid(run) && confirmationTimer===null)confirmationTimer=setTimeout(flushConfirmations,50);
    }
  }
  async function ownKeys(row, privateKey, run) {
    const crypto=window.ForumPrivateMessageHistoryCrypto;
    if(crypto.digest(row.encrypted_envelope)!==row.digest) throw Error('Envelope changed.');
    const id=row.message_id+':'+row.digest;
    let cached=originals.get(id);
    if(!cached) {
      cached={};if(originals.size>=10)originals.delete(originals.keys().next().value);originals.set(id,cached);
      try {cached.keys=await crypto.extractKeys(row.encrypted_envelope,privateKey);cached.transfer_id='';}
      catch (_) {
        if(row.candidate_available!==false) {
          await candidates(row.message_id,row.encrypted_envelope,run,async candidate=>{
            try {await crypto.readOriginal(row.encrypted_envelope,candidate.keys,row.sender_keys);Object.assign(cached,candidate,{verified:true});return true;}
            catch(error) {if(error.historyVerification){Object.assign(cached,candidate);return true;}return false;}
          });
        }
        if(!cached.keys)cached.noAccess=true;
      }
    }
    if(JSON.stringify(Array.from(originals.values()).map(entry=>entry.keys||[])).length>65536)originals.delete(id);
    if(cached.noAccess){const error=Error('No current history donor.');error.noAccess=true;throw error;}
    if(!cached.verified) {await crypto.readOriginal(row.encrypted_envelope,cached.keys,row.sender_keys);cached.verified=true;}
    return {keys:cached.keys,transfer_id:cached.transfer_id};
  }
  async function run() {
    const current=generation, crypto=window.ForumPrivateMessageHistoryCrypto;
    if(!crypto || !valid(current)) return;
    clearRecovery();
    aborter=new AbortController();
    const deadline=Date.now()+10000;
    const currentTurn={attempts:0,originals:new Set(),deadline};turn=currentTurn;
    let pending=false,stalled=false;
    const lifecycle=aborter;const timer=setTimeout(()=>lifecycle.abort(),15000);
    stats.unavailable=0;
    publish('working');
    try {
      const privateKey=localStorage.getItem('forum_pki_private_key')||'';
      if(!privateKey) { publish('waiting'); return; }
      const pgp=await window.__forumBrowserIdentity.ensureOpenPgpApi(['readPrivateKey']);
      const fingerprint=(await pgp.readPrivateKey({armoredKey:privateKey})).getFingerprint().toLowerCase();
      if(!valid(current))return;
      const finished={account:false,sender:false};
      let processed=0;
      workLoop: for(let batch=0;batch<8 && processed<8 && Date.now()<deadline;batch++) {
        if(currentTurn.attempts>=14){pending=true;break;}
        let works;
        if(combinedSupported) {
          let result;
          try { result=await request('work',{complete:Object.keys(finished).filter(mode=>finished[mode])},current); }
          catch(error) { if(error.status!==405)throw error;combinedSupported=false; }
          if(result) {
            if(result.mode!=='combined' || result.account!==account || result.source!==fingerprint || !Array.isArray(result.work) || !Array.isArray(result.complete) || typeof result.continuation!=='boolean') throw Error('Invalid combined history work.');
            if(result.revision && result.revision!==workRevision){clearRecovery();confirmed.clear();completedWork.clear();candidateCursors.clear();workRevision=result.revision;}
            pending=result.continuation;
            for(const mode of result.complete) {if(!['account','sender'].includes(mode))throw Error('Invalid completed mode.');finished[mode]=true;}
            works=result.work.sort((a,b)=>(a.mode===nextMode?-1:0)-(b.mode===nextMode?-1:0));
          }
        }
        if(!combinedSupported) {
          const mode=finished[nextMode]?(nextMode==='account'?'sender':'account'):nextMode;
          nextMode=mode==='account'?'sender':'account';
          try {sessionStorage.setItem('forum_history_sync_next_mode:'+account,nextMode);} catch (_) {}
          const work=await request(mode==='sender'?'work?mode=sender':'work',null,current);
          if(mode==='sender' && work.mode!=='sender') {finished.sender=true;if(finished.account)break;continue;}
          works=[{...work,mode}];pending=!work.cycle_end;
        }
        for(const work of works) {
          if(currentTurn.originals.size>=10 && work.messages.some(row=>!currentTurn.originals.has(row.message_id+':'+row.digest))){pending=true;break workLoop;}
          const signature=JSON.stringify([work.mode,work.target,work.checkpoint]);
          if(work.messages.length && completedWork.has(signature)){stalled=true;pending=false;break workLoop;}
          processed++;
          const mode=work.mode;
          if(!['account','sender'].includes(mode) || !valid(current)) throw Error('Invalid history work mode.');
          nextMode=mode==='account'?'sender':'account';
          try {sessionStorage.setItem('forum_history_sync_next_mode:'+account,nextMode);} catch (_) {}
          if((mode==='sender'?work.source_account:work.account)!==account || work.source!==fingerprint) throw Error('Saved key does not match authenticated identity.');
          const entries=[], receipts=[], unavailable=[], verified=[];
          let checkpoint=work.checkpoint,partial=false;
          for(const row of work.messages) {
            const id=row.message_id+':'+row.digest;
            if(!currentTurn.originals.has(id) && currentTurn.originals.size>=10){checkpoint=null;partial=true;pending=true;break;}
            currentTurn.originals.add(id);
            if(!valid(current)) throw Error('History identity changed.');
            try {
              const result=await ownKeys(row,privateKey,current);
              if(!valid(current))return;
              const item={message_id:row.message_id,digest:row.digest,transfer_id:result.transfer_id};
              verified.push(item);if(!confirmed.has(row.message_id+':'+row.digest))receipts.push(item);
              if(result.transfer_id) document.dispatchEvent(new CustomEvent('private-message-history-restored',{detail:{messageId:row.message_id}}));
              if(!verifiedIds.has(row.message_id+':'+row.digest)) {remember(verifiedIds,row.message_id+':'+row.digest);stats.verified++;}
              if(work.target && (mode==='sender' || work.target!==fingerprint)) entries.push({message_id:row.message_id,digest:row.digest,keys:result.keys});
            } catch (error) { if(error.budget || error.status===429)throw error;if(!valid(current))return;stats.unavailable++;if(error.noAccess)unavailable.push({message_id:row.message_id,digest:row.digest}); }
            if(Date.now()>=deadline)throw budgetError();
          }
          if(entries.length) {
            const ciphertext=await crypto.wrapHistoryKeys({account:work.account,source_account:account,source:fingerprint,target:work.target,privateKey,targetKey:work.target_key,entries});
            await request('transfers',{account:work.account,target:work.target,ciphertext,items:entries.map(({message_id,digest})=>({message_id,digest}))},current);
            if(!valid(current))return;
            stats.contributed+=entries.length;
          }
          // Confirm our own verified access even while contributing to another target.
          if(receipts.length || checkpoint) {
            await request('acknowledge',{items:receipts,verified:verified.map(({message_id,digest})=>({message_id,digest})),unavailable,checkpoint,mode},current);
            for(const item of receipts)remember(confirmed,item.message_id+':'+item.digest);
            if(checkpoint)remember(completedWork,signature);
          }
          if(!valid(current))return;
          publish('working');
          if(work.cycle_end && checkpoint) finished[mode]=true;
          if(partial || processed>=8 || Date.now()>=deadline) {pending=true;break workLoop;}
        }
        if(finished.account && finished.sender){pending=false;break;}
      }
      if(!valid(current))return;
      publish(stats.unavailable?'waiting':'ready');
    } catch (error) {
      pending=error.budget || error.status===429;
      if(valid(current))publish(pending?'waiting':'error');
    } finally {
      clearTimeout(timer);clearRecovery();if(turn===currentTurn)turn=null;
      stats.pending=pending && !stalled && valid(current);
      if(stats.pending)continueLater(current);
    }
  }
  function refresh(force=false,resuming=false) {
    if(window.ForumPrivateMessageAvailability.isDisabled() || !nav || document.hidden) return Promise.resolve();
    if(original!==identity()) { invalidate();return Promise.resolve(); }
    if(window.ForumPrivateMessageUnread?.historyEligibility() !== true) return Promise.resolve();
    if(active) return active;
    if(!force && Date.now()-lastRun<5000) return Promise.resolve();
    if(Date.now()<retryAt){continueLater(generation);return Promise.resolve();}
    if(!resuming)completedWork.clear();
    cancelContinuation();lastRun=Date.now();
    const execute=()=>run();
    const locks=typeof navigator!=='undefined'?navigator.locks:null;
    let entered=false;
    const task=locks?.request?locks.request('forum-history:'+account+':'+window.ForumPrivateMessageHistoryCrypto.digest(localStorage.getItem('forum_pki_public_key')||''),{ifAvailable:true},lock=>{entered=true;return lock?execute():undefined;}).catch(error=>{if(!entered)return execute();throw error;}):execute();
    active=Promise.resolve(task).finally(()=>{active=null;});
    return active;
  }
  function invalidate() { cancelContinuation();completedWork.clear();candidateCursors.clear();retryAt=0;generation++; if(aborter) aborter.abort();verifiedIds.clear();confirmed.clear();clearConfirmations();clearRecovery();stats.verified=0;publish('identity-changed'); }
  function stop() {
    cancelContinuation();completedWork.clear();candidateCursors.clear();retryAt=0;generation++;
    if(aborter)aborter.abort();
    nav=null;
    clearRecovery();confirmed.clear();clearConfirmations();
    publish('disabled');
  }
  function start(navElement) {
    if(nav===navElement && nav)return active || Promise.resolve();
    // History is bound to the session's viewer, which shared pages learn from unread status.
    if(navElement && !navElement.dataset.viewer) {
      document.addEventListener('private-message-viewer-known',()=>{if(navElement.isConnected)start(navElement);},{once:true});
      return Promise.resolve();
    }
    stop();
    nav=navElement;
    if(!nav)return Promise.resolve();
    const previousAccount=account, previousIdentity=original;
    account=nav.dataset.viewer;original=identity();lastRun=0;
    if(previousAccount!==account || previousIdentity!==original) {
      verifiedIds.clear();stats.verified=0;stats.contributed=0;stats.unavailable=0;
    }
    nextMode='account';
    try {if(sessionStorage.getItem('forum_history_sync_next_mode:'+account)==='sender')nextMode='sender';} catch (_) {}
    if(!listening) {
      listening=true;
      document.addEventListener('private-messaging-disabled',stop);
      document.addEventListener('private-message-history-eligibility',()=>refresh());
      window.addEventListener('pagehide',()=>{cancelContinuation();generation++;if(aborter)aborter.abort();clearRecovery();clearConfirmations();});
      window.addEventListener('pageshow',()=>refresh());
      window.addEventListener('focus',()=>refresh());
      window.addEventListener('storage',event=>{if(event.key===null || ['forum_pki_username','forum_pki_public_key','forum_pki_private_key'].includes(event.key)) invalidate();});
      document.addEventListener('visibilitychange',()=>{if(document.hidden){cancelContinuation();generation++;if(aborter)aborter.abort();clearRecovery();clearConfirmations();}else refresh();});
    }
    const current=generation;
    // Let the cancelled run drain before starting another bounded run.
    return Promise.resolve(active).then(()=>{if(valid(current))return refresh(true);});
  }
  window.ForumPrivateMessageHistorySync={refresh:()=>refresh(true),state:()=>({...stats}),candidates,confirm,start,stop};
  document.addEventListener('DOMContentLoaded',()=>start(document.querySelector('[data-private-message-unread]')));
})();
