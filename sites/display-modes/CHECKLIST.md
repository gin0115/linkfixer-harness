# Site: display modes and link icons

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/display-modes/blueprint.json

Five posts, one per setting. Each post forces its own fixer mode and link icon for its own page views, so the saved settings never change and the posts can be opened in any order. Each post has its own copy of the same five links. The harness panel sits in the bottom right; scroll slowly to the bottom of every post so each link gets checked.

| # | Link | Starts as |
|---|---|---|
| 1 | Broken, stays broken | broken, check due |
| 2 | Broken, now fixed | broken, check due, the check works |
| 3 | Broken, checked recently | broken, no check due |
| 4 | Healthy | working, check due |
| 5 | Hand-written archive link | a `web.archive.org/web/...` link typed into the post, never touched by the Link Fixer |

## 1. Replace, no icon (the page the site opens on)

1. Scroll to the bottom. Panel: 5 passed.
2. Links 1 and 3 are swapped to the archive. Links 2 and 4 are not.
3. No Internet Archive icon on any link.

## 2. Replace, icon before

1. Panel: press **Harness: mode replace, icon before**. Scroll to the bottom. Panel: 5 passed.
2. Links 1 and 3 are swapped and show the Internet Archive icon **before** the link text.
3. Link 5, the hand-written archive link, also shows the icon before it.
4. Links 2 and 4 have no icon.

## 3. Replace, icon after

1. Panel: press **Harness: mode replace, icon after**. Scroll to the bottom. Panel: 5 passed.
2. As page 2, but the icon is **after** the link text.

## 4. Check only

1. Panel: press **Harness: mode check only**. Scroll to the bottom. Panel: 5 passed.
2. Links 1, 2 and 4 are checked, but **nothing is swapped**.
3. Only link 5, the hand-written archive link, shows the icon.

## 5. Do nothing

1. Panel: press **Harness: mode do nothing**. Scroll to the bottom. Panel: 5 passed.
2. Panel: "Link Fixer front-end script is NOT loaded" is ticked.
3. Nothing is checked, nothing is swapped, and **no link has an icon**, not even link 5, although an icon is set for this page.

The panel measures the icon itself (the link's `::before` or `::after` background image), so a pass in the panel means the icon really is there, or really is not.
