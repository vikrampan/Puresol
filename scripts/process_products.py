"""
Product image processor:
- Removes white/light backgrounds from product images using alpha channel flood-fill
- Color grades textilesalt.png to warm cream/pink brand palette matching the other two products
- Outputs as PNG with transparency into assets/
"""

from PIL import Image, ImageFilter, ImageEnhance, ImageOps
import numpy as np
import os

ASSETS = r"c:\files (4) - Copy\assets"

# ── 1. Background removal via flood-fill from corners ──────────────────────

def remove_bg(img_path, out_path, threshold=30):
    """
    Opens image, converts to RGBA, then flood-fills white/near-white areas
    from all four corners and sets them to transparent.
    Uses a floodfill-style approach with numpy for speed.
    """
    img = Image.open(img_path).convert("RGBA")
    data = np.array(img, dtype=np.uint8)
    r, g, b, a = data[:,:,0], data[:,:,1], data[:,:,2], data[:,:,3]

    # Mark pixels close to white (or light grey for textilesalt)
    # Use a 3-channel distance from white
    dist_from_white = (255 - r.astype(int))**2 + (255 - g.astype(int))**2 + (255 - b.astype(int))**2
    is_bg_candidate = dist_from_white < threshold**2 * 3

    # BFS flood-fill from all four corners to find connected background region
    from collections import deque
    h, w = img.size[1], img.size[0]
    visited = np.zeros((h, w), dtype=bool)
    queue = deque()

    # Seed from corners and all border pixels
    for y in range(h):
        for x in [0, w-1]:
            if is_bg_candidate[y, x] and not visited[y, x]:
                queue.append((y, x))
                visited[y, x] = True
    for x in range(w):
        for y in [0, h-1]:
            if is_bg_candidate[y, x] and not visited[y, x]:
                queue.append((y, x))
                visited[y, x] = True

    while queue:
        cy, cx = queue.popleft()
        for dy, dx in [(-1,0),(1,0),(0,-1),(0,1)]:
            ny, nx = cy+dy, cx+dx
            if 0 <= ny < h and 0 <= nx < w and not visited[ny, nx] and is_bg_candidate[ny, nx]:
                visited[ny, nx] = True
                queue.append((ny, nx))

    # Feather the mask slightly with a small blur to anti-alias edges
    mask = visited.astype(np.uint8) * 255
    from PIL import Image as PILImage
    mask_img = PILImage.fromarray(mask, mode='L')
    mask_blurred = mask_img.filter(ImageFilter.GaussianBlur(radius=1.5))
    mask_arr = np.array(mask_blurred)

    # Apply: set alpha = 255 - mask (background → transparent, subject → opaque)
    alpha_channel = np.clip(255 - mask_arr.astype(int), 0, 255).astype(np.uint8)
    data[:,:,3] = alpha_channel

    result = Image.fromarray(data, 'RGBA')
    result.save(out_path, 'PNG')
    print(f"  ✓ Saved: {out_path}")
    return result


# ── 2. Color grade textilesalt to match the warm brand palette ─────────────

def color_grade_textile(img_path, out_path):
    """
    Grades the textilesalt image to match the warm cream/blush tones of
    naturalalkalinesalt and super7:
    - Adds warm ivory tint to neutral greys
    - Increases saturation slightly  
    - Warms up highlights with a slight pink-cream cast
    - Applies contrast comparable to the other two
    """
    img = Image.open(img_path).convert("RGBA")
    rgba_data = np.array(img, dtype=np.float32)
    alpha = rgba_data[:,:,3].copy()
    rgb = rgba_data[:,:,:3]

    # === Step 1: Warm up the image - add a warm cream cast ===
    # The other images have a warm ivory/cream background feel
    # We'll apply a warm grade: lift shadows to warm beige, keep highlights creamy
    
    # Normalize to 0-1
    rgb_norm = rgb / 255.0

    # Step 2: Increase warmth by boosting red slightly, cooling blue
    # Warm grade matrix: shift towards cream (#FFF8F0 feel)
    rgb_norm[:,:,0] = np.clip(rgb_norm[:,:,0] * 1.04 + 0.02, 0, 1)  # Red: slight lift
    rgb_norm[:,:,1] = np.clip(rgb_norm[:,:,1] * 1.01 + 0.01, 0, 1)  # Green: tiny lift
    rgb_norm[:,:,2] = np.clip(rgb_norm[:,:,2] * 0.92 - 0.01, 0, 1)  # Blue: reduce for warmth

    # Step 3: S-curve for contrast similar to the other images
    # Lift shadows slightly, keep mids crisp
    def s_curve(x, shadow_lift=0.03, highlight_compression=0.97):
        x = x * highlight_compression + shadow_lift * (1 - x)
        return np.clip(x, 0, 1)
    
    rgb_norm = s_curve(rgb_norm)

    # Step 4: Blend a warm pink tint into the background areas (light pixels)
    # Brand cream: (255, 248, 240) → normalized (1.0, 0.973, 0.941)
    # Brand blush: (255, 235, 240) → light areas get a slight blush cast
    luminance = 0.299 * rgb_norm[:,:,0] + 0.587 * rgb_norm[:,:,1] + 0.114 * rgb_norm[:,:,2]
    light_mask = np.clip((luminance - 0.6) / 0.35, 0, 1)[:,:,np.newaxis]  # Only very light areas
    
    # Warm cream tint for highlights
    cream_tint = np.array([1.0, 0.965, 0.935])
    rgb_norm = rgb_norm * (1 - light_mask * 0.12) + cream_tint * light_mask * 0.12

    # Step 5: Boost saturation slightly using HSV-style approach
    gray = luminance[:,:,np.newaxis]
    rgb_norm = rgb_norm * 1.12 + gray * (1 - 1.12)  # Increase saturation 12%
    rgb_norm = np.clip(rgb_norm, 0, 1)

    # Reconstruct
    result_rgb = (rgb_norm * 255).astype(np.uint8)
    result = np.dstack([result_rgb, alpha.astype(np.uint8)])
    result_img = Image.fromarray(result, 'RGBA')
    result_img.save(out_path, 'PNG')
    print(f"  ✓ Graded and saved: {out_path}")


# ── Main ───────────────────────────────────────────────────────────────────

print("Processing Natural Alkaline Salt...")
remove_bg(
    os.path.join(ASSETS, "naturalalkalinesalt.jpeg"),
    os.path.join(ASSETS, "naturalalkalinesalt.png"),
    threshold=35
)

print("Processing Super 7 Salt...")
remove_bg(
    os.path.join(ASSETS, "super7.jpeg"),
    os.path.join(ASSETS, "super7.png"),
    threshold=35
)

print("Processing Textile Salt (bg removal + color grade)...")
# First remove background
textile_no_bg_path = os.path.join(ASSETS, "_textile_nobg_tmp.png")
remove_bg(
    os.path.join(ASSETS, "textilesalt.png"),
    textile_no_bg_path,
    threshold=40  # Slightly higher threshold for the grey bg
)
# Then color grade the bg-removed version
color_grade_textile(
    textile_no_bg_path,
    os.path.join(ASSETS, "textilesalt_branded.png")
)
# Clean up temp
os.remove(textile_no_bg_path)

print("\nAll done! New files:")
print("  assets/naturalalkalinesalt.png  (bg removed)")
print("  assets/super7.png               (bg removed)")
print("  assets/textilesalt_branded.png  (bg removed + color graded)")
