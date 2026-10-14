"""Independent SFENC v0.2 reference. Standard library only; never uses browser code."""
import hashlib
import json
from pathlib import Path
import struct
import sys
import zlib

MASK = (1 << 64) - 1
LIMIT = 131072


def rng(seed):
    state = []
    for _ in range(4):
        seed = (seed + 0x9e3779b97f4a7c15) & MASK
        z = seed
        z = ((z ^ (z >> 30)) * 0xbf58476d1ce4e5b9) & MASK
        z = ((z ^ (z >> 27)) * 0x94d049bb133111eb) & MASK
        state.append(z ^ (z >> 31))
    def rot(x, n):
        return ((x << n) | (x >> (64-n))) & MASK
    while True:
        a,b,c,d = state
        yield (rot((b*5)&MASK, 7)*9)&MASK
        t = (b << 17)&MASK
        c ^= a; d ^= b; b ^= c; a ^= d; c ^= t; d = rot(d,45)
        state = [a,b,c,d]


def blocks(data):
    result = bytearray()
    for start in range(0, max(1, len(data)), 65535):
        part = data[start:start+65535]
        result += struct.pack('<BHH', int(start+65535 >= len(data)), len(part), len(part)^65535) + part
    return bytes(result)


def gzip(data):
    return bytes.fromhex('1f8b08000000000000ff') + blocks(data) + struct.pack('<II', zlib.crc32(data),len(data))


def size(length):
    n = (105*length+99)//100+50
    for w in (256,512,1024,2048):
        if (w//5)*((w-1)//5) >= n:
            return w,n
    raise ValueError('capacity')


def positions(w, random):
    cells = list(range((w//5)*((w-1)//5)))
    for i in range(len(cells)-1,0,-1):
        j = next(random) % (i+1)
        cells[i],cells[j] = cells[j],cells[i]
    return cells


def color(b):
    v = 192+(b%64)
    return [(v,v,v),(85*v//100,90*v//100,v),(v,v,70*v//100),(v,70*v//100,60*v//100)][b//64]


def encode(data,seed,pixels_only=False):
    if len(data)>LIMIT or not 0<=seed<=MASK:
        raise ValueError('bounds')
    zipped = gzip(data)
    w,n = size(len(zipped))
    pixels = bytearray(w*w*4)
    for y in range(w):
        for x in range(w):
            base = 2+(13*x+7*y+(seed%256))%6
            glow = max(0,24-abs(10*y-5*w-3*(x-w//2))//10)
            off = (y*w+x)*4
            pixels[off:off+4] = bytes((base+3*glow//5,base+7*glow//10,base+glow,255))
    random = rng(seed)
    cells = positions(w,random)
    for i in range(n):
        b = zipped[i] if i<len(zipped) else next(random)%256
        x,y = 2+5*(cells[i]%(w//5)),3+5*(cells[i]//(w//5))
        offsets = [(0,0)]
        if b//64 == 1:
            offsets += [(1,0),(-1,0),(0,1),(0,-1)]
        if b//64 >= 2:
            offsets = [(dx,dy) for dy in (-1,0,1) for dx in (-1,0,1)]
        if b//64 == 3:
            offsets += [(2,0),(-2,0),(0,2),(0,-2)]
        for dx,dy in offsets:
            off = ((y+dy)*w+x+dx)*4
            pixels[off:off+4] = bytes((*color(b),255))
    header = bytearray(66)
    header[:5] = b'SFEN\x02'
    struct.pack_into('<QQQ',header,8,seed,len(data),len(zipped))
    header[32] = 1
    struct.pack_into('<I',header,36,zlib.crc32(zipped))
    for i,b in enumerate(header):
        pixels[(i//3)*4+i%3] = b
    if pixels_only: return bytes(pixels)
    raw = b''.join(b'\0'+pixels[y*w*4:(y+1)*w*4] for y in range(w))
    def chunk(kind,body):
        return struct.pack('>I',len(body))+kind+body+struct.pack('>I',zlib.crc32(kind+body))
    return (bytes.fromhex('89504e470d0a1a0a')+chunk(b'IHDR',struct.pack('>IIBBBBB',w,w,8,6,0,0,0))+
            chunk(b'IDAT',b'\x78\x01'+blocks(raw)+struct.pack('>I',zlib.adler32(raw)))+chunk(b'IEND',b''))


def decode(png):
    if len(png)>20*1024*1024 or png[:8]!=bytes.fromhex('89504e470d0a1a0a'):
        raise ValueError('PNG')
    offset=8; ihdr=None; idat=[]; ended=False; after_data=False
    while offset<len(png):
        if ended or offset+12>len(png): raise ValueError('chunk bounds')
        length=struct.unpack_from('>I',png,offset)[0]
        if offset+length+12>len(png): raise ValueError('chunk bounds')
        kind=png[offset+4:offset+8]; body=png[offset+8:offset+8+length]
        if not all(65<=c<=90 or 97<=c<=122 for c in kind) or kind[2]&32: raise ValueError('chunk type')
        if zlib.crc32(kind+body)!=struct.unpack_from('>I',png,offset+8+length)[0]: raise ValueError('CRC')
        if ihdr is None and kind!=b'IHDR': raise ValueError('header first')
        if kind==b'IHDR':
            if ihdr is not None or length!=13: raise ValueError('header')
            ihdr=body
        elif kind==b'IDAT':
            if after_data: raise ValueError('IDAT order')
            idat.append(body)
        elif kind==b'IEND':
            if length or not idat: raise ValueError('end')
            ended=True
        else:
            if idat: after_data=True
            if not kind[0]&32 or kind==b'tRNS': raise ValueError('unsupported chunk')
        offset+=length+12
    if not ended: raise ValueError('missing end')
    w,h,depth,mode,compression,filtering,interlace=struct.unpack('>IIBBBBB',ihdr)
    if w!=h or w not in (256,512,1024,2048) or depth!=8 or mode not in (2,6) or compression or filtering or interlace:
        raise ValueError('dimensions or pixel encoding')
    channels=3 if mode==2 else 4; stride=w*channels; expected=h*(stride+1)
    inflater=zlib.decompressobj()
    raw=inflater.decompress(b''.join(idat),expected+1)
    if len(raw)!=expected or not inflater.eof or inflater.unused_data: raise ValueError('inflate bounds')
    pixels=bytearray(); previous=bytearray(stride)
    for y in range(h):
        filter_type=raw[y*(stride+1)]
        if filter_type>4: raise ValueError('filter')
        row=bytearray(stride)
        for x in range(stride):
            left=row[x-channels] if x>=channels else 0
            up=previous[x]; corner=previous[x-channels] if x>=channels else 0
            predictor=0
            if filter_type==1: predictor=left
            elif filter_type==2: predictor=up
            elif filter_type==3: predictor=(left+up)//2
            elif filter_type==4:
                estimate=left+up-corner
                candidates=[left,up,corner]
                predictor=min(candidates,key=lambda candidate:abs(estimate-candidate))
            row[x]=(raw[y*(stride+1)+1+x]+predictor)%256
        for x in range(w):
            pixels.extend(row[x*channels:x*channels+3])
            pixels.append(row[x*channels+3] if channels==4 else 255)
        previous=row
    header=bytes(pixels[(i//3)*4+i%3] for i in range(66))
    if header[:5]!=b'SFEN\x02' or header[32]!=1 or any(header[5:8]+header[33:36]+header[40:]): raise ValueError('header')
    seed,length,compressed=struct.unpack_from('<QQQ',header,8)
    if length>LIMIT or compressed!=len(gzip(bytes(length))) or size(compressed)[0]!=w: raise ValueError('length')
    cells=positions(w,rng(seed)); zipped=bytearray()
    lookup={color(b):b for b in range(256)}
    for cell in cells[:compressed]:
        off=((3+5*(cell//(w//5)))*w+2+5*(cell%(w//5)))*4
        rgb=tuple(pixels[off:off+3])
        if rgb not in lookup: raise ValueError('color')
        zipped.append(lookup[rgb])
    if zlib.crc32(zipped)!=struct.unpack_from('<I',header,36)[0]: raise ValueError('payload CRC')
    inflater=zlib.decompressobj(31)
    data=inflater.decompress(zipped,LIMIT+1)
    if len(data)!=length or not inflater.eof or gzip(data)!=zipped: raise ValueError('gzip')
    if encode(data,seed,pixels_only=True)!=pixels: raise ValueError('altered pixels')
    return data


def vectors():
    return [('empty',b'',0),('all-bytes',bytes(range(256)),1),('text',b'Starfield backup\n',0xffffffffffffffff),
            ('capacity-edge',bytes(i%256 for i in range(2406)),42)]


if __name__=='__main__':
    if sys.argv[1]=='--vectors':
        print(json.dumps([dict(name=n,payload_hex=d.hex(),seed=str(s),png_sha256=hashlib.sha256(encode(d,s)).hexdigest()) for n,d,s in vectors()],indent=2))
    elif sys.argv[1]=='--decode':
        sys.stdout.buffer.write(decode(Path(sys.argv[2]).read_bytes()))
    elif sys.argv[1]=='--test':
        fixtures=json.loads((Path(__file__).parent.parent/'fixtures/starfield/vectors.json').read_text())
        for v in fixtures:
            data=bytes.fromhex(v['payload_hex']); png=encode(data,int(v['seed']))
            assert hashlib.sha256(png).hexdigest()==v['png_sha256']
            assert decode(png)==data
            try: decode(png[:-1])
            except (ValueError,struct.error): pass
            else: raise AssertionError('truncation accepted')
        assert len({color(b) for b in range(256)})==256
        for length in (0,2406,2407,65535,65536,LIMIT):
            w,n=size(len(gzip(bytes(length))))
            assert n<=(w//5)*((w-1)//5)
            cells=positions(w,rng(0)); assert len(set(cells))==len(cells)
        try: encode(bytes(LIMIT+1),0)
        except ValueError: pass
        else: raise AssertionError('overflow')
        print('SFENC reference: vectors, round trips, capacity, collision and rejection checks passed')
