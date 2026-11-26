# 5. Construire le MCD dans Looping, pas à pas

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

Looping est un outil Merise français et gratuit. On saisit le dictionnaire, on dessine le
MCD, et il **génère le MLD puis le script SQL tout seul**.

À ne pas remplacer par Lucidchart : Lucid dessine en notation anglo-saxonne « patte d'oie »,
pas en Merise, et son offre gratuite plafonne à 60 objets.

---

# PARTIE A — Réapprendre les associations

*Si tu ne fais que recopier le tableau de la partie C, tu auras un MCD juste et tu ne sauras
pas le défendre en soutenance. Cette partie est courte, lis-la en entier.*

## A.1 — Le réflexe à désapprendre

Quand tu penses *« la borne est dans une station »*, tu as envie d'écrire une colonne
`id_station` dans BORNE. **C'est interdit dans un MCD**, et c'est l'erreur n° 1.

```
❌  BORNE ( id_borne, id_station, code_borne, ... )      ← ça, c'est déjà du MLD

✅  STATION ──(1,n)── CONTENIR ──(1,1)── BORNE           ← ça, c'est du MCD
```

**Pourquoi c'est mieux, et pas juste une convention :** la colonne `id_station` dit
seulement *« il y a un lien »*. Le MCD, lui, dit **trois choses de plus** :

```
le NOM du lien         CONTENIR — donc on sait ce que le lien signifie
le MINIMUM             une borne a-t-elle forcément une station ? oui → le 1 de (1,1)
le MAXIMUM             une station a-t-elle plusieurs bornes ? oui → le n de (1,n)
```

Et c'est **le maximum qui décide** de ce que Looping fabriquera au MLD. Une colonne, ou une
table entière. Tu ne le choisis pas : la cardinalité le détermine.

> **Retiens la phrase** : *dans un MCD, on ne relie pas les entités par des colonnes, on les
> relie par des liens nommés et comptés.*

## A.2 — Les deux questions, toujours dans le même sens

Une association a **deux couples de cardinalités**, un de chaque côté. Pour chacun, on pose
la même question :

```
« UNE occurrence de A est liée à COMBIEN d'occurrences de B ? »
     au minimum ___ , au maximum ___
```

**Et on écrit la réponse du côté de A.** C'est la convention Merise, et c'est là que tout le
monde se trompe : la cardinalité placée près de A **parle de A**, pas de B.

### Fais-le sur un exemple de ton projet

```
            STATION ──────── CONTENIR ──────── BORNE
```

**Question 1 — « UNE station contient COMBIEN de bornes ? »**
Au minimum : une station sans aucune borne, ça existe ? Non — une station **est** un
regroupement de bornes. → minimum **1**.
Au maximum : plusieurs, évidemment. → maximum **n**.
**On écrit `(1,n)` du côté STATION.**

**Question 2 — « UNE borne est contenue dans COMBIEN de stations ? »**
Au minimum : une borne sans station, ça n'a pas de sens, elle est physiquement quelque part.
→ minimum **1**.
Au maximum : une borne ne peut pas être dans deux stations à la fois. → maximum **1**.
**On écrit `(1,1)` du côté BORNE.**

```
STATION ──(1,n)── CONTENIR ──(1,1)── BORNE
```

> **Le minimum se discute, le maximum se déduit.** Le maximum est un fait sur le monde réel :
> une borne est à un seul endroit, point. Le minimum est une **décision de gestion** : est-ce
> qu'on accepte d'enregistrer une station vide, en attente d'installation ? Si oui, c'est
> `(0,n)`. **C'est cette décision que tu dois savoir justifier en soutenance**, pas le
> maximum.

## A.3 — Les trois formes, et ce que chacune devient

C'est le tableau à connaître. **Regarde les deux maximums, eux seuls comptent :**

| Les deux max     | Forme         | Ce que Looping fabrique au MLD                      |
| ---------------- | ------------- | --------------------------------------------------- |
| **1 et n** | **1-N** | une**clé étrangère**, du côté du `1`   |
| **n et n** | **N-N** | une**table de liaison** entière              |
| **1 et 1** | **1-1** | une clé étrangère, ou une fusion des deux tables |

### La règle qui décide où tombe la clé

> **La clé étrangère va du côté du `(x,1)`.**

Mnémonique : *le côté qui n'en a qu'un porte la clé de l'autre.* Une borne n'a qu'une
station → c'est **BORNE** qui reçoit `id_station`. Pas l'inverse : STATION ne peut pas porter
`id_borne`, elle en a plusieurs et une colonne ne contient qu'une valeur.

> **Ce raisonnement est aussi ta vérification.** Après avoir généré le MLD, regarde où sont
> tombées les clés. Si `id_borne` s'est retrouvée dans STATION, tu as inversé tes
> cardinalités. **C'est le contrôle le plus rapide de tout le projet.**

### Le cas N-N

Deux `n` : ni l'un ni l'autre ne peut porter une colonne. Looping crée une **troisième
table**, dont la clé est le **couple** des deux identifiants.

```
BORNE ──(1,n)── EQUIPER ──(0,n)── CONNECTEUR

devient   EQUIPER ( #id_borne , #id_connecteur , nombre )
                   └──── clé primaire composée ────┘
```

### Le cas 1-1

Rare, et souvent un signal d'alerte : si A et B sont en `(1,1)` des deux côtés, **c'est
peut-être une seule entité**. Dans ton projet il y en a un, `SE_CONNECTER`, et il est
justifié : un `(0,1)` d'un côté veut dire que tous les utilisateurs n'ont pas de compte web.
**Ce `0` est la raison d'être de la séparation** — sache le dire.

## A.4 — Association porteuse : le test en une question

Une association peut porter ses propres propriétés. Pour savoir si une information va sur
l'association ou dans une entité, une seule question :

> **Cette information appartient-elle à A ? à B ? ou au COUPLE (A, B) ?**

**Exemple `nombre` de prises :**

```
Est-ce une propriété de la BORNE ?       non — ça dépend de quel connecteur
Est-ce une propriété du CONNECTEUR ?     non — ça dépend de quelle borne
Est-ce une propriété du COUPLE ?         OUI — « cette borne-là a 2 prises de ce type-là »
                                         → sur l'association EQUIPER
```

**Même test pour `date_intervention` :** ce n'est ni une propriété du technicien, ni de
l'incident, mais du fait que *ce technicien-là est intervenu sur cet incident-là*. → sur
INTERVENIR.

> **En pratique, on ne porte des attributs que sur une association N-N.** Sur une 1-N, toute
> propriété de l'association peut être déplacée dans l'entité du côté `(1,1)` — et Looping le
> fera. Si tu te retrouves à vouloir porter une propriété sur une 1-N, **relis tes
> cardinalités** : il y a de bonnes chances que ce soit en réalité du N-N.

## A.5 — Association ou entité ?

Parfois l'association mérite de devenir une entité à part entière. Le critère :

> **Si le lien a besoin de son propre identifiant, ou s'il est lui-même relié à autre chose,
> ce n'est plus une association : c'est une entité.**

C'est le cas de **ABONNEMENT** dans ton projet. On aurait pu écrire
`UTILISATEUR ──(0,n)── EST_ABONNE ──(0,n)── TARIF` avec `date_debut` et `date_fin` portées.
Mais un abonnement a une existence propre, on veut pouvoir l'identifier, le résilier, le
renouveler. **Donc on en fait une entité, reliée par deux associations 1-N.** C'est un choix
de modélisation, et c'est exactement le genre de décision à expliquer dans le rapport.

---

# PARTIE B — Le piège du dictionnaire

> ⚠ **`rapport/donnees_projet.py` est écrit au format MLD, pas MCD.**

Il contient déjà les **clés étrangères** (`id_utilisateur` dans VEHICULE, `id_station` dans
BORNE…) parce qu'il décrit les tables finales. **Si tu le recopies tel quel dans Looping, tu
crées le doublon que le professeur cherche en priorité.**

```
Dans Looping, tu saisis :   l'identifiant + les propriétés « vraies »
Tu NE saisis PAS :          toute ligne marquée CE (clé étrangère)
```

La partie C ci-dessous te donne, pour chaque entité, **exactement ce qu'il faut taper**, les
clés étrangères déjà retirées. Utilise-la plutôt que le dictionnaire.

---

# PARTIE C — La saisie dans Looping

## Étape 1 — Le dictionnaire des données

Menu **Dictionnaire des données**. Saisis les propriétés **d'une entité à la fois**, crée
l'entité, puis passe à la suivante. Tout saisir d'un coup puis chercher dans une liste de 86
lignes est pénible.

## Étape 1 bis — Le dialogue « Rubrique », champ par champ

**C'est ici que se passe 80 % du temps de saisie, et c'est ici qu'on se trompe.**

Ton dictionnaire donne des types **PostgreSQL** : `SERIAL`, `VARCHAR(50)`, `NUMERIC(5,2)`,
`TIMESTAMP`, `BYTEA`. Looping ne connaît aucun de ces mots. Il propose des cases à cocher.
Sans la table de correspondance ci-dessous, on choisit au hasard — et le script SQL généré
est faux.

### La fenêtre, de haut en bas

```
Nom                 le code du dictionnaire, tel quel : id_utilisateur
Nom logique         rempli tout seul, ne touche pas

Type                Texte      ▸ Caractères ASCII · Caractères Unicode · Binaire
                               ▸ Variable · Fixe · Volumineux   + Longueur
                    Numérique  ▸ Entier · Décimal · Réel · Monétaire · Compteur
                    Date/Heure ▸ Date · Heure · Date-Heure
                    Autre      ▸ Booléen · Xml · Géométrique · Géographique · Libre

Propriétés          ☐ Identifiant   ☐ NOT NULL   ☐ UNIQUE
Commentaire         ce que Looping ne sait pas exprimer
```

### La table de correspondance

| Ton dictionnaire             | Dans Looping                            | Réglage                            |
| ---------------------------- | --------------------------------------- | ----------------------------------- |
| `SERIAL` *(identifiant)* | Numérique ▸**Compteur**         | cocher**Identifiant**         |
| `INTEGER`                  | Numérique ▸**Entier**           | —                                  |
| `SMALLINT`                 | Numérique ▸**Entier**           | —                                  |
| `VARCHAR(n)`               | Texte ▸ ASCII ▸**Variable**     | Longueur =`n`                     |
| `CHAR(n)`                  | Texte ▸ ASCII ▸**Fixe**         | Longueur =`n`                     |
| `TEXT`                     | Texte ▸ ASCII ▸**Volumineux**   | pas de longueur                     |
| `NUMERIC(p,s)`             | Numérique ▸**Décimal**         | Longueur =`p`, Décimales = `s` |
| `DATE`                     | Date/Heure ▸**Date**             | —                                  |
| `TIMESTAMP`                | Date/Heure ▸**Date-Heure**       | —                                  |
| `BOOLEAN`                  | Autre ▸**Booléen**              | —                                  |
| `BYTEA`                    | Texte ▸**Binaire** ▸ Volumineux | —                                  |
| `xxx (ENUM)`               | Texte ▸ ASCII ▸**Variable**     | voir plus bas                       |

> **Si ta version de Looping n'affiche pas de champ « Décimales » sur Décimal**, mets la
> longueur seule et note la précision dans **Commentaire** :
> `NUMERIC(5,2)`. Tu corrigeras ces 9 colonnes dans le script SQL, comme les ENUM.

### Les trois erreurs de ta capture

Tu avais `id_utilisateur` réglé en **Caractères ASCII, Variable, Longueur 50**, avec
**NOT NULL** coché et **Identifiant** décoché. Les trois sont à corriger :

|   | Ce que tu avais          | Ce qu'il faut                   | Pourquoi                                                                                                                         |
| - | ------------------------ | ------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| 1 | Texte ASCII, longueur 50 | Numérique ▸**Compteur** | `SERIAL` est un entier auto-incrémenté. En texte, PostgreSQL générerait `VARCHAR(50)` et tes clés seraient des chaînes |
| 2 | Identifiant ☐           | Identifiant ☑                  | sans cette case, l'entité**n'a pas de clé primaire**. Le menu *Vérifier le modèle* le signalera                      |
| 3 | NOT NULL ☑              | inutile                         | un identifiant est non nul et unique**par définition**. Cocher NOT NULL en plus n'est pas faux, c'est juste redondant     |

### Pourquoi « Caractères ASCII » et jamais « Unicode »

- **ASCII** → Looping génère `VARCHAR`. C'est ce que PostgreSQL attend.
- **Unicode** → il génère `NVARCHAR`, qui est un type **SQL Server**. PostgreSQL ne le
  connaît pas et le script échouera.

Et tu ne perds rien : en PostgreSQL, un `VARCHAR` stocke de l'UTF-8. « Préfecture » et
« Lomé » y entrent sans problème. Le mot « ASCII » dans Looping désigne le type SQL généré,
pas le jeu de caractères accepté.

### La case « Identifiant », ce qu'elle fait vraiment

Cocher **Identifiant** :

- désigne la propriété comme **clé primaire** de l'entité ;
- la **souligne** dans le MCD — c'est la convention Merise, ton correcteur la cherche ;
- entraîne `NOT NULL` et `UNIQUE` à la génération.

Trois règles :

1. **Une seule propriété Identifiant par entité.** Tes 13 entités ont chacune la sienne.
2. **Jamais sur une clé étrangère.** De toute façon tu ne les saisis pas (voir plus bas).
3. **Les clés composées ne se font pas ici.** `EQUIPER` a pour clé `(id_borne, id_connecteur)` : cette clé naît de l'**association N-N**, pas de cette case. Tu ne
   saisis aucune des deux colonnes.

### Les six ENUM

Looping ne connaît pas les types énumérés. Pour `type_client`, `type_courant`,
`etat_borne`, `statut_session`, `gravite_incident`, `role_web` :

```
Type         Texte ▸ Caractères ASCII ▸ Variable
Longueur     la plus longue valeur, arrondie au-dessus
Commentaire  ENUM : libre, occupee, hors_service, maintenance
```

Longueurs à mettre :

| Propriété          | Longueur | Valeurs à noter en commentaire           |
| -------------------- | -------- | ----------------------------------------- |
| `type_client`      | 15       | particulier, entreprise                   |
| `type_courant`     | 2        | AC, DC                                    |
| `etat_borne`       | 15       | libre, occupee, hors_service, maintenance |
| `statut_session`   | 15       | en_cours, terminee, interrompue           |
| `gravite_incident` | 10       | mineur, majeur, critique                  |
| `role_web`         | 15       | client, gestionnaire                      |

**Le commentaire n'est pas décoratif** : c'est lui qui te dira quoi écrire quand tu
reprendras le script SQL à la main (Partie E), et il apparaît dans la documentation que
Looping exporte.

> **Rappel :** `type_client` et `type_courant` sont aussi les **discriminants** des deux
> héritages (Étape 3). Note-le dans le commentaire : `ENUM + DISCRIMINANT`.

### Ce que tu ne saisis JAMAIS

**Les 20 clés étrangères.** Elles n'existent pas dans un MCD : elles apparaissent toutes
seules quand Looping génère le MLD, à partir des cardinalités.

```
❌  VEHICULE ( id_vehicule, id_utilisateur, id_connecteur, immatriculation, ... )
✅  VEHICULE ( id_vehicule, immatriculation, modele, capacite_batterie_kwh )
        + association POSSEDER vers UTILISATEUR
        + association UTILISER vers CONNECTEUR
```

Si tu saisis `id_utilisateur` dans `VEHICULE` **et** que tu traces l'association, tu
obtiendras la colonne **deux fois** dans le MLD. C'est l'erreur que le correcteur cherche
en premier.

### L'ordre de saisie le plus rapide

Tu as **91 lignes** au dictionnaire, mais **70 à saisir** : 20 sont des clés étrangères, que
Looping crée seul, et `id_intervention` dépend de la décision D.1. Groupe-les par type de réglage, tu ne changeras de cases à cocher que 10 fois
au lieu de 71.

| Ordre | Type dans Looping                             | Combien                                          |
| ----- | --------------------------------------------- | ------------------------------------------------ |
| 1     | Numérique ▸**Compteur** + Identifiant | **13** — un par entité, fais-les d'abord |
| 2     | Texte ▸ ASCII ▸ Variable                    | **18** VARCHAR + 6 ENUM = **24**     |
| 3     | Numérique ▸ Décimal                        | **9**                                      |
| 4     | Date/Heure ▸ Date                            | **6**                                      |
| 5     | Date/Heure ▸ Date-Heure                      | **5**                                      |
| 6     | Numérique ▸ Entier                          | **4** SMALLINT                             |
| 7     | Texte ▸ ASCII ▸ Fixe                        | **3** CHAR                                 |
| 8     | Autre ▸ Booléen                             | **3**                                      |
| 9     | Texte ▸ Binaire ▸ Volumineux                | **2** BYTEA                                |
| 10    | Texte ▸ ASCII ▸ Volumineux                  | **1** TEXT                                 |

**Commence par les 13 Compteurs.** En quinze minutes tes 13 entités existent, toutes avec
une clé primaire, et *Vérifier le modèle* ne te signale plus rien. Le reste n'est que du
remplissage.

> **Un 14e Compteur, peut-être.** `id_intervention` n'apparaît dans cette liste que si tu
> tranches la question **D.1** en faisant d'INTERVENIR une **entité**. Tant que c'est une
> association N-N à clé composée, tu ne saisis ni `id_intervention` ni les deux clés : tu
> ne poses que ses deux propriétés portées, `date_intervention` et `duree_minutes`.
> **Lis D.1 avant de commencer**, pour ne pas avoir à revenir en arrière.

### Les longueurs des champs texte, à recopier

Pour ne pas retourner au dictionnaire à chaque ligne :

```
UTILISATEUR   nom 50 · prenom 50 · email 120 · telephone 20
ENTREPRISE    raison_sociale 120 · siret CHAR(14)
VEHICULE      immatriculation 10 · modele 60
BADGE         code_rfid CHAR(16)
STATION       nom 80 · adresse 150 · ville 80
BORNE         code_borne 12
CONNECTEUR    libelle 30 · norme 20
TARIF         libelle 40
FACTURE       numero 20
TECHNICIEN    nom 50 · habilitation 30
COMPTE_WEB    login 40 · hash_mdp CHAR(60)
INCIDENT      description  →  TEXT, donc Volumineux, sans longueur
```

**Les trois `CHAR` sont en Fixe, pas en Variable.** `siret`, `code_rfid` et `hash_mdp` ont
une longueur invariable : 14 chiffres, 16 caractères hexadécimaux, 60 octets de bcrypt.
Les mettre en Variable n'est pas une faute grave, mais le Fixe dit quelque chose de vrai
sur la donnée, et c'est le genre de détail qui se remarque.

### Les UNIQUE, à cocher pendant la saisie

Huit propriétés portent `U` au dictionnaire. Coche **UNIQUE** au moment où tu les saisis,
tu éviteras de repasser dessus :

```
email · siret · immatriculation · code_rfid · code_borne · numero (facture) · libelle (connecteur) · login
```

---

## Étape 2 — Les 13 entités, propriété par propriété

**L'identifiant est en gras. Il se souligne dans le MCD.**

> Pour chaque ligne, traduis le type avec la **table de correspondance de l'étape 1 bis**.
> `SERIAL` → Compteur + Identifiant, `VARCHAR(n)` → ASCII Variable longueur n,
> `ENUM` → ASCII Variable + commentaire.

### Pôle clients

**UTILISATEUR** *(surtype)*

```
id_utilisateur      SERIAL         identifiant
nom                 VARCHAR(50)    non nul
prenom              VARCHAR(50)
email               VARCHAR(120)   unique, non nul
telephone           VARCHAR(20)
date_inscription    DATE           non nul
type_client         ENUM           non nul — DISCRIMINANT
```

**VEHICULE**

```
id_vehicule            SERIAL          identifiant
immatriculation        VARCHAR(10)     unique, non nul
modele                 VARCHAR(60)
capacite_batterie_kwh  NUMERIC(5,2)
```

*(ne saisis pas `id_utilisateur` ni `id_connecteur` : ce sont les deux associations)*

**BADGE**

```
id_badge     SERIAL       identifiant
code_rfid    CHAR(16)     unique, non nul
actif        BOOLEAN      non nul
```

*(pas de `id_utilisateur`)*

**ABONNEMENT**

```
id_abonnement   SERIAL   identifiant
date_debut      DATE     non nul
date_fin        DATE
```

*(pas de `id_utilisateur` ni `id_tarif`)*

**TARIF**

```
id_tarif      SERIAL          identifiant
libelle       VARCHAR(40)     non nul
prix_kwh      NUMERIC(6,4)    non nul
prix_minute   NUMERIC(6,4)
date_debut    DATE            non nul
```

**FACTURE**

```
id_facture      SERIAL          identifiant
numero          VARCHAR(20)     unique, non nul
date_emission   DATE            non nul
montant_ttc     NUMERIC(9,2)    non nul
payee           BOOLEAN         non nul
```

*(pas de `id_utilisateur`)*

**COMPTE_WEB**

```
id_compte   SERIAL        identifiant
login       VARCHAR(40)   unique, non nul
hash_mdp    CHAR(60)      non nul
role        ENUM          non nul
```

*(pas de `id_utilisateur`)*

### Pôle exploitation

**STATION**

```
id_station   SERIAL          identifiant
nom          VARCHAR(80)     non nul
adresse      VARCHAR(150)
ville        VARCHAR(80)     non nul
latitude     NUMERIC(9,6)
longitude    NUMERIC(9,6)
photo        BYTEA
```

**BORNE** *(surtype)*

```
id_borne                 SERIAL          identifiant
code_borne               VARCHAR(12)     unique, non nul
puissance_max_kw         NUMERIC(5,2)    non nul
etat                     ENUM            non nul
type_courant             ENUM            non nul — DISCRIMINANT
derniere_communication   TIMESTAMP
qr_code                  BYTEA
```

*(pas de `id_station`)*

**CONNECTEUR**

```
id_connecteur      SERIAL          identifiant
libelle            VARCHAR(30)     unique, non nul
norme              VARCHAR(20)
puissance_max_kw   NUMERIC(5,2)
```

**SESSION_CHARGE**

```
id_session     SERIAL          identifiant
debut          TIMESTAMP       non nul
fin            TIMESTAMP
energie_kwh    NUMERIC(7,3)
statut         ENUM            non nul
```

*(pas de `id_borne`, `id_badge`, `id_facture` : trois associations)*

**INCIDENT**

```
id_incident        SERIAL       identifiant
date_signalement   TIMESTAMP    non nul
gravite            ENUM         non nul
description        TEXT
resolu             BOOLEAN      non nul
```

*(pas de `id_borne`)*

**TECHNICIEN**

```
id_technicien   SERIAL         identifiant
nom             VARCHAR(50)    non nul
habilitation    VARCHAR(30)
```

## Étape 3 — Les deux héritages

Looping a un outil d'héritage : un triangle reliant un surtype à ses sous-types.

```
        UTILISATEUR                          BORNE
             △                                 △
      ┌──────┴──────┐                   ┌──────┴──────┐
 PARTICULIER   ENTREPRISE          BORNE_AC      BORNE_DC
 date_naissance  raison_sociale     nb_phases    tension_max_v
                 siret (unique)     SMALLINT     SMALLINT
```

**Les sous-types ne portent QUE leurs propriétés spécifiques.** Ils héritent de
l'identifiant et de tout le reste — ne les resaisis pas.

**La contrainte est `XT` dans les deux cas :**

- **T** pour *totalité* : un utilisateur est forcément particulier **ou** entreprise, il n'y
  a pas de troisième cas ;
- **X** pour *exclusion* : il ne peut pas être les deux à la fois.

> Looping te demandera cette contrainte. Les quatre possibilités sont **X** (exclusion
> seule), **T** (totalité seule), **XT** (les deux) et rien du tout. **Sache justifier ton
> XT** — c'est une question de soutenance facile à poser.

Les propriétés `type_client` et `type_courant` sont les **discriminants** : elles disent de
quel sous-type il s'agit, et **elles restent dans le surtype**.

## Étape 4 — Les 14 associations

**Crée-les une par une, et pour chacune, pose les deux questions à voix haute avant de
cliquer.** Le tableau donne la réponse ; le raisonnement, c'est toi.

| Association          | Entité A   | Card. A | Entité B      | Card. B | Porte                                    |
| -------------------- | ----------- | ------- | -------------- | ------- | ---------------------------------------- |
| POSSEDER             | UTILISATEUR | (1,n)   | VEHICULE       | (1,1)   |                                          |
| DETENIR              | UTILISATEUR | (1,n)   | BADGE          | (1,1)   |                                          |
| SE_BRANCHER          | CONNECTEUR  | (0,n)   | VEHICULE       | (1,1)   |                                          |
| CONTENIR             | STATION     | (1,n)   | BORNE          | (1,1)   |                                          |
| **EQUIPER**    | BORNE       | (1,n)   | CONNECTEUR     | (0,n)   | `nombre`                               |
| DEMARRER             | BADGE       | (0,n)   | SESSION_CHARGE | (1,1)   |                                          |
| ACCUEILLIR           | BORNE       | (0,n)   | SESSION_CHARGE | (1,1)   |                                          |
| REGROUPER            | FACTURE     | (1,n)   | SESSION_CHARGE | (0,1)   |                                          |
| EDITER               | UTILISATEUR | (0,n)   | FACTURE        | (1,1)   |                                          |
| SOUSCRIRE            | UTILISATEUR | (0,n)   | ABONNEMENT     | (1,1)   |                                          |
| APPLIQUER            | TARIF       | (0,n)   | ABONNEMENT     | (1,1)   |                                          |
| SIGNALER             | BORNE       | (0,n)   | INCIDENT       | (1,1)   |                                          |
| **INTERVENIR** | TECHNICIEN  | (0,n)   | INCIDENT       | (0,n)   | `date_intervention`, `duree_minutes` |
| SE_CONNECTER         | UTILISATEUR | (0,1)   | COMPTE_WEB     | (1,1)   |                                          |

### Les cinq qui méritent qu'on s'arrête

**POSSEDER `(1,n)` / `(1,1)`** — *« un utilisateur possède combien de véhicules ? »* au moins
un, sinon pourquoi serait-il client ; plusieurs, possible. *« Un véhicule appartient à
combien d'utilisateurs ? »* exactement un.

> **Le `1` minimum côté utilisateur est discutable.** Un client peut s'inscrire avant
> d'enregistrer son véhicule. Si tu veux permettre ça, c'est `(0,n)`. **Choisis, et
> justifie** — les deux se défendent, l'absence de choix ne se défend pas.

**SE_BRANCHER `(0,n)` / `(1,1)`** — un véhicule a **un** type de prise ; un type de prise
équipe zéro ou beaucoup de véhicules. Le `0` couvre un connecteur normalisé qu'aucun véhicule
de la base n'utilise encore.

**EQUIPER `(1,n)` / `(0,n)` — N-N** — *« une borne porte combien de types de connecteurs ? »*
au moins un, souvent plusieurs. *« Un type équipe combien de bornes ? »* peut-être aucune,
sinon beaucoup. **Deux `n` : c'est bien du N-N**, et `nombre` se porte dessus.

**REGROUPER `(1,n)` / `(0,1)`** — attention, c'est la plus subtile. *« Une facture regroupe
combien de sessions ? »* au moins une, sinon elle n'existerait pas → `(1,n)`. *« Une session
appartient à combien de factures ? »* **zéro ou une** → `(0,1)`.

> **Le `0` est essentiel et il est le cœur du métier** : une session en cours, ou terminée
> mais pas encore facturée, n'a pas de facture. C'est pour ça que `id_facture` est la seule
> clé étrangère **nullable** du modèle. Sache l'expliquer.

**INTERVENIR `(0,n)` / `(0,n)` — N-N** — *« un technicien traite combien d'incidents ? »*
peut-être aucun s'il vient d'être embauché. *« Un incident mobilise combien de
techniciens ? »* aucun tant qu'il n'est pas pris en charge, sinon un ou plusieurs.

## Étape 5 — Vérifier, puis générer

1. **Menu Vérifier le modèle.** Il repère les entités sans identifiant et les associations
   mal formées. Corrige tout avant d'aller plus loin.
2. **Génère le MLD**, et fais les **trois contrôles** :
   ```
   ☐ chaque (1,1) a produit une clé étrangère, DU BON CÔTÉ
   ☐ chaque N-N a produit une table de liaison à clé composée
   ☐ tu retrouves 18 tables et ~86 attributs
   ```

   **Si une clé est du mauvais côté, la cardinalité est inversée.** C'est toujours ça.
3. **Génère le script SQL**, cible **PostgreSQL**.
4. **Exporte MCD et MLD en PNG 300 dpi ou en PDF.** Jamais une capture d'écran : c'est flou à
   l'impression et le sujet demande un rapport de niveau professionnel.

---

# PARTIE D — Les deux décisions à justifier dans le rapport

Ce sont les deux points où ton modèle fait un choix. **Un correcteur les repérera ; autant
que ce soit toi qui en parles d'abord.**

## D.1 — La clé de INTERVENIR

Ton dictionnaire donne à la table `INTERVENTION` un identifiant propre, `id_intervention`,
alors que `EQUIPER` utilise une clé composée `(id_borne, id_connecteur)`. **Deux associations
N-N traitées différemment : il faut le justifier ou l'uniformiser.**

```
Clé composée (id_incident, id_technicien)
   → interdit qu'un technicien intervienne DEUX FOIS sur le même incident

Clé propre id_intervention
   → autorise plusieurs interventions du même technicien sur le même incident, à des dates différentes
```

> **La question à te poser est métier, pas technique : un technicien peut-il revenir une
> deuxième fois ?** Sur une panne de borne, oui, évidemment — un diagnostic puis une
> réparation. **Donc la clé propre est le bon choix**, et c'est ce qu'il faut écrire. Mais
> alors, en toute rigueur Merise, INTERVENIR n'est plus une association : **c'est une
> entité**, au même titre qu'ABONNEMENT (voir §A.5).
>
> **Les deux sont acceptables, l'incohérence non.** Choisis, et écris une phrase.

## D.2 — COMPTE_WEB séparée de UTILISATEUR

Le sujet demande cette table indépendamment de l'analyse métier. La justification tient en
deux arguments, et tous deux sont bons :

- **la cardinalité `(0,1)`** : tous les clients n'ont pas de compte web, donc fusionner
  laisserait trois colonnes nulles sur la plupart des lignes ;
- **la séparation des responsabilités** : `hash_mdp` est une donnée d'authentification, pas
  une donnée métier. On ne mélange pas les deux — c'est aussi ce qui permet de restreindre
  les droits d'accès à cette seule table.

---

# PARTIE E — Ce que Looping ne fera pas

Reprends le script SQL à la main pour :

- les **types énumérés** : `CREATE TYPE ... AS ENUM (...)`, six au total ;
- les colonnes **BYTEA** pour `photo` et `qr_code` ;
- les contraintes **CHECK**, par exemple `energie_kwh >= 0`, `fin > debut`,
  `date_fin > date_debut` ;
- les contraintes **UNIQUE** : `email`, `siret`, `code_rfid`, `immatriculation`,
  `code_borne`, `numero` de facture, `libelle` de connecteur, `login`.

Ce n'est pas un défaut de l'outil : **c'est le niveau de détail qu'un MCD ne décrit pas**, et
le dire dans le rapport montre que tu sais où s'arrête le modèle conceptuel.

---

# PARTIE F — Pour le rapport

Le MCD complet sur une page A4 portrait est illisible avec 15 entités. Combine :

- le **MCD complet en annexe**, en paysage ;
- dans le corps du rapport, **deux vues partielles** :
  - *pôle clients* — utilisateur, véhicule, badge, abonnement, tarif, facture, compte web
  - *pôle exploitation* — station, borne, connecteur, session, incident, technicien

---

## Le test qui prouve que tu as compris

Avant la soutenance, prends **trois associations au hasard** dans ton MCD, cache le tableau,
et pour chacune :

```
1. énonce les deux questions à voix haute
2. justifie le minimum ET le maximum de chaque côté
3. dis où tombera la clé étrangère, et pourquoi
```

**Si tu sais faire ça sur trois associations, tu sais le faire sur les quatorze** — et tu
tiendras n'importe quelle question de soutenance sur le modèle.

Le rappel théorique complet est dans
[la fiche 02 du cours de BD](../../02_Bases_de_donnees/00_mes_fiches_pas_a_pas/02_mcd_merise.md).
