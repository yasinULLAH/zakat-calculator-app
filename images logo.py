import os
import json
from PIL import Image

# --- CONFIGURATION ---
# All generated files will be placed in this directory.
OUTPUT_DIR = 'public'

# The name of your source logo file.
INPUT_LOGO = 'logo.png'

# Standard icon sizes required for the manifest.json
ICON_SIZES = [72, 96, 128, 144, 152, 192, 384, 512]

# Apple touch icon size (will also be used from the sizes above)
APPLE_TOUCH_ICON_SIZE = 192

# Favicon sizes for the .ico file
FAVICON_SIZES = [16, 24, 32, 48, 64]

# --- MANIFEST.JSON DETAILS ---
# Update these with your app's information.
MANIFEST_CONFIG = {
    "name": "زکوٰۃ مینیجر",
    "short_name": "زکوٰۃ مینیجر",
    "description": "A comprehensive tool to calculate, manage, and track your Zakat.",
    "start_url": "./zakatCalc.html",
    "display": "standalone",
    "background_color": "#ffffff",
    "theme_color": "#006400",
    "orientation": "portrait-primary",
    "icons": [] # This will be populated by the script
}

def create_output_directories():
    """Creates the main output directory and the 'icons' subdirectory."""
    icons_dir = os.path.join(OUTPUT_DIR, 'icons')
    os.makedirs(icons_dir, exist_ok=True)
    print(f"Directory '{icons_dir}' is ready.")

def generate_icons(logo_image):
    """Generates PNG icons of various sizes."""
    print("Generating PNG icons...")
    icons_dir = os.path.join(OUTPUT_DIR, 'icons')
    manifest_icons = []

    for size in ICON_SIZES:
        filename = f"icon-{size}x{size}.png"
        output_path = os.path.join(icons_dir, filename)
        
        # Resize image with high-quality downsampling filter
        resized_image = logo_image.resize((size, size), Image.Resampling.LANCZOS)
        resized_image.save(output_path, 'PNG')
        
        print(f"  - Created {filename}")

        # Add icon info to manifest list
        manifest_icons.append({
            "src": f"icons/{filename}",
            "type": "image/png",
            "sizes": f"{size}x{size}"
        })
        
        # Check if this size is the one for Apple touch icon
        if size == APPLE_TOUCH_ICON_SIZE:
            apple_touch_path = os.path.join(OUTPUT_DIR, 'apple-touch-icon.png')
            resized_image.save(apple_touch_path, 'PNG')
            print(f"  - Created apple-touch-icon.png")

    return manifest_icons

def generate_favicon(logo_image):
    """Generates a multi-size favicon.ico."""
    print("Generating favicon.ico...")
    favicon_path = os.path.join(OUTPUT_DIR, 'favicon.ico')
    logo_image.save(favicon_path, 'ICO', sizes=[(s, s) for s in FAVICON_SIZES])
    print("  - Created favicon.ico")

def generate_manifest(icons_list):
    """Creates the manifest.json file."""
    print("Generating manifest.json...")
    manifest_path = os.path.join(OUTPUT_DIR, 'manifest.json')
    
    MANIFEST_CONFIG['icons'] = icons_list
    
    with open(manifest_path, 'w', encoding='utf-8') as f:
        json.dump(MANIFEST_CONFIG, f, indent=4, ensure_ascii=False)
    print("  - Created manifest.json")

def generate_service_worker():
    """Creates a basic service worker for offline caching."""
    print("Generating sw.js (service worker)...")
    sw_path = os.path.join(OUTPUT_DIR, 'sw.js')
    
    sw_content = """
const CACHE_NAME = 'zakat-manager-cache-v1';
const urlsToCache = [
  './',
  './zakatCalc.html',
  './manifest.json',
  './prices-history.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      }
    )
  );
});
"""
    with open(sw_path, 'w', encoding='utf-8') as f:
        f.write(sw_content.strip())
    print("  - Created sw.js")


def main():
    """Main function to orchestrate the asset generation."""
    try:
        logo = Image.open(INPUT_LOGO)
    except FileNotFoundError:
        print(f"Error: The input file '{INPUT_LOGO}' was not found.")
        print("Please place your logo in the same directory as this script.")
        return

    create_output_directories()
    manifest_icons_list = generate_icons(logo)
    generate_favicon(logo)
    generate_manifest(manifest_icons_list)
    generate_service_worker()
    
    print("\n✅ All assets generated successfully!")
    print(f"Copy the contents of the '{OUTPUT_DIR}' folder to your project root.")

if __name__ == '__main__':
    main()