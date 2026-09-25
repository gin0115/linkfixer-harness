# Site: links on the page

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/links-on-page/blueprint.json

The site opens on the post **"Harness: every link state"**. The harness panel sits in the bottom right. For every link it shows what should happen on this page load and what actually happened. A link only gets checked once it scrolls into view, so always scroll slowly to the bottom of the post.

Archive.org is replaced by a scripted stand-in: nothing leaves the site.

## 1. First visit

1. Scroll slowly to the bottom of the post.
2. In the panel checklist, these are all ticked:
   - Harness fake Archive.org clients are active
   - Link Fixer front-end script is loaded
   - Every link has been on screen (17 of 17)
   - Every link did what was predicted on this load (17 passed)
   - No REST check for any excluded link
3. Each link, on this first visit:

| Link | Expected |
|---|---|
| L01 Broken, stays broken | Checked, fails again, swapped to the archive (red "archived" tag) |
| L02 Broken, now fixed | Checked, works, **not** swapped |
| L03 Fine, becomes broken | Checked, third failure in a row, swapped on this visit |
| L04 Fine, breaks slowly | Checked, first failure, not swapped |
| L05 Healthy | Checked, works, not swapped |
| L06 Broken, checked 30 minutes ago | **Not** checked, swapped from its stored broken state |
| L07 Flaky | Checked, not swapped |
| L08 Checker service offline | Check fails with an error, nothing recorded, not swapped |
| L09 Blocks bots (403) | Checked, third 403, swapped |
| L10 Rate limited (429) | Checked, 429 counts as working, not swapped |
| L11 Broken, no archive | Not checked, not swapped (nothing to swap to) |
| L12 Below the fold | Only checked once you scroll to it, then swapped |
| X01 Excluded by a settings rule | Never checked, never swapped |
| X02 Excluded by the built-in list (LinkedIn) | Never checked, never swapped |
| X03 Excluded by hand | Never checked, never swapped |
| X04 Excluded by the system (no-access) | Never checked, never swapped |
| X05 Excluded in this post only | Never checked, never swapped here |

## 2. Four days later

1. In the panel press **Age 4 days + reload**, then scroll slowly to the bottom.
2. Panel: every link did what was predicted (17 passed).
3. L04 has failed twice and is still **not** swapped.
4. L06 is checked, works again and is **no longer** swapped.

## 3. Eight days later

1. Press **Age 4 days + reload** again and scroll to the bottom.
2. Panel: every link did what was predicted (17 passed).
3. L04 has now failed three times in a row and **is** swapped.
4. L07 keeps flipping between working and failing and is still **not** swapped.

## 4. The same link in another post

1. In the panel, press **Harness: same link in another post** and scroll to the bottom.
2. X05, excluded in the first post, **is** checked and swapped here.

## 5. An excluded post

1. In the panel, press **Harness: excluded post** and scroll to the bottom.
2. Panel: "Link Fixer front-end script is NOT loaded" is ticked.
3. Nothing on the page is checked or swapped, including L01 which is broken in the first post.

## Starting again

Press **Reseed** in the panel to put every link back to its starting state.
