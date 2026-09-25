# Site: setup wizard (production)

**Open:** https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/gin0115/linkfixer-harness/main/sites/wizard-production/blueprint.json

A fresh install of the Link Fixer on a live (production) site. The checklist panel sits in the bottom left and ticks each step off as it sees it happen, on screen and in the saved settings. Each step also has an **Open** link where it needs a particular page.

| # | Do | Expect |
|---|---|---|
| 1 | Open the WordPress dashboard (the site opens here). | You are sent straight to the setup wizard, **step 1 of 3**, with a "Configure the Link Fixer" button and no back button. |
| 2 | Press **Configure the Link Fixer**. | **Step 2 of 3**, "Choose what to fix", with Posts and Pages already ticked and a back button to "About". |
| 3 | Leave Posts and Pages ticked and press **Configure the Auto Archiver**. | **Step 3 of 3**, "Preserve your content". The Link Fixer is now on for posts and pages, and a scan of your existing posts started straight away. |
| 4 | Before finishing, open the **Dashboard** again. | You are **not** sent to the wizard. A notice says the plugin is almost ready, with a link to the setup wizard. |
| 5 | Follow the notice's **run the setup wizard** link. | The wizard carries on at **step 3 of 3**. |
| 6 | Leave Posts and Pages ticked and press **Finish Setup**. | "You're all set!" with a "Go to the dashboard" button. Archiving of your own posts and pages is on, and they will be archived again regularly. |
| 7 | Open the **Dashboard**. | It opens normally: no wizard, no "almost ready" notice. |
| 8 | Open the wizard with `&rerun-wizard=1` on the address (the panel's Open link). | "You're all set!" now has a back button, "← Configure the Auto Archiver". |
| 9 | Press **← Configure the Auto Archiver**. | Back on step 3 of 3 with your choices still ticked. "Finish Setup" returns to "You're all set!". |
| 10 | Open **Link Fixer, Advanced Settings**. | The Auto Archiver section describes archiving your content. No "Non-production environment detected" message. |

The panel's **Copy report** puts the results on the clipboard as Markdown. **Start again** clears the ticks (it does not reset the wizard; open the blueprint again for a fresh site).
