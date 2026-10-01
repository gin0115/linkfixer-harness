# Site: Advanced Settings, excluded posts

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/settings-exclusions/blueprint.json

Three posts to find with the "Exclude posts" search boxes in Advanced Settings (`iawmlf_post_search`): "Harness: Tips & Tricks" (a post with an ampersand in its title), "Harness: About us" (a page) and "Harness: Draft tips" (a draft). Both excluded posts lists start empty and the Auto Archiver is on. The site opens on Advanced Settings.

| # | Do | Expect |
|---|---|---|
| 1 | In the Link Fixer's "Exclude posts" box type "Tips". | "Harness: Tips & Tricks" is offered, written as it appears on the site. |
| 2 | Type "amp" instead. | Only "Sample Page" ("amp" is in "Sample"); **not "Harness: Tips & Tricks"**, and **no title shows "&amp;"**. |
| 3 | Search "Tips", choose it, press Save Changes. | After reloading it is on the list as "Harness: Tips & Tricks". |
| 4 | In the Auto Archiver's "Exclude posts" box type "About". | The page "Harness: About us" is offered. |
| 5 | In the Link Fixer's box type "Draft". | **"Harness: Draft tips" is offered**, so it can be excluded before it is published. |
| 6 | Open Plugins. | The Link Fixer's row has a "Settings" link to Advanced Settings. |

On 1.5.0-RC1 steps 2 and 5 fail:

- Step 2: the title is stored here as "Harness: Tips &amp; Tricks" (the seed runs without a user, so WordPress filters the title; the search matched "amp" and the slug `harness-tips-tricks` has none). The search (`Post_Search_Ajax`, `LIKE` on `post_title`) matches "amp" inside the stored `&amp;`. Whether a title typed by an administrator is stored the same way has not been checked. The dropdown's `highlight()` in `admin_settings.js` then wraps "amp" inside the escaped `&amp;` (`&amp;<mark>amp</mark>;`), so the title shows as "Harness: Tips &amp; Tricks". Any title with `&`, `<`, `>` or quotes is affected when the search matches part of the entity.
- Step 5: the search only finds published posts (`post_status` `publish`). A draft cannot be excluded until it is published, and publishing scans its links straight away (`WP_Post_Controller::on_save_post_process_post_links()`). Whether drafts should be searchable is this checklist's call.
