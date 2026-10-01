# Site: Link formats

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/link-formats/blueprint.json

One post with twelve broken links, each written a different way, exactly as an author might type them. The Link Fixer's own scan stores them (`WP_Post_Controller::process_links_in_content()`), then each stored link is marked broken, archived, and checked yesterday, so none is due a check and the browser only has to match each link to its data and replace it. Fixer mode is "Replace link". The site opens on the post.

| Link | Written as | Stored by the scan as |
|---|---|---|
| V1 | `https://harness.test/v1-plain/check/404` | the same |
| V2 | `https://HARNESS.TEST/v2-upper-host/check/404` | the same (capitals kept) |
| V3 | `.../v3-slash/check/404/` | without the trailing slash |
| V4 | `.../v4 space/check/404` | `v4%20space` |
| V5 | `.../v5-café/check/404` | `v5-caf%C3%A9` |
| V6 | `https://harness.test:443/v6-port/check/404` | the same (`:443` kept) |
| V7 | `...?a=1&amp;b=2` in the HTML | `?a=1&b=2` |
| V8 | `...#part` | the same |
| V9 | `HTTPS://harness.test/v9-scheme/check/404` | **not stored** |
| V10 | `https://harness.test/x/../v10-dots/check/404` | the same (`/x/../` kept) |
| V11 | `" https://harness.test/v11-padded/check/404 "` | **not stored** |
| V12 | `https://bücher.example/v12-idn` | `https://xn--bcher-kva.example/v12-idn` |

| # | Do | Expect |
|---|---|---|
| 1 | Open the post (the site opens here). | All twelve links are replaced with their archives. |

On 1.5.0-RC1 V2, V6, V9, V10 and V11 are not replaced:

- V2, V6, V10: `front_link_checker.js` finds a link's data with `removeTrailingSlash(archivedLink.href) === removeTrailingSlash(link)`, comparing the stored address with the browser's `href`. The browser lowercases the host, drops the default port and resolves `..`, the stored address keeps them, so they never match.
- V9, V11: the scan does not store a link whose address has capitals in the scheme or spaces around it (both open normally in a browser), so these are never checked, archived or replaced.
