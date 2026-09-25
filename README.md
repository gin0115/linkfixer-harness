# Link Fixer Harness

Test harness for the [Internet Archive Wayback Machine Link Fixer](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer) plugin, built to run in WordPress Playground.

- **Fake Archive.org clients.** `iawmlf_snapshot_client`, `iawmlf_link_checker_client` and `iawmlf_system_client` are replaced, so nothing reaches archive.org and every response is scripted.
- **Call log.** Every fake call is stored in `{prefix}lfh_calls` with what triggered it (REST route, Action Scheduler action and attempt, admin page). REST responses carry the calls made during that request in the `X-LFH-Calls` header.
- **Seeded scenarios.** Posts and link rows written straight into the Link Fixer's table, with check histories dated relative to now.
- **Floating panel.** On a harness post, for an admin: predicts what the Link Fixer should do to every link on this load, watches what it actually does (REST checks, server calls, href swaps, `data-iawmlf-*` attributes), marks each link pass or fail, and runs a checklist.

## Blueprints

| Blueprint | Open |
|---|---|
| **Link Fixer Tests: every suite, run with one button** | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/blueprints/tests.json) |
| Broken links on the page: one post with a link in every state | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/blueprints/mixed-links.json) |
| Display modes and link icons: five pages, one per setting | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/blueprints/display-modes.json) |

## Test runner

The Tests blueprint lands on **Link Fixer Tests** in wp-admin. Press **Run all tests** (or **Run this suite**) and watch: the runner opens each page itself, scrolls, moves time forward, clicks through forms and switches settings, and a strip across the top shows the suite, the step and the running pass/fail count. When it finishes, the page shows every step with a green tick or red cross in plain English, with the technical detail folded away under "Details for developers". **Copy report** puts a Markdown version on the clipboard for a ticket.

Suites are data. Each is a class extending `Suite` whose `steps()` returns plain English steps, each with server `setup` actions, a page to `open`, `checks` (in the browser and on the server) and `then` actions (click, tick, fill). The format is documented at the top of `src/Suite.php`.

| Suite | Steps |
|---|---|
| Broken links on the page | 5 |
| Display modes and link icons | 5 |
| First run setup wizard | 17 |

## URL scripts

A link on `harness.test` carries its own Archive.org behaviour in its path:

```
https://harness.test/<slug>/check/404,200/archive/yes/save/offline,ok/status/pending,success/final/moved
```

| Key | Method | Values |
|---|---|---|
| `check` | `check_single()` | an HTTP code, or `offline` (throws `Service_Offline_Exception`) |
| `archive` | `get_latest_snapshot()` | `yes`, `none` |
| `save` | `create_snapshot()` | `ok`, `offline`, `limit` (throws `Exceeded_Snapshot_Limit_Exception`), `error` |
| `status` | `get_snapshot_status()` | `pending`, `success`, `error`, `no-access` |
| `final` | `get_final_url()` | `same`, `moved` |

The Nth call of a method for a URL gets the Nth value; the last value repeats. Reseeding clears the call log for the scenario URLs, so scripts start again. Any other host gets `check/200`, `archive/yes`, `save/ok`, `status/success`.

## Mixed links scenario

Defaults: checked every 3 days, broken after 3 failed checks, mode `replace_link`. History is `code (days ago)`.

| ID | Link | History | Script | Expected on the first load |
|---|---|---|---|---|
| L01 | Broken, stays broken | 404 (12), 404 (8), 404 (4) | 404 | checked, stays broken, swapped |
| L02 | Broken, now fixed | 404 (12), 404 (8), 404 (4) | 200 | checked, no longer broken, not swapped |
| L03 | Fine, becomes broken | 200 (12), 404 (8), 404 (4) | 404 | checked, third 404, broken and swapped on this load |
| L04 | Fine, breaks slowly | none | 404 | checked, 1 failure, not swapped. Broken on the third aged load |
| L05 | Healthy | 200 (4) | 200 | checked, not swapped |
| L06 | Broken, checked 30 minutes ago | 404 (10), 404 (6), 404 (0.02) | 200 | not checked, swapped from stored state. Recovers after ageing |
| L07 | Flaky | 404 (12), 200 (8), 404 (4) | 404, 200, 404, 200 | never broken |
| L08 | Checker service offline | 404 (8), 404 (4) | offline | REST 500, nothing recorded, not swapped |
| L09 | Blocks bots (403) | 403 (8), 403 (4) | 403 | third 403, broken and swapped |
| L10 | Rate limited (429) | 429 x3 | 429 | 429 is valid, never broken |
| L11 | Broken, no archive | 404 x3 | 404 | skipped by the front end, not swapped |
| L12 | Below the fold | 404 x3 | 404 | only checked once scrolled to, then swapped |
| X01 | Excluded: settings rule | 404 x3 | 404 | not in link data, never checked |
| X02 | Excluded: built-in list (LinkedIn) | 404 x3 | - | not in link data, never checked |
| X03 | Excluded: manually | 404 x3 | 404 | not in link data, never checked |
| X04 | Excluded: by the system (no-access) | 404 x3 | 404 | not in link data, never checked |
| X05 | Excluded in one post only (filter) | 404 x3 | 404 | excluded in the main post, checked and swapped in "same link in another post" |
| E01 | Link in the excluded post | 404 x3 | 404 | no script, no data, nothing swapped |

The panel's **Age 4 days + reload** moves every seeded check 4 days back, so the next load re-checks everything that is due.

## Display modes scenario

Five pages. Each forces `iawmlf_fixer_option` and `iawmlf_link_icon` for its own page views only (`pre_option_*`), so the saved settings never change and the pages can be open side by side. Each page has its own copy of five links: broken and stays broken, broken but recovers, broken and checked recently, healthy, and a hand-written `web.archive.org/web/...` link.

| Page | Mode | Icon | Expected |
|---|---|---|---|
| R | `replace_link` | none | broken links swapped, no icon anywhere |
| B | `replace_link` | before | swapped links and the hand-written archive link get the icon before |
| A | `replace_link` | after | as B, icon after |
| C | `check_only` | before | links checked, nothing swapped, only the hand-written archive link gets the icon |
| N | `do_nothing` | before | no script, no link data, no icon CSS: nothing checked, swapped or decorated |

The panel measures the icon from each link's computed `::before` / `::after` background image.

## REST routes

All under `lfh/v1`, `manage_options` only.

| Route | Does |
|---|---|
| `GET state` | settings, registry, recent calls, saved results |
| `GET calls?since=ID` | calls after an id |
| `POST seed` | reseed the scenario, returns `main_url` |
| `POST age` `{"days":4}` | move every seeded check back |
| `POST settings` `{"fixer_option":"check_only","archive_online":"no"}` | change the fixer mode or the fake online status |
| `GET/POST results` | per-load reports saved by the panel |

In the browser, `window.LFH.report()` returns the current load's report.
