Translate 
=========
Contributors: misternifty, tox2ik
Donate link: n/a
Tags: language, languages, translate, translation, images, pages, posts, dictionary, the_title, list_pages, 
Requires at least: 2.7
Tested up to: 3.1.3
Stable tag: 1.3


== Installation == 

1. Unzip `Translate.zip' to the `/wp-content/plugins/' directory
1. Activate the plugin through the `Plugins' menu in WordPress
1. Add languages in the Translate page under Plugins in the admin menu.
1. Visit: http://genja.org/wordpress/2011/06/worpress-translate/ for full 
	documentation of shortcodes and template tags. The same documentation 
	is also available in translate.html.

== Screenshots ==

1. The admin interface with a few terms and languages.

== Changes ==
== 1.3 ==
June 2011; Jaroslav Rakhmatoullin <jazzoslav@gmail.com>:
* The meta_key which is used for translating titles is no longer just
the language name. For clarity there is now an underscore folowed by
`title' like so: 
meta_key: english -> english_title

* updated the admin page.
	 - removing and making languages default works.
	 - added a dictionary and forms to manipulate it.

* removed the old wp_translate table. The new table names are now 
prefix_translate_langs and prefix_translate_dict. _langs table has new 
fields: icon and order which deside which img to show for language in 
list_translations() as well as the order of languages.

== 1.2 ==
* Translate no longer uses commas as delimiters for the parameters, use the 
| pipe symbol instead. This caused problems when trying to translate 
paragraphs with commas.
* Nested shortcodes. You can now add contact forms, photo galleries and 
whatever other plugin that uses shortcodes into the [translate] tags.
