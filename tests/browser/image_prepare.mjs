import assert from 'node:assert/strict';
import {chromium, webkit} from 'playwright-core';
const browser = await (process.env.IMAGE_BROWSER==='webkit' ? webkit.launch({headless:true}) : chromium.launch({executablePath:process.env.BROWSER_PATH || '/usr/bin/google-chrome',headless:true,args:['--no-sandbox']}));
try {
 const page = await browser.newPage({viewport:{width:390,height:844},hasTouch:true});
 await page.addScriptTag({path:'public/assets/image_prepare.js'});
 await page.addScriptTag({path:'public/assets/image_upload.js'});
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
   const form=document.createElement('form');form.innerHTML='<input name="image" type="file">';document.body.append(form);
   ForumImageUpload.bind(form);
   ForumImageUpload.select(form,await source(5000,10,'image/jpeg'));
   ForumImageUpload.select(form,await source(2,1,'image/png'));
   const state=await ForumImageUpload.ready(form);
   ForumImageUpload.lock(form,true);ForumImageUpload.select(form,new File(['bad'],'bad'));
   results.push({width:state.width,same:state===await ForumImageUpload.ready(form),sourceCleared:form.querySelector('input').value===''});
   ForumImageUpload.lock(form,false);ForumImageUpload.clear(form);
   results.push({cleared:!ForumImageUpload.has(form)});
   form.__imageEligible=false;ForumImageUpload.select(form,await source(2,1,'image/png'));
   results.push({retainedWhileUnavailable:ForumImageUpload.has(form) && Boolean((await ForumImageUpload.ready(form)).file)});
   return results;
 });
 assert.ok(results[10].retainedWhileUnavailable);
 assert.equal(results[0].width,2048);assert.ok(results[1].input>5*1024*1024);assert.ok(results[1].bytes<=5*1024*1024);
 assert.equal(results[2].type,'image/png');assert.equal(results[2].alpha,0);assert.equal(results[3].width,2);
 assert.deepEqual(results[8],{width:2,same:true,sourceCleared:true});assert.ok(results[9].cleared);
 assert.deepEqual(results[7],{width:6,height:12});
 assert.equal(results[4].gif,'image/png');assert.ok(results[5].rejected && results[6].rejected);
 console.log('PASS client preparation: large JPEG/PNG, WebP/GIF, transparency, no upscaling, invalid/oversized rejection');
 const photo = await page.evaluate(async()=>{
   // Builds a TIFF/EXIF block: IFD0 (Make, Model, Exif and GPS pointers), Exif IFD, GPS IFD.
   function tiff(le, ifds){
     const out=[]; const u16=n=>le?[n&255,n>>8&255]:[n>>8&255,n&255]; const u32=n=>le?[n&255,n>>8&255,n>>16&255,n>>>24&255]:[n>>>24&255,n>>16&255,n>>8&255,n&255];
     const sizes={2:1,3:2,4:4,5:8}; const layout=[]; let at=8;
     for(const entries of ifds){layout.push(at); at+=2+entries.length*12+4;}
     const extra=[]; let extraAt=at;
     const body=ifds.map((entries,i)=>{
       const bytes=[...u16(entries.length)];
       for(const [tag,type,values] of entries){
         let data=[]; let n=values.length;
         if(type===2){data=[...values].map(c=>c.charCodeAt(0)).concat([0]);n=data.length;}
         else for(const v of values){ if(type===3)data.push(...u16(v)); else if(type===4)data.push(...u32(typeof v==='string'?layout[+v]:v)); else data.push(...u32(v[0]),...u32(v[1])); }
         bytes.push(...u16(tag),...u16(type),...u32(n));
         if(data.length<=4){bytes.push(...data,...Array(4-data.length).fill(0));}
         else {bytes.push(...u32(extraAt)); extra.push(...data); extraAt+=data.length;}
       }
       bytes.push(...u32(0)); return bytes;
     });
     return new Uint8Array([...(le?[0x49,0x49]:[0x4d,0x4d]),...u16(42),...u32(8),...body.flat(),...extra]);
   }
   const full=le=>tiff(le,[
     [[0x010f,2,'Apple'],[0x0110,2,'iPhone 15 Pro'],[0x8769,4,['1']],[0x8825,4,['2']]],
     [[0x829a,5,[[1,120]]],[0x829d,5,[[178,100]]],[0x8827,3,[80]],[0x9003,2,'2026:11:14 16:12:08'],[0x9011,2,'-05:00'],[0xa434,2,'iPhone 15 Pro back camera 6.86mm f/1.78']],
     [[1,2,'N'],[2,5,[[42,1],[21,1],[1296,100]]],[3,2,'W'],[4,5,[[71,1],[7,1],[12,100]]]]
   ]);
   const canvasFile=async type=>{const c=document.createElement('canvas');c.width=8;c.height=4;c.getContext('2d').fillRect(0,0,4,4);return new Uint8Array(await (await new Promise(r=>c.toBlob(r,type))).arrayBuffer());};
   const jpegWith=async block=>{const j=await canvasFile('image/jpeg');const app1=[0xff,0xe1,(block.length+8)>>8,(block.length+8)&255,0x45,0x78,0x69,0x66,0,0];return new File([j.slice(0,2),new Uint8Array(app1),block,j.slice(2)],'p.jpg',{type:'image/jpeg'});};
   const crc=bytes=>{let c=~0;for(const x of bytes){c^=x;for(let k=0;k<8;k++)c=c&1?(c>>>1)^0xedb88320:c>>>1;}return ~c>>>0;};
   const pngWith=async block=>{const p=await canvasFile('image/png');const type=[0x65,0x58,0x49,0x66];const len=block.length;const chunk=new Uint8Array(12+len);new DataView(chunk.buffer).setUint32(0,len);chunk.set(type,4);chunk.set(block,8);new DataView(chunk.buffer).setUint32(8+len,crc([...type,...block]));return new File([p.slice(0,33),chunk,p.slice(33)],'p.png',{type:'image/png'});};
   const result={};
   const big=await ForumImagePrepare.prepare(await jpegWith(full(false)));
   result.big=big.details.map(d=>d.line); result.bigDisplay=big.details.map(d=>d.display);
   result.strippedFromFile=!new TextDecoder('latin1').decode(await big.file.arrayBuffer()).includes('iPhone');
   result.little=(await ForumImagePrepare.prepare(await pngWith(full(true)))).details.map(d=>d.key);
   result.none=(await ForumImagePrepare.prepare(new File([await canvasFile('image/jpeg')],'n.jpg',{type:'image/jpeg'}))).details.length;
   const block=full(false);
   result.truncated=(await ForumImagePrepare.prepare(await jpegWith(block.slice(0,30)))).details.length;
   const loop=tiff(false,[[[0x8769,4,['0']],[0x010f,2,'Loop']]]);
   result.loop=(await ForumImagePrepare.prepare(await jpegWith(loop))).details.length;
   const huge=block.slice(); new DataView(huge.buffer).setUint16(8,0xffff);
   result.huge=(await ForumImagePrepare.prepare(await jpegWith(huge))).details.length;
   const control=tiff(false,[[[0x010f,2,'Evil\nPhoto-Location: 1 2'],[0x0110,2,'Cam']]]);
   result.control=(await ForumImagePrepare.prepare(await jpegWith(control))).details.map(d=>d.line);
   return result;
 });
 assert.deepEqual(photo.big,['Photo-Camera: Apple iPhone 15 Pro','Photo-Lens: iPhone 15 Pro back camera 6.86mm f/1.78','Photo-Exposure: 1/120 s · f/1.8 · ISO 80','Photo-Taken: 2026-11-14T16:12:08-05:00','Photo-Location: 42.353600 -71.116700']);
 assert.equal(photo.bigDisplay[4],'42.3536° N, 71.1167° W');
 assert.match(photo.bigDisplay[3],/2026/);
 assert.ok(photo.strippedFromFile);
 assert.deepEqual(photo.little,['camera','lens','exposure','taken','location']);
 assert.equal(photo.none,0);assert.equal(photo.truncated,0);assert.equal(photo.huge,0);
 assert.ok(photo.loop<=1);
 assert.deepEqual(photo.control,['Photo-Camera: Evil Photo-Location: 1 2 Cam']);
 console.log('PASS photo details: big/little-endian EXIF in JPEG/PNG, none, truncated, looping, oversized, control characters');
} finally {await browser.close();}
