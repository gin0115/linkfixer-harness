# Site: Editor permissions

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/permissions/blueprint.json

The site opens logged in as a user called `editor` with the Editor role. Its plugin sets the Link Fixer's `iawmlf_reporting_page_capability` filter to `edit_posts` (on `init`), the example in the Link Fixer README, so editors get the reporting screens. Advanced Settings and the setup wizard stay `manage_options` only. Two posts are seeded: "Harness: an editor's links" with E1 (broken), E2 and E3 (working), and "Harness: an excluded post", which is on the Link Fixer excluded posts list. The site opens on the Dashboard.

| # | Do | Expect |
|---|---|---|
| 1 | Open the Dashboard (the site opens here). | You are logged in as "editor", and the Wayback Link Fixer widget is there. |
| 2 | Look at the buttons along the bottom of the widget. | Dashboard and View Links, but **no Advanced Settings** button. |
| 3 | Look at Link Fixer in the WordPress admin left-hand menu. | It has Dashboard and Links, and no Advanced Settings. |
| 4 | Open `/wp-admin/admin.php?page=iawmlf_settings`, then go back. | "Sorry, you are not allowed to access this page." |
| 5 | Open `/wp-admin/admin.php?page=iawmlf-setup-wizard`, then go back. | "Sorry, you are not allowed to access this page." |
| 6 | Open Link Fixer (its Dashboard page). | The link numbers show, and **no Advanced Settings** button. |
| 7 | Open Link Fixer, Links. | Three rows (E1 to E3), and Bulk actions offers all four actions. |
| 8 | Tick E1, choose "Check link status" and press Apply. | A notice says E1 was checked successfully with 404 status. |
| 9 | Open E3, tick "Exclude this link" and confirm. | "Link updated successfully." E3 is saved as excluded, recorded as asked for by "editor". |
| 10 | Open Posts. | The Links column shows "1 broken out of 3" and "Excluded post". |
| 11 | Look at "Excluded post" in the Links column. | Plain text, **not a link** to Advanced Settings. |

The panel cannot load on WordPress's "not allowed" screen, so for 4 and 5 it opens the address itself as the editor and checks for a 403 with that message.

On 1.5.0-RC1, 2, 6 and 11 fail: the widget (`templates/admin/dashboard/widget.php`, also used on the Link Fixer Dashboard page) always prints the Advanced Settings button, and `WP_Post_Table_Controller::render_link_column()` always links "Excluded post" to Advanced Settings. An editor following either gets "Sorry, you are not allowed to access this page."
