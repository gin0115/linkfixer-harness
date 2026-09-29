# Site: advanced settings

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/settings/blueprint.json

An onboarded site with default settings and no Archive.org keys. The site opens on Link Fixer, Advanced Settings. Archive.org is the harness stand-in: the keys `valid-access` / `valid-secret` are accepted, anything else is refused. The checklist panel sits in the bottom left; it checks each page as it loads and again whenever you change a field.

| # | Do | Expect |
|---|---|---|
| 1 | Open Advanced Settings (the site opens here). | A notice says you are in unauthenticated mode. Both key fields are empty. |
| 2 | Type `wrong` into both key fields, **Save Changes**. | "Settings saved." Keys shown as `****************`, the fields say "The Archive.org API keys are invalid", a notice says your credentials are invalid. |
| 3 | Type `valid-access` and `valid-secret`, **Save Changes**. | Keys masked; no "invalid" or "unauthenticated mode" message. |
| 4 | Open the WordPress **Dashboard**. | The Wayback Link Fixer widget says Archive.org is online and shows Today's Snapshots **12/100000**. |
| 5 | Back in Advanced Settings, untick **Enable Link Fixer** (do not save). | The Link Fixer settings below it hide straight away. |
| 6 | Tick it again and change the fixer mode to **Check only**. | The Link Fixer settings show again, but the **Link Icon** row hides (icons only show on replaced links). |
| 7 | Change the fixer mode back to **Replace link**. | The Link Icon row shows again. |
| 8 | Set the check frequency to **7** days and failures before broken to **5**, **Save Changes**. | "Settings saved." The fields show 7 and 5. |
| 9 | Type `*example.org/private*` in the link exclusion box, **Add**, **Save Changes**. | The rule is listed under link exclusions. |
| 10 | Untick **Enable Link Fixer**, **Save Changes**. | It stays unticked and the Link Fixer settings stay hidden after the reload. |
