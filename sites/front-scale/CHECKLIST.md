# Site: Front end link checks at scale

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/front-scale/blueprint.json

One post with 40 links, F01 to F40, all archived, all last checked 4 days ago (links are checked every 3 days, so all are due), and all in one paragraph at the top so they are on screen as the post opens. The Archive.org stand-in takes half a second to answer about each (`wait/0.5`). The site opens on the post, as a visitor would see it (the panels show because you are logged in).

| # | Do | Expect |
|---|---|---|
| 1 | Open the post (the site opens here) and wait. | One check per link (40), Archive.org asked about each once, all answered within a minute. |
| 2 | Look at how the checks were sent. | **No more than 6 waiting at the same time.** |
| 3 | Reload the post. | Nothing is checked again. |

On 1.5.0-RC1 step 2 fails: `front_link_checker.js` sends a check (`POST iawmlf/v1/link-check`) for each due link as soon as it scrolls into view, with no limit, so all 40 went out within a quarter of a second and waited together (the last answered 15.6 seconds after the first was sent). Each check holds a PHP worker on the server while Archive.org answers. This is a load concern for posts with many links and busy sites, not a wrong result: every link was checked once and correctly. The limit of 6 is this checklist's choice.
