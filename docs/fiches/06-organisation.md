# 6. Organisation, à deux

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

## D'abord, une question à poser en TD

Le sujet dit : « chaque équipe est constituée de 3 étudiants (ou éventuellement 2 selon
l'effectif des groupes de TD) », et ajoute une règle de composition, au moins un étudiant
extérieur n'ayant pas fait sa L2 à CYU, et au moins un qui l'a faite.

Nous sommes deux. **À faire valider par l'encadrant avant de se répartir le travail.** Si
l'équipe doit passer à trois, mieux vaut le savoir maintenant que fin octobre.

## Les échéances

| Semaines | Version | Ce qui est attendu | État |
|---|---|---|---|
| 37-38 | v#1 | contexte, planning, dictionnaire initial, ébauche des échanges | écrit, à rendre |
| 39-40 | v#2 | dictionnaire complet avec exemples, MCD, MLD, 2 diagrammes applicatifs | en cours |
| 41-42 | v#3 | script DDL, 4 à 5 scénarios réseau, jeu de données | à venir |
| 43-45 | v#4 | requêtes DML, client et serveur réseau, connexions à la base | à venir |
| 46-47 | v#5 | tests d'intégration et de robustesse avec netcat | à venir |
| 48 | livraison | **dimanche 29 novembre 2026, 23h59** | |
| 50 | soutenance | 5 min et 5-6 slides pour la BD, 10 min à distance pour le réseau | |

Chaque rendu est une version incrémentale du **même** rapport. On n'écrit pas cinq
documents, on en enrichit un seul.

## Les livrables finaux, à ne pas oublier

- le rapport en `.odt` **et** `.pdf`
- le script SQL de création : DDL plus tous les INSERT du jeu de données
- un fichier unique avec les requêtes SELECT
- le code du site web
- le code du client et du serveur réseau
- un `readme.md` pour s'y retrouver dans les codes
- **deux vidéos de 3 minutes maximum**, une pour la BD, une pour le réseau
- la grille critériée du réseau

Les vidéos sont le piège classique : elles se préparent, et personne n'y pense avant le
dernier week-end.

## Répartition proposée

À discuter, mais il faut un découpage où chacun a un morceau entier plutôt que deux
personnes sur tout.

**Moi (<membre 1>)** : partie réseau. Le protocole, le serveur Java avec JDBC, le client
Python, les tests netcat. C'est là que je veux progresser, et ça recoupe les TP 3 et 4.

**Mon coéquipier** : partie web. Le site PHP, l'authentification, les deux situations
demandées par le sujet, une modification de la base et une extraction représentative.

**À deux, sans découper** : le MCD et le MLD, parce que tout le reste en dépend et qu'il
faut que les deux les maîtrisent pour la soutenance. Les 8 à 10 requêtes SELECT aussi, à se
partager mais à relire mutuellement.

**Le rapport** : chacun écrit la section qu'il a réalisée. Personne ne rédige le travail de
l'autre, sinon on ne sait plus répondre aux questions.

## Ce qui est déjà en place

Dans `rapport/` :

| Fichier | Contenu |
|---|---|
| `Rapport_SAE_BD_reseau_v1.0.docx` | contexte rédigé, choix techniques, dictionnaire complet, sommaire et numérotation automatiques |
| `donnees_projet.py` | le contexte et le dictionnaire, **source unique** |
| `generer_rapport.py` | reconstruit le Word à partir de `donnees_projet.py` |
| `generer_diagrammes.py` | reconstruit les trois `.drawio` |
| `architecture_reseau.drawio` | architecture physique et flux applicatifs |
| `echanges_reseau.drawio` | trois diagrammes de séquence |
| `planning_gantt.drawio` | le Gantt du projet |

Règle de travail : on modifie `donnees_projet.py` puis on relance le script. On ne modifie
pas le `.docx` tant qu'on n'a pas commencé à y écrire du texte libre. Le jour où on
commence à rédiger dedans, on arrête de relancer le script, sinon il écrase tout.

## Prochaines actions, dans l'ordre

1. Valider l'équipe à deux auprès de l'encadrant.
2. Rendre le v#1, même avec quelques jours de retard.
3. Saisir le MCD dans Looping, voir le fichier 05.
4. Générer le MLD, vérifier les clés étrangères et les deux tables de liaison.
5. Compléter les diagrammes applicatifs pour arriver à quatre ou cinq scénarios.
6. Suivre les TP 3 et 4 de réseau en prenant des notes orientées projet.
