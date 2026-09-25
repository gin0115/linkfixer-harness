# Site: setup wizard (staging)

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/wizard-staging/blueprint.json

A fresh install of the Link Fixer on a staging site (`WP_ENVIRONMENT_TYPE` is `staging`). The wizard has only two steps, because your own posts are never archived from staging. The checklist panel sits in the bottom left and ticks each step off as it sees it happen.

| # | Do | Expect |
|---|---|---|
| 1 | Open the WordPress dashboard (the site opens here). | You are sent straight to the setup wizard, **step 1 of 2**. |
| 2 | Press **Configure the Link Fixer**. | **Step 2 of 2**, "Choose what to fix", Posts and Pages ticked, and the button says **Finish Setup**. |
| 3 | Leave Posts and Pages ticked and press **Finish Setup**. | "You're all set!". The Link Fixer is on for posts and pages and a scan of existing posts started. Archiving of your own posts was never switched on. |
| 4 | Open the **Dashboard**. | It opens normally: no wizard, no "almost ready" notice. |
| 5 | Open **Link Fixer, Advanced Settings**. | The Auto Archiver section says "Non-production environment detected - auto archiving is disabled". |

Compare with the production site, where the wizard has a third step, "Preserve your content".
