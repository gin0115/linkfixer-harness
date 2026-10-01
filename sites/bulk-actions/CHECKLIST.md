# Site: Bulk actions at scale

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/bulk-actions/blueprint.json

Sixty working links, B01 to B60, all archived in 2020. The Archive.org stand-in takes a quarter of a second to answer about each link (`wait/0.25` in the link address) and accepts 10 new snapshots a minute (`lfh_snapshot_limit`). Each step runs a Links report bulk action on all 60 at once. All four bulk actions ask Archive.org about every link inside the page request, one after another, so the page takes as long as all the answers together. The site opens on Link Fixer, Links.

| # | Do | Expect |
|---|---|---|
| 1 | Screen Options, "Number of items per page" 100, Apply. | All 60 links on one page. |
| 2 | Select all, "Check link status", Apply. | Each link checked once, 60 "checked successfully" notices, back within a minute. |
| 3 | Select all, "Update to latest snapshot", Apply. | All 60 move from the 2020 to the 2024 snapshot, 60 "updated successfully" notices. |
| 4 | Select all, "Create new snapshot", Apply. | 10 snapshots start. Once Archive.org says the limit is reached, **no more are asked for** and the rest are **queued to try later**. |
| 5 | Wait a minute, select all, "Verify link allows checking", Apply. | 10 verification requests. **Only those 10 say "Validating"**; the 50 refused ones say they were refused. |

On 1.5.0-RC1 the bold parts fail:

- Step 4: `Report_Table::process_new_snapshot()` goes on asking Archive.org for every remaining link after the limit is reached (60 requests for 10 snapshots), and the refused links are not queued for later: they only get a "Could not create a new snapshot ... Exceeded snapshot limit." notice.
- Step 5: `Report_Table::process_excluded_links()` ignores the result of `Validate_Link_Action::validate_link()`, so every link says "Validating ... to ensure we can check its current status", including the 50 that Archive.org refused and that have nothing queued.
