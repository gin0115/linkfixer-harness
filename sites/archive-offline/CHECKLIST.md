# Site: Archive.org offline

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/archive-offline/blueprint.json

The Archive.org stand-in starts offline, with two new links found by the Link Fixer's scan: O1 (already archived) and O2 (needs a new snapshot). Background jobs only run when you press **Run next job** in the panel. The panel's switch takes the stand-in offline or brings it back online (it also clears the Link Fixer's one hour cache of the online status). The site opens on the Dashboard.

| # | Do | Expect |
|---|---|---|
| 1 | Open the Dashboard (the site opens here). | The Wayback Link Fixer widget says "Archive.org API is offline. Processes will be delayed". |
| 2 | Open Link Fixer, Links. | The Bulk actions dropdown only offers "API Offline, options disabled". |
| 3 | Press **Run next job** once. | O1's "Find or create a snapshot" job asks Archive.org nothing and is put back for **1 hour**. |
| 4 | Press **Bring back online**, then open the Dashboard. | The widget says Archive.org API services are online. |
| 5 | Open Link Fixer, Links. | All four bulk actions are back. |
| 6 | Press **Run next job** until O2 is archived. | O2 gets one new snapshot and is archived. |
| 7 | Keep pressing **Run next job**. | O1 (its job was put back an hour, so it comes last) gets its existing archive URL. |

Note: the Link Fixer has a "The Wayback Machine is currently offline" notice (`iawmlf_render_wayback_offline_notice()` in `functions.php`), but nothing calls it in 1.5.0-RC1, so it never shows.
