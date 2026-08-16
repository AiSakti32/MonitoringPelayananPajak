from PIL import Image

src = r"d:\Projek\26AGS004\public\assets\img\_logo_src.png"
out = r"d:\Projek\26AGS004\public\assets\img\kajanglakologobersih.png"

img = Image.open(src).convert("RGBA")
pixels = img.load()
w, h = img.size

for y in range(h):
    for x in range(w):
        r, g, b, a = pixels[x, y]
        if r <= 18 and g <= 18 and b <= 18:
            pixels[x, y] = (0, 0, 0, 0)
        elif r <= 35 and g <= 35 and b <= 35:
            fade = max(0, min(255, int((max(r, g, b) / 35) * 255)))
            pixels[x, y] = (r, g, b, fade)

bbox = img.getbbox()
if bbox:
    left, top, right, bottom = bbox
    pad = 8
    left = max(0, left - pad)
    top = max(0, top - pad)
    right = min(w, right + pad)
    bottom = min(h, bottom + pad)
    img = img.crop((left, top, right, bottom))

max_side = 256
iw, ih = img.size
scale = min(max_side / iw, max_side / ih, 1.0)
if scale < 1.0:
    img = img.resize((max(1, int(iw * scale)), max(1, int(ih * scale))), Image.Resampling.LANCZOS)

img.save(out, "PNG", optimize=True)
print("saved", out, img.size, img.mode)
