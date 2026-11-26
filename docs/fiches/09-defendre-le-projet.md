# 9. Défendre le projet devant l'encadrant : tout comprendre, du câble à la requête

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

Ce fichier répond à une question : **« qu'est-ce qui se passe, exactement, quand une
voiture se recharge sur une borne Voltoise ? »** Si tu sais raconter ça en suivant les
parties 2 à 6, tu sais défendre le projet.

```
Partie 1   les 4 remarques du prof : ce qu'il veut dire, ce qu'on répond
Partie 2   l'architecture globale (la pièce qui manque au rapport v3)
Partie 3   le protocole : la "langue" entre la borne et le serveur
Partie 4   le code, en suivant UNE charge de bout en bout
Partie 5   les 10 questions : tables, opérations de l'algèbre, SQL
Partie 6   les scénarios : chaque étape = un message + du SQL
Partie 7   les questions qu'il peut te poser, et tes réponses
```

---

## Partie 1. Les 4 remarques du prof

### 1.1 « Le MCD n'est pas lisible, trop petit »

**Pourquoi :** le MCD fait 3 421 × 1 551 pixels, très large et peu haut, collé dans une
page A4 en portrait. Word le réduit à la largeur de la page : le texte devient minuscule.

**Ce qu'on fait :** mettre la page du MCD en **paysage**, et resserrer le schéma (les
entités sont trop espacées). À l'écrit, une règle simple : le texte des attributs doit être
**au moins de la taille du texte du rapport** une fois imprimé.

### 1.2 « L'héritage client / particulier / entreprise n'est pas légitime, il n'est jamais utilisé »

**Ce qu'il veut dire :** un héritage se justifie quand **les sous-types se comportent
différemment** dans le système. Chez nous, `PARTICULIER` (civilité, date de naissance) et
`ENTREPRISE` (raison sociale, SIRET, nombre de véhicules) **stockent** des informations
différentes, mais **rien ne s'en sert** :
- le serveur Java ne lit jamais ces deux tables (vérifie : aucune requête de
  `AccesBase.java` ne contient `particulier` ou `entreprise`) ;
- seules les questions Q8 et Q9 les lisent, pour de l'affichage.

Il a raison sur le fond : un héritage qui ne change aucun comportement, c'est un attribut
`type_client` qui a pris de l'ampleur. En plus, notre `CLIENT` a **déjà** la colonne
`type_client` : l'information « particulier ou entreprise » est donc stockée **deux fois**
(dans la colonne, et par la présence d'une ligne dans la table fille). C'est une
redondance, l'objection la plus forte.

**Ce qu'on a fait en v4 (décision de l'équipe) : l'héritage décide du paiement.**
- un **particulier** paie chaque charge **à la borne, par carte** ;
- une **entreprise** ne paie pas à la borne : ses charges vont sur une **facture
  mensuelle** établie sur son SIRET.

Concrètement :
- le serveur, au `STOP`, cherche le titulaire du badge dans `PARTICULIER` ou dans
  `ENTREPRISE` (`AccesBase.modePaiement`, deux `LEFT JOIN`) et répond `... CB` ou
  `... FACTURE` ;
- nouvelle commande `PAYE <session> CB-<réf>` : la borne confirme le paiement carte, le
  serveur l'enregistre (`date_paiement`, `ref_paiement` dans `SESSION_CHARGE`) ;
- nouvelle question **Q11** : le montant à facturer à chaque entreprise ;
- la colonne `client.type_client` est **supprimée** : le sous-type se lit dans la table
  fille, le stocker aussi dans CLIENT était une redondance.

**Ce que tu dis au prof :** « Vous aviez raison, l'héritage ne servait à rien. Maintenant les
deux sous-types ne paient pas de la même façon : c'est ce comportement différent qui justifie
deux entités. Vous le voyez dans les scénarios 3 et 4 et dans Q11. »

### 1.3 « Il n'y a pas l'architecture globale »

**Pourquoi :** la version 3 du rapport a **supprimé** la section « 3. Schéma
d'architecture » (le script `reviser_rapport_v3.py` la retire, ligne « architecture :
retirée »). Le sujet la demande. Il faut la remettre : c'est la partie 2 ci-dessous.

### 1.4 « Les scénarios ne suffisent pas, il faut une étape en plus »

Les scénarios v3 s'arrêtaient au moment où le serveur répond, et certains (le 5, la
maintenance) n'étaient pas expliqués. En v4 :
- **scénario 3** (particulière) : **deux étapes de plus** après le calcul du montant : le
  serveur lit le type de client (héritage), puis la borne encaisse par carte et envoie
  `PAYE` ;
- **scénario 4** (nouveau, entreprise) : même `STOP`, réponse `FACTURE`, puis la facture du
  mois (Q11) ;
- **scénario 6** (l'ancien 5, maintenance) : réécrit avec la règle en clair (« on ne coupe
  jamais une charge en cours ») et une étape de plus qui montre l'**effet** : après la mise
  en maintenance, un `START` est refusé ;
- sous **chaque** figure du rapport, un paragraphe « Ce que montre ce scénario ».

**Le scénario de la maintenance, expliqué en 4 phrases (si le prof redemande) :**
1. Le technicien demande la maintenance, mais un client charge : le serveur refuse (409),
   parce qu'on ne coupe pas le courant à quelqu'un en pleine charge.
2. Le client termine sa charge (STOP) : la borne redevient libre.
3. Le technicien redemande : accepté, la borne passe en état `maintenance` dans la base.
4. Un nouveau client passe son badge : refusé (409), car `START` n'accepte qu'une borne
   `libre`.

### 1.5 « Il ne doit pas y avoir plusieurs clés primaires » (MLD)

Dans notre MLD, une seule table a **deux attributs soulignés** : `EQUIPER (#id_borne,
#id_connecteur, nombre)`. Ce n'est **pas** deux clés primaires : c'est **une** clé primaire
**composée** de deux attributs (en SQL : un seul `PRIMARY KEY (id_borne, id_connecteur)`).
C'est la règle du cours : une association N-N devient une table dont la clé est le couple
des clés des entités.

**Ce qu'on corrige dans le rapport :** écrire sous le MLD « clé primaire de EQUIPER : le
couple (id_borne, id_connecteur) », pour que la lecture soit sans ambiguïté. Et si le prof
parlait d'une autre table, il faut savoir laquelle (question à la fin).

---

## Partie 2. L'architecture globale

### 2.1 Les machines et qui parle à qui

```
     PARKING                    SALLE SERVEURS (Voltoise)                     
 ┌──────────────┐           ┌────────────────────────┐                       
 │ BORNE        │  TCP 5000 │ SERVEUR APPLICATIF     │  JDBC / TCP 5432  ┌──────────────┐
 │ client Python├──────────►│ Java : ServeurBornes   ├──────────────────►│ PostgreSQL   │
 │ borne.py     │◄──────────┤ un thread par borne    │◄──────────────────┤ base voltoise│
 └──────────────┘ protocole └────────────────────────┘     SQL           └──────▲───────┘
   (x 9 bornes)   maison                                                         │ PDO / 5432
                  texte                                                   ┌──────┴───────┐
 ┌──────────────┐           HTTPS / TCP 443                               │ SERVEUR WEB  │
 │ NAVIGATEUR   ├──────────────────────────────────────────────────────── ►│ PHP          │
 │ client, gest.│                                                         └──────────────┘
 └──────────────┘
```

**La règle d'or (à savoir défendre) :** aucun poste « du terrain » ne parle à la base. La
borne est sur un parking public : si elle avait le mot de passe de la base, il suffirait de
l'ouvrir pour lire toutes les données de tous les clients. Le serveur Java est un **sas** :
il reçoit des paramètres simples (`START 1`), vérifie, et lui seul exécute du SQL.

### 2.2 Les couches (la même chose, vue « logiciel »)

| Couche | Où | Rôle | Fichier |
|---|---|---|---|
| Présentation | borne | écran, lecteur de badge, mesure de l'énergie | `borne.py` |
| Réseau | entre borne et serveur | transporter des lignes de texte | TCP + notre protocole |
| Métier | serveur Java | décider : badge valide ? borne libre ? combien payer ? | `DialogueBorne.java` |
| Données | serveur Java → PostgreSQL | lire et écrire, en transactions | `AccesBase.java` |
| Stockage | PostgreSQL | garder, et refuser les données incohérentes (CHECK, clés) | `01_creation.sql` |

### 2.3 Ce qu'on a déployé aujourd'hui en salle de TP

```
 poste CLIENT (Debian)  ──TCP 5000──►  poste SERVEUR (Debian)  ──TCP 5432──►  devwebdb2.etu
 python3 borne.py                      java -jar serveur-bornes.jar          (base intranet)
```
C'est **exactement** l'architecture cible, avec trois machines réelles au lieu de quatre (le
serveur web n'est pas encore codé). Le plan d'adressage du rapport (192.168.10.x et .20.x)
décrit la cible ; en TP, ce sont les adresses de la salle.

---

## Partie 3. Le protocole : la langue entre la borne et le serveur

### 3.1 Ce qu'est un protocole

Deux programmes sur deux machines ne partagent rien, sauf un **tuyau** (la connexion TCP)
où passent des octets. Un protocole, c'est la **convention** qui dit :
1. **le format** : comment découper le flux en messages (chez nous : une ligne = un
   message, terminée par `\n`, en texte UTF-8) ;
2. **le vocabulaire** : les commandes possibles (`HELLO`, `START`…) et leurs paramètres ;
3. **les réponses** : toujours `OK ...` ou `ERR <code> <message>` ;
4. **l'ordre autorisé** : par exemple, rien avant `HELLO`, pas de `STOP` sans `START`.

**Pourquoi TCP et pas UDP :** une charge, c'est de l'argent. On ne peut pas perdre un
`STOP` ni le recevoir deux fois. TCP garantit que les octets arrivent **tous**, **dans
l'ordre**, **une seule fois**. UDP ne garantit rien de tout ça.

**Pourquoi « une ligne = un message » :** TCP transporte un **flux** d'octets, sans
frontières. Si la borne envoie `HELLO X\n` puis `TARIF\n`, le serveur peut recevoir
`HEL`, puis `LO X\nTAR`, puis `IF\n`. Le `\n` est le seul moyen de savoir où finit un
message. C'est pour ça que le serveur lit **octet par octet jusqu'au `\n`**
(`lireLigne()`), et que le client utilise `makefile(...).readline()`.

### 3.2 Les messages

| La borne envoie | Le serveur vérifie | SQL exécuté | Réponse |
|---|---|---|---|
| `HELLO CGY-01-B01` | la borne existe | `SELECT id_borne, etat FROM borne WHERE code_borne = ?` puis `UPDATE borne SET derniere_communication` | `OK 1001 libre` |
| `TARIF` | qui charge en ce moment ? | tarif du client du badge en cours, sinon tarif `Public` | `OK 0.4900 0.0500 Public` |
| `START 1` | badge connu ? actif ? borne libre ? | `SELECT actif FROM badge`, puis **transaction** : `SELECT ... FOR UPDATE`, `INSERT INTO session_charge`, `UPDATE borne SET etat='occupee'` | `OK 18` (n° de session) |
| `STOP 18.45` | une charge est en cours | **transaction** : `UPDATE session_charge SET fin, energie, statut='terminee'`, `UPDATE borne SET etat='libre'` ; puis lecture du tarif, puis du **type de client** (héritage) | `OK 18 6.46 CB` (particulier) ou `OK 31 7.84 FACTURE` (entreprise) |
| `PAYE 18 CB-48882143` | la session est sur cette borne, terminée, pas encore payée, client particulier | `UPDATE session_charge SET date_paiement, ref_paiement` | `OK 18 payee` |
| `STATUS maintenance` | pas de charge en cours | `UPDATE borne SET etat = ?` | `OK maintenance` |
| `BYE` | rien | si une charge était en cours : la marquer interrompue | `OK au revoir`, fermeture |

**Les codes d'erreur** imitent ceux du web (tu les connais : 404 = page introuvable) :
400 message mal formé · 401 borne inconnue · 403 interdit (pas identifiée, badge bloqué)
· 404 badge inconnu · 409 conflit avec l'état (déjà en charge, borne occupée) · 413 ligne
trop longue · 500 erreur interne · 503 base indisponible.

### 3.3 Le protocole a des ÉTATS (le schéma à dessiner au tableau)

```
              HELLO ok                 START ok
 ┌────────────┐ ──────► ┌────────────┐ ──────► ┌──────────────┐
 │ NON        │         │ IDENTIFIÉE │         │ EN CHARGE    │
 │ IDENTIFIÉE │         │ (borne≠null)│ ◄────── │ (session≠0)  │
 └────────────┘         └────────────┘  STOP ok └──────────────┘
   seuls HELLO           TARIF, START,           TARIF, STOP,
   et BYE acceptés       STATUS, BYE             BYE (→ session interrompue)
                                                    après STOP : PAYE (si CB)
```

Dans le code, ces états sont **deux variables** de `DialogueBorne` :
`borne` (null tant que `HELLO` n'a pas réussi) et `sessionEnCours` (0 = pas de charge).
Chaque commande commence par vérifier l'état, et répond `ERR 403` ou `ERR 409` si l'ordre
n'est pas respecté.

---

## Partie 4. Le code, en suivant UNE charge de bout en bout

On suit `python3 borne.py CGY-01-B01` (badge 1, Claire Martin, abonnée).

### 4.1 Avant : le serveur attend (`ServeurBornes.java`)

```java
ServerSocket serveur = new ServerSocket(port);      // ouvre le port 5000
while (true) {
    Socket socket = serveur.accept();               // BLOQUE jusqu'à ce qu'une borne se connecte
    pool.submit(new DialogueBorne(socket, numero)); // confie cette borne à un thread
}
```
- `ServerSocket` = la **prise murale** : elle attend des connexions.
- `accept()` rend un `Socket` = **le câble** vers UNE borne.
- `pool` = 50 threads au plus. **Pourquoi des threads :** pendant que la borne 1 charge
  (une heure), la borne 2 doit pouvoir se connecter. Un thread par borne = chaque borne a
  son propre « employé » qui l'écoute.
- La configuration (port, URL de la base, mot de passe) est lue dans
  `serveur.properties` : on change de base **sans recompiler**.

### 4.2 La borne se connecte (`borne.py`)

```python
self.sock = socket.create_connection((hote, port), timeout=delai)   # 3-way handshake TCP
self.flux = self.sock.makefile("r", encoding="utf-8", newline="\n") # pour lire ligne par ligne
```
Côté serveur, `accept()` se débloque, un thread démarre `DialogueBorne.run()`, qui ouvre
**sa propre connexion à la base** (`new AccesBase()`).

### 4.3 HELLO : l'identification

Python : `borne.envoyer("HELLO CGY-01-B01")` → envoie la ligne, attend la réponse.

Java (`dialoguer()`) : lit la ligne, la coupe en mots (`split`), regarde le premier mot,
et appelle `hello(mots)` grâce au `switch`. `hello` vérifie le format (expression
régulière), cherche la borne en base (`trouverBorne`), mémorise `borne`, répond
`OK 1001 libre`.

Python lit `OK 1001 libre`, découpe : `etat = "libre"`. Si la borne n'est pas libre, elle
s'arrête là (`BYE`).

### 4.4 START : le moment critique (la transaction)

`start()` vérifie dans l'ordre : pas déjà en charge (409) → nombre valide (400) → badge
connu (404) → badge actif (403). Puis `ouvrirSession()` dans `AccesBase` :

```java
cnx.setAutoCommit(false);                                   // début de transaction
SELECT etat FROM borne WHERE id_borne = ? FOR UPDATE        // VERROU sur la ligne de la borne
if (!etat.equals("libre")) { rollback; return -1; }
INSERT INTO session_charge (...) VALUES (..., 'en_cours') RETURNING id_session
UPDATE borne SET etat = 'occupee'
cnx.commit();                                               // tout, ou rien
```

**Pourquoi une transaction :** on fait deux écritures (créer la session, occuper la
borne). Si le serveur plantait entre les deux, on aurait une session sans borne occupée.
La transaction garantit **tout ou rien** (atomicité).

**Pourquoi `FOR UPDATE` :** deux voitures passent leur badge au même instant sur la même
borne (deux threads). Sans verrou, les deux lisent « libre » et créent deux sessions. Avec
`FOR UPDATE`, le second thread **attend** que le premier ait fini, puis lit « occupée » et
reçoit `ERR 409`. Et même si on oubliait le verrou, la base refuserait : l'index unique
`une_charge_par_borne` interdit deux sessions `en_cours` sur une borne.

**Pourquoi `PreparedStatement` avec des `?` :** le code borne ou le badge viennent du
réseau. Si on collait le texte dans la requête, un message comme
`HELLO X'; DROP TABLE client; --` serait exécuté (**injection SQL**). Avec `?`, la valeur
est toujours traitée comme une donnée, jamais comme du code.

### 4.5 STOP : fin de charge et montant

La borne a mesuré l'énergie (simulée : `random.uniform(5, 40)`), envoie `STOP 30.716`.
`fermerSession()` : transaction qui termine la session et libère la borne, puis
`tarifApplicable(idBadge)` lit le tarif du **client titulaire du badge**
(BADGE ⋈ CLIENT ⋈ TARIF), et calcule :

```
montant = énergie × prix_kwh + minutes × prix_minute       arrondi à 2 décimales
```
Puis `modePaiement(idBadge)` regarde si le client est dans `PARTICULIER` ou `ENTREPRISE`.
Réponse : `OK 30 6.22 CB`. La borne (Python) voit `CB` : elle simule le terminal de
paiement, puis envoie `PAYE 30 CB-48882143` ; le serveur vérifie et enregistre le
paiement. Avec `FACTURE`, la borne affiche seulement « ajouté à la facture ».

Le montant n'est **pas stocké** : c'est une donnée **calculée** (comme au cours : on ne
stocke pas ce qui se déduit). Q3 le recalcule de la même façon.

### 4.6 BYE, ou la coupure

`BYE` → `OK au revoir`, le thread sort de la boucle, `try (socket)` ferme la connexion.

**Si la borne disparaît en pleine charge** (câble arraché, 4G coupée) : `lireLigne()`
reçoit -1 (fin de flux) ou rien pendant 5 minutes (`setSoTimeout`). Dans les deux cas, le
`finally` appelle `libererBorne()` : la session passe `interrompue`, la borne redevient
`libre`. Sans ça, la borne resterait « occupée » pour toujours.

### 4.7 Ce que fait la base TOUTE SEULE (les contraintes)

Même si le Java avait un bug, PostgreSQL refuse :
- une session `terminee` sans énergie (`CHECK statut <> 'terminee' OR energie_kwh IS NOT NULL`) ;
- une fin avant le début ;
- deux sessions en cours sur la même borne ou le même badge (index uniques partiels) ;
- un badge qui référence un client inexistant (clé étrangère).

Défendre ça : « la cohérence est garantie **à deux niveaux** : le serveur vérifie avant
d'écrire, et la base refuse ce qui est incohérent, quoi qu'il arrive ».

---

## Partie 5. Les 11 questions : tables, opérations, SQL

**Rappel :** en SQL, `WHERE` = **restriction σ**, la liste du `SELECT` = **projection
π**, `JOIN ... ON` = **jointure ⋈**, `NOT EXISTS` / `NOT IN` = **différence \** (ou
anti-jointure ⊳), `IN` / `EXISTS` = **semi-jointure ⋉** (ou intersection),
`GROUP BY` + `COUNT/SUM` = **agrégation** (une extension de l'algèbre de base).

| Q | Qui demande | Opérations | En algèbre (simplifié) |
|---|---|---|---|
| Q1 bornes libres à Cergy, avec prises | client | ⋈ ×3, σ, π, regroupement | π_{code_borne, nom_station, prises}(σ_{etat='libre' ∧ ville='Cergy'}(BORNE ⋈ STATION ⋈ EQUIPER ⋈ CONNECTEUR)) |
| Q2 charges et kWh par station, septembre | gestionnaire | ⋈, σ, **agrégat** COUNT/SUM | γ_{station; COUNT, SUM}(σ_{statut='terminee' ∧ septembre}(STATION ⋈ BORNE ⋈ SESSION)) |
| Q3 coût des charges de Claire Martin | cliente | ⋈ ×3, σ, π, **attribut calculé** | π_{session, montant}(σ_{email=…}(CLIENT ⋈ BADGE ⋈ SESSION ⋈ TARIF)) |
| Q4 clients > 60 kWh | gestionnaire | ⋈, agrégat, **HAVING** (σ sur un agrégat) | σ_{total>60}(γ_{client; SUM(energie)}(CLIENT ⋈ BADGE ⋈ SESSION)) |
| Q5 bornes sans incident | gestionnaire | **différence** (NOT EXISTS), ⋈ | (π_{id_borne}(BORNE) \ π_{id_borne}(INCIDENT)) ⋈ BORNE ⋈ STATION |
| Q6 incidents ouverts | technicien | σ (IS NULL), ⋈, tri | σ_{date_resolution=⊥}(INCIDENT) ⋈ BORNE ⋈ STATION |
| Q7 charges au-dessus de la moyenne | gestionnaire | **sous-requête** + agrégat AVG, σ | σ_{energie > moy}(SESSION ⋈ BORNE), moy = AVG(energie) |
| Q8 entreprises avec un badge bloqué | service client | **héritage** + **semi-jointure** (IN) ≡ **intersection** | (π_{id_client}(ENTREPRISE) ∩ π_{id_client}(σ_{¬actif}(BADGE))) ⋈ ENTREPRISE ⋈ CLIENT |
| Q9 particuliers dont un badge actif n'a jamais servi | marketing | héritage + **différence** | (π_{id_badge}(σ_{actif}(BADGE)) \ π_{id_badge}(SESSION)) ⋈ BADGE ⋈ CLIENT ⋈ PARTICULIER |
| Q10 tarif le plus utilisé | direction | agrégat + **>= ALL** (le maximum) | le groupe dont le COUNT est ≥ à tous les autres COUNT |
| Q11 à facturer à chaque entreprise | comptabilité | **héritage** + ⋈, σ (non payées), agrégat | γ_{entreprise; SUM(montant)}(σ_{terminee ∧ date_paiement=⊥}(ENTREPRISE ⋈ CLIENT ⋈ TARIF ⋈ BADGE ⋈ SESSION)) |

**Comment expliquer une question à l'oral (méthode, exemple Q5) :**
1. *La question* : « les bornes qui n'ont jamais eu d'incident ».
2. *Le mot-clé* : « jamais » = une **négation** → une **différence** : toutes les bornes
   **moins** celles qui ont un incident.
3. *En SQL* : `NOT EXISTS (SELECT 1 FROM incident i WHERE i.id_borne = b.id_borne)` :
   « il n'existe aucun incident pour cette borne ».
4. *Le piège évité* : un `JOIN incident` + `WHERE ... IS NULL` normal ne marcherait pas,
   une borne sans incident n'a aucune ligne dans la jointure.

**Ce qui manque dans nos 10 questions (à savoir dire, ou à ajouter) :** une **union**
(par exemple « les contacts à prévenir d'une coupure : les e-mails des clients qui ont
chargé à la station ∪ ceux des gestionnaires ») et une **division** (« les clients qui ont
chargé dans **toutes** les stations »). Le TD #9 de l'encadrant cite ces opérations : si
la grille de la SAÉ les demande, c'est une question facile à ajouter.

---

## Partie 6. Les scénarios : chaque étape = un message + du SQL

Un scénario du rapport se lit toujours sur **quatre colonnes** (les lignes de vie du
diagramme) : Conducteur → Borne (Python) → Serveur (Java) → Base. Chaque flèche est soit un
message du protocole, soit une requête SQL.

| Scénario (rapport v4) | Étapes | Ce qu'il montre | Code concerné |
|---|---|---|---|
| 1 Mise en service | HELLO → TARIF public | identification, lecture | `hello()`, `tarifApplicable(0)` |
| 2 Début de charge | START → vérif. badge → transaction → TARIF abonné | mise à jour, transaction, verrou | `start()`, `ouvrirSession()` |
| 3 Fin de charge, particulière | STOP → montant → **type de client** → **CB + PAYE** | calcul, héritage, paiement | `stop()`, `modePaiement()`, `paye()`, `payerCB()` |
| 4 Fin de charge, entreprise | STOP → **type de client** → FACTURE → Q11 | héritage, facture mensuelle | `modePaiement()`, Q11 |
| 5 Badge refusé | START 4 → ERR 403 → START 2 → OK | réponse négative et reprise | `badgeActif()` |
| 6 Maintenance | STATUS refusé → STOP → STATUS ok → **START refusé** | l'état protège la session | `status()`, `ouvrirSession()` |
| 7 Fin de dialogue | BYE propre / coupure → interrompue | robustesse | `bye()`, `libererBorne()` |

---|---|---|---|
| 1 Mise en service | HELLO → TARIF public | identification, lecture | `hello()`, `tarifApplicable(0)` |
| 2 Début de charge | START → vérif. badge → transaction → TARIF abonné | mise à jour, transaction, verrou | `start()`, `ouvrirSession()` |
| 3 Fin de charge | STOP → transaction → calcul du montant | calcul, donnée non stockée | `stop()`, `fermerSession()` |
| 4 Badge refusé | START 4 → ERR 403 → START 2 → OK | réponse négative et reprise | `badgeActif()` |
| 5 Maintenance | STATUS refusé (409) → STOP → STATUS ok | l'état protège la session | `status()` |
| 6 Fin de dialogue | BYE propre / coupure → interrompue | robustesse | `bye()`, `libererBorne()` |

**L'étape en plus** se placera dans un de ces tableaux selon ce que le prof a demandé
(paiement après STOP, ou authentification après HELLO) : voir la fin du message.

---

## Partie 7. Les questions probables, et tes réponses

| Question | Réponse courte |
|---|---|
| Pourquoi un serveur Java entre la borne et la base ? | Sécurité (la borne ne connaît pas le mot de passe de la base), et un seul endroit où sont les règles métier. |
| Pourquoi TCP ? | Pas de perte, pas de doublon, l'ordre est garanti : une charge, c'est de l'argent. |
| Comment le serveur sait où finit un message ? | Le `\n`. TCP est un flux sans frontières. |
| Que se passe-t-il si deux bornes se connectent en même temps ? | Un thread par borne (pool de 50) : elles sont servies en parallèle. |
| Et deux badges sur la même borne au même instant ? | Transaction + `SELECT ... FOR UPDATE` : le second attend puis reçoit `ERR 409`. En dernier recours, l'index unique de la base refuse. |
| Et si la borne est débranchée en pleine charge ? | Fin de flux ou 5 min sans message → session `interrompue`, borne `libre`. |
| Comment évitez-vous l'injection SQL ? | Uniquement des `PreparedStatement` avec `?`. |
| Pourquoi le montant n'est pas une colonne ? | C'est une donnée calculée (énergie × prix) : la stocker créerait une redondance qui peut devenir fausse. |
| Différence identification / authentification ? | Identifier = dire qui on est (`HELLO code`). Authentifier = le **prouver** (un secret). Aujourd'hui on identifie seulement : c'est une limite connue. |
| À quoi sert l'héritage ? | Au paiement : un particulier paie à la borne par carte, une entreprise est facturée au mois. Le serveur lit la table fille au STOP. |
| Pourquoi avoir retiré type_client ? | C'était une redondance : l'information est déjà donnée par la table fille où se trouve le client. |
| Pourquoi EQUIPER a deux attributs soulignés ? | C'est **une** clé primaire **composée** : une association N-N borne-connecteur, une ligne par couple. |
