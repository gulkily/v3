# Parity Minimal Fixture Tree V1

This fixture tree seeds the smallest self-contained canonical repository slice needed for the retained PHP rewrite scope.

## Included Families

- `records/posts/`: one thread root and one reply
- `records/identity/`: one identity bootstrap record
- `records/public-keys/`: the reusable signer key for that identity
- `records/instance/`: one published instance facts file

## Intended Uses

- board index reads
- thread and permalink reads
- profile and username-route reads
- instance-page reads
- bootstrap/public-key storage verification

## Notes

- The identity bootstrap file and public-key file intentionally carry the same armored key block.
- The post fixtures are plain in-scope examples with no merge, moderation, task, or thread-title-update dependencies.
