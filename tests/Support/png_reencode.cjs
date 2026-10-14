// Test-only optimizer: rewrite a canonical RGBA PNG as filtered/compressed RGB
// or RGBA with split IDAT and metadata. Does not depend on the SFENC decoder.
const { inflateSync, deflateSync } = require('node:zlib');
function crc(bytes) {
  let c=0xffffffff;
  for(const b of bytes){c^=b;for(let i=0;i<8;i++)c=(c>>>1)^((c&1)?0xedb88320:0);}
  return (c^0xffffffff)>>>0;
}
function chunk(type,data) {
  const out=Buffer.alloc(data.length+12);out.writeUInt32BE(data.length);out.write(type,4);data.copy(out,8);
  out.writeUInt32BE(crc(out.subarray(4,-4)),out.length-4);return out;
}
module.exports=function reencode(input,{rgb=true,filter=4,alterPixel=false,extraByte=false}={}){
  const png=Buffer.from(input),w=png.readUInt32BE(16),h=png.readUInt32BE(20),parts=[];
  for(let at=8;at<png.length;){const length=png.readUInt32BE(at);if(png.toString('ascii',at+4,at+8)==='IDAT')parts.push(png.subarray(at+8,at+8+length));at+=12+length;}
  const rgba=inflateSync(Buffer.concat(parts)),channels=rgb?3:4,stride=w*channels;
  const raw=Buffer.alloc(h*(stride+1)+(extraByte?1:0));let previous=Buffer.alloc(stride);
  for(let y=0;y<h;y++){
    const row=Buffer.alloc(stride);
    for(let x=0;x<w;x++)for(let c=0;c<channels;c++)row[x*channels+c]=rgba[y*(w*4+1)+1+x*4+c];
    if(alterPixel&&y===2)row[3]^=1;
    raw[y*(stride+1)]=filter;
    for(let x=0;x<stride;x++){
      const a=x>=channels?row[x-channels]:0,b=previous[x],c=x>=channels?previous[x-channels]:0;
      let predictor=0;
      if(filter===1)predictor=a;
      if(filter===2)predictor=b;
      if(filter===3)predictor=Math.floor((a+b)/2);
      if(filter===4){const p=a+b-c;predictor=[a,b,c].reduce((best,v)=>Math.abs(p-v)<Math.abs(p-best)?v:best,a);}
      raw[y*(stride+1)+1+x]=(row[x]-predictor)&255;
    }
    previous=row;
  }
  const compressed=deflateSync(raw,{level:9}),half=Math.floor(compressed.length/2),ihdr=Buffer.from(png.subarray(16,29));ihdr[9]=rgb?2:6;
  return Buffer.concat([png.subarray(0,8),chunk('IHDR',ihdr),chunk('tEXt',Buffer.from('Comment\0Lossless optimizer fixture')),chunk('IDAT',compressed.subarray(0,half)),chunk('IDAT',compressed.subarray(half)),chunk('IEND',Buffer.alloc(0))]);
};
