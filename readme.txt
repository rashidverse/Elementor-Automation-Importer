=== Elementor Automation Importer ===
Contributors: RashidVerse
Tags: elementor, import, automation, json, media
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Import custom Elementor automation JSON files, upload embedded/base64 image assets to the WordPress Media Library, replace asset placeholders, and create Elementor Library templates.

== Description ==

Elementor Automation Importer is an admin-only WordPress tool for importing automation-ready Elementor template JSON files.

The plugin processes a structured JSON file, uploads optional embedded image assets to the Media Library, replaces placeholder tokens inside Elementor content, saves a processed JSON copy, and can create a ready-to-edit Elementor Library template.

== Features ==

* Upload custom `.json` automation files from the WordPress dashboard.
* Create Elementor Library templates from imported Elementor content.
* Upload embedded base64 images to the Media Library.
* Replace asset placeholders with real attachment IDs, URLs, filenames, or MIME types.
* Override the template title during import.
* Save a processed JSON copy in the WordPress uploads directory.
* Optional SVG import with basic unsafe-content checks.
* Admin-only access using the `manage_options` capability.
* Nonce-protected import form.

== Requirements ==

* WordPress 6.0 or newer.
* PHP 7.4 or newer.
* Elementor installed and active for template creation.
* Administrator access to the WordPress dashboard.

Elementor is required when creating templates in the Elementor Library. If Elementor is inactive, the plugin can still process JSON and assets, but template creation will not be available.

== Installation ==

1. Upload the `elementor-automation-importer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Go to WordPress Dashboard > Elementor Automation.

== How to use ==

1. Go to WordPress Dashboard > Elementor Automation.
2. Upload an automation JSON file.
3. Optionally enter a template title override.
4. Keep "Create Elementor Library template" enabled if you want a template created automatically.
5. Enable SVG assets only when the uploaded SVG files are trusted.
6. Click Process & Import.
7. Use the Edit with Elementor button or download the processed JSON after import.

== JSON format ==

The importer expects an Elementor export-like object with a `content` array. It also accepts `elementor_data` for older automation files, and raw Elementor content arrays where the first item contains `elType`.

Example:

`
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
  "content": []
}
`

A minimal sample file is included at `samples/sample-automation-template.json`.

== Supported template types ==

Supported values are `section`, `container`, `page`, `post`, `header`, `footer`, `single`, `archive`, `popup`, `error-404`, and `wp-page`.

Unsupported or missing template types are normalized to `section`.

== Asset placeholders ==

Use these placeholders inside Elementor widget settings, page settings, or content strings:

`
{{asset:main_image:id}}
{{asset:main_image:url}}
{{asset:main_image:filename}}
{{asset:main_image:mime}}
`

If the whole JSON string is an ID placeholder, the value is converted to an integer. Other placeholders are replaced as strings.

== Supported asset types ==

The plugin supports these image MIME types:

* `image/jpeg`
* `image/png`
* `image/webp`
* `image/gif`
* `image/svg+xml` only when SVG import is enabled for that import.

== Output ==

After a successful import, the plugin can show:

* Imported template title.
* Elementor Library template ID.
* Edit with Elementor link.
* Uploaded asset count.
* Download link for the processed JSON.

Processed JSON files are saved in `/wp-content/uploads/elementor-automation-importer/`.

== Security ==

Only administrators can access the importer screen. The import request is nonce-protected, and uploaded files must use the `.json` extension.

SVG upload is disabled by default. You may enable it per import only when the SVG file is trusted. SVG files are checked for common risky patterns such as scripts, event handlers, iframes, objects, embeds, and `javascript:` URLs.

== Frequently Asked Questions ==

= Elementor template is not created. What should I check? =

Make sure Elementor is installed and active. The plugin creates templates in the `elementor_library` post type, which is registered by Elementor.

= Why was my asset blocked? =

The asset must include valid base64 data and use a supported image MIME type. SVG files must be explicitly enabled for the import.

= Can I import JSON without embedded assets? =

Yes. The `assets` array can be empty.

== Changelog ==

= 1.0.0 =

* Initial release.
