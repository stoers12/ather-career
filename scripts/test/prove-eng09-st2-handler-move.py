#!/usr/bin/env python3
"""ENG-09 ST-2: prove each handler is only moved and its __DIR__ paths rebased."""

import argparse
from pathlib import Path
import subprocess

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--base", default="origin/main", help="ST-1 base ref to compare")
args = parser.parse_args()
root = Path(__file__).resolve().parents[2]


def git(*arguments):
    return subprocess.check_output(["git", "-C", str(root), *arguments])


names = sorted(name for name in git("ls-tree", "--name-only", args.base).decode().splitlines()
               if name.endswith(".php"))
assert len(names) == 29, f"Expected 29 handlers at {args.base}, found {len(names)}"
assert not list(root.glob("*.php")), "Repository root still contains PHP files"
assert sorted(path.name for path in (root / "handlers").glob("*.php")) == names
replacements = 0
for name in names:
    original = git("show", f"{args.base}:{name}")
    expected = original
    count = 0
    for before, after in [(b"__DIR__ . '/", b"dirname(__DIR__) . '/"),
                          (b"__DIR__.'/", b"dirname(__DIR__).'/")]:
        count += expected.count(before)
        expected = expected.replace(before, after)
    actual = (root / "handlers" / name).read_bytes()
    assert b"\r\n" not in actual, f"CRLF found: {name}"
    assert actual == expected, f"Non-mechanical change: handlers/{name}"
    replacements += count
    print(f"IDENTICAL handlers/{name}: {count} replacements")
assert replacements == 198, f"Expected 198 replacements, found {replacements}"
print(f"PURE_MOVE_PROOF_PASSED: {len(names)} identical, {replacements} replacements, 0 root PHP files")
