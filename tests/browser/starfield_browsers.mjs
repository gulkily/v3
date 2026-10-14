import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { chromium, firefox } from 'playwright-core';
import reencode from '../Support/png_reencode.cjs';
const fixtures=JSON.parse(await readFile('tests/fixtures/starfield/vectors.json','utf8'));
for(const engine of [chromium,firefox]){
  const browser=await engine.launch({headless:true,...(engine===chromium?{executablePath:process.env.CHROMIUM_PATH||'/opt/google/chrome/chrome',args:['--no-sandbox']}:{})});
  try{
    const page=await browser.newPage();
    await page.route('http://localhost/**',route=>route.fulfill({body:'<!doctype html><title>Codec test</title>',contentType:'text/html'}));
    await page.goto('http://localhost/');await page.addScriptTag({path:'public/assets/starfield.js'});
    for(const fixture of fixtures){
      const result=await page.evaluate(async v=>{
        const data=Uint8Array.from(v.payload_hex.match(/../g)||[],x=>parseInt(x,16));
        const png=window.ForumStarfield.encodeStarfield(data,BigInt(v.seed));
        const blob=new Blob([png],{type:'image/png'}),saved=new Uint8Array(await blob.arrayBuffer());
        const restored=await window.ForumStarfield.decodeStarfield(saved);
        const hash=Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',saved)),b=>b.toString(16).padStart(2,'0')).join('');
        return {hash,equal:restored.length===data.length&&restored.every((b,i)=>b===data[i])};
      },fixture);
      assert.equal(result.hash,fixture.png_sha256);assert(result.equal);
    }
    const canonical=await page.evaluate(()=>Array.from(window.ForumStarfield.encodeStarfield(Uint8Array.from({length:256},(_,i)=>i),1n)));
    for(const rgb of [true,false])for(let filter=0;filter<5;filter++){
      const pixels=Array.from(reencode(canonical,{rgb,filter}));
      assert.equal(await page.evaluate(async png=>{const value=await window.ForumStarfield.decodeStarfield(Uint8Array.from(png));return value.length===256&&value.every((b,i)=>b===i);},pixels),true);
    }
    console.log(engine.name()+': canonical PNG vectors and lossless RGB/RGBA recompression with all five filters passed');
  }finally{await browser.close();}
}
