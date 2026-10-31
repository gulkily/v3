# Invitation records

Format: Authored canonical post reply with Board-Tags: identity invitation internal; detached signature covers exact post bytes.
Body headers: Invitation-ID, Invitation-Action (issue/revoke/redeem), Verification-Hash. Issuance requires Expires-At and permits internal Destination.
Max-Redemptions: Optional issuance-only decimal integer 1–1000; absent means 1. Reject duplicates, leading zeros, signs, fractions, overflow and out-of-range values. Never rewrite existing signed bytes.
API: Existing invitation prepare accepts optional max_redemptions; omission preserves the legacy canonical body. Finalization binds the exact prepared bytes and detached signature.
Compatibility: Upgrade every writer and replay reader before creating multi-use records. Older software assumes one recipient and must not write or rebuild a repository containing these records. Recovery is roll-forward with compatible software and trusted full repository backup; do not edit signed records to downgrade.
Acceptance: Count distinct successfully committed recipient keys. Multi-use redemptions require validated repository acceptance receipts; missing or conflicting evidence fails closed. Partial revocation closes remaining capacity without removing earlier grants. Duplicate signed recovery consumes no extra use.
Replay: The write ledger and full/incremental approval projections share lifecycle rules; acceptance time governs expiry and receipts preserve grants across later revocation.
Status: Existing signed APIs support the optional limit with atomic final checks and same-key recovery.
