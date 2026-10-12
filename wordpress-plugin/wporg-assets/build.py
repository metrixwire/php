#!/usr/bin/env python3
"""
Builds the WordPress.org banner and icon for MetrixWire APM from the brand logo
(apps/web/public/logo.svg). Run from anywhere: python3 wporg-assets/build.py
"""
import io
from pathlib import Path

import cairosvg
from PIL import Image, ImageDraw, ImageFont

OUT = Path(__file__).parent
INDIGO = "#4f46e5"

# The waveform mark from apps/web/public/logo.svg, kept in sync by hand.
MARK_PATH = (
    "M30 20h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0"
    "L17.24 10.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 12.49 20H10"
)
STROKE = f'fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"'

LOGO_SVG = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40">
  <rect width="40" height="40" rx="9" fill="{INDIGO}" />
  <path d="{MARK_PATH}" {STROKE} />
</svg>'''

BANNER_SVG = f'''<svg xmlns="http://www.w3.org/2000/svg" width="1544" height="500" viewBox="0 0 1544 500">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#1e1b4b"/>
      <stop offset="1" stop-color="#4338ca"/>
    </linearGradient>
  </defs>
  <rect width="1544" height="500" fill="url(#bg)"/>
  <g transform="translate(880,60) scale(11)" opacity="0.13">
    <path d="{MARK_PATH}" {STROKE} />
  </g>
  <g transform="translate(96,170) scale(4)">
    <rect width="40" height="40" rx="9" fill="{INDIGO}" />
    <path d="{MARK_PATH}" {STROKE} />
  </g>
</svg>'''


def font(bold: bool, size: int) -> ImageFont.FreeTypeFont:
    name = "Arial Bold.ttf" if bold else "Arial.ttf"
    return ImageFont.truetype(f"/System/Library/Fonts/Supplemental/{name}", size)


def render(svg: str, width: int, height: int) -> Image.Image:
    png = cairosvg.svg2png(bytestring=svg.encode(), output_width=width, output_height=height)
    return Image.open(io.BytesIO(png)).convert("RGBA")


def save(image: Image.Image, name: str) -> None:
    image.convert("RGB").save(OUT / name)
    print(name, image.size)


# --- Icons: the logo mark, straight from the brand SVG ------------------------
(OUT / "icon.svg").write_text(LOGO_SVG + "\n")
print("icon.svg")
for size in (128, 256):
    save(render(LOGO_SVG, size, size), f"icon-{size}x{size}.png")

# --- Banner: brand background at 2x, wordmark drawn on top --------------------
banner = render(BANNER_SVG, 1544, 500)
draw = ImageDraw.Draw(banner)
draw.text((304, 258), "MetrixWire APM", font=font(True, 84), fill="#ffffff", anchor="ls")
draw.text((304, 322), "Zero-config APM for WordPress", font=font(False, 38), fill="#c7d2fe", anchor="ls")

save(banner, "banner-1544x500.png")
save(banner.resize((772, 250), Image.LANCZOS), "banner-772x250.png")
