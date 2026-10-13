"""Builds PAL Market logo SVGs from outlined Manrope ExtraBold glyphs (font units, baseline y=0)."""
import json, sys, os

d = json.load(open(sys.argv[1]))
out = sys.argv[2]
os.makedirs(out, exist_ok=True)
g = {x['ch']: x for x in d['glyphs']}
CAP = 1440
INK, BLUE = '#1C2228', '#1C73C1'
WHITE, BLUE_DARK = '#FFFFFF', '#4D9BE0'
R = 205          # dot radius
GAP = 95         # gap between last letter and dot

left = g['P']['bounds'][0]
l_right = g['L']['bounds'][2]
dot_cx = l_right + GAP + R
width = dot_cx + R - left

p_right = g['P']['bounds'][2]
mark_cx = p_right + GAP + R
mark_w = mark_cx + R - left

letters = ''.join(x['d'] for x in d['glyphs'])
p_only = g['P']['d']

def svg(view, body, title):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{view}" role="img" aria-label="{title}">'
            f'<title>{title}</title>{body}</svg>\n')

files = {}
for variant, ink, blue in (('light', INK, BLUE), ('dark', WHITE, BLUE_DARK)):
    files[f'pal-wordmark-{variant}.svg'] = svg(
        f'{left} {-CAP} {width} {CAP}',
        f'<path fill="{ink}" d="{letters}"/><circle fill="{blue}" cx="{dot_cx}" cy="{-R}" r="{R}"/>',
        'PAL Market')
    files[f'pal-mark-{variant}.svg'] = svg(
        f'{left} {-CAP} {mark_w} {CAP}',
        f'<path fill="{ink}" d="{p_only}"/><circle fill="{blue}" cx="{mark_cx}" cy="{-R}" r="{R}"/>',
        'PAL Market')

# Favicon: ink rounded tile, white P, blue dot. Tile 2400 units square.
T = 2400
scale_h = CAP
ox = (T - mark_w) / 2 - left
oy = (T + CAP) / 2
files['favicon.svg'] = (
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {T} {T}">'
    f'<rect width="{T}" height="{T}" rx="480" fill="{INK}"/>'
    f'<g transform="translate({ox} {oy})"><path fill="{WHITE}" d="{p_only}"/>'
    f'<circle fill="{BLUE_DARK}" cx="{mark_cx}" cy="{-R}" r="{R}"/></g></svg>\n')

for name, content in files.items():
    open(os.path.join(out, name), 'w').write(content)

# Shapes for the React component
json.dump({'letters': letters, 'p': p_only, 'left': left, 'cap': CAP, 'r': R,
           'dotCx': dot_cx, 'width': width, 'markCx': mark_cx, 'markW': mark_w},
          open(os.path.join(out, 'shapes.json'), 'w'))
print('wordmark viewBox', left, -CAP, width, CAP, 'mark', mark_w)
