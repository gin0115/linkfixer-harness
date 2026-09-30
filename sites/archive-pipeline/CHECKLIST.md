# Site: background archiving and retries

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/archive-pipeline/blueprint.json

One post, "Harness: archive pipeline", with eight links. Publishing it ran the Link Fixer's real scan, which found the links and queued a job for each. Action Scheduler is stopped from running jobs by itself: the panel's **Background jobs** list shows every waiting job (which link, which attempt, when it is due), and **Run next job** runs the one due soonest, as if time had passed until then. So each retry can be watched. The site opens on Link Fixer, Links; reload it to see the links change.

| Link | Scripted to |
|---|---|
| P1 | Already have a Wayback Machine copy |
| P2 | Need a new snapshot; the first status check says pending, the second success |
| P3 | Fail the first save (Archive.org busy), succeed on the retry |
| P4 | Hit the daily snapshot limit on every try |
| P5 | Be refused by Archive.org (no-access) |
| P6 | Never finish (status pending forever) |
| P7 | Have a copy, but return 403 when checked; Archive.org is then refused |
| P8 | Redirect somewhere else |

Press **Run next job** over and over. Each row ticks itself off when the panel sees it happen.

| # | Expect |
|---|---|
| 1 | Before anything runs: 8 "Find or create a snapshot" jobs waiting, and Archive.org not yet asked anything. |
| 2 | P1 gets its existing archive URL straight away, is checked once (200), and no new snapshot is asked for. |
| 3 | P2 asks for a new snapshot, and "Check the new snapshot" waits **10 minutes**. |
| 4 | P3's save fails, and the retry waits **15 minutes**. |
| 5 | P4 hits the daily limit, and the retry waits **24 hours**. |
| 6 | P5 is refused: excluded, finished, no archive. |
| 7 | P7 is checked (403), Archive.org is asked to try it once, is refused, and P7 is excluded (keeping the archive URL it had). |
| 8 | P2: the snapshot is checked exactly twice (pending, then success) and P2 is archived. |
| 9 | P8 records the address it redirects to and ends up archived. |
| 10 | P3 is saved on the second try and archived. |
| 11 | P6 is checked 3 times, then the Link Fixer gives up: finished, no archive. |
| 12 | P4 is tried 4 times (the first and 3 retries), then the Link Fixer gives up: finished, no archive. |
| 13 | Every link is finished and no archiving job is left waiting. |

**Run all jobs** runs everything at once; the rows that are about waiting (3, 4 and 5) can only tick while that job is still waiting, so use **Run next job** to see them.
