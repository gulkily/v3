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
  assert.throws(()=>encode(new Uint8Array(131073),0n));
  assert.throws(()=>decode(Uint8Array.of(255,216,255)));
  assert.throws(()=>decode(new Uint8Array(20*1024*1024+1)));
  console.log('SFENC: exact Python vectors, independent cross-decode, maximum-size round trips and corruption/size rejection passed');
}finally{fs.rmSync(dir,{recursive:true,force:true});}
