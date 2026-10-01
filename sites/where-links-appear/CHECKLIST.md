# Site: Where links appear

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/where-links-appear/blueprint.json

Broken, archived links in different places on the site (Twenty Twenty-Five, whose home page shows each post in full):

- "Harness: newest post (excluded)": the newest post, so first on the home page, and on the Link Fixer excluded posts list. Its link W5 should be left alone.
- "Harness: older post": the broken link W1.
- "Harness: links in blocks": W2 in a Button block, W3 an image link, W4 inside a synced pattern. The Link Fixer's own scan finds these.

Fixer mode is "Replace link". The site opens on the home page.

| # | Do | Expect |
|---|---|---|
| 1 | Open "Harness: links in blocks". | W2, W3 and W4 are replaced with their archives. |
| 2 | Open "Harness: older post". | W1 is replaced. |
| 3 | Open the home page (the site opens here). | **W1 is replaced in the older post there too**; W5 in the excluded post is left alone. |

On 1.5.0-RC1 step 3 fails: `WP_Post_Controller::enqueue_frontend_script()` decides whether to load the Link Fixer's front end script from `get_the_ID()`, which on the home page is the first post listed, and returns without loading it when that post is on the excluded posts list. Each other post's link data is still on the page (`append_link_data()` adds it per post), but nothing reads it, so no link on the page is replaced. With a first post that is not excluded the script loads on the home page (seen on the Link formats site). Category, tag and search pages list posts the same way, so they are likely affected too (not run here).
