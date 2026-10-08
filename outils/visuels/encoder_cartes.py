#!/usr/bin/env python3
"""Encode les cartes à collectionner et les fonds des combats (étape 7, VISUELS.md § 4 bis et § 7).

Usage (nécessite Pillow) : python3 projects/jarvis-jr/outils/visuels/encoder_cartes.py

Entrées :
  assets-originaux/generees/cartes/{q,a,e,p}01..13.png et r01..18.png (générées par Colin)
  assets-originaux/generees/fonds/fond-{bug,99,poly,flemme}.png
Sorties :
  site/img/cartes/<code>.webp : 900 x 900 px (300 points sur l'iPhone 14)
  site/img/fonds/fond-<boss>.webp : taille d'origine (1024 x 1536)
  site/data/cartes.json : les 70 cartes (code, série, condition, titre, réplique de Junior, image)
Source unique des textes et des prompts : CARTES.md (cartes) et VISUELS.md (fonds).
Chaque WebP reçoit en XMP son origine et le prompt exact (message de départ de la série + message de la carte).
Une carte sans image est signalée et sortie avec "image": null : l'app l'affiche EN IMPRESSION.
"""
import json
import pathlib
import re
from xml.sax.saxutils import escape

from PIL import Image

RACINE = pathlib.Path(__file__).resolve().parents[2]
GENEREES = RACINE / "assets-originaux/generees"
SITE = RACINE / "site"
TAILLE_CARTE = 900

SERIES = {  # titre de section dans CARTES.md -> (série, lettre)
    "QYRIS · Le Bug": ("qyris", "q"),
    "Asso · Le 99+": ("asso", "a"),
    "Études · Le Poly": ("etudes", "e"),
    "Perso · La Flemme": ("perso", "p"),
    "Rares · les 4 boss": ("rare", "r"),
}
FONDS = {  # fichier -> titre du prompt dans VISUELS.md § 4 bis
    "fond-bug": "1. Le bureau de QYRIS",
    "fond-99": "2. La scène du Cabaret",
    "fond-poly": "3. L'amphi",
    "fond-flemme": "4. Le salon",
}
DATE_GENERATION = "2026-10-08"


def lire_cartes():
    texte = (RACINE / "CARTES.md").read_text(encoding="utf-8")
    cartes = []
    for bloc in re.split(r"^## ", texte, flags=re.M)[1:]:
        titre_section = bloc.splitlines()[0].strip()
        if titre_section not in SERIES:
            continue
        serie, lettre = SERIES[titre_section]
        depart = re.search(r"^\*\*Message de départ\*\*[^\n]*:\n\n> (.+)$", bloc, flags=re.M).group(1)
        prompts = dict(re.findall(r"^\*\*([QAEPR]\d\d)\*\* :\n\n> (.+)$", bloc, flags=re.M))
        for code, condition, titre, junior in re.findall(
                r"^\| ([QAEPR]\d\d) \| ([^|]+) \| ([^|]+) \| ([^|]+) \|$", bloc, flags=re.M):
            numero = int(code[1:])
            cartes.append({
                "code": code.lower(),
                "serie": serie,
                "numero": numero,
                "total": 18 if serie == "rare" else 13,
                "niveau": None if serie == "rare" else numero,  # n° 1 offerte à l'installation (niveau 1)
                "condition": condition.strip(),
                "titre": titre.strip(),
                "junior": junior.strip(),
                "prompt_depart": depart,
                "prompt": prompts[code],
            })
        assert lettre and all(c["code"][0] == lettre for c in cartes if c["serie"] == serie)
    return cartes


def lire_fonds():
    texte = (RACINE / "VISUELS.md").read_text(encoding="utf-8")
    trouve = dict(re.findall(r"^\*\*(.+?)\*\*[^\n]* :\n\n> (.+)$", texte, flags=re.M))
    return {nom: trouve[titre] for nom, titre in FONDS.items()}


def xmp(description):
    return (
        '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">'
        '<dc:creator><rdf:Seq><rdf:li>Colin Flicourt (ChatGPT), encodage Claude</rdf:li></rdf:Seq></dc:creator>'
        f'<dc:description><rdf:Alt><rdf:li xml:lang="x-default">{escape(description)}</rdf:li></rdf:Alt></dc:description>'
        '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>'
    ).encode("utf-8")


def main():
    cartes = lire_cartes()
    assert len(cartes) == 70, f"{len(cartes)} cartes lues dans CARTES.md au lieu de 70"
    (SITE / "img/cartes").mkdir(parents=True, exist_ok=True)
    (SITE / "img/fonds").mkdir(parents=True, exist_ok=True)
    (SITE / "data").mkdir(parents=True, exist_ok=True)

    manquantes, poids = [], 0
    for c in cartes:
        source = GENEREES / "cartes" / f"{c['code']}.png"
        if not source.exists():
            manquantes.append(c["code"])
            c["image"] = None
            continue
        img = Image.open(source).convert("RGB")
        if img.width != img.height:
            raise SystemExit(f"{source.name} n'est pas carrée : {img.size}")
        img = img.resize((TAILLE_CARTE, TAILLE_CARTE), Image.LANCZOS)
        origine = (
            f"Carte à collectionner {c['code'].upper()} « {c['titre']} » de Jarvis Jr. Illustration générée par "
            f"Colin Flicourt avec ChatGPT le {DATE_GENERATION} (original : assets-originaux/generees/cartes/"
            f"{c['code']}.png), encodée par Claude (Claude Code) avec outils/visuels/encoder_cartes.py. "
            f"Message de départ de la série : {c['prompt_depart']} Message de la carte : {c['prompt']}"
        )
        sortie = SITE / "img/cartes" / f"{c['code']}.webp"
        img.save(sortie, quality=84, method=6, xmp=xmp(origine))
        c["image"] = f"img/cartes/{c['code']}.webp"
        poids += sortie.stat().st_size

    for nom, prompt in lire_fonds().items():
        source = GENEREES / "fonds" / f"{nom}.png"
        if not source.exists():
            manquantes.append(nom)
            continue
        img = Image.open(source).convert("RGB")
        origine = (
            f"Fond de combat « {nom} » de la session focus de Jarvis Jr. Image générée par Colin Flicourt avec "
            f"ChatGPT le {DATE_GENERATION} (original : assets-originaux/generees/fonds/{nom}.png), encodée par "
            f"Claude (Claude Code) avec outils/visuels/encoder_cartes.py. Prompt exact : {prompt}"
        )
        sortie = SITE / "img/fonds" / f"{nom}.webp"
        img.save(sortie, quality=84, method=6, xmp=xmp(origine))
        print(f"{sortie.relative_to(RACINE)} {img.size} ({sortie.stat().st_size // 1024} Ko)")

    donnees = [{k: v for k, v in c.items() if not k.startswith("prompt")} for c in cartes]
    (SITE / "data/cartes.json").write_text(json.dumps(donnees, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    faites = sum(1 for c in cartes if c["image"])
    print(f"{faites} cartes encodées en {TAILLE_CARTE} px ({poids // 1024} Ko au total), site/data/cartes.json écrit")
    if manquantes:
        print("Sans image (EN IMPRESSION) :", ", ".join(manquantes))


if __name__ == "__main__":
    main()
