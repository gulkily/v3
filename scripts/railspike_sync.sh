#!/usr/bin/env bash
set -euo pipefail

railspike_usage() {
  cat <<'USAGE'
Usage:
  ./v3 railspike sync [--repository-root=/path/repository] [--remote-url=https://github.com/rlspk/railspike.git] [--ref=v0.4.0-beta.1]
  ./v3 fdp sync ...  (deprecated alias)

Updates docs/railspike to an explicit release tag using Git subtree, creating update commits.
Without --ref the pinned tag v0.4.0-beta.1 (0dcab223) is used, so a plain sync never moves
the installed version. Branches such as main are not accepted; pass a newer tag deliberately.
The target must descend from the installed revision recorded in RAILSPIKE_SOURCE.md.
Requires a clean migrated repository with subtree history. FDP stays frozen.
An explicit --remote-url selects and records a different source after success.
See docs/reference/railspike_workflow.md for review and recovery guidance.
USAGE
}

railspike_sync() {
  local repository_root canonical source ref pinned_ref pinned_commit installed record explicit=0 argument top existing marker split revision operation
  repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  canonical='https://github.com/rlspk/railspike.git'
  pinned_ref='v0.4.0-beta.1'
  pinned_commit='0dcab22380874e53dae641b3d82a24c703241ac9'
  ref="${pinned_ref}"
  source="${canonical}"
  for argument in "$@"; do
    case "${argument}" in
      --repository-root=*) repository_root="${argument#--repository-root=}" ;;
      --remote-url=*) source="${argument#--remote-url=}"; explicit=1 ;;
      --ref=*) ref="${argument#--ref=}" ;;
      -h|--help) railspike_usage; return 0 ;;
      *) echo "Unknown Railspike sync option: ${argument}" >&2; railspike_usage >&2; return 1 ;;
    esac
  done
  if [[ -z "${repository_root}" || ! -d "${repository_root}" ]]; then
    echo "Railspike repository root is not a directory: ${repository_root}" >&2; return 1
  fi
  if [[ -z "${source}" || "${source}" == -* ]]; then
    echo 'Railspike remote URL must be nonempty and cannot start with a dash.' >&2; return 1
  fi
  if [[ ! "${ref}" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*$ || "${ref}" == */ || "${ref}" == *..* ]]; then
    echo 'Railspike ref must be a release tag name such as v0.4.0-beta.1.' >&2; return 1
  fi
  repository_root="$(cd "${repository_root}" && pwd -P)"
  if ! top="$(git -C "${repository_root}" rev-parse --show-toplevel 2>/dev/null)" || [[ "${repository_root}" != "${top}" ]]; then
    echo 'Railspike requires the root of a Git working tree.' >&2; return 1
  fi
  for operation in MERGE_HEAD CHERRY_PICK_HEAD REVERT_HEAD rebase-merge rebase-apply; do
    if [[ -e "$(git -C "${repository_root}" rev-parse --path-format=absolute --git-path "${operation}")" ]]; then
      echo 'Finish or abort the existing Git operation before Railspike sync.' >&2; return 1
    fi
  done
  if [[ -n "$(git -C "${repository_root}" status --porcelain --untracked-files=all)" ]]; then
    echo 'Railspike sync requires a clean working tree; preserve existing changes first.' >&2; return 1
  fi
  if [[ -L "${repository_root}/docs" || -L "${repository_root}/docs/railspike" ]] ||
     [[ "$(git -C "${repository_root}" cat-file -t HEAD:docs/railspike 2>/dev/null || true)" != tree ]] ||
     ! git -C "${repository_root}" cat-file -e HEAD:docs/railspike/RAILSPIKE.md 2>/dev/null; then
    echo 'Target is not migrated. Integrate the reviewed Railspike migration first; see docs/reference/railspike_workflow.md.' >&2; return 1
  fi
  marker="$(git -C "${repository_root}" log -1 --format=%B --grep='^git-subtree-dir: docs/railspike/*$' HEAD)"
  split="$(sed -n 's/^git-subtree-split: //p' <<< "${marker}")"
  if [[ ! "${split}" =~ ^[0-9a-f]{40}$ ]]; then
    echo 'Missing Railspike subtree metadata. Restore full import history; do not reuse FDP metadata. See docs/reference/railspike_workflow.md.' >&2; return 1
  fi
  existing="$(git -C "${repository_root}" remote get-url --all railspike 2>/dev/null || true)"
  if [[ "${existing}" == *$'\n'* ]]; then
    echo 'Railspike remote has multiple URLs; resolve its configuration before syncing.' >&2; return 1
  fi
  if (( ! explicit )) && [[ -n "${existing}" && "${existing}" != "${canonical}" ]]; then
    echo "Railspike remote URL is not the canonical source: ${existing}" >&2
    echo "Expected: ${canonical}. Use --remote-url deliberately to select a source." >&2; return 1
  fi
  if ! git -C "${repository_root}" fetch --no-tags "${source}" "refs/tags/${ref}"; then
    echo "Railspike fetch of tag ${ref} failed. Check the tag name, source and connectivity; working files and remote configuration were not changed. Branches are not accepted." >&2; return 1
  fi
  revision="$(git -C "${repository_root}" rev-parse 'FETCH_HEAD^{commit}')"
  if [[ "${ref}" == "${pinned_ref}" && "${source}" == "${canonical}" && "${revision}" != "${pinned_commit}" ]]; then
    echo "Tag ${pinned_ref} no longer resolves to ${pinned_commit}; refusing. No merge was attempted." >&2; return 1
  fi
  record="${repository_root}/docs/railspike/RAILSPIKE_SOURCE.md"
  installed="${split}"
  if [[ -f "${record}" ]]; then
    installed="$(sed -n 's/^- Commit: \([0-9a-f]\{40\}\)$/\1/p' "${record}" | head -n 1)"
    : "${installed:=${split}}"
  fi
  if [[ "${installed}" == "${revision}" ]]; then
    echo "Railspike is already at ${ref} (${revision}); nothing to do."; return 0
  fi
  if ! git -C "${repository_root}" cat-file -e "${installed}^{commit}" 2>/dev/null ||
     ! git -C "${repository_root}" merge-base --is-ancestor "${installed}" "${revision}"; then
    echo 'Upstream does not contain the recorded Railspike revision. Restore source/history before retrying; no merge was attempted.' >&2; return 1
  fi
  if ! git -C "${repository_root}" cat-file -e "${revision}:RAILSPIKE.md" 2>/dev/null; then
    echo 'Upstream is missing RAILSPIKE.md; no merge was attempted.' >&2; return 1
  fi
  if ! git -C "${repository_root}" subtree merge --prefix=docs/railspike "${revision}" --squash; then
    echo 'Railspike merge failed. Inspect git status in the target; resolve and commit, or use git merge --abort if a merge is in progress. Preserve local work; see docs/reference/railspike_workflow.md.' >&2; return 1
  fi
  if [[ -f "${record}" ]]; then
    sed -i -e "s|^- Source: .*|- Source: ${source}|" -e "s|^- Ref: .*|- Ref: tag ${ref}|" \
      -e "s|^- Commit: .*|- Commit: ${revision}|" "${record}"
    git -C "${repository_root}" add docs/railspike/RAILSPIKE_SOURCE.md
    git -C "${repository_root}" commit --quiet -m "Record Railspike ${ref} (${revision}) in RAILSPIKE_SOURCE.md"
  fi
  if [[ -n "${existing}" ]]; then
    git -C "${repository_root}" remote set-url railspike "${source}"
  else
    git -C "${repository_root}" remote add railspike "${source}"
  fi
  echo "Railspike ${ref}: ${installed} -> ${revision}. Review the update commits; FDP was not changed."
}

railspike_sync "$@"
