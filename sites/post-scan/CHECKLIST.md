# Site: Scanning existing posts

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/post-scan/blueprint.json

Content written before the Link Fixer was installed, so none of it has been scanned: five posts, a page, a post on the Link Fixer excluded posts list, and a draft, each with one link. "Scan Existing Posts" is on. The scan (`iawmlf_scan_existing_posts`) takes 2 posts a batch here (`iawmlf_posts_per_batch`), and the next scan is only queued when an admin page loads, 10 minutes later. Jobs only run when you press **Run next job**. Anything else on the site (Hello world, Sample Page) counts as already scanned. The site opens on Link Fixer (its Dashboard page).

| # | Do | Expect |
|---|---|---|
| 1 | Open Link Fixer (the site opens here). | Onboarding in progress, "Posts Checked" counts none of the 7 Harness posts yet, and a scan is waiting. |
| 2 | Press **Run next job** once. | One batch: 2 of the 6 posts that can be scanned. |
| 3 | Reload the page. | The next scan is queued for about 10 minutes from now. |
| 4 | Keep pressing **Run next job**, reloading after each scan, until all 6 are scanned. | The 5 posts and the page are scanned, their links are in the Links table; the draft and the excluded post are not. |
| 5 | Open Link Fixer. | Onboarding **ends** and the "Link Statistics Overview" shows. |
| 6 | Publish the draft. | Its link is found as it is published. |

On 1.5.0-RC1 step 5 fails: `Dashboard_Statistics::get_post_count()` counts every post without link data as unscanned, including posts on the Link Fixer excluded posts list, which the scan never touches (`Scan_Posts_Event` skips them and `WP_Post_Controller::process_links_in_content()` returns early). So "Posts Checked" stops one short (8 / 9 here) and onboarding only ends when its 7 days run out.
