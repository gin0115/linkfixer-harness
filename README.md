# Link Fixer Harness

WordPress Playground test sites for the [Internet Archive Wayback Machine Link Fixer](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer) plugin. Each site is its own Playground, opened in a known state, with a floating panel that shows what should happen and watches what does. Every site has a `CHECKLIST.md` a person or an AI can follow, and the same steps can be driven by Puppeteer or Playwright.

## Sites

| Site | Open | Checklist |
|---|---|---|
| Links on the page: one post with a link in every state, over three visits | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/links-on-page/blueprint.json) | [CHECKLIST.md](sites/links-on-page/CHECKLIST.md) |
| Display modes and link icons: five posts, one per setting | [Open in Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/display-modes/blueprint.json) | [CHECKLIST.md](sites/display-modes/CHECKLIST.md) |

Each blueprint installs three plugins: the Link Fixer release zip, `zips/core.zip` (the shared helper) and the site's own zip.

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
