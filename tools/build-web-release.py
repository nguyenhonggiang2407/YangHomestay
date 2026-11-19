#!/usr/bin/env python3
"""Build the PHP web deployment without repository internals or local secrets."""

import argparse
import hashlib
import json
import subprocess
import zipfile
from datetime import datetime, timezone
from pathlib import Path, PurePosixPath


ROOT = Path(__file__).resolve().parents[1]
PHP_BASE = "6af0d6a7f8511462c1944978620b88a97eca1e9a"
FRONTEND_SOURCE = "291a3018cd82b50e4e3136dd19cbe1fc18f65f27"
ROOT_FILES = {".htaccess", "index.php", "robots.txt", "sitemap.xml"}
STATIC_ROOTS = {"customer", "admin", "manager", "staff", "assets"}
STATIC_EXTENSIONS = {".html", ".css", ".js", ".jpg", ".jpeg", ".png", ".webp", ".svg", ".ico", ".woff", ".woff2"}
PHP_ENDPOINTS = {"backend/api.php", "backend/customer-api.php", "backend/bootstrap.php", "backend/portal.php"}


def git(*arguments):
    return subprocess.check_output(["git", "-C", str(ROOT), *arguments]).decode("utf-8").strip()


def allowed(name):
    path = PurePosixPath(name)
    if name in ROOT_FILES or name in PHP_ENDPOINTS:
        return True
    if path.parts[0] in STATIC_ROOTS:
        return path.suffix.lower() in STATIC_EXTENSIONS
    return len(path.parts) == 3 and path.parts[:2] in {
        ("backend", "lib"), ("backend", "services")
    } and path.suffix == ".php"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("output", type=Path, help="ZIP path outside the repository")
    args = parser.parse_args()
    if git("status", "--porcelain"):
        raise SystemExit("Commit the release first; the package must identify a clean source tree.")
    output = args.output.resolve()
    if output.is_relative_to(ROOT):
        raise SystemExit("Write the deployment ZIP outside the repository.")
    if output.exists():
        raise SystemExit("Output already exists; choose a new release filename.")
    names = [name for name in git("ls-files").splitlines() if allowed(name)]
    if not ROOT_FILES.issubset(names) or not PHP_ENDPOINTS.issubset(names):
        raise SystemExit("Required web files are missing.")
    entries = {}
    for name in names:
        path = ROOT / name
        if path.is_symlink() or not path.resolve().is_relative_to(ROOT):
            raise SystemExit(f"Refusing non-repository file: {name}")
        entries[name] = path.read_bytes()
    release = {
        "project": "YangHomestay",
        "runtime": "php-supabase",
        "sourceCommit": git("rev-parse", "HEAD"),
        "phpBaseCommit": PHP_BASE,
        "frontendSourceCommit": FRONTEND_SOURCE,
        "javaBackendDeployed": False,
        "builtAt": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    }
    entries["release.json"] = (json.dumps(release, indent=2) + "\n").encode("utf-8")
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, "x", compression=zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
        for name, data in sorted(entries.items()):
            entry = zipfile.ZipInfo(name)
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            entry.compress_type = zipfile.ZIP_DEFLATED
            archive.writestr(entry, data, compresslevel=6)
    with zipfile.ZipFile(output) as archive:
        if archive.testzip() is not None or set(archive.namelist()) != set(entries):
            raise SystemExit("Archive integrity verification failed.")
        if any((entry.external_attr >> 16) & 0o777 != 0o644 for entry in archive.infolist()):
            raise SystemExit("Archive files must be readable by the web server (0644).")
    manifest = {
        **release,
        "archiveSha256": hashlib.sha256(output.read_bytes()).hexdigest(),
        "files": {name: hashlib.sha256(data).hexdigest() for name, data in sorted(entries.items())},
    }
    output.with_suffix(".manifest.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"archive": str(output), "files": len(entries), "bytes": output.stat().st_size, **release}, indent=2))


if __name__ == "__main__":
    main()
