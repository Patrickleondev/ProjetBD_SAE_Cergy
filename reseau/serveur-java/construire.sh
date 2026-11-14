#!/bin/sh
# Recompile le serveur sans Maven ni NetBeans : produit dist/serveur-bornes.jar.
# Il faut javac 17 ou plus. Sous Windows, lancer depuis Git Bash.
cd "$(dirname "$0")"
rm -rf build && mkdir -p build
javac --release 17 -d build src/main/java/fr/voltoise/serveur/*.java || exit 1
printf 'Main-Class: fr.voltoise.serveur.ServeurBornes\nClass-Path: lib/postgresql-42.7.4.jar\n' > build/MANIFEST.MF
jar cfm dist/serveur-bornes.jar build/MANIFEST.MF -C build fr
rm -rf build
echo "dist/serveur-bornes.jar reconstruit"
