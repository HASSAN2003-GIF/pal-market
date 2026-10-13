import sys, json
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.boundsPen import BoundsPen
from fontTools.pens.transformPen import TransformPen

font = TTFont(sys.argv[1])
gs = font.getGlyphSet()
cmap = font.getBestCmap()
upm = font['head'].unitsPerEm
text = sys.argv[2]
tracking = float(sys.argv[3]) if len(sys.argv) > 3 else 0

x = 0
paths = []
for ch in text:
    name = cmap[ord(ch)]
    g = gs[name]
    pen = SVGPathPen(gs)
    # flip y: font units -> svg (y down), baseline at y=0
    tp = TransformPen(pen, (1, 0, 0, -1, x, 0))
    g.draw(tp)
    bp = BoundsPen(gs)
    g.draw(TransformPen(bp, (1, 0, 0, -1, x, 0)))
    paths.append({"ch": ch, "d": pen.getCommands(), "bounds": bp.bounds, "adv": g.width})
    x += g.width + tracking

os2 = font['OS/2']
print(json.dumps({"upm": upm, "capHeight": getattr(os2, 'sCapHeight', None), "glyphs": paths, "total": x}))
