const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const crypto = require('node:crypto').webcrypto;
const source = fs.readFileSync('public/assets/browser_signing.js', 'utf8');
const start = source.indexOf('  function composeImageFile(');
const end = source.indexOf('  async function signAndFinalizePreparedPost(', start);
let prepares = 0, finalizations = [], body = 'A picture', photoLines = [];
const file = new Blob([Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=', 'base64')], {type:'image/png'});
const form = {__imageIdentity:async()=>'test',querySelector(){return {files:[file]};}};
const context = {window:{crypto,ForumImageUpload:{has:()=>true,chosenLines:()=>photoLines,lock(){},assertEligible:async()=>{},ready:async()=>{form.__imageState={file,width:1,height:1};return form.__imageState;}}}, crypto, navigator:{onLine:true}, TextEncoder, Uint8Array, FormData, btoa,
 createImageBitmap:async()=>({width:1,height:1,close(){}}),
 composeFormFieldValue:()=>"Pixel & <test>", collectSignedSubmitFields:()=>({body,author_identity_id:'test'}),
 isReplyComposeForm:()=>false, threadPrepareApiPath:()=>'/api/prepare_thread',
 submitUrlEncoded:async(path,fields)=>{prepares++;return {text:JSON.stringify({ok:true,canonicalRecord:fields.body,prepareToken:'one'})};},
 parsePreparedPostJsonResponse: text=>JSON.parse(text), signCanonicalRecord:async text=>'signature:' + text,
 fetch:async(path,request)=>{finalizations.push(request);return {text:async()=>JSON.stringify({ok:true})};}
};
vm.createContext(context);
vm.runInContext(source.slice(start,end),context);
(async()=>{
 await context.submitImageCompose(form);
 await context.submitImageCompose(form);
 assert.equal(prepares,1);
 assert.equal(finalizations.length,2);
 assert.match(Buffer.from(finalizations[0].body.get('canonical_record_base64'), 'base64').toString(), /Image-v1: [a-f0-9]{64} png 68 1 1 Pixel%20%26%20%3Ctest%3E/);
 assert.equal(finalizations[0].body.get('image').size,68);
 body='Changed text';await context.submitImageCompose(form);assert.equal(prepares,2);
 context.composeFormFieldValue=()=>'';await context.submitImageCompose(form);
 assert.match(Buffer.from(finalizations.at(-1).body.get('canonical_record_base64'), 'base64').toString(), /Image-v1: [a-f0-9]{64} png 68 1 1$/);
 photoLines=['Photo-Camera: Apple iPhone 15 Pro','Photo-Taken: 2026-11-14T16:12:08-05:00'];await context.submitImageCompose(form);
 assert.match(Buffer.from(finalizations.at(-1).body.get('canonical_record_base64'), 'base64').toString(), /Image-v1: [a-f0-9]{64} png 68 1 1\nPhoto-Camera: Apple iPhone 15 Pro\nPhoto-Taken: 2026-11-14T16:12:08-05:00$/);
 photoLines=['Photo-Camera: x\nPhoto-Location: 1 2'];await assert.rejects(context.submitImageCompose(form),/Photo details are malformed/);
 photoLines=['Photo-Secret: x'];await assert.rejects(context.submitImageCompose(form),/Photo details are malformed/);
 photoLines=[];
 context.navigator.onLine=false;await assert.rejects(context.submitImageCompose(form),/online HTTPS/);
 console.log('PASS image compose metadata, photo detail lines, multipart bytes, retry identity, edits and offline rejection');
})().catch(e=>{console.error(e);process.exitCode=1;});
