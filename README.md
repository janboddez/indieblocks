# IndieBlocks
Use blocks, and, optionally, "short-form" post types to more easily "IndieWebify" your WordPress site.

## Why
This plugin aims to provide a basis for:

* Adding a "microblog" to either a new or existing WordPress site
* Adding (basic) Webmention support to your blog, to enable rich _cross-site_ conversations
* Adding a number of "microformats" to your WordPress site's front end and posts, so that Webmention endpoints are able to correctly interpret your posts (short-form or otherwise)

## How
IndieBlocks provides a number of blocks that can be used to add microformats to posts without having to tweak HTML. It also adds a number of "short-form" Custom Post Types. (Both are optional, like, well, all of IndieBlocks features.)

IndieBlocks also hooks into the existing Micropub plugin, so that Micropub posts are given the correct post type, and, when applicable, "microformats" blocks.

If you're running a "block theme," it will attempt to add "microformats2" to it. (If your theme already supports microformats2, just leave this part disabled.)

There's also Webmention, which can be enabled for posts, notes, and likes (and any other post types of your choosing). This, in combination with the aforementioned microformats2 and this plugin's blocks (and block patterns), will enable richer _cross-site_ conversations. (If you're perfectly happy with your existing Webmention setup, here, too, you can simply leave this setting disabled.)

## Classic Editor and Themes
IndieBlocks, despite its name, runs just fine on sites that have the Classic Editor plugin installed, or don't (yet) use a "block theme." The Custom Post Types and Webmention options will just work. Microformats, however, will need to be added some other way. (It's absolutely okay to run a "classic" theme with microformats2 support and add microformats to posts by hand or through a different plugin.)
