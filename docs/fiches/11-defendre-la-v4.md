# 11 : Défendre le rapport v4 (termes, héritage, mise en page)

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

Cette fiche sert à **comprendre** ce qui est écrit dans le rapport v4 et à **répondre** si l'encadrant
pose une question. Lis une partie, ferme la fiche, et réexplique-la à voix haute.

---

## 1. Ce qui a changé dans cette version du rapport

| Avant | Maintenant | Pourquoi |
|---|---|---|
| Architecture avec routeur, pare-feu, adresses IP (192.168.x.x) et ports (5000, 5432, 443) | **3 postes de la salle de TP** + la base de l'intranet, sans IP ni port | l'encadrant veut l'architecture **globale**, pas le plan d'adressage. Les adresses et les ports sont fixés par le labo (voir `deploiement_lab/LISEZ_MOI_LAB.md`) |
| Figure « architecture physique : segments réseau et adressage » | supprimée | même raison |
| Scénarios avec prénoms inventés, heures, « Transports Durand », « liaison 4G » | scénarios courts : un client, un badge, une borne | moins de mise en scène, on voit mieux le protocole |
| Pied de page : le numéro au milieu sur les pages en paysage | numéro collé à droite sur toutes les pages | voir §4 |
| En-tête : « version 3.0 » barré + « version 4.0 » | « version 4.0 » écrit directement | voir §4 |

### Les 3 postes, à savoir dire en une phrase chacun

| Poste | Ce qui tourne dessus | Son rôle |
|---|---|---|
| **Poste client** | `borne.py` (Python) | il **joue le rôle d'une borne** : HELLO, START, STOP, PAYE, BYE |
| **Poste serveur** | serveur Java (`ServeurBornes`) + site web PHP | il reçoit les commandes, vérifie, et il est **le seul à parler à la base** |
| **Poste de test** | `netcat` + un navigateur | on tape des commandes **à la main**, y compris des **fausses** (ligne trop longue, badge inconnu…) pour vérifier que le serveur répond par une erreur sans planter |
| *(pas un poste de l'équipe)* | base PostgreSQL de l'intranet | fournie par l'université, rien à installer |

**Question possible : « Pourquoi un poste de test, alors que le client existe déjà ? »**
Parce que `borne.py` envoie toujours des commandes **correctes**. Pour tester la robustesse, il faut
envoyer ce qu'une vraie borne n'enverrait jamais. Avec netcat, on tape ce qu'on veut.

> À vérifier avec ton binôme : le site web PHP n'est pas encore écrit (semaines 43 à 45 du planning).
> Sur le schéma, il est placé sur le poste serveur. Si vous le mettez ailleurs, dis-le-moi et je change
> la figure.

---

## 2. Les termes du rapport, expliqués

### Côté métier (l'entreprise Voltoise)

| Terme | Ce que ça veut dire | Où dans le rapport |
|---|---|---|
| **SIRET** | numéro à **14 chiffres** qui identifie un établissement d'entreprise en France (9 chiffres SIREN de l'entreprise + 5 chiffres de l'établissement). Toute facture à une entreprise le mentionne | attribut `siret` d'ENTREPRISE : `CHAR(14)`, `UNIQUE`, `CHECK (siret ~ '^[0-9]{14}$')` |
| **Raison sociale** | le nom officiel de l'entreprise (ex. « Transports Durand SAS ») | attribut d'ENTREPRISE |
| **SAS** | « société par actions simplifiée », une forme d'entreprise. Ça fait juste partie du nom dans le jeu de données | jeu de données |
| **Flotte** / `nb_vehicules` | les véhicules d'une entreprise ; le tarif « Flotte Pro » est réservé aux entreprises | ENTREPRISE, TARIF |
| **CB** | carte bancaire : le particulier paie **tout de suite**, à la borne | réponse `OK … CB` du STOP |
| **Facture mensuelle** | l'entreprise ne paie pas à chaque charge ; on additionne ses charges du mois et on lui envoie **une** facture | réponse `OK … FACTURE`, requête Q11 |
| **kWh** | kilowattheure, l'énergie mise dans la batterie. Prix = kWh × prix_kwh + minutes × prix_minute | STOP, montant |
| **Badge RFID** | carte sans contact du client. `actif = faux` : badge bloqué (impayé, perte) | BADGE |
| **Session de charge** | une charge, du START au STOP | SESSION_CHARGE |

### Côté base de données

| Terme | En une phrase |
|---|---|
| **Héritage (est-un)** | CLIENT est la table « générale » ; PARTICULIER et ENTREPRISE sont des **sous-types** qui ajoutent leurs attributs. Leur clé primaire `id_client` est **aussi** une clé étrangère vers CLIENT |
| **Partition XT** | notation Merise : **X**, exclusion (on ne peut pas être les deux) et **T**, totalité (on est forcément l'un des deux). Un client est **exactement un** des deux |
| **Transaction** | plusieurs ordres SQL faits **tous ou aucun** (`COMMIT` valide, `ROLLBACK` annule). START crée la session **et** met la borne « occupée » : jamais l'un sans l'autre |
| **Verrou `FOR UPDATE`** | `SELECT … FOR UPDATE` bloque la ligne de la borne jusqu'au COMMIT : deux badges qui arrivent en même temps ne peuvent pas prendre la même borne |
| **Requête préparée** | le SQL est écrit avec des `?`, et les valeurs sont envoyées à part : un badge `1; DROP TABLE client` reste une valeur, pas du code. C'est la protection contre l'**injection SQL** |
| **LEFT JOIN** | jointure qui garde la ligne de gauche même sans correspondance (sert à tester « est-il dans PARTICULIER ? dans ENTREPRISE ? ») |
| **NOT EXISTS / IN** | différence et semi-jointure de l'algèbre (Q5, Q8, Q9) |
| **CHECK, ENUM, SERIAL** | règle vérifiée par la base ; type à valeurs fixes ; entier numéroté automatiquement |

### Côté réseau

| Terme | En une phrase |
|---|---|
| **TCP** | le transport fiable : ce qui est envoyé arrive, dans l'ordre |
| **Protocole Voltoise** | **notre** protocole : une commande texte par ligne (`START 1`), le serveur répond `OK …` ou `ERR code message` |
| **Codes d'erreur** | repris de HTTP pour être lisibles : 400 mal écrit, 401 borne inconnue, 403 interdit (badge bloqué, pas identifiée), 404 introuvable, 409 conflit avec l'état (borne occupée, charge en cours), 413 trop long, 500 erreur du serveur, 503 base indisponible |
| **Thread** | le serveur traite chaque borne connectée dans un fil d'exécution à part : 10 bornes = 10 dialogues en parallèle (50 au plus) |
| **JDBC / PDO** | la bibliothèque qui permet à Java / PHP d'envoyer du SQL à PostgreSQL |
| **netcat (`nc`)** | outil qui ouvre une connexion TCP et envoie ce que tu tapes : un « client à la main » |

---

## 3. L'héritage PARTICULIER / ENTREPRISE : est-il utilisé ? Comment le défendre ?

La remarque de la v3 était : « héritage jamais utilisé ». La bonne réponse n'est pas « il est dans le
MCD ». Il faut montrer **où il change le comportement du système**. Il y a quatre preuves, dans le code
qui tourne :

| # | Où | Ce que fait l'héritage |
|---|---|---|
| 1 | `AccesBase.java`, méthode `modePaiement()` | à la fin d'une charge, le serveur cherche le titulaire du badge dans PARTICULIER ou ENTREPRISE (deux LEFT JOIN). Particulier → `CB`, entreprise → `FACTURE`. **Le même STOP donne deux réponses différentes** (scénarios 3 et 4) |
| 2 | `AccesBase.java`, méthode `payerCB()` | `PAYE` est **refusé** (ERR 409) si le client est une entreprise : elle ne paie pas à la borne |
| 3 | requête **Q11** | la facture du mois ne se calcule **que** sur ENTREPRISE |
| 4 | requêtes **Q8 et Q9** | Q8 part d'ENTREPRISE (flotte, badges bloqués), Q9 part de PARTICULIER (badges jamais utilisés) |

Et, en plus, des **attributs propres** : civilité et date de naissance n'ont de sens que pour une
personne ; raison sociale, SIRET et nombre de véhicules que pour une entreprise.

### Les questions probables, et comment y répondre

**« Pourquoi pas une seule table CLIENT avec une colonne type_client ? »**
Il faudrait mettre dans CLIENT les colonnes des deux : un particulier aurait `siret` vide, une entreprise
`date_naissance` vide. On ne pourrait plus écrire `siret NOT NULL UNIQUE`, car la contrainte deviendrait
« obligatoire seulement si c'est une entreprise ». Avec deux tables, chaque contrainte est simple. C'est
aussi pour ça qu'en v4 on a **retiré** `type_client` : le type se lit dans la table où se trouve le
client, le stocker deux fois était une redondance.

**« Pourquoi pas deux tables séparées, sans CLIENT ? »**
BADGE, TARIF (par SOUSCRIRE) et COMPTE_WEB concernent **tout** client. Sans CLIENT, BADGE aurait besoin
de deux clés étrangères (une vers chaque table), dont une toujours vide. Avec CLIENT, il y a **une**
clé étrangère `#id_client`.

**« Qu'est-ce qui empêche un client d'être à la fois particulier et entreprise ? »**
Réponse honnête : **la base ne l'empêche pas toute seule**. Les clés étrangères garantissent seulement
qu'un sous-type pointe vers un client existant. L'exclusion est une **règle de gestion**, respectée à
l'insertion. Pour la faire vérifier par la base, il faudrait un **trigger** : avant d'insérer dans
ENTREPRISE, vérifier que l'id n'est pas dans PARTICULIER, et inversement. Dire « on le sait, et voici
comment on le corrigerait » vaut mieux que l'inventer. (Et `modePaiement()` a un cas prévu : un client
sans sous-type paie par CB, par prudence.)

**« Pourquoi 0,1 côté CLIENT et 1,1 côté sous-type ? »**
Un client est **au plus une fois** particulier (0,1 : il peut aussi être entreprise à la place). Une
ligne de PARTICULIER correspond à **exactement un** client (1,1). D'où, au MLD, `#id_client` à la fois
clé primaire et clé étrangère (fiche BD 28, partie E8).

---

## 4. « Pourquoi les pages ne se ressemblent pas ? »

Trois raisons. Deux sont voulues, une était une erreur, maintenant corrigée.

| Ce que tu vois | Cause | Normal ? |
|---|---|---|
| Des pages **larges** (paysage) et des pages **étroites** (portrait) | le dictionnaire (§4) a 6 colonnes, le jeu de données et les annexes (§8 à §11) ont des tableaux jusqu'à 8 colonnes. En portrait il fallait du 7,5 pt, illisible (remarque du prof). Ces parties sont donc en paysage, le reste en portrait. **Le §3 est repassé en portrait**, car le nouveau schéma tient dans la largeur | oui, c'est voulu. À dire si on demande : « les tableaux larges sont en paysage pour rester en 10 pt » |
| Le numéro de page (18/65) **au milieu** sur les pages paysage | le pied de page utilisait une tabulation fixe à 16,5 cm, la largeur d'une page portrait | **c'était l'erreur.** Corrigé : la tabulation suit maintenant la marge droite, en portrait comme en paysage |
| Du texte **rouge barré**, des traits gris dans la marge, « version 3.0 » barré dans l'en-tête | le `.docx` est en **suivi des modifications** : le prof voit ce qui a changé depuis la v3. L'encadré « Suivi des modifications activé » vient de là | oui pour le `.docx`. L'en-tête n'est plus suivi. Pour lire la version finale : **Révision → Accepter → Accepter toutes les modifications** |

**Quel fichier rendre ?** Le **PDF** `Rapport_SAE_BD_reseau_v4.0.pdf` a toutes les modifications
acceptées : propre, sans rouge. Le `.docx` en suivi ne sert que si le sujet ou le prof demande de voir les
changements.

---

## 5. Entraîne-toi (réponds AVANT de regarder le corrigé)

1. Cite les 3 postes et ce que fait chacun, en une phrase.
2. Pourquoi la borne n'a-t-elle jamais le mot de passe de la base ?
3. Donne deux endroits du **code** où l'héritage change le comportement.
4. Pourquoi `siret` ne pourrait pas être `NOT NULL` dans une table CLIENT unique ?
5. Qu'est-ce qui empêche deux badges de démarrer sur la même borne au même instant ?
6. Que répond le serveur à `PAYE` pour une entreprise, et pourquoi ?

### Corrigé

1. Client = `borne.py`, joue la borne ; serveur = Java + site PHP, seul à parler à la base ; test =
   netcat et navigateur, envoie des commandes à la main (y compris fausses).
2. Une borne est sur un parking public : qui l'ouvre lirait les données de tous les clients. Elle envoie
   des commandes simples, le serveur vérifie et exécute le SQL.
3. `modePaiement()` (CB ou FACTURE selon le sous-type) et `payerCB()` (PAYE refusé pour une entreprise).
   Aussi acceptés : Q11, Q8, Q9.
4. Parce qu'un particulier n'a pas de SIRET : la colonne serait vide pour lui.
5. La transaction de START avec `SELECT … FOR UPDATE` sur la ligne de la borne : le second attend, puis
   voit « occupee » et reçoit ERR 409.
6. `ERR 409` : une entreprise ne paie pas à la borne, sa charge attend la facture du mois.
