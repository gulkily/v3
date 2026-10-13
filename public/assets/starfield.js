(function () {
  'use strict';
  const MAX_BYTES = 131072, MAX_FILE_BYTES = 20 * 1024 * 1024;
  const MASK = (1n << 64n) - 1n;
  const signature = Uint8Array.of(137,80,78,71,13,10,26,10);
  const gzipHeader = Uint8Array.of(31,139,8,0,0,0,0,0,0,255);
  const crcTable = Uint32Array.from({ length: 256 }, (_, i) => {
    for (let n=0;n<8;n++) i=(i>>>1)^((i&1)?0xedb88320:0);
    return i>>>0;
  });
  function fail() { throw new Error('This image is damaged or is not an original Starfield backup. Use the original downloaded PNG or text backup.'); }
  function view(bytes) { return new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength); }
  function equal(a,b) { return a.length===b.length && a.every((v,i)=>v===b[i]); }
  function concat(...parts) {
    const out=new Uint8Array(parts.reduce((n,p)=>n+p.length,0));
    let offset=0; for(const part of parts){out.set(part,offset);offset+=part.length;} return out;
  }
  function crc(bytes) {
    let value=0xffffffff;
    for(const b of bytes) value=(value>>>8)^crcTable[(value^b)&255];
    return (value^0xffffffff)>>>0;
  }
  function adler(bytes) {
    let a=1,b=0;
    for(const value of bytes){a=(a+value)%65521;b=(b+a)%65521;}
    return ((b<<16)|a)>>>0;
  }
  function u32(n,little=false) { const b=new Uint8Array(4);view(b).setUint32(0,n,little);return b; }
  function stored(data) {
    const out=new Uint8Array(data.length+5*Math.max(1,Math.ceil(data.length/65535)));
    let offset=0;
    for(let start=0;start<Math.max(1,data.length);start+=65535){
      const part=data.subarray(start,start+65535), len=part.length;
      out[offset]=start+65535>=data.length?1:0;
      view(out).setUint16(offset+1,len,true); view(out).setUint16(offset+3,len^65535,true);
      out.set(part,offset+5);offset+=5+len;
    }
    return out;
  }
  function unstored(data,expected) {
    const out=new Uint8Array(expected);let offset=0,written=0,final=false;
    while(!final){
      if(offset+5>data.length || data[offset]>1) fail();
      final=data[offset]===1;
      const len=view(data).getUint16(offset+1,true);
      if(view(data).getUint16(offset+3,true)!==(len^65535) || offset+5+len>data.length || written+len>expected) fail();
      if(!final && len!==65535) fail();
      out.set(data.subarray(offset+5,offset+5+len),written);written+=len;offset+=5+len;
    }
    if(offset!==data.length || written!==expected) fail();
    return out;
  }
  function gzip(data) { return concat(gzipHeader,stored(data),u32(crc(data),true),u32(data.length,true)); }
  function ungzip(bytes,length) {
    if(bytes.length<23 || !equal(bytes.subarray(0,10),gzipHeader)) fail();
    const out=unstored(bytes.subarray(10,-8),length);
    if(view(bytes).getUint32(bytes.length-8,true)!==crc(out) || view(bytes).getUint32(bytes.length-4,true)!==length) fail();
    return out;
  }
  function random(seed) {
    const state=[];
    for(let i=0;i<4;i++){
      seed=(seed+0x9e3779b97f4a7c15n)&MASK;let z=seed;
      z=((z^(z>>30n))*0xbf58476d1ce4e5b9n)&MASK;
      z=((z^(z>>27n))*0x94d049bb133111ebn)&MASK;state.push(z^(z>>31n));
    }
    const rot=(x,n)=>((x<<n)|(x>>(64n-n)))&MASK;
    return ()=>{
      const result=(rot((state[1]*5n)&MASK,7n)*9n)&MASK,t=(state[1]<<17n)&MASK;
      state[2]^=state[0];state[3]^=state[1];state[1]^=state[2];state[0]^=state[3];state[2]^=t;state[3]=rot(state[3],45n);
      return result;
    };
  }
  function dimensions(length) {
    const n=Math.ceil(105*length/100)+50;
    for(const w of [256,512,1024,2048]) if(Math.floor(w/5)*Math.floor((w-1)/5)>=n) return {w,n};
    fail();
  }
  function cells(w,next) {
    const out=Uint32Array.from({length:Math.floor(w/5)*Math.floor((w-1)/5)},(_,i)=>i);
    for(let i=out.length-1;i>0;i--){const j=Number(next()%BigInt(i+1)),old=out[i];out[i]=out[j];out[j]=old;}
    return out;
  }
  function color(b) {
    const v=192+(b&63);
    switch(b>>6){
      case 0:return [v,v,v];
      case 1:return [Math.floor(85*v/100),Math.floor(90*v/100),v];
      case 2:return [v,v,Math.floor(70*v/100)];
      default:return [v,Math.floor(70*v/100),Math.floor(60*v/100)];
    }
  }
  function byteAt(pixels,offset) {
    const r=pixels[offset],g=pixels[offset+1],b=pixels[offset+2];
    const hue=r===g&&g===b?0:b>g&&g>r?1:r===g&&g>b?2:3;
    const value=Math.max(r,g,b)-192+64*hue;
    if(value<0 || value>255 || !equal(color(value),[r,g,b])) fail();
    return value;
  }
  function chunk(type,data) {
    const name=new TextEncoder().encode(type),body=concat(name,data);
    return concat(u32(data.length),body,u32(crc(body)));
  }
  function encodeStarfield(data,seed) {
    if(!(data instanceof Uint8Array) || data.length>MAX_BYTES) throw new Error('This payload is too large for a Starfield backup.');
    if(seed===undefined){const words=crypto.getRandomValues(new Uint32Array(2));seed=(BigInt(words[0])<<32n)|BigInt(words[1]);}
    seed=BigInt(seed);if(seed<0n || seed>MASK) fail();
    const zipped=gzip(data),{w,n}=dimensions(zipped.length),pixels=new Uint8Array(w*w*4),q=Number(seed&255n);
    for(let y=0;y<w;y++)for(let x=0;x<w;x++){
      const base=2+(13*x+7*y+q)%6,glow=Math.max(0,24-Math.floor(Math.abs(10*y-5*w-3*(x-w/2))/10)),i=(y*w+x)*4;
      pixels[i]=base+Math.floor(3*glow/5);pixels[i+1]=base+Math.floor(7*glow/10);pixels[i+2]=base+glow;pixels[i+3]=255;
    }
    const next=random(seed),positions=cells(w,next),cols=Math.floor(w/5);
    for(let i=0;i<n;i++){
      const b=i<zipped.length?zipped[i]:Number(next()%256n),rgb=color(b),x=2+5*(positions[i]%cols),y=3+5*Math.floor(positions[i]/cols),hue=b>>6;
      for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++){
        const draw=(dx===0&&dy===0)||(hue===1&&Math.abs(dx)+Math.abs(dy)===1)||
          (hue>=2&&Math.abs(dx)<=1&&Math.abs(dy)<=1)||(hue===3&&((dx===0&&Math.abs(dy)===2)||(dy===0&&Math.abs(dx)===2)));
        if(draw){const off=((y+dy)*w+x+dx)*4;pixels.set(rgb,off);}
      }
    }
    const header=new Uint8Array(66),hv=view(header);header.set([83,70,69,78,2]);
    hv.setBigUint64(8,seed,true);hv.setBigUint64(16,BigInt(data.length),true);hv.setBigUint64(24,BigInt(zipped.length),true);header[32]=1;hv.setUint32(36,crc(zipped),true);
    header.forEach((b,i)=>{pixels[Math.floor(i/3)*4+i%3]=b;});
    const raw=new Uint8Array(w*(w*4+1));
    for(let y=0;y<w;y++)raw.set(pixels.subarray(y*w*4,(y+1)*w*4),y*(w*4+1)+1);
    const ihdr=concat(u32(w),u32(w),Uint8Array.of(8,6,0,0,0));
    return concat(signature,chunk('IHDR',ihdr),chunk('IDAT',concat(Uint8Array.of(120,1),stored(raw),u32(adler(raw)))),chunk('IEND',new Uint8Array()));
  }
  function decodeStarfield(png) {
    if(!(png instanceof Uint8Array) || png.length>MAX_FILE_BYTES || !equal(png.subarray(0,8),signature)) fail();
    let offset=8;const parts=[];
    for(const type of ['IHDR','IDAT','IEND']){
      if(offset+12>png.length) fail();
      const length=view(png).getUint32(offset);
      if(offset+12+length>png.length || new TextDecoder().decode(png.subarray(offset+4,offset+8))!==type) fail();
      const body=png.subarray(offset+8,offset+8+length);
      if(crc(png.subarray(offset+4,offset+8+length))!==view(png).getUint32(offset+8+length)) fail();
      parts.push(body);offset+=length+12;
    }
    if(offset!==png.length || parts[0].length!==13 || parts[2].length) fail();
    const w=view(parts[0]).getUint32(0),h=view(parts[0]).getUint32(4);
    if(w!==h || ![256,512,1024,2048].includes(w) || !equal(parts[0].subarray(8),[8,6,0,0,0])) fail();
    const idat=parts[1];if(idat.length<11 || idat[0]!==120 || idat[1]!==1) fail();
    const raw=unstored(idat.subarray(2,-4),w*(w*4+1));
    if(adler(raw)!==view(idat).getUint32(idat.length-4)) fail();
    const pixels=new Uint8Array(w*w*4);
    for(let y=0;y<w;y++){if(raw[y*(w*4+1)]!==0) fail();pixels.set(raw.subarray(y*(w*4+1)+1,(y+1)*(w*4+1)),y*w*4);}
    const header=Uint8Array.from({length:66},(_,i)=>pixels[Math.floor(i/3)*4+i%3]),hv=view(header);
    if(!equal(header.subarray(0,5),[83,70,69,78,2]) || header[32]!==1 || [...header.subarray(5,8),...header.subarray(33,36),...header.subarray(40)].some(b=>b)) fail();
    const seed=hv.getBigUint64(8,true),length=hv.getBigUint64(16,true),compressed=hv.getBigUint64(24,true);
    if(length>BigInt(MAX_BYTES) || compressed!==length+18n+5n*((length+65534n)/65535n||1n)) fail();
    const l=Number(compressed);if(dimensions(l).w!==w) fail();
    const positions=cells(w,random(seed)),cols=Math.floor(w/5),zipped=new Uint8Array(l);
    for(let i=0;i<l;i++)zipped[i]=byteAt(pixels,((3+5*Math.floor(positions[i]/cols))*w+2+5*(positions[i]%cols))*4);
    if(crc(zipped)!==hv.getUint32(36,true)) fail();
    const data=ungzip(zipped,Number(length));
    if(!equal(encodeStarfield(data,seed),png)) fail();
    return data;
  }
  window.ForumStarfield={encodeStarfield,decodeStarfield,MAX_BYTES,MAX_FILE_BYTES};
})();
