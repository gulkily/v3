# Calendar export timezone

Cycle v3/calendar_export_timezone · One-shot · [Plans](../README.md)

Debug mode enabled. Effort study R1/P2, extended estimates disabled.

**Cycle estimate: 0.05**

## Scope

On November 20, 2026, Ilya asked to fix the Tiny Habits listing to 7 p.m. EST, then asked to write the fix. The live listing already says 19:00, while its iCalendar and Google Calendar exports use floating times. This authorizes implementing the export correction. Deployment and production configuration are separate.

Reuse CalendarIcs for the main and tag feeds, the per-thread file, and the Google Calendar link. Add an optional instance setting for interpreting stored clock times and share the conversion to UTC. Preserve date-only events, existing records, and floating times on instances without a timezone. Configure ZenMemes with America/New_York. Check winter and summer offsets and midnight rollover. No matching feedback item was found.

Concepts touched include [Calendar feed](../../concepts/calendar_feed.md). Update its description and the calendar reference. Run smoke and calendar tests and verify the production case by replaying the exported start date and clock time locally. Production verification follows deployment.

Baseline f924dbb on fix/calendar-export-timezone. Planning commit pending.

## Study checkpoints

- Intake and entry, November 20, 2026. Exposed estimate 0.05 for implementation, focused regression tests and documentation. R1 not independently reverified.

Authored by Codex, November 20, 2026 UTC.
