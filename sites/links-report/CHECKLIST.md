# Site: links report

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/links-report/blueprint.json

Twelve seeded links, A1 to A12, one for each state the Links report (Link Fixer, Links) can show. The site opens on the report. The checklist panel sits in the bottom left and ticks each step off as it sees it happen, on screen and in the saved link data.

| Link | Seeded as |
|---|---|
| A1 | Broken, with an archive |
| A2 | Working |
| A3 | Broken, no archive |
| A4 | New, not processed yet |
| A5 | Pending |
| A6 | Excluded by hand |
| A7 | Excluded by the settings rule `*harness.test/a7-rule*` |
| A8 | Excluded by the built-in list (a LinkedIn link) |
| A9 | Working; its next check returns 404 |
| A10 | Archived to an old 2020 snapshot; a 2024 one exists |
| A11 | For creating a new snapshot |
| A12 | For "Verify link allows checking"; Archive.org answers no-access |

| # | Do | Expect |
|---|---|---|
| 1 | Open Link Fixer, Links (the site opens here). | A table of the twelve links. |
| 2 | Choose **Show broken links** and press **Filter**. | Only A1, A3, A6, A7 and A8. |
| 3 | Set the filter back to All and search for `a3`. | One row: A3. |
| 4 | Search for `nothing-matches`. | "No links to display." |
| 5 | Clear the search. Tick A9, choose **Check link status**, press **Apply**. | A notice: A9 checked successfully with 404 status. |
| 6 | Tick A10, choose **Update to latest snapshot**, press **Apply**. | A notice: the archived URL for A10 was updated (now the 2024 snapshot). |
| 7 | Tick A11, choose **Create new snapshot**, press **Apply**. | A notice: A11 was added to the queue and a new snapshot will be created in the coming minutes. |
| 8 | Tick A12, choose **Verify link allows checking**, press **Apply**. | A notice: A12 is being validated. |
| 9 | Click A1's address to open its details. | "HAS ARCHIVE", its 404 checks, and "Found In" lists "Harness: report links". |
| 10 | Back to all links, open A3. | "NO ARCHIVE". |
| 11 | Open A4. | "NEW". |
| 12 | Open A5. | "PENDING". |
| 13 | Open A6. | Link Exclusion: currently excluded; "Exclude this link" is ticked and can be changed. |
| 14 | Open A7. | Link Exclusion: excluded by your exclusion settings list; the checkbox cannot be changed. |
| 15 | Open A8. | Link Exclusion: excluded by the built-in exclusion list; the checkbox cannot be changed. |
| 16 | Open A2, tick **Exclude this link** and confirm. | "Link updated successfully." and it now says currently excluded. |
