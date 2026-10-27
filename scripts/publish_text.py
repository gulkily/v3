#!/usr/bin/env python3
"""Publish a UTF-8 text file through an instance's write API."""

import argparse
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
from urllib.error import HTTPError, URLError
from urllib.parse import quote, urlencode, urlsplit
from urllib.request import HTTPRedirectHandler, Request, build_opener


class NoRedirects(HTTPRedirectHandler):
    # Treat redirects as errors rather than forwarding authored content elsewhere.
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def post(base, route, fields, signed=False):
    """Send one form request; signed routes return JSON, anonymous routes key=value."""
    request = Request(base + "/api/" + route, data=urlencode(fields).encode("utf-8"),
                      headers={"Content-Type": "application/x-www-form-urlencoded"})
    with build_opener(NoRedirects).open(request, timeout=60) as response:
        result = response.read().decode("utf-8")
    if signed:
        try:
            values = json.loads(result)
        except ValueError:
            raise ValueError("Expected JSON from API; check the URL and instance access policy.")
    else:
        values = dict(line.split("=", 1) for line in result.splitlines() if "=" in line)
    if not isinstance(values, dict) or values.get("status") != "ok":
        raise ValueError(values.get("error", "Unexpected API response; check instance access policy.")
                         if isinstance(values, dict) else "Unexpected API response.")
    return values


def sign_record(record, fingerprint):
    # The server's canonical record is the signing input, not the original file.
    # Preserve every UTF-8 byte and newline; temporary files hold no private key.
    with tempfile.TemporaryDirectory(prefix="publish-text-") as directory:
        path = Path(directory) / "record.txt"
        signature = Path(directory) / "record.txt.asc"
        path.write_bytes(record.encode("utf-8"))
        result = subprocess.run([
            "gpg", "--armor", "--detach-sign", "--local-user", fingerprint,
            "--output", str(signature), str(path),
        ])
        if result.returncode:
            raise ValueError("GPG signing failed. Check the secret key and unlock it with GPG/pinentry.")
        return signature.read_text(encoding="ascii")


def generate_key(name):
    # GPG keeps the key in its normal keyring and obtains the passphrase via pinentry.
    # KEY_CREATED identifies the new primary key without an ambiguous name lookup.
    result = subprocess.run([
        "gpg", "--status-fd", "1", "--quick-generate-key", name, "ed25519", "sign", "0",
    ], stdout=subprocess.PIPE, text=True)
    match = re.search(r"^\[GNUPG:\] KEY_CREATED [BP] ([0-9A-Fa-f]{40,64})$",
                      result.stdout, re.MULTILINE)
    if not match:
        raise ValueError("GPG did not report a new key. Check GPG/pinentry and your keyring before retrying.")
    fingerprint = match.group(1)
    # Report immediately: registration or publication can fail after key creation.
    print("Signing-key: " + fingerprint, flush=True)
    print("Key-location: GPG keyring (GNUPGHOME if set)", flush=True)
    if result.returncode:
        raise ValueError("GPG reported an error after creating the key; inspect the retained key before retrying.")
    return fingerprint


def resolve_signing_key(selector):
    # Preserve explicit fingerprints. For names, let GPG match local user IDs,
    # then count primary keys only (a signing subkey is not a separate identity).
    if re.fullmatch(r"[0-9a-fA-F]{40}|[0-9a-fA-F]{64}", selector):
        return selector
    result = subprocess.run([
        "gpg", "--batch", "--with-colons", "--fingerprint", "--list-secret-keys", "--", selector,
    ], stdout=subprocess.PIPE, text=True)
    fingerprints = set()
    primary = False
    for line in result.stdout.splitlines():
        fields = line.split(":")
        if fields[0] in ("sec", "ssb"):
            primary = fields[0] == "sec"
        elif fields[0] == "fpr" and primary:
            if len(fields) > 9 and re.fullmatch(r"[0-9a-fA-F]{40}|[0-9a-fA-F]{64}", fields[9]):
                fingerprints.add(fields[9])
            primary = False
    if len(fingerprints) > 1:
        raise ValueError("Username matches multiple secret keys; use --signing-key with a full primary fingerprint.")
    if result.returncode or not fingerprints:
        raise ValueError("No matching secret key found. Check the GPG user ID and keyring/GNUPGHOME.")
    fingerprint = fingerprints.pop()
    print("Signing-key: " + fingerprint, flush=True)
    return fingerprint


def register_key(base, fingerprint):
    # Only export the public key. Registration proves possession by signing the
    # server's prepared identity record before the post is prepared separately.
    exported = subprocess.run(["gpg", "--armor", "--export", fingerprint],
                              stdout=subprocess.PIPE)
    if exported.returncode or not exported.stdout:
        raise ValueError("Unable to export the generated public key; the secret key remains in your keyring.")
    prepared = post(base, "prepare_identity", {"public_key": exported.stdout.decode("ascii")}, signed=True)
    required = ("prepare_token", "canonical_record")
    if any(not isinstance(prepared.get(key), str) or not prepared[key] for key in required):
        raise ValueError("Identity prepare response is missing required signing fields.")
    payload = {key: prepared[key] for key in required}
    payload["detached_signature"] = sign_record(prepared["canonical_record"], fingerprint)
    registered = post(base, "create_identity", payload, signed=True)
    identity = "openpgp:" + fingerprint.lower()
    if registered.get("identity_id") != identity:
        raise ValueError("Registration response identity does not match the generated key.")
    print("Identity-ID: " + identity, flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__, epilog=(
        "Requires Python 3; --signing-key also requires GPG and an identity already registered on the instance. "
        "--generate-key creates a persistent key and registers a new identity before publishing. "
        "Without either key option, publishes anonymously. "
        "The whole file becomes the body; instance text rules still apply. "
        "Requests are never retried automatically."
    ))
    parser.add_argument("instance", help="instance base URL, including any path prefix")
    parser.add_argument("file", type=Path, help="UTF-8 text file to publish")
    parser.add_argument("--subject", default="", help="new thread subject")
    parser.add_argument("--board-tags", default="general", help="comma-separated board tags")
    parser.add_argument("--thread-id", help="publish a reply in this thread")
    parser.add_argument("--parent-id", help="reply to this post (defaults to thread ID)")
    keys = parser.add_mutually_exclusive_group()
    keys.add_argument("--signing-key", help="full primary fingerprint or username matching one local GPG secret key; identity must be registered")
    keys.add_argument("--generate-key", metavar="NAME", help="create a persistent Ed25519 signing key, register its public identity, and publish; GPG prompts for a passphrase")
    args = parser.parse_args()

    # Validate local input before creating a persistent key or contacting the server.
    base = args.instance.rstrip("/")
    url = urlsplit(base)
    if (url.scheme not in ("http", "https") or not url.hostname
            or url.username is not None or url.password is not None
            or url.query or url.fragment):
        parser.error("instance must be an HTTP(S) base URL without credentials, query or fragment")
    if args.parent_id and not args.thread_id:
        parser.error("--parent-id requires --thread-id")
    if args.thread_id and args.subject:
        parser.error("--subject only applies to new threads")
    if args.signing_key is not None and (not args.signing_key.strip()
            or any(ord(char) < 32 or ord(char) == 127 for char in args.signing_key)):
        parser.error("--signing-key requires a nonempty fingerprint or username without control characters")
    if args.generate_key is not None and (not args.generate_key.strip()
            or any(ord(char) < 32 or ord(char) == 127 for char in args.generate_key)):
        parser.error("--generate-key requires a nonempty name without control characters")
    try:
        # read_bytes avoids Python's universal-newline conversion.
        body = args.file.read_bytes().decode("utf-8")
    except (OSError, UnicodeError) as exc:
        parser.error(f"cannot read UTF-8 text file: {exc}")
    if not body.strip():
        parser.error("text file must not be empty")

    fields = {"body": body, "board_tags": args.board_tags}
    route = "create_thread"
    if args.thread_id:
        route = "create_reply"
        fields.update(thread_id=args.thread_id, parent_id=args.parent_id or args.thread_id)
    else:
        fields["subject"] = args.subject
    prepared_id = None
    try:
        if args.signing_key is not None:
            args.signing_key = resolve_signing_key(args.signing_key)
        if args.generate_key is not None:
            args.signing_key = generate_key(args.generate_key.strip())
            register_key(base, args.signing_key)
        if args.signing_key:
            # Return the token, path, ID and canonical record unchanged from prepare.
            # Identity IDs always use the lowercase primary fingerprint.
            fields["author_identity_id"] = "openpgp:" + args.signing_key.lower()
            prepared = post(base, route.replace("create_", "prepare_"), fields, signed=True)
            required = ("prepare_token", "canonical_record", "record_path", "post_id")
            if any(not isinstance(prepared.get(key), str) or not prepared[key] for key in required):
                raise ValueError("Prepare response is missing required signing fields.")
            prepared_id = prepared["post_id"]
            payload = {key: prepared[key] for key in required}
            payload["author_identity_id"] = fields["author_identity_id"]
            payload["detached_signature"] = sign_record(prepared["canonical_record"], args.signing_key)
            values = post(base, "create_prepared_post", payload, signed=True)
        else:
            values = post(base, route, fields)
        if not isinstance(values.get("post_id"), str) or not values["post_id"]:
            raise ValueError("Publication response is missing the post ID.")
    except HTTPError as exc:
        # A failed response does not prove the write failed. Never automatically
        # retry a publication; report the prepared ID for a manual existence check.
        detail = exc.read().decode("utf-8", errors="replace")
        errors = [line[6:] for line in detail.splitlines() if line.startswith("error=")]
        try:
            data = json.loads(detail)
            if isinstance(data, dict) and isinstance(data.get("error"), str):
                errors = [data["error"]]
        except ValueError:
            pass
        print(f"Error: HTTP {exc.code}: " + (errors[0] if errors else
              "Check the instance URL and access policy."), file=sys.stderr)
        print("Check the instance before retrying; publication may have completed.", file=sys.stderr)
        if prepared_id:
            print("Post-ID: " + prepared_id, file=sys.stderr)
        return 1
    except (OSError, URLError, UnicodeError, ValueError) as exc:
        print(f"Error: {exc}", file=sys.stderr)
        print("Check the instance before retrying; publication may have completed.", file=sys.stderr)
        if prepared_id:
            print("Post-ID: " + prepared_id, file=sys.stderr)
        return 1

    print("Post-ID: " + values["post_id"])
    if values.get("thread_id"):
        print("Thread-ID: " + values["thread_id"])
    print("URL: " + base + "/posts/" + quote(values["post_id"], safe=""))
    return 0


if __name__ == "__main__":
    sys.exit(main())
