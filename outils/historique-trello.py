#!/usr/bin/env python3
"""Extrait l'historique Trello utile à la régulation de l'XP (lecture seule, requêtes GET uniquement).

Pour les 4 tableaux de Jarvis (QYRIS, Asso, Études, Perso) : les cartes ouvertes et les cartes archivées actives
depuis DEPUIS, avec identifiant, domaine, colonne, dates, Urgent, étapes. Aucun titre, aucune description, aucun nom
d'étape n'est écrit dans la sortie. S'y ajoute le journal des gestes datés (étapes cochées, cartes déplacées ou
archivées) quand Trello le fournit : c'est la vraie heure de fin, bien meilleure que dateLastActivity.

Sortie : outils/donnees/historique-trello.json (hors git).
Lancer à travers le filtre :
    { python3 outils/historique-trello.py ; } 2>&1 | ~/.claude/hooks/redact-secrets
"""
import importlib.util
import json
import sys
from datetime import datetime, timezone
from pathlib import Path

DEPUIS = "2026-10-01T00:00:00.000Z"
SORTIE = Path(__file__).resolve().parent / "donnees" / "historique-trello.json"
TRELLO = Path(__file__).resolve().parents[3] / ".claude" / "scripts" / "trello.py"


def charger_trello():
    spec = importlib.util.spec_from_file_location("trello_jarvis", TRELLO)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def get(trello, chemin, params=None):
    return trello.api("GET", chemin, params=params)


def creation_carte(id_carte):
    return datetime.fromtimestamp(int(id_carte[:8], 16), timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.000Z")


def cartes_du_tableau(trello, t, domaine):
    noms_colonnes = {i: n for n, i in t["colonnes"].items()}
    noms_etiquettes = {i: n for n, i in t["etiquettes"].items()}
    brutes = get(trello, f"/boards/{t['id']}/cards/all", {
        "fields": "id,idList,idLabels,due,dueComplete,dateLastActivity,closed",
        "checklists": "all",
    })
    cartes = []
    for c in brutes:
        if c["closed"] and c["dateLastActivity"] < DEPUIS:
            continue
        elements = [e for l in c.get("checklists", []) for e in l["checkItems"]]
        cartes.append({
            "id": c["id"],
            "domaine": domaine,
            "colonne": noms_colonnes.get(c["idList"], "?"),
            "ouverte": not c["closed"],
            "creee": creation_carte(c["id"]),
            "dateLastActivity": c["dateLastActivity"],
            "due": c["due"],
            "dueComplete": c["dueComplete"],
            "urgent": "Urgent" in [noms_etiquettes.get(i) for i in c["idLabels"]],
            "etapes": len(elements),
            "etapes_cochees": sum(1 for e in elements if e["state"] == "complete"),
        })
    return cartes


def gestes_du_tableau(trello, t, domaine):
    """Journal daté : sans titre ni nom, seulement identifiants, colonnes et états."""
    gestes = []
    avant = None
    for _ in range(30):
        params = {"filter": "createCard,copyCard,updateCard,updateCheckItemStateOnCard", "since": DEPUIS, "limit": 1000}
        if avant:
            params["before"] = avant
        lot = get(trello, f"/boards/{t['id']}/actions", params)
        if not lot:
            break
        for a in lot:
            d = a.get("data", {})
            carte = d.get("card", {})
            geste = {"domaine": domaine, "date": a["date"], "type": a["type"], "carte": carte.get("id")}
            if a["type"] == "updateCheckItemStateOnCard":
                geste["etat"] = d.get("checkItem", {}).get("state")
                geste["element"] = d.get("checkItem", {}).get("id")
            elif a["type"] == "updateCard":
                if "listAfter" in d:
                    geste["de"] = d.get("listBefore", {}).get("name")
                    geste["vers"] = d["listAfter"].get("name")
                elif "closed" in carte:
                    geste["archivee"] = carte["closed"]
                elif "dueComplete" in carte:
                    geste["dueComplete"] = carte["dueComplete"]
                else:
                    continue
            gestes.append(geste)
        if len(lot) < 1000:
            break
        avant = lot[-1]["date"]
    return gestes


def main():
    trello = charger_trello()
    ids = trello.charger_ids()
    cartes, gestes, resume = [], [], {}
    for domaine in trello.DOMAINES:
        t = ids["tableaux"][domaine]
        c = cartes_du_tableau(trello, t, domaine)
        g = gestes_du_tableau(trello, t, domaine)
        cartes += c
        gestes += g
        resume[domaine] = {
            "cartes": len(c),
            "etapes": sum(x["etapes"] for x in c),
            "etapes_cochees": sum(x["etapes_cochees"] for x in c),
            "gestes": len(g),
        }
    gestes.sort(key=lambda g: g["date"])
    SORTIE.parent.mkdir(exist_ok=True)
    SORTIE.write_text(json.dumps({
        "extrait_le": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "depuis": DEPUIS,
        "cartes": cartes,
        "gestes": gestes,
    }, ensure_ascii=False, indent=1))
    print(f"Écrit : {SORTIE}")
    for domaine, r in resume.items():
        print(f"  {domaine:7} {r['cartes']:4} cartes, {r['etapes']:4} étapes ({r['etapes_cochees']} cochées), {r['gestes']} gestes")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(f"Erreur : {e}", file=sys.stderr)
        sys.exit(1)
