#!/usr/bin/env python3
"""Rend l'icône et l'écran de lancement de Jarvis Jr. (étape 7, VISUELS.md).

Usage, depuis n'importe où : python3 projects/jarvis-jr/outils/visuels/rendre.py

Sorties dans site/ :
  apple-touch-icon.png (180), icons/icon-192.png, icons/icon-512.png, favicon-32.png,
  img/lancement-1170x2532.png (écran de lancement de l'iPhone 14).
Chaque fichier reçoit son origine (impeccable embed-prompt).
"""
import pathlib
import subprocess
import tempfile

from PIL import Image

RACINE = pathlib.Path(__file__).resolve().parents[2]
SOURCE = RACINE / "outils/visuels/icone.html"
SITE = RACINE / "site"
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
IMPECCABLE = pathlib.Path.home() / ".claude/skills/impeccable/scripts/impeccable"
GRAPHITE = (0x12, 0x14, 0x17)

ORIGINE_ICONE = (
    "Icône de Jarvis Jr., piste C « La touche GO », choisie par Colin le 2026-10-07. "
    "Dessin original de Claude (Claude Code) en HTML et CSS, source outils/visuels/icone.html, "
    "police Archivo (SIL Open Font License 1.1), rendu par Chrome sans fenêtre."
)
ORIGINE_LANCEMENT = (
    "Écran de lancement de Jarvis Jr. pour l'iPhone 14 (1170 x 2532), aplat graphite #121417 "
    "comme le fond de l'app, généré par outils/visuels/rendre.py (Claude, 2026-10-07)."
)
ICONES = {
    "apple-touch-icon.png": 180,
    "icons/icon-192.png": 192,
    "icons/icon-512.png": 512,
    "favicon-32.png": 32,
}


def rendre_icone(dest):
    subprocess.run(
        [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars",
         "--allow-file-access-from-files", "--virtual-time-budget=3000",
         "--window-size=1024,1024", f"--screenshot={dest}", SOURCE.as_uri()],
        check=True, capture_output=True,
    )


def inscrire(chemin, origine):
    subprocess.run([str(IMPECCABLE), "embed-prompt", str(chemin), "--prompt", origine],
                   check=True, capture_output=True)


def main():
    sorties = []
    with tempfile.TemporaryDirectory() as tmp:
        grande = pathlib.Path(tmp) / "icone-1024.png"
        rendre_icone(grande)
        img = Image.open(grande).convert("RGB")  # fond plein : iOS noircit la transparence
        if img.size != (1024, 1024):
            raise SystemExit(f"Rendu inattendu : {img.size}")
        for nom, taille in ICONES.items():
            chemin = SITE / nom
            chemin.parent.mkdir(parents=True, exist_ok=True)
            img.resize((taille, taille), Image.LANCZOS).save(chemin, optimize=True)
            inscrire(chemin, ORIGINE_ICONE)
            sorties.append(chemin)
    lancement = SITE / "img/lancement-1170x2532.png"
    lancement.parent.mkdir(parents=True, exist_ok=True)
    Image.new("RGB", (1170, 2532), GRAPHITE).save(lancement, optimize=True)
    inscrire(lancement, ORIGINE_LANCEMENT)
    sorties.append(lancement)
    for chemin in sorties:
        print(chemin.relative_to(RACINE), Image.open(chemin).size)


if __name__ == "__main__":
    main()
