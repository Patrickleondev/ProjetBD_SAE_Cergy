# 10. Le dictionnaire de données avec les types SQL (demande de l'encadrant)

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

## Ce que le prof demande, et pourquoi il a raison

Notre dictionnaire utilisait les types « de conception » : A (alphabétique), AN
(alphanumérique), N (numérique) avec une taille. l'encadrant demande les **types SQL**
(`VARCHAR`, `CHAR`, `NUMERIC`, `INTEGER`, `DATE`...).

**Pourquoi c'est plus juste pour notre projet :** le dictionnaire doit décrire **les
données telles qu'elles seront stockées**. Avec A/AN/N, il disait autre chose que le
script SQL, et c'est ce qu'il a remarqué :

| Donnée | Ancien dictionnaire | Ce que fait vraiment le SQL | Problème |
|---|---|---|---|
| `id_client` | N, taille 6 | `SERIAL` = `INTEGER` auto-incrémenté | « 6 chiffres » n'existe nulle part dans la base : un INTEGER va jusqu'à 2 147 483 647 |
| `nombre` | N, taille 1 | `SMALLINT` | même chose |
| `prix_kwh` | N, taille « 5,4 » | `NUMERIC(5,4)` | « 5,4 » se lit « 5 virgule 4 » au lieu de « 5 chiffres dont 4 décimales » |
| `nom` | A, taille 50 | `VARCHAR(50)` | « A » laisse croire « que des lettres », alors que l'apostrophe et le tiret sont admis |

Avec les types SQL, **une ligne du dictionnaire = une colonne du CREATE TABLE**. Le
correcteur peut vérifier l'un avec l'autre.

## Comprendre chaque type utilisé

| Type SQL | Ce qu'il stocke | Quand l'utiliser | Exemple chez nous |
|---|---|---|---|
| `VARCHAR(n)` | un texte de **longueur variable**, **n caractères au plus** | les textes dont la longueur change | `nom VARCHAR(50)` : « Li » ou « Martin-Dupont » |
| `CHAR(n)` | un texte de **exactement n caractères** | les codes de longueur fixe | `telephone CHAR(10)`, `siret CHAR(14)`, `code_postal CHAR(5)` |
| `INTEGER` | un entier (jusqu'à environ 2 milliards) | les compteurs | `duree_minutes` |
| `SMALLINT` | un petit entier (jusqu'à 32 767) | les petites quantités | `nombre` (1 à 4), `nb_vehicules` |
| `SERIAL` | un `INTEGER` que la base **numérote toute seule** (1, 2, 3...) | les identifiants | `id_client`, `id_borne`... |
| `NUMERIC(p,s)` | un nombre décimal **exact** : **p chiffres en tout, dont s après la virgule** | les montants, les mesures | `prix_kwh NUMERIC(5,4)` : jusqu'à 9,9999 € |
| `DATE` | une date (AAAA-MM-JJ) | les jours | `date_inscription` |
| `TIMESTAMP(0)` | date + heure, **à la seconde** (le 0 = pas de fraction de seconde) | les instants | `debut`, `fin` d'une charge |
| `BOOLEAN` | vrai / faux | les oui / non | `actif` |
| `BYTEA` | des octets (fichier binaire) | les images | `photo` |
| `ENUM` (type créé par nous) | **une valeur parmi une liste fixée** | les états, les catégories | `etat` : libre, occupee, hors_service, maintenance |

**Comment lire `NUMERIC(p,s)` sans se tromper :**
- `NUMERIC(5,4)` : 5 chiffres **au total**, dont 4 après la virgule, donc 1 seul avant :
  de 0,0000 à 9,9999. Suffisant pour un prix au kWh (0,3500 €), au dixième de centime.
- `NUMERIC(6,3)` : 3 chiffres avant, 3 après → jusqu'à 999,999 kWh (la règle limite à 300).
- `NUMERIC(4,1)` : jusqu'à 999,9 kW (borne DC jusqu'à 350, prise jusqu'à 400).
- `NUMERIC(7,2)` : jusqu'à 99 999,99 € (le montant calculé, arrondi au centime).

**Pourquoi `NUMERIC` et pas `FLOAT` pour l'argent :** `FLOAT` est approximatif
(0,1 + 0,2 n'y fait pas exactement 0,3). Pour de l'argent, on veut des calculs exacts :
c'est aussi pour ça que le serveur Java utilise `BigDecimal` et pas `double`.

**`VARCHAR` ou `CHAR` ?** Si la donnée a **toujours** la même longueur (téléphone :
10 chiffres, SIRET : 14), `CHAR(n)`. Sinon `VARCHAR(n)`. Un point qu'on pourrait corriger :
`code_borne` a toujours 10 caractères (`CGY-01-B02`) mais est en `VARCHAR(10)` ;
`CHAR(10)` serait plus cohérent.

**La taille n'est pas « au hasard » :** `email VARCHAR(120)` parce qu'une adresse longue
dépasse 50 caractères ; `description VARCHAR(500)` pour quelques phrases. Si on te demande
« pourquoi 50 ? » : « assez pour les noms réels les plus longs, sans réserver d'espace
inutile ».

## Le dictionnaire complet (55 données, version 4, types vérifiés contre `sql/01_creation.sql`)

Nature : E élémentaire (stockée), C calculée (non stockée : son type est celui de la
valeur calculée). Les clés étrangères ne figurent pas dans le dictionnaire : ce sont des
références vers ces mêmes identifiants, elles apparaissent au MLD.

| Code | Désignation | Type SQL | Nature | Règle de gestion | Exemple |
|---|---|---|---|---|---|
| `id_client` | Numéro du client | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 1 |
| `nom` | Nom de famille du client (ou du contact pour une entreprise) | VARCHAR(50) | E | obligatoire ; 2 caractères minimum ; majuscule initiale ; lettres, espace, apostrophe, tiret | N'Diaye |
| `prenom` | Prénom | VARCHAR(50) | E | obligatoire ; majuscule initiale puis minuscules | Claire |
| `email` | Adresse électronique | VARCHAR(120) | E | obligatoire ; unique ; en minuscules ; forme nom@domaine.ext | claire.martin@exemple.fr |
| `telephone` | Numéro de téléphone | CHAR(10) | E | obligatoire ; 10 chiffres sans espace ; commence par 01 à 07 ou 09 | 0612345678 |
| `date_inscription` | Date d'ouverture du compte | DATE | E | entre le 01/01/2024 et aujourd'hui | 2024-03-14 |
| `civilite` | Civilité | VARCHAR(3) | E | M ou Mme ; particuliers seulement | Mme |
| `date_naissance` | Date de naissance | DATE | E | client majeur : entre 110 ans et 18 ans avant aujourd'hui ; particuliers seulement | 1988-07-22 |
| `age` | Âge du client | INTEGER (valeur calculée) | C | calculé à partir de date_naissance ; non stocké | 38 |
| `raison_sociale` | Dénomination légale de l'entreprise | VARCHAR(120) | E | obligatoire ; 2 à 120 caractères ; entreprises seulement | Transports Durand SAS |
| `siret` | Numéro SIRET | CHAR(14) | E | obligatoire ; unique ; exactement 14 chiffres | 81234567800019 |
| `nb_vehicules` | Nombre de véhicules de la flotte | SMALLINT | E | entre 1 et 5 000 ; entreprises seulement | 24 |
| `id_tarif` | Numéro du tarif | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 2 |
| `libelle_tarif` | Nom commercial du tarif | VARCHAR(30) | E | obligatoire ; unique ; majuscule initiale | Abonné |
| `prix_kwh` | Prix du kilowattheure (€) | NUMERIC(5,4) | E | strictement positif ; 2 € au plus | 0,3500 |
| `prix_minute` | Prix de la minute de charge (€) | NUMERIC(5,4) | E | entre 0 et 1 € ; 0 par défaut | 0,0500 |
| `date_debut` | Début de validité du tarif | DATE | E | obligatoire ; à partir du 01/01/2024 | 2024-01-01 |
| `date_fin` | Fin de validité du tarif | DATE | E | vide si en vigueur ; après date_debut | 2024-12-31 |
| `id_badge` | Numéro du badge, envoyé par la borne | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 1 |
| `code_rfid` | Code inscrit dans la puce du badge | CHAR(16) | E | obligatoire ; unique ; 16 caractères hexadécimaux en majuscules | 04A3F19C22B18E70 |
| `date_activation` | Date de mise en service du badge | DATE | E | entre le 01/01/2024 et aujourd'hui | 2024-03-14 |
| `actif` | Badge utilisable | BOOLEAN | E | vrai par défaut ; faux = badge bloqué | vrai |
| `id_station` | Numéro de la station | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 1 |
| `nom_station` | Nom d'exploitation de la station | VARCHAR(60) | E | obligatoire ; unique ; 3 à 60 caractères | Cergy Préfecture |
| `adresse` | Numéro et nom de la voie | VARCHAR(120) | E | obligatoire ; commence par un numéro (bis ou ter admis) | 12 place des Colonnes |
| `code_postal` | Code postal | CHAR(5) | E | 5 chiffres ; département d'Île-de-France (75, 77, 78, 91 à 95) | 95000 |
| `ville` | Commune | VARCHAR(60) | E | obligatoire ; majuscule initiale | Cergy |
| `photo` | Photographie de la station | BYTEA | E | facultative ; 2 Mo au plus | (image) |
| `id_borne` | Numéro de la borne | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 2 |
| `code_borne` | Code annoncé par la borne au serveur | VARCHAR(10) | E | obligatoire ; unique ; forme VVV-NN-BNN | CGY-01-B02 |
| `type_courant` | Nature du courant délivré | ENUM type_courant (AC, DC) | E | AC ou DC | AC |
| `puissance_max_kw` | Puissance maximale de la borne (kW) | NUMERIC(4,1) | E | AC : de 3,7 à 22 ; DC : de 50 à 350 | 22,0 |
| `etat` | État courant de la borne | ENUM etat_borne (libre, occupee, hors_service, maintenance) | E | libre, occupee, hors_service ou maintenance ; libre par défaut | libre |
| `date_mise_service` | Date de mise en service de la borne | DATE | E | entre le 01/01/2024 et aujourd'hui | 2024-01-15 |
| `derniere_communication` | Date et heure du dernier message reçu | TIMESTAMP(0) | E | pas dans le futur ; pas avant la mise en service | 2026-10-08 07:58:05 |
| `id_connecteur` | Numéro du type de prise | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 1 |
| `libelle_connecteur` | Nom courant du type de prise | VARCHAR(30) | E | obligatoire ; unique | Type 2 |
| `norme` | Norme de la prise | VARCHAR(20) | E | obligatoire ; forme « IEC nnnnn-n » | IEC 62196-2 |
| `puissance_prise_kw` | Puissance admissible par la prise (kW) | NUMERIC(4,1) | E | de 3,7 à 400 | 43,0 |
| `nombre` | Nombre de prises d'un type sur une borne | SMALLINT | E | de 1 à 4 | 2 |
| `id_session` | Numéro de la charge, renvoyé à la borne | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 18 |
| `debut` | Date et heure de début de charge | TIMESTAMP(0) | E | obligatoire ; pas avant 2024 ; pas dans le futur | 2026-09-01 08:12:00 |
| `fin` | Date et heure de fin de charge | TIMESTAMP(0) | E | vide si en cours ; après le début ; moins de 24 h après | 2026-09-01 10:05:00 |
| `energie_kwh` | Énergie délivrée (kWh) | NUMERIC(6,3) | E | de 0 à 300 ; obligatoire quand la charge est terminée | 31,200 |
| `statut` | Déroulement de la charge | ENUM statut_session (en_cours, terminee, interrompue) | E | en_cours (si et seulement si pas de fin), terminee ou interrompue ; une seule charge en cours par borne et par badge | terminee |
| `date_paiement` | Date et heure du paiement de la charge | TIMESTAMP(0) | E | vide tant que la charge n'est pas payée ; seulement pour une charge terminée ; après la fin | 2026-09-01 10:06:00 |
| `ref_paiement` | Référence du paiement | VARCHAR(20) | E | CB-… (carte, particulier) ou FAC-… (facture mensuelle, entreprise) | CB-00000001 |
| `duree_minutes` | Durée de la charge | INTEGER (valeur calculée) | C | fin moins début ; non stockée | 113 |
| `montant` | Prix de la charge (€) | NUMERIC(7,2) (valeur calculée) | C | énergie × prix_kwh + durée × prix_minute ; non stocké | 6,46 |
| `id_incident` | Numéro de l'incident | SERIAL (INTEGER auto-incrémenté) | E | identifiant, attribué par le système | 3 |
| `date_signalement` | Date et heure du signalement | TIMESTAMP(0) | E | obligatoire ; pas avant 2024 ; pas dans le futur | 2026-10-06 18:35:00 |
| `gravite` | Niveau de gravité | ENUM gravite_incident (mineur, majeur, critique) | E | mineur, majeur ou critique | critique |
| `description` | Description de la panne | VARCHAR(500) | E | obligatoire ; 10 caractères au moins | Câble CCS sectionné |
| `date_resolution` | Date et heure de résolution | TIMESTAMP(0) | E | vide si l'incident est ouvert ; après le signalement ; pas dans le futur | 2026-09-09 14:30:00 |
| `resolu` | Incident clos | BOOLEAN (valeur calculée) | C | vrai si date_resolution est renseignée ; non stocké | faux |

**Vérification faite :** pour chacune des données stockées (nature E), le type de la
colonne « Type SQL » est **exactement** celui du `CREATE TABLE` (comparaison automatique
avec `sql/01_creation.sql`, 0 écart).
