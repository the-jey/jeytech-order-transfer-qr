#!/usr/bin/env python3
"""Decode the PHP output with ZXing-C++, independent of the vendored encoder.
Development only: python -m venv /tmp/qr-verify; pip install zxing-cpp pillow.
Run after both integration suites, before a minimum-version suite overwrites posts samples.
"""
import json,struct,zlib
from pathlib import Path
import zxingcpp
from PIL import Image
root=Path(__file__).resolve().parents[1]
results=[]
for mode in ['hpos','posts']:
 for sample in json.loads((root/'dev'/f'.test-qr-{mode}.json').read_text()):
  path=root/'dev'/sample['file'];png=path.read_bytes();im=Image.open(path)
  code=zxingcpp.read_barcode(im)
  assert code and code.bytes==sample['payload'].encode('utf-8'),sample['file']
  assert im.width==im.height and im.width<=462
  pixels=im.load()
  for x in range(im.width):
   for y in range(24):
    assert pixels[x,y]==255 and pixels[x,im.height-1-y]==255
    assert pixels[y,x]==255 and pixels[im.width-1-y,x]==255
  cursor=8
  while cursor<len(png):
   size=struct.unpack('>I',png[cursor:cursor+4])[0];data=png[cursor+4:cursor+8+size]
   assert zlib.crc32(data)==struct.unpack('>I',png[cursor+8+size:cursor+12+size])[0]
   cursor+=12+size
  results.append({'file':sample['file'],'payloadBytes':sample['bytes'],'dimensions':[im.width,im.height],'decodedExactBytes':True,'quietZone':4,'pngCrc':True})
(root/'dev/qr-decode-verification.json').write_text(json.dumps(results,indent=2)+'\n')
print(f'{len(results)} PNGs decoded with exact bytes, valid CRCs and four-module quiet zones.')
