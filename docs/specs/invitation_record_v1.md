# Invitation records

Format: Authored canonical post reply with Board-Tags: identity invitation internal; detached signature covers exact post bytes.
Body headers: Invitation-ID, Invitation-Action (issue/revoke/redeem), Verification-Hash. Issuance requires Expires-At and permits internal Destination.
Max-Redemptions: Optional issuance-only decimal integer 1–1000; absent means 1. Reject duplicates, leading zeros, signs, fractions, overflow and out-of-range values. Never rewrite existing signed bytes.
API: Existing invitation prepare accepts optional max_redemptions; omission preserves the legacy canonical body. Finalization binds the exact prepared bytes and detached signature.
Compatibility: Upgrade every writer and replay reader before creating multi-use records. Older software assumes one recipient and must not write or rebuild a repository containing these records. Recovery is roll-forward with compatible software and trusted full repository backup; do not edit signed records to downgrade.
Status: Canonical parser support first; API enablement follows receipt-aware replay and atomic admission verification.
