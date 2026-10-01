# Site: Bulk actions at scale

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/bulk-actions/blueprint.json

Sixty working links, B01 to B60, all archived in 2020. The Archive.org stand-in takes a quarter of a second to answer about each link (`wait/0.25` in the link address) and accepts 10 new snapshots a minute (`lfh_snapshot_limit`). Each step runs a Links report bulk action on all 60 at once. Background jobs only run when you press the panel's buttons. The site opens on Link Fixer, Links.

"Check link status", "Update to latest snapshot" and "Verify link allows checking" ask Archive.org about every link inside the page request, one after another, so the page takes as long as all the answers together (about 15 seconds here). "Create new snapshot" does that for 5 links or fewer; for more it queues one "Create a new snapshot" job per link.

| # | Do | Expect |
|---|---|---|
| 1 | Screen Options, "Number of items per page" 100, Apply. | All 60 links on one page. |
| 2 | Select all, "Check link status", Apply. | Each link checked once, 60 "checked successfully" notices, back within a minute. |
| 3 | Select all, "Update to latest snapshot", Apply. | All 60 move from the 2020 to the 2024 snapshot, 60 "updated successfully" notices. |
| 4 | Select all, "Create new snapshot", Apply. | One notice lists all 60 as added to the queue; 60 "Create a new snapshot" jobs wait; Archive.org is not asked anything yet. |
| 5 | Press **Run all jobs**. | Each job asks Archive.org once: 10 get a snapshot and a snapshot check, the 50 refused are put back for 24 hours. |
| 6 | Wait a minute, select all, "Verify link allows checking", Apply. | 10 verification requests. **Only those 10 say "Validating"**; the 50 refused ones say they were refused. |

On 1.5.0-RC1 step 6 fails: `Report_Table::process_excluded_links()` ignores the result of `Validate_Link_Action::validate_link()`, so every link says "Validating ... to ensure we can check its current status", including the 50 that Archive.org refused and that have nothing queued.
