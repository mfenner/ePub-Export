=== ePub Export ===
Plugin URI: http://wordpress.org/extend/plugins/epub-export/
Contributors: mfenner
Donate link: None
Tags: export, epub, css, html, science, research, res-comms
Requires at least: 3.0
Tested up to: 3.1.3
Stable tag: 1.1.1

ePub Export automatically creates an ePub file of every published or updated post or page.

== Description ==

ePub Export automatically creates an ePub file when a post or page is published or updated. The ePubs are stored in the uploads directory. This plugin uses the PHP ePub class, the htmLawed library, and the STIX fonts.


== Installation ==

Installation Instructions:

1. Download the plugin and unzip it.
2. Put the epub-export folder into the <code>wp-content/plugins/</code> directory.
3. Go to the Plugins page in your WordPress Administration area and click 'Activate' next to ePub Export.
4. Go to <code>Tools/ePub Export</code> to configure the plugin options. 
5. An ePub file is created in the uploads directory every time a post or page is created/updated.

== Frequently Asked Questions ==

= It don't work - What should I do? =

First of all, make sure that the plugin is activated.

= How can I change the look of my ePub =

Add a CSS file called epub.css to your theme.


== Screenshots ==

1. The ePub Export options page

 
== Changelog ==

= 1.1.1 =
* Added support for custom post type article

= 1.1 =
* Automatically creates cover images. This function requires the gd library with freetype support. Without these libraries a textual front page is created.

= 1.0.2 =
* Use CSS from active theme if it has epub.css file

= 1.0.1 =
* Use apply_filters (shortcodes, etc.) before writing XHTML to ePub
* Added unique identifier (uuid)
* Changed location for ePub files to uploads directory 

= 1.0 =
* Initial Release


== Upgrade Notice ==

= 1.1.1 =
* Added support for custom post type article

= 1.1 =
* Automatically creates cover images. This function requires the gd library with freetype support. Without these libraries a textual front page is created.

= 1.0.2 =
* Use CSS from active theme if it has epub.css file

= 1.0.1 =
* Use apply_filters (shortcodes, etc.) before writing XHTML to ePub
* Added unique identifier (uuid)
* Changed location for ePub files to uploads directory 

= 1.0 =
* Initial Release