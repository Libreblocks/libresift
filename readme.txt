=== LibreSift ===
Contributors: libreblocks
Tags: filter, ajax filter, search, facets, listing
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Requires Plugins: libreblocks-core, libreforge
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Filters for LibreForge listings, with instant results.

== Description ==

Create a filter once (city, price range, rooms, search, sorting) and place it next to a Loop Grid with the "Filter" block, the Elementor widget or a shortcode.

* Filter by taxonomy, custom field (choices, ranges or text), text search and sorting.
* Checkboxes, single choice buttons, dropdowns, from/to ranges and search boxes.
* Options are found automatically: taxonomy terms, field options (glossaries included) or saved values.
* Instant results without reloading the page, and a readable address (?f_city=sao-paulo) that can be shared and indexed.
* Works without JavaScript too: filters are regular forms.
* Secure: listings are refreshed only from settings signed by the server.

Shortcodes: `[libresift_filter id="12"]` and `[libresift_reset]`.

Requires LibreBlocks Core and LibreForge.

== Changelog ==

= 0.1.0 =
* First release: taxonomy, field, search and sorting filters with AJAX results.
