# Site: Failed job clean-up

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/housekeeping/blueprint.json

Failed retry jobs for two links. H1's are from 10 days ago, older than the clean-up's 7 day limit (`iawmlf_failed_event_gc_days_threshold`): 3 snapshot checks, 2 new snapshots, 2 archive URL updates and 2 verification checks. H2's are from 2 days ago: 2 snapshot checks and 1 new snapshot. The daily clean-up (`iawmlf_failed_event_garbage_collection`, "Clean up old failed jobs" in the panel) is due now. Jobs only run when you press **Run next job**. The site opens on Link Fixer, Links.

| # | Do | Expect |
|---|---|---|
| 1 | Open Link Fixer, Links (the site opens here). | 12 failed retry jobs before the clean-up. |
| 2 | Press **Run next job** once. | Only the last attempt of each of H1's old jobs is kept, and H2's are left alone: 6 remain. |
| 3 | Look at the waiting jobs, without reloading. | The clean-up has queued itself again for midnight. |

Reloading the page after step 2 queues the clean-up again (`Event_Controller` schedules it on every admin page load), and step 3 then passes; judge it before reloading.

On 1.5.0-RC1 step 3 fails: `Failed_Event_Garbage_Collection_Event::__invoke()` calls `add_to_action_scheduler()` in its `finally` block, which only schedules when `as_next_scheduled_action()` finds nothing. Action Scheduler marks the job that is running as in progress (`ActionScheduler_DBStore::log_execution()`), and `as_next_scheduled_action()` counts in-progress jobs, so the job finds itself and nothing is queued. The next admin page load or cron run queues it instead, so on a working site the effect is small.
