import assert from 'node:assert/strict';
import {chromium} from 'playwright-core';
const browser = await chromium.launch({executablePath:process.env.BROWSER_PATH || '/usr/bin/google-chrome',headless:true,args:['--no-sandbox']});
try {
 const page = await browser.newPage({viewport:{width:390,height:844},hasTouch:true});
 await page.addScriptTag({path:'public/assets/image_prepare.js'});
 const results = await page.evaluate(async()=>{
   const source = async(w,h,type,noise=false)=>{
     const c=document.createElement('canvas');c.width=w;c.height=h;const ctx=c.getContext('2d');
     if(noise){const pixels=ctx.createImageData(w,h);for(let i=0;i<pixels.data.length;i++)pixels.data[i]=Math.random()*256;ctx.putImageData(pixels,0,0);}
     else {ctx.fillStyle='red';ctx.fillRect(0,0,w/2,h);}
     return new File([await new Promise(r=>c.toBlob(r,type))],'source',{type});
   };
   const results=[];
   for(const [w,h,type,noise] of [[5000,10,'image/jpeg',false],[1800,1800,'image/png',true],[20,10,'image/webp',false],[2,1,'image/png',false]]){
     const input=await source(w,h,type,noise);const out=await ForumImagePrepare.prepare(input);
     const bitmap=await createImageBitmap(out.file);const c=document.createElement('canvas');c.width=out.width;c.height=out.height;c.getContext('2d').drawImage(bitmap,0,0);
     results.push({input:input.size,bytes:out.file.size,width:out.width,height:out.height,type:out.file.type,alpha:c.getContext('2d').getImageData(out.width-1,0,1,1).data[3]});bitmap.close();
   }
   const gif=Uint8Array.from(atob('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'),c=>c.charCodeAt(0));
   results.push({gif:(await ForumImagePrepare.prepare(new File([gif],'a.gif',{type:'image/gif'}))).file.type});
   for(const file of [new File(['bad'],'bad'),new File([new Uint8Array(32*1024*1024+1)],'large')]){
     try {await ForumImagePrepare.prepare(file);results.push({rejected:false});}catch{results.push({rejected:true});}
   }
   const jpeg=new Uint8Array(await (await source(12,6,'image/jpeg')).arrayBuffer());
   const exif=Uint8Array.from('ffe100224578696600004d4d002a00000008000101120003000000010006000000000000'.match(/../g),v=>parseInt(v,16));
   const rotated=await ForumImagePrepare.prepare(new File([jpeg.slice(0,2),exif,jpeg.slice(2)],'rotated.jpg',{type:'image/jpeg'}));
   results.push({width:rotated.width,height:rotated.height});
   return results;
 });
 assert.equal(results[0].width,2048);assert.ok(results[1].input>5*1024*1024);assert.ok(results[1].bytes<=5*1024*1024);
 assert.equal(results[2].type,'image/png');assert.equal(results[2].alpha,0);assert.equal(results[3].width,2);
 assert.deepEqual(results[7],{width:6,height:12});
 assert.equal(results[4].gif,'image/png');assert.ok(results[5].rejected && results[6].rejected);
 console.log('PASS client preparation: large JPEG/PNG, WebP/GIF, transparency, no upscaling, invalid/oversized rejection');
} finally {await browser.close();}
