const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),crypto=require('node:crypto'),os=require('node:os'),path=require('node:path'),cp=require('node:child_process');
global.window={};global.crypto=crypto.webcrypto;
vm.runInThisContext(fs.readFileSync('public/assets/starfield.js','utf8'));
const {encodeStarfield:encode,decodeStarfield:decode}=window.ForumStarfield;
const fixtures=JSON.parse(fs.readFileSync('tests/fixtures/starfield/vectors.json'));
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'starfield-codec-'));
try{
  for(const vector of fixtures){
    const data=Uint8Array.from(Buffer.from(vector.payload_hex,'hex')),png=encode(data,BigInt(vector.seed));
    assert.equal(crypto.createHash('sha256').update(png).digest('hex'),vector.png_sha256,vector.name);
    assert.deepEqual(decode(png),data);
    const file=path.join(dir,vector.name+'.png');fs.writeFileSync(file,png);
    const result=cp.spawnSync('python3',['tests/Support/starfield_reference.py','--decode',file]);
    assert.equal(result.status,0,result.stderr.toString());assert.deepEqual(result.stdout,Buffer.from(data));
    assert.throws(()=>decode(png.slice(0,-1)));
    const corrupted=png.slice();corrupted[100]^=1;assert.throws(()=>decode(corrupted));
  }
  for(const size of [2407,65535,65536,131072]){
    const data=Uint8Array.from({length:size},(_,i)=>i%256);assert.deepEqual(decode(encode(data,42n)),data);
  }
  // Repair outer checksums so malformed metadata reaches inner validation.
  const crc=bytes=>{let c=0xffffffff;for(const b of bytes){c^=b;for(let i=0;i<8;i++)c=(c>>>1)^((c&1)?0xedb88320:0);}return(c^0xffffffff)>>>0;};
  const original=Buffer.from(encode(Uint8Array.of(1,2,3),0n));
  const repair=png=>{for(let at=8;at<png.length;){const n=png.readUInt32BE(at);png.writeUInt32BE(crc(png.subarray(at+4,at+8+n)),at+8+n);at+=12+n;}};
  const dimension=Buffer.from(original);dimension.writeUInt32BE(4096,16);repair(dimension);assert.throws(()=>decode(dimension));
  for(const [index,value] of [[4,1],[5,1],[16,255],[24,255],[32,2],[40,1],[64,1]]){
    const png=Buffer.from(original),idatStart=41,idatLength=png.readUInt32BE(33);
    const rawOffset=1+Math.floor(index/3)*4+index%3;
    png[idatStart+7+rawOffset]=value;
    const raw=require('node:zlib').inflateRawSync(png.subarray(idatStart+2,idatStart+idatLength-4));
    let a=1,b=0;for(const byte of raw){a=(a+byte)%65521;b=(b+a)%65521;}
    png.writeUInt32BE(((b<<16)|a)>>>0,idatStart+idatLength-4);repair(png);
    assert.throws(()=>decode(png),'header byte '+index);
  }
  assert.throws(()=>encode(new Uint8Array(131073),0n));
  assert.throws(()=>decode(Uint8Array.of(255,216,255)));
  assert.throws(()=>decode(new Uint8Array(20*1024*1024+1)));
  console.log('SFENC: exact Python vectors, independent cross-decode, maximum-size round trips and corruption/size rejection passed');
}finally{fs.rmSync(dir,{recursive:true,force:true});}
