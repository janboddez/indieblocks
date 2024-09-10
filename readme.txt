=== IndieBlocks ===
Contributors: janboddez
Tags: blocks, gutenberg, indieweb, notes, likes, microblog, microblogging, micropub, fse, site editor, webmention, syndication
Tested up to: 6.6
Stable tag: 0.13.1
License: GNU General Public License v3.0
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Use blocks, and, optionally, "short-form" post types to easily "IndieWebify" your WordPress site.

== Description ==
Use blocks, and, optionally, "short-form" post types to easily "IndieWebify" your WordPress site.

IndieBlocks registers several blocks (Bookmark, Like, Reply, and Repost, as well as the older Context block) that take a URL and output corresponding _microformatted_ HTML.

In combination with a microformats-compatible theme, these help ensure microformats clients are able to determine a post's type.

It also comes with "short-form" (Note and Like) custom post types, and a (somewhat experimental) option to add microformats to (all!) *block-based* themes.

These microformats, in combination with the Webmention protocol, allow for rich _cross-site_ conversations. IndieBlocks comes with its own Webmention implementation, but a separate plugin can be used, too.

IndieBlocks also registers several "theme" blocks (Facepile, Location, Syndication, and Link Preview), to be used in "block theme" templates.

== Installation ==
Upload this plugin's ZIP file via the Plugins > Add New > "Upload Plugin" button.

After activation, head over to *Settings > IndieBlocks*, and enable or disable its different features.

More details can be found on [https://indieblocks.xyz/](https://indieblocks.xyz/). Issues may be filed at [https://github.com/janboddez/indieblocks](https://github.com/janboddez/indieblocks).

== Frequently Asked Questions ==
= How does this plugin interact with the various other IndieWeb plugins? =
While IndieBlocks does not depend on _any_ other plugin, it is compatible with, and extends, the Micropub plugin for WordPress. See [https://indieblocks.xyz/documentation/micropub-and-indieauth/](https://indieblocks.xyz/documentation/micropub-and-indieauth/) for some more information.

IndieBlocks&rsquo; Facepile and Syndication blocks also aim to be compatible with, respectively, the Webmention and Syndication Links plugins.

== Changelog ==
= 0.13.1 =
Minor bug fixes. Improved "Facepile" compatibility (with the ActivityPub plugin).

= 0.13.0 =
Improve Gutenberg compatibility of Location and Webmention "meta boxes." Add Syndication block prefix and suffix attributes. Support "update" and "delete" webmentions even after mentions are closed. Add avatar proxy option.

= 0.12.0 =
Improve comment mentions, remove margin "below" hidden note and like titles.

= 0.11.0 =
Improve avatar deletion, add meta box for outgoing "comment mentions," hide meta boxes if empty.

= 0.10.0 =
Send webmentions also for comments, to mentioned sites and the comment parent, if it exists and itself originated as a webmention.

= 0.9.1 =
Fix Webmention backlinks in Facepile block, add avatar background and icon color pickers.

= 0.9.0 =
Overhaul theme microformats functionality.

= 0.8.1 =
Fix issue with saving meta from block editor. Fix Markdown in Micropub notes.

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
