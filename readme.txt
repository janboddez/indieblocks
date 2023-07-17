=== IndieBlocks ===
Contributors: janboddez
Tags: blocks, gutenberg, indieweb, notes, likes, microblog, microblogging, micropub, fse, site editor, webmention, syndication
Tested up to: 6.2
Stable tag: 0.7.1
License: GNU General Public License v3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Use blocks, and, optionally, "short-form" post types to easily "IndieWebify" your WordPress site.

== Description ==
IndieBlocks registers several blocks (Bookmark, Like, Reply, and Repost, as well as the older Context block) that take a URL and output corresponding _microformatted_ HTML.

In combination with a microformats-compatible theme, these help ensure microformats clients are able to determine a post's type.

It also comes with "short-form" (Note and Like) custom post types, and a (somewhat experimental) option to add microformats to (all!) *block-based* themes.

These microformats, in combination with the Webmention protocol, allow for rich _cross-site_ conversations. IndieBlocks comes with its own Webmention implementation, but a separate plugin can be used, too.

IndieBlocks also registers several "theme" blocks (Facepile, Location, Syndication, and Link Preview), to be used in (block) theme templates.

== Installation ==
Upload this plugin's ZIP file via the Plugins > Add New > "Upload Plugin" button.

After activation, head over to *Settings > IndieBlocks*, and enable or disable its different features.

More details can be found on [https://indieblocks.xyz/](https://indieblocks.xyz/). Issues may be filed at [https://github.com/janboddez/indieblocks](https://github.com/janboddez/indieblocks).

== Changelog ==
= 0.8.0 =
Various bug fixes. Add Link Preview block. Also, webmentions are now closed when comments are, although this behavior is filterable.
= 0.7.1 =
Add Location block. The Facepile block now supports v5.0 and up of the Webmention plugin.
= 0.7.0 =
Store temperatures in Kelvin rather than degrees Celsius. Update `masterminds/html5` to version 2.8.0. Add Location block.
= 0.6.0 =
"Facepile" likes, bookmarks, and reposts.
= 0.5.0 =
Add Bookmark, Like, Reply and Repost blocks. Additional title options.
= 0.4.0 =
Add `indieblocks/syndication-links` block.
= 0.3.6 =
Minor bug fix, new plugin URL.
= 0.3.5 =
Fix rescheduling of webmentions from the classic editor.
= 0.3.4 =
Webmention tweaks.
= 0.3.3 =
Slight block changes. Bug fixes, and basic Webmention support.
= 0.2.0 =
Slightly improved "empty" URL handling, and permalink flushing. Additional CPT, feed and Micropub options. Date-based CPT archives, and basic location functions.
= 0.1.0 =
Very first version.
