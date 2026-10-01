# Link Fixer Harness

WordPress Playground test sites for the [Internet Archive Wayback Machine Link Fixer](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer) plugin. Each site is its own Playground, opened in a known state, with a floating panel that shows what should happen and watches what does. Every site has a `CHECKLIST.md` a person or an AI can follow, and the same steps can be driven by Puppeteer or Playwright.

## Sites

| Site | Open | Checklist |
|---|---|---|
| Setup wizard (production): fresh install, three step wizard | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/wizard-production/blueprint.json) | [CHECKLIST.md](sites/wizard-production/CHECKLIST.md) |
| Setup wizard (staging): fresh install on a staging site, two step wizard | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/wizard-staging/blueprint.json) | [CHECKLIST.md](sites/wizard-staging/CHECKLIST.md) |
| Links on the page: one post with a link in every state, over three visits | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/links-on-page/blueprint.json) | [CHECKLIST.md](sites/links-on-page/CHECKLIST.md) |
| Links report: twelve links in every state, filters, search, bulk actions, link details | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/links-report/blueprint.json) | [CHECKLIST.md](sites/links-report/CHECKLIST.md) |
| Advanced settings: Archive.org keys, settings that show and hide, saving values | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/settings/blueprint.json) | [CHECKLIST.md](sites/settings/CHECKLIST.md) |
| Background archiving and retries: eight links through the Action Scheduler jobs, one job at a time | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/archive-pipeline/blueprint.json) | [CHECKLIST.md](sites/archive-pipeline/CHECKLIST.md) |
| Archive.org offline: what shows while offline, jobs waiting an hour, carrying on once back | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/archive-offline/blueprint.json) | [CHECKLIST.md](sites/archive-offline/CHECKLIST.md) |
| Display modes and link icons: five posts, one per setting | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/display-modes/blueprint.json) | [CHECKLIST.md](sites/display-modes/CHECKLIST.md) |
| Auto Archiver: your own posts archived on publish and every 28 days, excluded posts, offline | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/auto-archiver/blueprint.json) | [CHECKLIST.md](sites/auto-archiver/CHECKLIST.md) |
| Editor permissions: logged in as an editor, with the reporting screens opened to editors | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/permissions/blueprint.json) | [CHECKLIST.md](sites/permissions/CHECKLIST.md) |

Each blueprint installs three plugins: the Link Fixer release zip, `zips/core.zip` (the shared helper) and the site's own zip.

## Playground notes

- Playground runs WordPress on SQLite. `Link_Repository` queries with the default order (`ORDER_DATE_DESC`) use `JSON_LENGTH`, which that database does not have, so they failed and counted 0. Core adds `JSON_LENGTH` to the SQLite connection (`LinkFixer_Harness\Sqlite`), so the Link Fixer Dashboard's total ("Links Found So Far", "Total Links") and the widget's "View Links (N)" count the links as they would on MySQL.
- Still failing on Playground: `CONCAT("$[", JSON_LENGTH(checks) - 1, "].date")` in the date orders. The SQLite translation turns it into `'$[' || JSON_LENGTH(checks) - 1 || '].date'`, and SQLite's `||` binds tighter than `-`, so the JSON path is `-1` and the query fails ("bad JSON path"). So the Link Fixer Dashboard's list of the last 10 checks is empty. Sorting the Links table by its last check column uses the same expression (not run here). MySQL is not affected.

## Layout

```
core/                    the shared helper plugin, zipped as zips/core.zip (folder linkfixer-harness/)
sites/<site>/
  plugin/                that site's own plugin: its seed data and scenario, zipped as zips/<site>.zip
  blueprint.json         the Playground blueprint for the site
  CHECKLIST.md           the steps and expected results
zips/                    built by build.sh, installed by the blueprints
build.sh                 rebuilds every zip
```

Run `./build.sh` after changing anything in `core/` or `sites/*/plugin/`, and commit the zips with the change.

To add a site: copy a folder under `sites/`, give its plugin a scenario class extending `LinkFixer_Harness\Scenario`, register it on `lfh_loaded` through the `lfh_scenarios` filter, point the blueprint at `zips/<site>.zip`, and run `./build.sh`.

## The shared helper (core)

- **Fake Archive.org clients.** `iawmlf_snapshot_client`, `iawmlf_link_checker_client` and `iawmlf_system_client` are replaced, so nothing reaches archive.org and every response is scripted by the link URL (see below).
- **Call log.** Every fake call is stored in `{prefix}lfh_calls` with what triggered it (REST route, Action Scheduler action, admin page). REST responses carry the calls made during that request in the `X-LFH-Calls` header.
- **Scenarios.** `LinkFixer_Harness\Scenario` seeds posts and link rows straight into the Link Fixer's table, with check histories dated relative to now, and can reset and age them. A post can force options for its own page views (`pre_option_*`).
- **Checklist panel.** On admin and front end pages, for an administrator (a site can open it to other roles with the `lfh_checklist_capability` filter): the site's steps, ticked off as the panel sees them happen. A `page` check opens an address as the logged in user, for pages the panel cannot load on.
- **Floating panel.** On a scenario post, for an administrator: predicts what the Link Fixer should do to every link on this load, watches what it actually does (REST checks, server calls, href swaps, `data-iawmlf-*` attributes, the link icon), marks each link pass or fail, and runs a checklist. Buttons: Age 4 days + reload, Reload, Reseed, Copy report, Save results. `window.LFH.report()` returns the same as JSON.

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

## REST routes

All under `lfh/v1`, administrators only.

| Route | Does |
|---|---|
| `GET state` | settings, every scenario's registry, recent calls, saved results |
| `GET calls?since=ID` | calls after an id |
| `POST seed` `{"scenario":"mixed-links"}` | reseed a scenario, returns `main_url` |
| `POST age` `{"days":4,"scenario":"mixed-links"}` | move a scenario's checks back (all scenarios when none is given) |
| `POST settings` `{"fixer_option":"check_only","archive_online":"no"}` | change the fixer mode or the fake online status |
| `GET/POST results` | per-load reports saved by the panel |
