# Site: Dashboard

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/dashboard/blueprint.json

Seven links, D1 to D7: D1 and D2 broken with an archive, D3 broken without one, D4 working with an archive, D5 working without one, D6 new and never checked, D7 broken with an archive but excluded by hand. Each last check is a day older than the one before (D1 yesterday). Onboarding finished 8 days ago, so the Link Fixer Dashboard shows its overview. Archive.org keys are set (the stand-in reports 12 of 100000 snapshots today). The site opens on Link Fixer (its Dashboard page).

| # | Do | Expect |
|---|---|---|
| 1 | Open Link Fixer (the site opens here). | "Link Statistics Overview": Total Links 7, Links Saved 2, Archived Successfully 4, Ineligible for redirect 3, Checks in progress 1, Total Broken Links 3. |
| 2 | Click each number. | Each opens the Links table with the **same links the number counted**. |
| 3 | Look at "Recent Link Checks". | All 7, newest check first: D1 at the top, D6 (never checked) at the bottom. |
| 4 | Open the "Latest Links" tab. | The 6 links that are not excluded, newest first (D6 at the top); D7 is left out. |
| 5 | Open the WordPress Dashboard. | The widget: Today's Snapshots 12/100000, Pending Snapshots 0, Link Processing and Auto Archiver on, Scan Existing Posts off, checked every 3 days and broken after 3 failures, "View Links (7)". |

For 2 the panel opens each number's link itself and counts the table's rows.

On 1.5.0-RC1 step 2 fails for Total Broken Links: the number counts broken links that are not excluded (3: `Dashboard_Statistics::compile_link_statistics()` passes `false` for excluded), but its link only sets `iawmlf_status=1`, so the table also lists the excluded D7 (4 rows).
