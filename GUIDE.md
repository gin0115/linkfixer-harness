# Link Fixer Harness: guide

A set of ready-made WordPress Playground sites for testing the [Internet Archive Wayback Machine Link Fixer](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer) plugin by clicking around, the way a site owner or editor would. Each site opens in the browser in a known state, with a checklist panel that says what to do, what should happen, and ticks each step off when it sees it happen. Anyone can run them: no local setup, no Archive.org account, nothing leaves the browser.

Written against Link Fixer **1.5.0-RC1**.

- Repository: https://github.com/gin0115/linkfixer-harness
- Short overview and site list: [README.md](README.md)
- Each site's steps: `sites/<site>/CHECKLIST.md`

---

## 1. How to use a site

1. Click a site's link below. Playground builds the site in your browser (about 30 seconds).
2. A panel sits in the bottom left: the site's **checklist**. Each item says what to do and what should happen. Do them in order.
3. Items tick themselves off (green) when the panel sees the expected result, or turn red with the reason. Nothing needs marking by hand.
4. **Copy report** in the panel gives a plain text report of every item, for pasting into an issue or a chat.
5. On sites with background jobs, the panel also lists the waiting jobs, with **Run next job** and **Run all jobs** buttons. Jobs only run when you press them, so each step can be watched.

If the Playground shows a grey page with a broken document icon, load the link again (a Playground hiccup, not the site).

Every site has a `CHECKLIST.md` with the same steps in a table, so the steps can also be followed without the panel, or by Puppeteer or Playwright.

---

## 2. The sites

Open link pattern: `https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/<site>/blueprint.json`

"Result on 1.5.0-RC1" is what the checklist gave when run on the release candidate. A failing item is a problem in the Link Fixer unless noted.

### Setting up

| Site | What it covers | Items | Result on 1.5.0-RC1 |
|---|---|---|---|
| [wizard-production](sites/wizard-production/CHECKLIST.md) | Fresh install on a production site: the three step setup wizard | 10 | 10 pass |
| [wizard-staging](sites/wizard-staging/CHECKLIST.md) | Fresh install on a staging site: the two step wizard | 5 | 5 pass |
| [settings](sites/settings/CHECKLIST.md) | Advanced Settings: Archive.org keys, settings that show and hide, saving values, link exclusion rules | 13 | 10 pass, 3 fail (Link Icon row twice, `%20` in a rule) |
| [settings-exclusions](sites/settings-exclusions/CHECKLIST.md) | Advanced Settings: finding posts to exclude (a title with `&`, a page, a draft), saving the list, the Plugins screen link | 6 | 4 pass, 2 fail (`&` titles, drafts) |
| [permissions](sites/permissions/CHECKLIST.md) | Logged in as an editor, with the reporting screens opened to editors (`iawmlf_reporting_page_capability`) | 12 | 8 pass, 4 fail (links to Advanced Settings) |

### Links on the site

| Site | What it covers | Items | Result on 1.5.0-RC1 |
|---|---|---|---|
| [links-on-page](sites/links-on-page/CHECKLIST.md) | One post with a link in every state, over three visits (fixed, broken, excluded every way, aged checks) | front end panel, 17 links | all pass |
| [display-modes](sites/display-modes/CHECKLIST.md) | Five posts, one per fixer mode and link icon | front end panel, 25 checks | all pass |
| [link-formats](sites/link-formats/CHECKLIST.md) | The same broken link written twelve ways (capitals, spaces, accents, ports, entities) | 1 | fails: 5 of 12 links not replaced |
| [where-links-appear](sites/where-links-appear/CHECKLIST.md) | The blog home page, a Button block, an image link, a synced pattern | 3 | 2 pass, 1 fail (home page with an excluded first post) |
| [front-scale](sites/front-scale/CHECKLIST.md) | A visitor opens a post with 40 links all due a check | 3 | 2 pass, 1 fail (40 checks at once; a load concern, see below) |

### The admin screens

| Site | What it covers | Items | Result on 1.5.0-RC1 |
|---|---|---|---|
| [links-report](sites/links-report/CHECKLIST.md) | Link Fixer, Links: twelve links in every state, filters, search, the four bulk actions, link details | 16 | 16 pass |
| [bulk-actions](sites/bulk-actions/CHECKLIST.md) | The four bulk actions on 60 links at once, a slow Archive.org, 10 snapshots a minute | 6 | 5 pass, 1 fail ("Validating" for refused links) |
| [dashboard](sites/dashboard/CHECKLIST.md) | The Link Fixer Dashboard after onboarding: its six numbers and the tables they open, Recent Link Checks, Latest Links, the widget | 5 | 4 pass, 1 fail (Total Broken Links) |
| [post-scan](sites/post-scan/CHECKLIST.md) | Content from before the plugin was installed, scanned in batches; onboarding ending | 6 | 4 pass, 2 fail ("Posts Checked") |

### Background work

| Site | What it covers | Items | Result on 1.5.0-RC1 |
|---|---|---|---|
| [archive-pipeline](sites/archive-pipeline/CHECKLIST.md) | Eight links through the archiving jobs and their retries, one job at a time | 13 | 13 pass |
| [archive-offline](sites/archive-offline/CHECKLIST.md) | Archive.org offline: what shows, jobs waiting an hour, carrying on once back | 7 | 7 pass |
| [auto-archiver](sites/auto-archiver/CHECKLIST.md) | Your own posts archived on publish and every 28 days, excluded posts, offline | 10 | 10 pass |
| [auto-archiver-staging](sites/auto-archiver-staging/CHECKLIST.md) | A staging copy with the Auto Archiver on stays quiet | 4 | 4 pass |
| [housekeeping](sites/housekeeping/CHECKLIST.md) | The daily clean-up of old failed retry jobs | 3 | 2 pass, 1 fail (does not requeue itself) |

---

## 3. What was found in 1.5.0-RC1

Opened on the Link Fixer repository:

| Issue | What | Shown by |
|---|---|---|
| [#383](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer/issues/383) | Editors are shown links to Advanced Settings they cannot open (widget button, Link Fixer Dashboard page, "Excluded post" in both posts list columns) | permissions |
| [#384](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer/issues/384) | Advanced Settings: Link Icon row shows in "Check only" mode; the excluded posts search mangles titles with `&`; link exclusion rules lose `%20` when saved; drafts cannot be excluded before publishing (plan in a comment) | settings, settings-exclusions |
| [#385](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer/issues/385) | Link Fixer Dashboard: "Total Broken Links" does not match the table it opens; onboarding never finishes on a site with drafts or excluded posts | dashboard, post-scan |
| [#386](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer/issues/386) | Small fixes: "Verify link allows checking" says "Validating" for refused links; the failed job clean-up does not requeue itself; two unused pieces of code to mark as deprecated | bulk-actions, housekeeping |
| [#387](https://github.com/a8cteam51/internet-archive-wayback-machine-link-fixer/issues/387) | Front end: five ways of writing a link are never replaced; nothing is replaced on a listing page whose first post is excluded; link checks should be limited to 2 at a time per page view | link-formats, where-links-appear, front-scale |

Also checked and found to be fine: the archiving jobs, retries and Archive.org's snapshot limit (jobs over the limit are put back for 24 hours), the Auto Archiver on production and staging, the scan of existing posts in batches, links inside blocks and synced patterns, and every hook the README documents is applied in the code.

---

## 4. How it works

### Three plugins per site

Every site's blueprint installs:

1. **The Link Fixer** release zip (currently `1.5.0-RC1` from the GitHub release).
2. **The harness core** (`zips/core.zip`, folder `linkfixer-harness`): the shared helper, described below.
3. **The site's own plugin** (`zips/<site>.zip`, folder `lfh-site-<site>`): its seed data (a **scenario**) and its checklist.

Then a `runPHP` step seeds the scenario. Some blueprints also set `WP_ENVIRONMENT_TYPE` to `staging`, or log in as a different user.

### The Archive.org stand-in

The core replaces the Link Fixer's three Archive.org clients through its own filters (`iawmlf_link_checker_client`, `iawmlf_snapshot_client`, `iawmlf_system_client`), so nothing is sent to Archive.org and every answer is predictable. What the stand-in answers about a link is written into the link's own address, on the host `harness.test`:

```
https://harness.test/<name>/check/404,200/archive/yes/save/ok/status/pending,success/final/moved/wait/0.5
```

| Key | Answers | Values |
|---|---|---|
| `check` | whether the link works | an HTTP code, or `offline` |
| `archive` | whether Archive.org has a copy | `yes`, `none` |
| `save` | a request for a new snapshot | `ok`, `offline`, `limit`, `error` |
| `status` | progress of a new snapshot | `pending`, `success`, `error`, `no-access` |
| `final` | where the link redirects | `same`, `moved` |
| `wait` | how long every answer takes, in seconds | e.g. `0.25` |

The Nth question about a link gets the Nth value, and the last value repeats, so `check/404,200` fails once and then works. Other hosts get working, archived answers.

Two settings change the stand-in as a whole:

- `lfh_archive_online`: `no` takes it offline (the panel's **Take offline** / **Bring back online** button sets this and clears the Link Fixer's cached online status).
- `lfh_snapshot_limit`: how many new snapshots it accepts a minute, like Archive.org's own limit (0 for no limit).

Every question asked is recorded in a call log (table `{prefix}lfh_calls`) with what triggered it, so checks can count calls ("Archive.org was asked once about this link").

### Scenarios

A scenario (a class extending `LinkFixer_Harness\Scenario`) describes a site's posts and links. Seeding it:

- creates the posts (posts, pages or drafts),
- writes link rows straight into the Link Fixer's table with a check history dated relative to now ("failed 3 days ago, then yesterday"), the broken flag, an archive, exclusions,
- or leaves "static" links for the Link Fixer's own scan to find, when the test is about the scan.

A scenario can also force options for its own posts' page views only, so one site can show several fixer modes at once (display-modes). The front end panel can age a scenario's checks by days, so "come back in 4 days" takes one click.

### The checklist panel

The bottom-left panel, on admin and front end pages for administrators (a site can open it to other roles; the permissions site opens it to editors). Each item has:

- **do** and **expect**, in plain words;
- **when**: checks that say "this is the page and the moment this item is about";
- **checks**: what must be true then.

An item is judged whenever every **when** check passes: on page load, after any change to a field, when content arrives later (search results), and when a link changes on the page. Once passed it stays passed. Results are saved, so ticks carry across pages.

Checks that run in the browser:

| Check | Means |
|---|---|
| `url`, `param` | the address, or one query parameter |
| `text` | an element's text has, lacks, or is exactly something |
| `notice` | an admin notice says something (or how many do) |
| `exists`, `missing`, `visible`, `checked`, `value`, `count` | the state of elements on the page |
| `page` | opens another address as the logged in user and checks its status and text, or how many rows the Links table there lists (for pages the panel cannot load on, such as "Sorry, you are not allowed") |
| `timing` | how long the page took to come back |
| `fetches` | the Link Fixer's front end link checks: how many, how many at once, how long |
| `script` | whether the Link Fixer's front end script is loaded |

Checks answered by the server:

| Check | Means |
|---|---|
| `option` | a WordPress option's value |
| `action` | background jobs: how many of a kind, in which state, for which link or post, due when |
| `link_row` | a field of a link in the Link Fixer's table (broken, excluded, archive, checks, message) |
| `calls` | how many times the stand-in was asked something about a link |
| `post_meta`, `meta_count` | a post's meta, or how many of a set of posts have it |

### The front end panel

On a scenario's posts, a second panel (bottom right) predicts what the Link Fixer should do to every link on this page load (check it or not, replace it or not, which icon) and watches what actually happens, marking each link pass or fail. Its buttons: **Age 4 days + reload**, **Reload**, **Reseed**, **Copy report**, **Save results**. The links-on-page and display-modes sites are judged this way.

### Background jobs

The Link Fixer does its Archive.org work in Action Scheduler jobs. On most sites the harness stops Action Scheduler running them on its own (`Queue::manual()`), and the checklist panel lists the waiting jobs with **Run next job** and **Run all jobs** (one job per request, up to 60). Running a job ignores its due time, so "try again in an hour" can be watched straight away.

### Playground's database

Playground runs WordPress on SQLite. Two Link Fixer queries need MySQL features SQLite lacks, so the core adds them on SQLite only: a `JSON_LENGTH` function, and brackets around `JSON_LENGTH(checks) - 1` (SQLite's `||` would otherwise bind before the `-`). Without them the Dashboard's link counts showed 0 and "Recent Link Checks" was empty. On MySQL the core does nothing here.

---

## 5. Adding a site

1. Copy a folder under `sites/` (pick one close to what you need).
2. In `plugin/`, write a scenario class extending `LinkFixer_Harness\Scenario` (`slug`, `title`, `summary`, `posts`, `links`), and register it on the `lfh_loaded` action through the `lfh_scenarios` filter.
3. Add the checklist on the `lfh_checklist` filter: title, intro, optional `queue` and `online_toggle`, and the items.
4. Point `blueprint.json` at `zips/<site>.zip` and seed with `\LinkFixer_Harness\Scenarios::get( '<slug>' )->seed();`.
5. Write `CHECKLIST.md`, add a row to the README, run `./build.sh`, and commit the zips with the change.

To test a change before the `raw.githubusercontent.com` cache (a few minutes) catches up, open the blueprint with the zip addresses pinned to the commit instead of `main`.

---

## 6. Limits and things that can break

- **Exact wording and markup.** Many checks match the Link Fixer's English text ("Link updated successfully.") or its HTML (`#iawmlf_dashboard_widget`, `th.column-wayback_archived`). Copy or markup changes fail checks without a real bug.
- **Seeding writes the Link Fixer's own storage** (its link table and option names). If those change, seeding breaks.
- **Order matters on some sites**: steps depend on earlier ones (Auto Archiver, Archive.org offline, the bulk actions limit is per minute).
- **One request at a time.** Playground's PHP may not run two requests in parallel, so problems that need two visitors at the same moment (two checks of the same link racing) cannot be shown here.
- **The release is fixed in each blueprint.** Testing another Link Fixer version means changing the release zip address in every `blueprint.json`.
- **Not covered**: the developer filters one by one, upgrading from 1.4.3 (no table changes between the two), uninstalling, and speed with a very large links table (the `url` and `redirect_url` columns have no index; not measured).
