# Private message chat refinement Step 1 solution assessment

> **Feature plan:** [Step 1](./private_message_chat_refinement_step1_solution_assessment.md) · [Step 2](./private_message_chat_refinement_step2_feature_description.md) · [Step 3](./private_message_chat_refinement_step3_development_plan.md) · [Step 4](./private_message_chat_refinement_step4_implementation_summary.md)

## Original Query

Looks good, please merge into main and write Step 1 of the next one.

## Understood Intent

Cycle 1 is merged into local `main`. Assess Cycle 2 of the [master checklist](./private_messaging_usability_master_checklist.md): make existing conversations comfortable to read and reply to, with compact presentation, local timestamps, inline sending, and recoverable failures. Older-history retrieval and unread tracking remain in Cycles 3 and 4.

## Problem Statement

Conversations still use tall message cards and a large bottom-of-page composer, reload after sending, and lack reader retry or reliable recovery from an uncertain send outcome.

## Option A Compact conversation with an inline composer

Keep normal page scrolling and the composer after the transcript, show the newest message after initial decryption, and append confirmed sends without reloading.

- Pros: smallest layout change; naturally accommodates mobile keyboards, zoom, and long messages; reuses existing conversation, composer, and reader behavior.
- Cons: replying after browsing earlier messages still requires returning to the bottom; a shortcut back to the composer would help.

## Option B Compact conversation with a sticky composer

Enhance the existing page with compact directional messages and a composer that remains available while the document scrolls; append confirmed sends in place and preserve the reader's position.

- Pros: keeps replying within reach while reading; meets the requested chat behavior while reusing the current encryption, reader, and composer components.
- Cons: composer height, mobile keyboards, and zoom can obscure messages; requires responsive fallback and deliberate focus/scroll behavior.

## Recommendation

Choose **Option B**, using normal document scrolling and a compact sticky composer where space permits; fall back to an inline composer when keeping it visible would obscure the transcript. Carry these decisions into Step 2:

- Use directional alignment/backgrounds with accessible sender cues, consecutive-sender groups, local-date separators, and the shared friendly-time formatter. Compare a representative transcript against the current density target without sacrificing legibility.
- Start the labeled composer at two or three rows, grow it with content, remove redundant visible headings, and place one muted encryption note beneath it. Send with Ctrl/Cmd+Enter; Enter and Shift+Enter insert newlines.
- Show the newest message after initial decryption settles, without stealing focus or opening the mobile keyboard. Preserve position while reading earlier messages and keep keyboard focus predictable after sends/retries.
- Append only confirmed sends through shared message rendering and verification. Distinguish delivery confirmation from signature verification; preserve text entered while a send is pending.
- Resolve uncertain-send recovery before interface integration: retry must recognize an already accepted attempt without duplicating it or accepting different content as that attempt. Failed attempts retain their draft; changed content is a separate send.
- Correct verification text, keep successful marks quiet, and make missing/invalid signatures and decryption failures clear, with per-message retry where recoverable. Preserve profile/aggregate-user composer behavior.

The vertical slice is Messages → open conversation → read recent verified messages → send and see a confirmed reply without reloading → recover failed reads or uncertain delivery. Keep the existing initial history bound and exclude polling, live incoming updates, older-history loading, and unread state. If recovery plus presentation exceeds a day or eight stages, split into usable releases before implementation.
