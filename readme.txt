=== Translate ===
Translate
Contributors: tox2ik
Donate link: n/a
Tags: language, languages, translate, translation, images, pages, posts, dictionary, the_title, list_pages, 
Requires at least: 2.7
Tested up to: 3.1.3
Stable tag: 1.3

== Description == 

There are plenty of auto translate plugins, but they leave the content rigid
with grammatical errors.  For those needing a solution to translate a WordPress site manually into
unlimited languages or versions, WP-Translate will do the job.

Using shortcodes, template tags, and a widget, you can easily create a site that will reflect appropriate content, images, links, and text for each language.  You can use simple shortcodes in your content, or customize your templates to change your entire site.  The beautiful thing is that you don't need to create multiple posts or pages for each language.  All content is filtered off one post or page.
To facilitate the process of translating themes, there is a dictionary of terms in the admin page.  The term translations may be retrieved with a shortcode or a template tag and make customization of elements such as forms or titles on the page very handy.  



== Installation == 

1. Unzip `Translate.zip` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Add languages in the Translate page under Plugins in the admin menu.
1. Visit: [http://genja.org/wordpress/2011/06/worpress-translate/](the author's
blog) for full documentation of shortcodes and template tags. The same
documentation is also available in `translate.html`.

== Screenshots ==

1. The admin interface with a few terms and languages.

== Previous Versions ==

This plugin is a continuation of the Translate plugin by misternifty. The 
new maintainer is tox2ik as of version 1.3. 

== Changelog == 

= 1.3 =
* June 2011; Jaroslav Rakhmatoullin <jazzoslav@gmail.com>:
* The meta_key which is used for translating titles is no longer just the language name. For clarity there is now an underscore folowed by `title' like so: 
	 - meta_key: english -> english_title
* updated the admin page.
	 - removing and making languages default works.
	 - added a dictionary and forms to manipulate it.

* removed the old wp_translate table. The new table names are now 
prefix_translate_langs and prefix_translate_dict. _langs table has new 
fields: icon and order which deside which img to show for language in 
list_translations() as well as the order of languages.
*  misternifty removed from the list of contributors (his request)

= 1.2 = 

* Translate no longer uses commas as delimiters for the parameters, use the  | pipe symbol instead.
This caused problems when trying to translate paragraphs with commas.
* Nested shortcodes. You can now add contact forms, photo galleries and whatever other plugin that uses shortcodes into the [translate] tags.

== Upgrade Notice ==
= 1.3 =
Adds support for a defining and and using translated strings as well 
as a couple new shortcodes.
= 1.2 =
Comma delimiters have been changed. translate_{text,image,link} now takes
arguments separated by the pipe (|) character and not comma (,) as previously.
This is done to allow commas in the text=argument.
