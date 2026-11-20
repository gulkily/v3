# Calendar export timezone diagnostics

2026-11-20 23:34 UTC · v3/calendar_export_timezone · implementation handoff

The live thread and day page showed 19:00. The feed, single-event file and Google link omitted the timezone. Reproducing the local November 20 date and time with the ZenMemes profile yielded 20261121T000000Z in both exporters. July coverage exercised the daylight offset.

System PHP was absent. A temporary PHP 8.4 runtime ran the focused tests successfully. Smoke failures including GPG-agent startup and HTTP test harness errors occurred both with the change and on the base checkout. Production export verification remains pending deployment.
