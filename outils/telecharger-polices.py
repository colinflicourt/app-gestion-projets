#!/usr/bin/env python3
"""Télécharge les 3 polices de Jarvis Jr. (woff2 variables, sous-ensemble latin) et leurs licences.

Usage : python3 outils/telecharger-polices.py   (écrit dans site/fonts/ et site/fonts/licences/)
Le sous-ensemble latin couvre les accents, œ, ’, … et € : tous les textes de l'app."""
import pathlib
import re
import urllib.request

DOSSIER = pathlib.Path(__file__).resolve().parent.parent / 'site' / 'fonts'
CSS = ('https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900'
       '&family=Martian+Mono:wdth,wght@75..112.5,100..800&family=Permanent+Marker&display=swap')
NAVIGATEUR = ('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
              '(KHTML, like Gecko) Chrome/140.0 Safari/537.36')
FICHIERS = {'Archivo': 'archivo.woff2', 'Martian Mono': 'martian-mono.woff2', 'Permanent Marker': 'permanent-marker.woff2'}
LICENCES = {
    'OFL-Archivo.txt': 'https://raw.githubusercontent.com/google/fonts/main/ofl/archivo/OFL.txt',
    'OFL-MartianMono.txt': 'https://raw.githubusercontent.com/google/fonts/main/ofl/martianmono/OFL.txt',
    'Apache-PermanentMarker.txt': 'https://raw.githubusercontent.com/google/fonts/main/apache/permanentmarker/LICENSE.txt',
}


def lire(url):
    requete = urllib.request.Request(url, headers={'User-Agent': NAVIGATEUR})
    with urllib.request.urlopen(requete, timeout=30) as reponse:
        return reponse.read()


css = lire(CSS).decode()
# Un bloc @font-face par sous-ensemble, précédé de son nom en commentaire : on garde « latin »
trouves = {}
for sous_ensemble, bloc in re.findall(r'/\* (\S+) \*/\s*@font-face \{(.*?)\}', css, re.S):
    famille = re.search(r"font-family: '([^']+)'", bloc).group(1)
    if sous_ensemble == 'latin' and famille in FICHIERS:
        trouves[famille] = re.search(r'url\((https://[^)]+\.woff2)\)', bloc).group(1)
manquants = sorted(set(FICHIERS) - set(trouves))
if manquants:
    raise SystemExit(f'Introuvable dans la CSS de Google Fonts : {", ".join(manquants)}')

(DOSSIER / 'licences').mkdir(parents=True, exist_ok=True)
for famille, url in trouves.items():
    contenu = lire(url)
    (DOSSIER / FICHIERS[famille]).write_bytes(contenu)
    print(f'{FICHIERS[famille]} : {len(contenu) // 1024} Ko')
for nom, url in LICENCES.items():
    (DOSSIER / 'licences' / nom).write_bytes(lire(url))
    print(f'licences/{nom}')
