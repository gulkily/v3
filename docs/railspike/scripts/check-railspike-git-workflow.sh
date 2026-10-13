#!/usr/bin/env bash
# Exercises the README's exact update block in local, disposable repositories.
set -euo pipefail
source_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
fixture_dir="$(mktemp -d "${TMPDIR:-/tmp}/railspike-git-workflow.XXXXXX")"
case "$fixture_dir" in
  "${TMPDIR:-/tmp}"/railspike-git-workflow.*) ;;
  *) printf 'unexpected fixture path: %s\n' "$fixture_dir" >&2; exit 1 ;;
esac
trap 'rm -rf -- "$fixture_dir"' EXIT
upstream_work="$fixture_dir/upstream-work"
upstream_bare="$fixture_dir/upstream.git"
host="$fixture_dir/host with spaces"
clone="$fixture_dir/clone"
configure_identity() {
  git -C "$1" config user.name 'Railspike fixture'
  git -C "$1" config user.email 'railspike-fixture@example.invalid'
}
# Isolate fixtures from user Git configuration, hooks, signing, and aliases.
export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_NOSYSTEM=1
export GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=protocol.file.allow GIT_CONFIG_VALUE_0=always
awk '/<!-- railspike-update-start -->/{copy=1;next} /<!-- railspike-update-end -->/{copy=0} copy && !/^```/{print}' "$source_root/README.md" > "$fixture_dir/update.sh"
test -s "$fixture_dir/update.sh"
# Replace only the canonical network URL with this test's local upstream.
sed -i "s|https://github.com/gulkily/railspike.git|$upstream_bare|g" "$fixture_dir/update.sh"
update() { (cd "$1" && bash "$fixture_dir/update.sh") > "$fixture_dir/update.log" 2>&1; }
refuse() {
  local repo="$1" before_head before_index before_status
  before_head="$(git -C "$repo/docs/railspike" rev-parse HEAD)"
  before_index="$(git -C "$repo" ls-files -s)"
  before_status="$(git -C "$repo" status --porcelain --untracked-files=all)"
  if update "$repo"; then printf 'Expected refusal: %s\n' "$2" >&2; exit 1; fi
  test "$(git -C "$repo/docs/railspike" rev-parse HEAD)" = "$before_head"
  test "$(git -C "$repo" ls-files -s)" = "$before_index"
  test "$(git -C "$repo" status --porcelain --untracked-files=all)" = "$before_status"
}
git init -q --initial-branch=master "$upstream_work"
configure_identity "$upstream_work"
printf 'first\n' > "$upstream_work/README.md"
git -C "$upstream_work" add README.md
git -C "$upstream_work" commit -qm 'initial upstream'
git clone -q --bare "$upstream_work" "$upstream_bare"
git -C "$upstream_work" remote add origin "$upstream_bare"
git init -q --initial-branch=master "$host"
configure_identity "$host"
printf 'host\n' > "$host/README.md"
git -C "$host" add README.md
git -C "$host" commit -qm 'initial host'
# Same one-command installation as the README, substituting the fixture upstream.
git -C "$host" submodule add "$upstream_bare" docs/railspike >/dev/null 2>&1
test -z "$(git -C "$host" config --file .gitmodules --get submodule.docs/railspike.branch || :)"
test "$(git -C "$host/docs/railspike" branch --show-current)" = master
test "$(git -C "$host" diff --cached --name-only | sort)" = $'.gitmodules\ndocs/railspike'
git -C "$host" commit -qm 'add Railspike'
initial_revision="$(git -C "$host/docs/railspike" rev-parse HEAD)"
git clone -q "$host" "$clone"
configure_identity "$clone"
if update "$clone"; then printf 'Uninitialized update should fail\n' >&2; exit 1; fi
# Advance upstream before initializing: init must still preserve the host pointer.
printf 'second\n' >> "$upstream_work/README.md"
git -C "$upstream_work" commit -am 'advance upstream' -q
git -C "$upstream_work" push -q origin master
git -C "$clone" submodule update --init -- docs/railspike >/dev/null 2>&1
test "$(git -C "$clone/docs/railspike" rev-parse HEAD)" = "$initial_revision"
test -z "$(git -C "$clone/docs/railspike" branch --show-current)"
# Dirty host and submodule refusal before any checkout movement.
printf 'keep\n' > "$host/untracked.txt"
refuse "$host" 'dirty host'
rm "$host/untracked.txt"
printf 'keep\n' > "$host/docs/railspike/untracked.txt"
refuse "$host" 'dirty submodule'
rm "$host/docs/railspike/untracked.txt"
git -C "$host/docs/railspike" remote set-url origin "$fixture_dir/wrong.git"
refuse "$host" 'wrong origin'
git -C "$host/docs/railspike" remote set-url origin "$upstream_bare"
git -C "$host" config submodule.docs/railspike.branch other
refuse "$host" 'unexpected branch override'
git -C "$host" config --unset submodule.docs/railspike.branch
# Both attached and detached checkouts update, without changing host HEAD/index.
for repo in "$host" "$clone"; do
  host_head="$(git -C "$repo" rev-parse HEAD)"
  index_before="$(git -C "$repo" ls-files -s)"
  update "$repo" || { cat "$fixture_dir/update.log"; exit 1; }
  test "$(git -C "$repo/docs/railspike" rev-parse HEAD)" = "$(git -C "$upstream_work" rev-parse HEAD)"
  test "$(git -C "$repo" diff --name-only)" = docs/railspike
  test "$(git -C "$repo" rev-parse HEAD)" = "$host_head"
  test "$(git -C "$repo" ls-files -s)" = "$index_before"
  git -C "$repo" config submodule.docs/railspike.ignore all
  refuse "$repo" 'unreviewed pointer, even when status normally hides it'
  git -C "$repo" config --unset submodule.docs/railspike.ignore
  git -C "$repo" add docs/railspike
  git -C "$repo" commit -qm 'reviewed update'
  update "$repo"
  test -z "$(git -C "$repo" status --porcelain)"
done
# Local history ahead of upstream must not be silently accepted as current.
configure_identity "$host/docs/railspike"
printf 'local\n' >> "$host/docs/railspike/README.md"
git -C "$host/docs/railspike" commit -am 'local work' -q
git -C "$host" add docs/railspike
git -C "$host" commit -qm 'local pointer'
refuse "$host" 'local history ahead'
printf 'third\n' >> "$upstream_work/README.md"
git -C "$upstream_work" commit -am 'diverge upstream' -q
git -C "$upstream_work" push -q origin master
refuse "$host" 'divergent history'
# Missing upstream: state stays intact and failure is visible.
mv "$upstream_bare" "$fixture_dir/unavailable.git"
refuse "$clone" 'unavailable upstream'
printf 'Railspike Git workflow fixture passed.\n'
