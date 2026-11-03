# Post format records v1

A post's display format (see `Body-Format` in the [canonical post record](canonical_post_record_v1.md))
can change after publication without touching the post. Each change is its own
append-only record:

- `records/post-formats/<Record-ID>.txt`: the exact signed request.
- The same path plus `.asc`: its detached ASCII-armored OpenPGP signature by the actor.

Headers, in exactly this order, followed by a blank line and an empty body:

```text
Record-ID: post-format-<Post-ID>-<YYYYMMDDHHMMSS>-<8 lowercase hex>
Created-At: <RFC 3339 UTC, whole seconds; its digits match the Record-ID>
Post-ID: <the post being switched>
Body-Format: markdown | plain
Actor-Identity-ID: openpgp:<lowercase fingerprint of the signer>
```

The actor is the post's author or a root-approved operator. The writer checks that
authority and the signature when it accepts the record; the read model applies
committed records without re-deciding authority. For each post the latest record
(by `Created-At`, then `Record-ID`) sets the displayed format, overriding the
post's own header or its absence. Records for missing or retracted posts are
ignored, as are records that fail to parse. Switching back is another record;
nothing is rewritten or deleted.
