#!/bin/bash
# Six review frames spread over a clip, as JPEGs in out/frames/<slug>-N.jpg, and the clip's ffprobe facts.
#   bin/clips/frames.sh <slug>
set -euo pipefail
OUT="$(cd "$(dirname "$0")/../.." && pwd)/.superpowers/guides/clips/out"; S="${1:?slug}"
FF=/opt/homebrew/bin/ffmpeg; FP=/opt/homebrew/bin/ffprobe
mkdir -p "$OUT/frames"
D=$("$FP" -v error -show_entries format=duration -of csv=p=0 "$OUT/$S.mp4")
for N in 1 2 3 4 5 6; do
  T=$(python3 -c "print(round($D*($N-0.5)/6,2))")
  "$FF" -y -loglevel error -ss "$T" -i "$OUT/$S.mp4" -frames:v 1 -vf scale=1280:-2 -q:v 3 "$OUT/frames/$S-$N.jpg"
done
"$FP" -v error -show_entries stream=codec_name,pix_fmt,width,height:format=duration,size,nb_streams -of compact "$OUT/$S.mp4"
python3 - "$OUT/$S.mp4" <<'PY'
import sys,struct
f=open(sys.argv[1],'rb'); o=[]
while True:
    h=f.read(8)
    if len(h)<8: break
    n,t=struct.unpack('>I4s',h); t=t.decode()
    if n==1: n=struct.unpack('>Q',f.read(8))[0]; f.seek(n-16,1)
    else: f.seek(n-8,1)
    o.append(t)
print('faststart' if o.index('moov')<o.index('mdat') else 'NOT faststart')
PY
