#!/bin/sh
# Lance le serveur compilé (Java 17 ou plus). Le pilote PostgreSQL est dans lib/.
# Usage : sh lancer_serveur.sh [fichier.properties]   (par défaut ../serveur.properties)
cd "$(dirname "$0")"
java -version
java -jar serveur-bornes.jar "${1:-../serveur.properties}"
