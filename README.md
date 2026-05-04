# Elementor Automation Importer

Import custom Elementor automation JSON files, upload embedded image assets to the WordPress Media Library, replace asset placeholders, and create Elementor Library templates from one admin screen.

## Overview

Elementor Automation Importer is a WordPress admin tool for moving generated or automation-ready Elementor template data into a WordPress site. It accepts a structured JSON file, processes optional embedded base64 image assets, replaces placeholder tokens inside Elementor content, saves a processed JSON copy, and can create a ready-to-edit Elementor Library template.

## Features

- Upload custom `.json` automation files from the WordPress dashboard.
- Create Elementor Library templates from imported Elementor content.
- Upload embedded base64 images to the Media Library.
- Replace asset placeholders with real attachment IDs, URLs, filenames, or MIME types.
- Supports title override during import.
- Saves a processed JSON file under the WordPress uploads directory.
- Optional SVG import with basic unsafe-content checks.
- Admin-only access using the `manage_options` capability.
- Nonce-protected import form.

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- Elementor installed and active for template creation.
- Administrator access to the WordPress dashboard.

Elementor is required when creating templates in the Elementor Library. If Elementor is inactive, the plugin can still process the JSON and assets, but Elementor template creation will not be available.

## Installation

1. Download or clone this repository.
2. Copy the `elementor-automation-importer` folder into `wp-content/plugins/`.
3. In WordPress admin, go to **Plugins**.
4. Activate **Elementor Automation Importer**.
5. Go to **Elementor Automation** in the WordPress dashboard.

## How To Use

1. Open **WordPress Dashboard > Elementor Automation**.
2. Upload an automation JSON file.
3. Optionally enter a template title override.
4. Keep **Create Elementor Library template** enabled if you want a template created automatically.
5. Enable **Allow SVG assets for this import** only when the uploaded SVG files are trusted.
6. Click **Process & Import**.
7. After import, use **Edit with Elementor** or download the processed JSON.

## JSON Format

The importer expects an Elementor export-like object with a `content` array. It also accepts `elementor_data` for older automation files, and raw Elementor content arrays where the first item contains `elType`.

```json
{
  "title": "Template Title",
  "type": "section",
  "version": "0.4",
  "page_settings": [],
  "assets": [
    {
      "key": "main_image",
      "filename": "main-image.webp",
      "mime": "image/webp",
      "data": "BASE64_IMAGE_DATA"
    }
  ],
  "content": [
    {
      "elType": "container",
      "settings": {},
      "elements": [
        {
          "elType": "widget",
          "widgetType": "image",
          "settings": {
            "image": {
              "id": "{{asset:main_image:id}}",
              "url": "{{asset:main_image:url}}"
            }
          }
        }
      ]
    }
  ]
}
```

A minimal example is available at [`samples/sample-automation-template.json`](samples/sample-automation-template.json).

## Supported Template Types

The importer normalizes unsupported or missing template types to `section`. Supported values are:

- `section`
- `container`
- `page`
- `post`
- `header`
- `footer`
- `single`
- `archive`
- `popup`
- `error-404`
- `wp-page`

## Asset Objects

Each asset in the `assets` array should include:

| Field | Required | Description |
| --- | --- | --- |
| `key` | Yes | Unique placeholder key, such as `main_image`. |
| `filename` | No | File name to use in the Media Library. If no extension is provided, the importer adds one from the MIME type. |
| `mime` | No | Image MIME type. If omitted, the plugin attempts to detect it from the binary data. |
| `data` | Yes | Base64 image data. Data URI format is also supported. |

Supported image MIME types:

- `image/jpeg`
- `image/png`
- `image/webp`
- `image/gif`
- `image/svg+xml` only when SVG import is enabled for that import

## Asset Placeholders

Use placeholders anywhere inside Elementor settings, page settings, or content strings. The importer replaces them after uploading the matching asset.

```text
{{asset:main_image:id}}
{{asset:main_image:url}}
{{asset:main_image:filename}}
{{asset:main_image:mime}}
```

If the whole JSON string is an ID placeholder, the value is converted to an integer. Other placeholders are replaced as strings.

## Output

After a successful import, the plugin can return:

- The imported template title.
- The Elementor Library template ID.
- A direct **Edit with Elementor** link.
- The number of uploaded assets.
- A downloadable processed JSON file.

Processed JSON files are saved in:

```text
wp-content/uploads/elementor-automation-importer/
```

## Security Notes

- Only administrators with `manage_options` can access the importer screen.
- The import request is protected with a WordPress nonce.
- Uploaded files must use the `.json` extension.
- SVG import is disabled by default.
- SVG files are checked for common risky patterns such as scripts, event handlers, iframes, objects, embeds, and `javascript:` URLs.
- Only import JSON and SVG files from sources you trust.

## Troubleshooting

**Elementor template is not created**

Make sure Elementor is installed and active. The plugin creates posts in the `elementor_library` post type, which is registered by Elementor.

**Asset upload fails**

Check that every asset has a valid `key` and valid base64 `data`, and that the MIME type is supported.

**SVG asset is blocked**

SVG upload must be enabled per import. The SVG can still be blocked if it contains unsafe patterns.

**JSON import fails**

Confirm that the file is valid JSON and includes one of these structures:

- A `content` array.
- An `elementor_data` array.
- A raw Elementor content array.

## Project Structure

```text
elementor-automation-importer.php
includes/
  class-eai-admin.php
  class-eai-importer.php
  class-eai-asset-processor.php
  class-eai-template-creator.php
assets/
  admin.css
samples/
  sample-automation-template.json
readme.txt
```

## License

GPL-2.0-or-later.

## Author

Developed by [RashidVerse](https://rashidverse.github.io/portfolio-websites/).
