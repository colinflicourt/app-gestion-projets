#!/usr/bin/env python3
"""Détoure les 8 images des boss de la session focus (étape 7, VISUELS.md § 4).

Usage (nécessite Pillow et numpy) : python3 projects/jarvis-jr/outils/visuels/detourer_boss.py

Entrées : assets-originaux/generees/boss-*.png (fond magenta, ou vert pour La Flemme).
Sorties : site/img/boss/boss-*.webp, toutes sur la même toile de 1020 x 660 px
(340 x 220 points sur l'iPhone 14), personnage centré et posé en bas : la pose K.O.
remplace la pose debout sans que rien ne bouge autour.

Principe du détourage :
- le fond est mesuré sur les 4 coins, pas supposé ;
- un pixel est du fond s'il est très proche de cette couleur (seuil strict), où qu'il soit :
  les trous fermés (entre les pattes du Bug) deviennent transparents aussi ;
- l'alpha progressif et le dé-mélange ne s'appliquent que sur une bande de 3 px autour du fond,
  c'est-à-dire sur le contour noir. L'intérieur reste opaque : le violet du 99+, proche du
  magenta, ne devient pas transparent.
Échelle : le boss debout fait 660 px de haut ; sa pose K.O. garde la même échelle (le prompt
demandait « la même taille »), réduite seulement si elle ne tient pas dans la toile.
Chaque WebP reçoit son origine et le prompt exact dans ses métadonnées XMP.
"""
import pathlib
import re
from xml.sax.saxutils import escape

import numpy as np
from PIL import Image

RACINE = pathlib.Path(__file__).resolve().parents[2]
ENTREES = RACINE / "assets-originaux/generees"
SORTIES = RACINE / "site/img/boss"
VISUELS = RACINE / "VISUELS.md"

TOILE = (1020, 660)  # largeur, hauteur en px (3x)
SEUIL_FOND = 90      # distance RVB en dessous de laquelle un pixel est du fond
BANDE = 3            # px de contour où l'alpha est progressif
MARGE_BAS = 6        # px sous le personnage, pour ne pas couper l'antialiasing

# nom du fichier -> (titre du prompt dans VISUELS.md, nom affiché)
BOSS = {
    "poly": ("1. Le Poly", "Le Poly"),
    "bug": ("2. Le Bug", "Le Bug"),
    "99": ("3. Le 99+", "Le 99+"),
    "flemme": ("4. La Flemme", "La Flemme"),
}


def prompts():
    """Lit les prompts exacts dans VISUELS.md (une ligne « > » après chaque titre en gras)."""
    texte = VISUELS.read_text(encoding="utf-8")
    trouve = dict(re.findall(r"^\*\*(.+?)\*\*[^\n]* :\n\n> (.+)$", texte, flags=re.M))
    resultat = {}
    for cle, (titre, nom) in BOSS.items():
        numero = titre.split(".")[0]
        resultat[f"boss-{cle}"] = trouve[titre]
        resultat[f"boss-{cle}-ko"] = trouve[f"{numero} bis. {nom}, K.O."]
    return resultat


def dilater(masque, n):
    """Dilatation carrée de n px, sans scipy."""
    out = masque.copy()
    for _ in range(n):
        m = out.copy()
        m[1:, :] |= out[:-1, :]
        m[:-1, :] |= out[1:, :]
        m[:, 1:] |= out[:, :-1]
        m[:, :-1] |= out[:, 1:]
        out = m
    return out


def detourer(chemin):
    c = np.array(Image.open(chemin).convert("RGB")).astype(np.float32)
    coins = np.concatenate([c[:20, :20].reshape(-1, 3), c[:20, -20:].reshape(-1, 3),
                            c[-20:, :20].reshape(-1, 3), c[-20:, -20:].reshape(-1, 3)])
    bg = np.median(coins, axis=0)
    r, g, b = c[..., 0], c[..., 1], c[..., 2]
    if bg[1] > bg[0]:  # vert
        fond = "vert"
        score, bg_score = g - np.maximum(r, b), bg[1] - max(bg[0], bg[2])
    else:              # magenta
        fond = "magenta"
        score, bg_score = np.minimum(r, b) - g, min(bg[0], bg[2]) - bg[1]

    est_fond = np.linalg.norm(c - bg, axis=-1) < SEUIL_FOND
    bande = dilater(est_fond, BANDE) & ~est_fond

    a = np.ones(est_fond.shape, np.float32)
    a[est_fond] = 0
    a_bande = np.clip(1 - score / bg_score, 0, 1)
    a[bande] = a_bande[bande]

    # dé-mélange : c = a*fg + (1-a)*bg  ->  fg = (c - (1-a)*bg) / a
    a3 = np.maximum(a, 1e-3)[..., None]
    fg = np.clip((c - (1 - a[..., None]) * bg) / a3, 0, 255)
    fg[est_fond] = 0
    rgba = np.dstack([fg, a * 255]).astype(np.uint8)

    ys, xs = np.where(a > 0.02)
    boite = (xs.min(), ys.min(), xs.max() + 1, ys.max() + 1)
    return Image.fromarray(rgba, "RGBA").crop(boite), fond, bg


def poser(perso, echelle):
    w, h = perso.size
    perso = perso.resize((max(1, round(w * echelle)), max(1, round(h * echelle))), Image.LANCZOS)
    toile = Image.new("RGBA", TOILE, (0, 0, 0, 0))
    x = (TOILE[0] - perso.width) // 2
    y = TOILE[1] - MARGE_BAS - perso.height
    toile.alpha_composite(perso, (x, y))
    return toile


def xmp(description):
    return (
        '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        '<dc:creator><rdf:Seq><rdf:li>Colin Flicourt (ChatGPT), détourage Claude</rdf:li></rdf:Seq></dc:creator>'
        f'<dc:description><rdf:Alt><rdf:li xml:lang="x-default">{escape(description)}</rdf:li></rdf:Alt></dc:description>'
        '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>'
    ).encode("utf-8")


def main():
    textes = prompts()
    SORTIES.mkdir(parents=True, exist_ok=True)
    hauteur_utile = TOILE[1] - MARGE_BAS
    for cle, (_, nom) in BOSS.items():
        debout, f1, bg1 = detourer(ENTREES / f"boss-{cle}.png")
        ko, f2, bg2 = detourer(ENTREES / f"boss-{cle}-ko.png")
        echelle = min(hauteur_utile / debout.height, TOILE[0] / debout.width)
        echelle_ko = min(echelle, hauteur_utile / ko.height, TOILE[0] / ko.width)
        for suffixe, perso, ech, fond, bg in (("", debout, echelle, f1, bg1), ("-ko", ko, echelle_ko, f2, bg2)):
            base = f"boss-{cle}{suffixe}"
            pose = "vaincu, K.O." if suffixe else "debout"
            origine = (
                f"Boss « {nom} », {pose}, session focus de Jarvis Jr. Image générée par Colin Flicourt "
                f"avec ChatGPT le 2026-10-07 (original : assets-originaux/generees/{base}.png), détourée "
                f"sur fond {fond} par Claude (Claude Code) le 2026-10-08 avec outils/visuels/detourer_boss.py. "
                f"Prompt exact : {textes[base]}"
            )
            sortie = SORTIES / f"{base}.webp"
            poser(perso, ech).save(sortie, quality=90, alpha_quality=100, method=6, xmp=xmp(origine))
            print(f"{base}: fond {fond} {tuple(int(v) for v in bg)}, personnage {perso.size} "
                  f"x{ech:.3f} -> {sortie.relative_to(RACINE)} ({sortie.stat().st_size // 1024} Ko)")


if __name__ == "__main__":
    main()
