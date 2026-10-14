# PAL Market logo assets

Source: the "PAL." logo photo supplied by the owner (October 2026): heavy geometric capitals in near-black with a round blue full stop. Colours sampled from that image: letters `#1C2228`, dot `#1C73C1`.

The vectors are the letters P, A and L outlined from Manrope ExtraBold (SIL Open Font License, already the app's body font) plus a circle, so the mark is clean at any size and needs no font at runtime.

| File | Use |
| --- | --- |
| `public/brand/pal-wordmark-light.svg` | Wordmark on light backgrounds |
| `public/brand/pal-wordmark-dark.svg` | Wordmark on dark backgrounds (white letters, lighter dot `#4D9BE0`) |
| `public/brand/pal-mark-light.svg`, `pal-mark-dark.svg` | Compact "P." mark |
| `public/favicon.svg`, `public/apple-touch-icon.png` | Browser tab and home-screen icon |
| `resources/js/components/Logo.jsx` | In-app logo; colours follow the theme through `--logo-ink` / `--logo-dot` |

Regenerate:

```bash
pip install fonttools brotli
npm pack @fontsource/manrope@5 && tar xzf fontsource-manrope-*.tgz
python3 scripts/brand/outline.py package/files/manrope-latin-800-normal.woff2 PAL -20 > pal.json
python3 scripts/brand/build.py pal.json out/
```
