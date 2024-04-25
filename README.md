# IndieBlocks
Use blocks, and, optionally, "short-form" post types to more easily "IndieWebify" your WordPress site.

## Why
This plugin lets you:

* Add a "microblog" to either a new or existing WordPress site
* Add Webmention support to enable rich _cross-site_ conversations
* Add "microformats" to your WordPress site's front end and posts, so that Webmention endpoints are able to correctly interpret your posts

## How
IndieBlocks provides a number of blocks that can be used to add microformats to posts _without having to tweak or know HTML_. It also adds a number of "short-form" Custom Post Types to more easily add a "microblog" to your site. (Both are optional, like, well, all of IndieBlocks features.)

IndieBlocks also "hooks" into the [Micropub](https://wordpress.org/plugins/micropub/) plugin, so that Micropub posts are given the correct post type, and, when applicable, "microformats" blocks.

If you're running a "block theme," it will attempt to add "microformats" to it. (If your theme already supports microformats, just leave this part disabled.)

There's also Webmention, which can be enabled for posts, notes, and likes (and any other post types of your choosing). This, in combination with the aforementioned microformats and this plugin's blocks, enables richer _cross-site_ conversations. (If you're perfectly happy with your existing Webmention setup, here, too, you can simply leave this setting disabled.)

## Classic Editor and Themes
IndieBlocks, despite its name, runs just fine on sites that have the Classic Editor plugin installed, or don't (yet) use a "block theme." The Custom Post Types and Webmention options will just work, as will most of its smaller "modules." Microformats, however, will need to be added some other way. (It's absolutely okay to run a "classic" theme with microformats support and add microformats to posts by hand or through a different plugin.)
