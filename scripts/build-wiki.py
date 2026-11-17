#!/usr/bin/env python3
"""Erzeugt das GitHub-Wiki aus handbuch/*.md und pusht es optional nach <repo>.wiki.git.

Das Wiki wird nie von Hand bearbeitet: Quelle ist handbuch/. Das Skript
- kopiert jede Seite und macht aus Links wie ``Plex.md`` bzw. ``Plex.md#abschnitt`` Wiki-Links,
- erzeugt ``_Sidebar.md`` (Reihenfolge wie die Links in ``Home.md``) und ``_Footer.md``,
- spiegelt mit ``--push`` das Ergebnis ins Wiki-Repo (überzählige Seiten werden dort entfernt).

Aufruf:
    python3 scripts/build-wiki.py            # nach build/wiki schreiben
    python3 scripts/build-wiki.py --push     # bauen und ins Wiki pushen
"""

from __future__ import annotations

import argparse
import re
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "handbuch"
DEFAULT_OUTPUT = ROOT / "build" / "wiki"

# Neutrale Identität für Wiki-Commits: keine persönlichen Daten im Wiki-Verlauf.
COMMIT_NAME = "Filmlog wiki builder"
COMMIT_EMAIL = "wiki-builder@users.noreply.github.com"

LINK_PATTERN = re.compile(r"\]\((?P<page>[A-Za-z0-9_-]+)\.md(?P<anchor>#[^)\s]*)?\)")
TITLE_PATTERN = re.compile(r"^#\s+(.+?)\s*$", re.MULTILINE)


def page_title(name: str, text: str) -> str:
    """Erste Überschrift der Seite, sonst der Dateiname."""
    match = TITLE_PATTERN.search(text)
    return match.group(1) if match else name.replace("-", " ")


def convert_links(text: str, pages: set[str], source: str) -> str:
    """Macht aus ``[Text](Seite.md)`` den Wiki-Link ``[Text](Seite)``; unbekannte Seiten brechen den Build ab."""

    def replace(match: re.Match[str]) -> str:
        page = match.group("page")
        if page not in pages:
            raise SystemExit(f"{source}: Link auf unbekannte Seite {page}.md")
        return f"]({page}{match.group('anchor') or ''})"

    return LINK_PATTERN.sub(replace, text)


def sidebar_order(pages: dict[str, str]) -> list[str]:
    """Reihenfolge der Links in Home.md, danach übrige Seiten alphabetisch."""
    ordered = [match.group("page") for match in LINK_PATTERN.finditer(pages.get("Home", ""))]
    ordered = list(dict.fromkeys(page for page in ordered if page in pages and page != "Home"))
    return ordered + sorted(page for page in pages if page not in ordered and page != "Home")


def build(output: Path) -> list[str]:
    sources = sorted(SOURCE.glob("*.md"))
    if not sources:
        raise SystemExit(f"Keine Seiten in {SOURCE}")

    pages = {path.stem: path.read_text(encoding="utf-8") for path in sources}
    if "Home" not in pages:
        raise SystemExit("handbuch/Home.md fehlt (Startseite des Wikis)")

    if output.exists():
        shutil.rmtree(output)
    output.mkdir(parents=True)

    for name, text in pages.items():
        (output / f"{name}.md").write_text(convert_links(text, set(pages), f"{name}.md"), encoding="utf-8")

    sidebar = ["**[Filmlog](Home)**", ""]
    sidebar += [f"- [{page_title(page, pages[page])}]({page})" for page in sidebar_order(pages)]
    (output / "_Sidebar.md").write_text("\n".join(sidebar) + "\n", encoding="utf-8")

    (output / "_Footer.md").write_text(
        "Generated from [`handbuch/`](../tree/main/handbuch) by `scripts/build-wiki.py`. "
        "Please edit the files in the repository, not this wiki.\n",
        encoding="utf-8",
    )

    return sorted(path.name for path in output.iterdir())


def git(*args: str, cwd: Path) -> str:
    return subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True, text=True).stdout.strip()


def wiki_remote() -> str:
    origin = git("remote", "get-url", "origin", cwd=ROOT)
    return re.sub(r"(\.git)?$", ".wiki.git", origin, count=1)


def push(output: Path) -> None:
    remote = wiki_remote()
    revision = git("rev-parse", "--short", "HEAD", cwd=ROOT)

    with tempfile.TemporaryDirectory(prefix="filmlog-wiki-") as directory:
        clone = Path(directory) / "wiki"
        try:
            git("clone", "--quiet", remote, str(clone), cwd=ROOT)
        except subprocess.CalledProcessError as error:
            raise SystemExit(
                f"Wiki-Repo {remote} nicht erreichbar ({error.stderr.strip()}).\n"
                "Ist das Wiki aktiviert und gibt es schon eine erste Seite? (GitHub legt das Wiki-Repo erst damit an.)"
            ) from error

        for existing in clone.glob("*.md"):
            existing.unlink()
        for page in output.glob("*.md"):
            shutil.copy2(page, clone / page.name)

        git("add", "--all", cwd=clone)
        if not git("status", "--porcelain", cwd=clone):
            print("Wiki ist bereits aktuell.")
            return

        git("-c", f"user.name={COMMIT_NAME}", "-c", f"user.email={COMMIT_EMAIL}",
            "commit", "--quiet", "-m", f"Wiki aus handbuch/ neu erzeugt ({revision})", cwd=clone)
        git("push", "--quiet", "origin", "HEAD", cwd=clone)
        print(f"Wiki gepusht: {remote}")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT, help="Zielordner (Standard: build/wiki)")
    parser.add_argument("--push", action="store_true", help="Ergebnis ins Wiki-Repo pushen")
    arguments = parser.parse_args()

    files = build(arguments.output)
    print(f"{len(files)} Wiki-Dateien in {arguments.output.relative_to(ROOT) if arguments.output.is_relative_to(ROOT) else arguments.output}")

    if arguments.push:
        push(arguments.output)

    return 0


if __name__ == "__main__":
    sys.exit(main())
