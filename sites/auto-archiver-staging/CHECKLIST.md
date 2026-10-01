# Site: Auto Archiver on staging

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/auto-archiver-staging/blueprint.json

A staging site (`WP_ENVIRONMENT_TYPE` is `staging`) copied from a production site with the Auto Archiver and its routine rescan turned on. "Harness: archived 30 days ago (staging)" would be archived again on production. The Link Fixer keeps the Auto Archiver off on any site that is not production (`Settings::add_own_links()`, `Scan_Own_Posts_Event::add_to_action_scheduler()`), so staging addresses are never archived. Jobs only run when you press **Run next job**. The site opens on Advanced Settings.

| # | Do | Expect |
|---|---|---|
| 1 | Open Advanced Settings (the site opens here). | The Auto Archiver section says a non-production environment was detected and auto archiving is disabled. |
| 2 | Open the WordPress Dashboard. | The widget shows the Auto Archiver as off, although its setting is on. |
| 3 | Stay on the Dashboard. | No routine rescan ("Queue your posts for archiving") is scheduled. |
| 4 | Write and publish a new post, then open Posts. | No job to archive it is queued. |
