=== Elementor Automation Importer ===
Contributors: ChatGPT
Tags: elementor, import, automation, json, media
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Elementor Automation Importer processes custom automation JSON files with embedded base64 image assets, uploads those assets to the WordPress Media Library, replaces placeholders with the new media ID/URL, and creates Elementor Library templates.

== How to use ==
1. Install and activate the plugin.
2. Go to WordPress Dashboard > Elementor Automation.
3. Upload an automation JSON file.
4. Click Process & Import.
5. Use the Edit with Elementor button after import.

== JSON placeholders ==
Use these placeholders inside Elementor widget settings:
{{asset:main_image:id}}
{{asset:main_image:url}}
{{asset:main_image:filename}}
{{asset:main_image:mime}}

== Security ==
SVG upload is disabled by default. You may enable it per import if the SVG file is trusted.
