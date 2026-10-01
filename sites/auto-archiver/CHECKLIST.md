# Site: Auto Archiver

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/auto-archiver/blueprint.json

The Auto Archiver asks Archive.org to snapshot your own posts: an hour after you publish or update one (`iawmlf_process_local_post`), and again once 28 days have passed (the routine rescan, `iawmlf_add_own_posts`). Four posts are seeded: "Harness: never archived", "Harness: archived 3 days ago", "Harness: archived 30 days ago" and "Harness: excluded from auto archiving" (on the Auto Archiver excluded posts list). Background jobs only run when you press **Run next job** in the panel. The site opens on Posts.

| # | Do | Expect |
|---|---|---|
| 1 | Open Posts (the site opens here). | The Links column shows. Last Archived is hidden until chosen in Screen Options. |
| 2 | Screen Options, tick "Last Archived". | "Never archived", a date for the 3 and 30 day posts, "Excluded post" for the excluded one. |
| 3 | Press **Run next job** until "Queue your posts for archiving" has run. | The never archived and 30 day posts are queued. The 3 day post and the excluded post are not. |
| 4 | Keep pressing until "Harness: never archived" is archived. | One snapshot of the post's own address, Last Archived is now. |
| 5 | Keep pressing until "Harness: archived 30 days ago" is archived. | One snapshot, Last Archived moves from 30 days ago to now. |
| 6 | Write and publish a new post, then open Posts. | A job to archive it is queued for about 1 hour from now. |
| 7 | Press **Run next job** until your post is archived (it comes last). | One snapshot of your post's address, Last Archived is now. |
| 8 | Open the excluded post, press Update, then open Posts. | No job is queued for it. |
| 9 | Press **Take offline**, update your post, press **Run next job** until its job has run. | Archive.org is not asked: the job fails ("Service is offline, trying again in 1 hour.") and a new one is queued for 1 hour. |
| 10 | Press **Bring back online**, then **Run next job** until your post's job runs again. | A second snapshot of your post is asked for. |

While offline, each **Run next job** runs your post's new job straight away (the panel ignores due times), so it fails again each time. That is expected.
