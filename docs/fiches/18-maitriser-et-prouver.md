# 18. Maîtriser le projet et prouver chaque critère toi-même

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

**Comment travailler avec cette fiche :**
- lis une partie, puis fais le bloc **« À toi »** sans regarder, à voix haute ou sur papier ;
- ensuite seulement, compare avec la fiche ;
- pour la partie 5, ouvre les fenêtres et tape les commandes toi-même : c'est ce que tu feras devant le prof.

---

## 1. Le contexte et la problématique, en simple

### Le contexte

Voltoise est une entreprise (imaginaire) qui exploite des bornes de recharge pour voitures électriques dans le Val-d'Oise :
- **4 stations** : Cergy Préfecture, Cergy Saint-Christophe, Pontoise Gare, Saint-Ouen Centre ;
- **9 bornes** en tout, chacune avec un code comme `CGY-01-B01` (ville, station, borne) ;
- des **clients** qui chargent leur voiture en passant un **badge** sur la borne.

Il y a deux sortes de clients :
- un **particulier** paie chaque charge tout de suite, par carte bancaire, sur la borne ;
- une **entreprise** (une flotte de véhicules) ne paie rien à la borne : ses charges s'additionnent sur une **facture à la fin du mois**.

Aujourd'hui, Voltoise a trois problèmes :
1. il ne sait pas quelles bornes sont libres, occupées ou en panne ;
2. il calcule le prix des charges à la main ;
3. ses clients ne peuvent pas voir leurs charges ni ce qu'elles coûtent.

### La problématique

> Comment relier des bornes posées dans la rue, un site web et une base de données, sans que la borne puisse lire la base, et sans que la base devienne fausse quand une borne perd sa connexion au milieu d'une charge ?

Chaque morceau de cette phrase est un vrai problème. Voici ce qu'il veut dire et ce qu'on a fait :

| Difficulté | Pourquoi c'est un problème | Ce qu'on a fait | Où dans le code |
|---|---|---|---|
| **La borne est dans la rue** | n'importe qui peut ouvrir une borne. Si elle contenait le mot de passe de la base, on pourrait lire les données de tous les clients | la borne ne parle **jamais** à la base. Elle envoie des commandes courtes (`START 1`) à un serveur, et c'est le serveur qui fait le SQL | `borne.py` ne contient aucun SQL ; tout le SQL est dans `AccesBase.java` |
| **Une charge dure longtemps** | entre START et STOP il peut se passer une heure. Si le réseau coupe, la borne resterait « occupée » pour toujours dans la base | le serveur détecte la coupure (fin de connexion, ou 5 minutes de silence) et passe la charge à `interrompue`, la borne à `libre` | `DialogueBorne.libererBorne()`, appelée dans un `finally` |
| **Plusieurs bornes en même temps** | deux bornes écrivent dans la base au même moment ; deux badges pourraient démarrer sur la même borne | un **thread** par borne ; START verrouille la ligne de la borne (`FOR UPDATE`) le temps de la transaction | `ServeurBornes` (pool de threads), `AccesBase.ouvrirSession()` |
| **Deux façons de payer** | à la fin d'une charge, il faut savoir si on encaisse à la borne ou si on attend la facture | dans la base, CLIENT a deux sous-types : PARTICULIER et ENTREPRISE (l'**héritage**). Le serveur regarde dans quelle table est le client | `AccesBase.modePaiement()` |

### À toi

Explique à ton binôme, en 4 phrases et sans regarder : qui est Voltoise, quels sont ses 3 problèmes, quelle est la question qu'on se pose, et une des 4 difficultés avec ce qu'on a fait.

---

## 2. Le chemin complet d'une charge

Suis une charge du début à la fin. Chaque ligne est une étape réelle du code.

1. **Le conducteur** passe le badge n°1 sur la borne CGY-01-B01.
2. **`borne.py`** ouvre une connexion **TCP** vers le serveur, port **5000**.
3. **`ServeurBornes`** (Java) attend sur `accept()`. La connexion arrive : il crée un **`DialogueBorne`** et le confie à un thread.
4. **`DialogueBorne`** ouvre sa propre connexion à la base (**JDBC**, via `AccesBase`).
5. La borne envoie `HELLO CGY-01-B01`. Le serveur cherche ce code dans la table `borne` : il existe, il répond `OK 1001 libre` (1001 = numéro du dialogue).
6. La borne envoie `START 1`. Le serveur vérifie que le badge 1 existe et n'est pas bloqué, puis, **dans une transaction** : crée une ligne dans `session_charge` (statut `en_cours`) et passe la borne à `occupee`. Il répond `OK 18` (18 = numéro de la session).
7. La borne envoie `STOP 12.5` (kWh). Le serveur termine la session, libère la borne, calcule le montant, et regarde si le client est particulier ou entreprise. Il répond `OK 18 4.38 CB`.
8. `CB` : la borne encaisse par carte, puis envoie `PAYE 18 CB-41060116`. Le serveur enregistre la date et la référence du paiement.
9. La borne envoie `BYE`. Le serveur répond `OK au revoir` et ferme.
10. **Le site PHP** lit la même base : la charge apparaît dans l'espace client de Claire Martin.

### À toi

Sans regarder : à l'étape 6, quelles **deux** modifications de la base se font ensemble ? Pourquoi faut-il qu'elles se fassent ensemble ?

---

## 3. Le serveur Java : trois classes, trois rôles

Une image pour retenir :
- **`ServeurBornes`** = l'accueil d'un bureau : il ouvre la porte et envoie chaque visiteur vers un guichet ;
- **`DialogueBorne`** = un guichet : il parle avec **une** borne, du HELLO au BYE ;
- **`AccesBase`** = le seul employé qui a la clé des archives : lui seul écrit du SQL.

### 3.1 `ServeurBornes.java` (54 lignes) : l'accueil

Ce que fait `main()`, dans l'ordre :

| Ligne de code | Ce qu'elle fait | Pourquoi |
|---|---|---|
| `config.load(in)` | lit `serveur.properties` : port, `db.url`, `db.user`, `db.password` | rien n'est écrit en dur : on change de base ou de port sans recompiler (critère de la grille) |
| `AccesBase.configurer(...)` | donne à `AccesBase` l'adresse et les accès de la base | chaque dialogue ouvrira sa connexion avec ces valeurs |
| `Executors.newFixedThreadPool(50)` | prépare 50 threads au plus | une borne = un thread ; au-delà de 50, les connexions attendent |
| `new ServerSocket(port)` | ouvre le port 5000 et écoute | c'est ici que se produit le **bind** |
| `while (true) { serveur.accept(); ... }` | attend une connexion, puis la confie à un `DialogueBorne` dans le pool | sans thread, la 2e borne attendrait que la 1re ait fini |
| `catch (BindException e)` | si le port est déjà pris : message clair et arrêt | critère « bind » de la grille |
| `static synchronized void log(...)` | écrit une ligne horodatée dans la console | `synchronized` : plusieurs threads écrivent en même temps, les lignes ne se mélangent pas |

`compteurDialogues` est un `AtomicInteger` : deux threads peuvent l'augmenter en même temps sans donner deux fois le même numéro.

### 3.2 `DialogueBorne.java` (283 lignes) : une conversation avec une borne

C'est un `Runnable` : sa méthode `run()` s'exécute dans un thread du pool.

**Ce qu'il retient pendant la conversation (son état) :**
- `borne` : `null` tant que HELLO n'a pas réussi ;
- `sessionEnCours` : le numéro de la charge en cours, 0 s'il n'y en a pas ;
- `badgeEnCours` : le badge qui charge.

**`run()`, dans l'ordre :**
1. `socket.setSoTimeout(5 minutes)` : si la borne ne dit rien pendant 5 minutes, la lecture lève `SocketTimeoutException` ;
2. ouvre un `AccesBase` (une connexion JDBC rien que pour cette borne) ;
3. lance `dialoguer()` dans un `try ... finally { libererBorne(); }` : **quoi qu'il arrive** (BYE, coupure, délai dépassé, erreur), si une charge était en cours, elle est marquée interrompue ;
4. les `catch` transforment chaque problème en ligne de journal : `base inaccessible` (et `ERR 503` à la borne), `inactivite trop longue`, `connexion perdue`.

**`dialoguer()`** est la boucle principale :
1. lit une ligne avec `lireLigne()` ;
2. la découpe en mots, met la commande en majuscules ;
3. si la borne n'est pas identifiée et que ce n'est ni HELLO ni BYE : `ERR 403 identification requise` ;
4. un `switch` appelle la bonne méthode ; une commande inconnue reçoit `ERR 400` ;
5. une `SQLException` devient `ERR 500 erreur interne` : **le détail SQL reste dans le journal du serveur**, la borne ne le voit jamais.

**Les méthodes, une par commande :**

| Méthode | Vérifie | Appelle dans `AccesBase` | Répond |
|---|---|---|---|
| `hello` | pas déjà identifiée (409) ; code au bon format (400) | `trouverBorne`, `signalerCommunication` | `OK <dialogue> <état>` ou `ERR 401` puis fermeture |
| `start` | pas de charge en cours (409) ; badge entier (400) ; badge existe (404) et actif (403) | `badgeActif`, `ouvrirSession` | `OK <session>` ou `ERR 409 borne indisponible` |
| `stop` | une charge en cours (409) ; énergie décimale positive (400) | `fermerSession`, `modePaiement` | `OK <session> <montant> CB` ou `FACTURE` |
| `paye` | format `PAYE <n> CB-<ref>` (400) | `payerCB` | `OK <n> payee`, `ERR 404` ou `ERR 409` |
| `tarif` | rien | `tarifApplicable` | `OK <prix_kwh> <prix_minute> <libellé>` |
| `status` | état autorisé (400) ; pas de charge en cours (409) | `changerEtat` | `OK <état>` |
| `bye` | rien | `libererBorne` | `OK au revoir`, puis fin |

**Deux méthodes à savoir expliquer :**
- **`lireLigne()`** lit octet par octet jusqu'au `\n`, et s'arrête à **1 024 octets** (`ERR 413` puis fermeture). Pourquoi pas `readLine()` ? Parce qu'il n'a pas de limite : un client qui n'envoie jamais de `\n` ferait grossir la mémoire du serveur sans fin. Elle enlève aussi le `\r` que netcat ajoute sous Windows.
- **`envoyer()`** écrit la réponse suivie de `\n`, l'envoie tout de suite (`flush`) et l'écrit dans le journal avec `->`.

### 3.3 `AccesBase.java` (241 lignes) : le seul qui parle SQL

Une instance = **une connexion JDBC**. Elle est `AutoCloseable` : le `try (AccesBase b = ...)` de `run()` la ferme automatiquement.

| Méthode | SQL | Effet sur la base |
|---|---|---|
| `trouverBorne(code)` | `SELECT id_borne, etat FROM borne WHERE code_borne = ?` | lecture |
| `signalerCommunication(id)` | `UPDATE borne SET derniere_communication = LOCALTIMESTAMP` | date du dernier message |
| `badgeActif(id)` | `SELECT actif FROM badge WHERE id_badge = ?` | lecture ; `null` si le badge n'existe pas |
| `ouvrirSession(borne, badge)` | **transaction** : `SELECT etat ... FOR UPDATE`, puis `INSERT INTO session_charge ... RETURNING id_session`, puis borne à `occupee`, puis `COMMIT` | une ligne `en_cours` + borne `occupee` |
| `fermerSession(...)` | **transaction** : `UPDATE session_charge SET fin, energie_kwh, statut = 'terminee'`, borne à `libre`, `COMMIT` ; puis calcul du montant en Java | session terminée, borne libre |
| `modePaiement(badge)` | `LEFT JOIN particulier` et `LEFT JOIN entreprise` | lecture : `CB` ou `FACTURE` (l'héritage) |
| `payerCB(session, borne, ref)` | vérifie : session de **cette** borne (sinon 404), `terminee`, pas déjà payée, pas une entreprise (sinon 409) ; puis `UPDATE ... date_paiement, ref_paiement` | paiement enregistré |
| `interrompreSession(...)` | `UPDATE ... statut = 'interrompue' WHERE ... AND statut = 'en_cours'`, borne à `libre` | après une coupure |
| `changerEtat(borne, état)` | `UPDATE borne SET etat = ?::etat_borne` | état de la borne |
| `tarifApplicable(badge)` | tarif du client s'il est en vigueur, sinon le tarif `Public` | lecture |

**La transaction de `ouvrirSession()`, ligne par ligne :**
1. `cnx.setAutoCommit(false)` : à partir d'ici, rien n'est définitif tant qu'on n'a pas fait `commit()` ;
2. `SELECT etat FROM borne WHERE id_borne = ? FOR UPDATE` : lit l'état **et verrouille la ligne**. Si un 2e START arrive sur la même borne au même moment, il attend que la 1re transaction finisse, puis il voit `occupee` ;
3. si l'état n'est pas `libre` : `rollback()` et on renvoie -1 (la borne répond `ERR 409 borne indisponible`) ;
4. `INSERT ... RETURNING id_session` : crée la charge et récupère son numéro ;
5. `changerEtat(idBorne, "occupee")` ;
6. `cnx.commit()` : les deux modifications deviennent visibles **ensemble** ;
7. en cas d'erreur : `rollback()`, aucune des deux n'est gardée ;
8. `finally { setAutoCommit(true) }` : on revient au mode normal.

### 3.4 Comment on a mis JDBC

**JDBC** est la bibliothèque standard de Java pour parler à une base de données. Chaque SGBD fournit un **pilote** ; pour PostgreSQL, c'est le fichier `postgresql-42.7.4.jar`.

- **Où est le pilote :** dans `reseau/serveur-java/dist/lib/`.
- **Comment Java le trouve :** `construire.sh` écrit dans le manifeste du .jar la ligne `Class-Path: lib/postgresql-42.7.4.jar`. Quand on lance `java -jar serveur-bornes.jar`, Java charge aussi le pilote.
- **La connexion :** `DriverManager.getConnection(url, user, password)`, avec une URL de la forme `jdbc:postgresql://devwebdb2.etu:5432/<base>` (lue dans `serveur.properties`).
- **Les requêtes :** toujours `cnx.prepareStatement("... WHERE id_badge = ?")`, puis `ps.setInt(1, idBadge)`. Le `?` est remplacé par une **valeur**, jamais par du texte SQL.

**Pourquoi c'est important (injection SQL) :** si on écrivait `"... WHERE id_badge = " + texteRecu`, une borne piratée pourrait envoyer `START 1; DROP TABLE badge` et le serveur exécuterait le `DROP`. Chez nous, c'est bloqué deux fois : `entier()` refuse tout ce qui n'est pas un nombre (`ERR 400`), et même sinon, le `?` traiterait le texte comme une valeur, pas comme du SQL.

### À toi

1. Pourquoi `libererBorne()` est-elle dans un `finally` et pas juste après `dialoguer()` ?
2. Que se passe-t-il si deux START arrivent en même temps sur la même borne ? Quelle ligne de code l'empêche ?
3. Où est le mot de passe de la base, et pourquoi pas dans le code Java ?

---

## 4. Le client `borne.py` (156 lignes), interprété

### La classe `Borne`

| Code | Ce qu'il fait |
|---|---|
| `socket.create_connection((hote, port), timeout=10)` | ouvre la connexion TCP. Le `timeout=10` vaut ensuite pour chaque lecture : si le serveur ne répond pas en 10 s, on obtient `timed out` |
| `self.sock.makefile("r", ...)` | permet de lire **ligne par ligne**. TCP est un flux d'octets : il ne garde pas les frontières des messages, c'est le `\n` qui les marque |
| `envoyer(message)` | affiche `-> message`, envoie `message\n` (`sendall`), lit **une** ligne de réponse (1 024 octets au plus), affiche `<- réponse`, et renvoie `(ok, reste)` |
| réponse vide | le serveur a fermé : `ConnectionError("le serveur a fermé la connexion")` |

### `charge_complete(borne, id_badge)` : le mode normal

1. `HELLO <code>` ; si refusé : « borne refusée par le serveur, arrêt » ;
2. si l'état n'est pas `libre` : « pas de charge possible », BYE ;
3. `TARIF` (le tarif public, affiché avant de badger) ;
4. `START <badge>` ; si refusé (badge bloqué...) : « charge refusée », BYE ;
5. attend 2 secondes (la charge), tire une énergie au hasard entre 5 et 40 kWh ;
6. `TARIF` (cette fois le tarif du client) puis `STOP <énergie>` ;
7. si la réponse finit par `CB` : simule le paiement carte, puis `PAYE <session> CB-xxxxxxxx` ; si `FACTURE` : « charge ajoutée à la facture mensuelle » ;
8. `BYE`.

### `verifier()` et `interactif()` : le mode `-i`

`FORMATS` donne, pour chaque commande, le format attendu (le même que côté serveur). Avant d'envoyer, `verifier()` compare : une commande mal formée affiche `non envoyé : ...` et **n'est pas envoyée**. Le client vérifie, et le serveur revérifie quand même.

### `main()`

- `argparse` lit `code`, `--hote` (défaut 127.0.0.1), `--port` (défaut 5000), `--badge`, `-i` ;
- connexion impossible (port fermé, nom inconnu) : « connexion impossible à ... » et sortie ;
- connexion perdue en cours (serveur arrêté, délai dépassé) : « connexion perdue : ... ».

### À toi

Lis cette trace et explique chaque ligne :
```
-> START 6
<- OK 19
-> STOP 20.601
<- OK 19 6.39 FACTURE
client entreprise : charge ajoutée à la facture mensuelle
-> BYE
```
Pourquoi n'y a-t-il pas de PAYE ? Que contient `ref_paiement` pour cette session dans la base, juste après ?

---

## 5. Prouver chaque critère de la grille toi-même

### Préparer

```
cd D:\All\Licence_Cergy\Semestre_5\05_SAE_BD_Reseau\lancer_en_local
powershell -ExecutionPolicy Bypass -File lancer_tout.ps1 -Reinitialiser
```
Ça ouvre trois fenêtres : SERVEUR (le journal), SITE, BORNE. Les commandes ci-dessous se tapent dans la fenêtre **BORNE** (dossier `reseau\client-python`).

Pour regarder la base, dans une autre fenêtre PowerShell :
```
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h localhost -p 54329 -d voltoise
```

### Échanges et implémentation

| Critère | Tu tapes | Tu dois voir | Ce que ça prouve |
|---|---|---|---|
| Diagrammes, TCP, port | tu ouvres le rapport, §8.3 | « Transport TCP, serveur sur le port 5000 » sur chaque diagramme | les diagrammes sont complets |
| Client et serveur se lancent, échangent | `python borne.py CGY-01-B01 --badge 1` | HELLO ... BYE à gauche, les mêmes lignes dans SERVEUR | le protocole marche de bout en bout |
| Échange alternatif | `python borne.py CGY-01-B02 --badge 6` puis `--badge 4` | `FACTURE` sans PAYE ; puis `ERR 403 badge bloque` | deux chemins différents du diagramme |
| Ouverture et fermeture | (même commande) | HELLO au début, BYE et `OK au revoir` à la fin | l'ouverture et la fermeture applicatives existent |

### netcat

| Critère | Tu tapes | Tu dois voir | Ce que ça prouve |
|---|---|---|---|
| netcat en client | `ncat 127.0.0.1 5000`, puis `START 1`, `HELLO CGY-02-B01`, `BONJOUR`, `STOP 10`, `BYE` | `ERR 403`, `OK ... libre`, `ERR 400 commande inconnue`, `ERR 409`, `OK au revoir` | le serveur suit le protocole et rejette le reste |
| netcat en serveur | les commandes de l'encadré ci-dessous | netcat affiche HELLO, TARIF, START, TARIF, STOP, PAYE, BYE | notre client suit le diagramme, même face à un autre serveur |

**netcat en serveur.** Dans une 2e fenêtre PowerShell, prépare les réponses du serveur, puis fais écouter netcat :
```
Set-Content rep.txt "OK 1001 libre","OK 0.4900 0.0500 Public","OK 42","OK 0.3500 0.0000 Abonne","OK 42 9.18 CB","OK 42 payee","OK au revoir"
Get-Content rep.txt | ncat -l 5001
```
Puis, dans BORNE : `python borne.py CGY-01-B01 --port 5001`.

### Coupures et délais

| Critère | Tu tapes | Tu dois voir | Ce que ça prouve |
|---|---|---|---|
| Coupure du client | `ncat 127.0.0.1 5000`, `HELLO PTS-01-B01`, `START 2`, puis **Ctrl+C** ; dans psql : `SELECT id_session, statut FROM session_charge ORDER BY id_session DESC LIMIT 1;` | journal : `session ... marquee interrompue` ; psql : `interrompue` | le serveur détecte la coupure et répare la base |
| Coupure du serveur | `python borne.py CGY-01-B01 -i`, `HELLO CGY-01-B01`, `START 1` ; ferme la fenêtre SERVEUR ; tape `TARIF` | `connexion perdue : le serveur a fermé la connexion` | le client détecte la coupure. Relance ensuite `lancer_tout.ps1 -Reinitialiser` |
| Serveur trop lent | 2e fenêtre : `ncat -l 5002` (tu ne réponds rien) ; BORNE : `python borne.py CGY-01-B01 --port 5002` | au bout de 10 s : `connexion perdue : timed out` | le client ne reste pas bloqué |
| Client trop lent | `ncat 127.0.0.1 5000`, `HELLO CGY-01-B01`, puis tu ne tapes plus rien pendant 5 minutes | journal : `inactivite trop longue, fermeture` | le serveur ne garde pas une connexion morte |

### Erreurs réseau et robustesse

| Critère | Tu tapes | Tu dois voir | Ce que ça prouve |
|---|---|---|---|
| bind (port pris) | dans `reseau\serveur-java\dist` : `& "C:\Program Files\Android\Android Studio\jbr\bin\java.exe" -jar serveur-bornes.jar ..\..\..\lancer_en_local\serveur-local.properties` | `port 5000 deja utilise : arreter l'autre serveur ou changer port= ...` | message clair, pas de pile d'erreurs Java |
| Port non ouvert | `python borne.py CGY-01-B01 --port 5999` | `connexion impossible à 127.0.0.1:5999 : ... refused` | le client affiche l'erreur et s'arrête |
| Nom inconnu | `python borne.py CGY-01-B01 --hote serveur-inexistant.voltoise` | `getaddrinfo failed` | idem pour un nom DNS |
| IP et port paramétrables | ouvre `lancer_en_local\serveur-local.properties` (`port=5000`) ; `python borne.py --help` | `--hote`, `--port`, défaut 5000 | rien n'est écrit en dur |
| Traces | regarde SERVEUR et BORNE | serveur : heure, `#n° de dialogue`, code de borne, `<-` et `->` ; client : `->` et `<-` | les deux côtés tracent tout |
| Dépassement de taille | la commande de l'encadré ci-dessous | `ERR 413 ligne trop longue` | le serveur limite la mémoire utilisée |
| Hors protocole | dans ncat : `BONJOUR` | `ERR 400 commande inconnue` et la ligne dans le journal | rejeté et tracé |
| Le serveur construit le SQL | ouvre `AccesBase.java` : uniquement des `prepareStatement` avec `?` | la borne n'envoie que des valeurs | pas d'injection possible depuis la borne |
| Le client vérifie | `python borne.py CGY-01-B01 -i`, puis `START abc` | `non envoyé : START <id_badge>, un entier` | le client contrôle avant d'envoyer |
| Le serveur vérifie | dans ncat (après HELLO) : `START abc` | `ERR 400 usage : START <id_badge>` | le serveur revérifie quand même |

**Dépassement de taille :**
```
python -c "print('A'*2000)" | ncat 127.0.0.1 5000
```

### La partie BD (vidéo BD)

| Ce que le sujet demande | Tu fais sur le site | Tu vérifies dans psql |
|---|---|---|
| Modifier la base | gestionnaire, Incidents : déclarer un incident sur PTS-01-B02, cocher « hors service » | `SELECT i.id_incident, b.code_borne, b.etat FROM incident i JOIN borne b USING (id_borne) ORDER BY 1 DESC LIMIT 1;` : l'incident et la borne `hors_service`, faits dans la même transaction |
| Extraire des données | gestionnaire, Chiffres | les mêmes calculs que Q2 (GROUP BY) et Q10 (héritage) dans `sql/02_requetes.sql` |
| Un client ne touche qu'à ses données | claire.martin bloque son badge | `SELECT id_badge, actif FROM badge WHERE id_client = 1;` : `f`. Le code fait `WHERE id_badge = $1 AND id_client = $2` |

---

## 6. Les questions qu'on peut te poser

Réponds d'abord seul, puis regarde la réponse.

1. **Pourquoi TCP et pas UDP ?**
   Une charge, c'est START puis STOP. Avec UDP, un message peut se perdre ou arriver dans le désordre. TCP renvoie ce qui est perdu et garde l'ordre, tant que la connexion tient. Si elle tombe, c'est notre serveur qui répare (charge interrompue).

2. **Pourquoi un thread par borne ?**
   `accept()` puis le dialogue bloquent. Sans thread, la 2e borne attendrait que la 1re ait dit BYE, peut-être une heure.

3. **Pourquoi la borne ne parle pas directement à la base ?**
   Elle est dans la rue. Avec le mot de passe de la base dedans, ouvrir une borne suffirait à lire tous les clients.

4. **Que se passe-t-il si la base tombe ?**
   `new AccesBase()` lève `SQLException` : la borne reçoit `ERR 503 service indisponible`, le détail est dans le journal.

5. **Pourquoi le montant n'est-il pas stocké ?**
   C'est une donnée calculée (énergie × prix_kwh + durée × prix_minute). La stocker, c'est risquer qu'elle ne corresponde plus au tarif.

6. **À quoi sert l'héritage ici ?**
   PARTICULIER et ENTREPRISE n'ont pas les mêmes attributs (date de naissance d'un côté, SIRET de l'autre) et pas le même paiement. `modePaiement()` et Q10 s'en servent.

7. **Que montre `ERR 404` au PAYE du 24/11 ?**
   On envoyait le numéro renvoyé par HELLO (le dialogue, 1001...) au lieu de celui renvoyé par START (la session). `payerCB` cherche la session **sur cette borne** : pas trouvée, donc 404.

---

## 7. Refaire le TP toi-même : compiler, brancher le pilote, lancer

Fais ces exercices dans l'ordre, dans PowerShell. Chaque résultat attendu ci-dessous a été obtenu en vrai sur ton PC le 26/11.

**Avant de commencer :**
```
cd D:\All\Licence_Cergy\Semestre_5\05_SAE_BD_Reseau\reseau\serveur-java
$J = "C:\Program Files\Android\Android Studio\jbr\bin"
```
`$J` est le dossier du JDK d'Android Studio. Le `java` de ton PATH est une vieille version 1.8, qui ne connaît pas le `switch ->` ni les `record` du code.

Le serveur de `lancer_tout.ps1` occupe déjà le port 5000. Pour tes essais, crée un fichier de configuration sur le port 5011 :
```
Set-Content essai.properties "port=5011","db.url=jdbc:postgresql://localhost:54329/voltoise","db.user=postgres","db.password="
```

### Exercice 1. Compiler

```
mkdir build
& "$J\javac.exe" --release 17 -d build src\main\java\fr\voltoise\serveur\*.java
Get-ChildItem build -Recurse -Filter *.class
```
**Tu dois voir 5 fichiers :** `AccesBase.class`, `AccesBase$Borne.class`, `AccesBase$Tarif.class`, `DialogueBorne.class`, `ServeurBornes.class`.

**À comprendre :**
- `--release 17` : le code produit tourne sur Java 17 et plus. En salle, c'est OpenJDK 21 ;
- `-d build` : les .class vont dans `build\`, rangés selon le package (`fr\voltoise\serveur\`) ;
- `Borne` et `Tarif` sont des `record` déclarés dans `AccesBase` : Java en fait deux classes à part, avec un `$` dans le nom ;
- **le pilote PostgreSQL n'est pas nécessaire pour compiler.** Le code n'utilise que `java.sql.Connection`, `PreparedStatement`..., qui sont dans le JDK. Le pilote ne sert qu'à l'exécution.

### Exercice 2. Lancer SANS le pilote (l'erreur à connaître)

```
& "$J\java.exe" -cp build fr.voltoise.serveur.ServeurBornes essai.properties
```
Dans une autre fenêtre : `ncat 127.0.0.1 5011`, puis tape `HELLO CGY-01-B01`.

**Tu dois voir :**
```
<- ERR 503 service indisponible           (dans ncat)
base inaccessible : No suitable driver found for jdbc:postgresql://localhost:54329/voltoise   (journal)
```
**À comprendre :** le serveur démarre quand même, car la connexion à la base ne s'ouvre qu'à l'arrivée d'une borne (`new AccesBase()` dans `run()`). `DriverManager` cherche un pilote qui comprend les adresses `jdbc:postgresql:`, n'en trouve aucun, et lève une `SQLException`, que `run()` transforme en `ERR 503`.

Arrête le serveur avec Ctrl+C.

### Exercice 3. Lancer AVEC le pilote

```
& "$J\java.exe" -cp "build;dist\lib\postgresql-42.7.4.jar" fr.voltoise.serveur.ServeurBornes essai.properties
```
Dans ncat : `HELLO CGY-01-B01`, puis `BYE`.

**Tu dois voir :** `OK 1001 libre` puis `OK au revoir`.

**À comprendre :** `-cp` (classpath) donne à Java la liste des endroits où chercher les classes. Sous Windows, on les sépare par `;` (sous Linux, en salle, c'est `:`). Le pilote est maintenant trouvé.

### Exercice 4. Comprendre le .jar

Un .jar est un fichier zip qui contient les .class et un fichier de description, le **manifeste**.
```
& "$J\jar.exe" tf dist\serveur-bornes.jar
```
**Tu dois voir** `META-INF/MANIFEST.MF` et les 5 .class.

Pour lire le manifeste :
```
mkdir lecture ; cd lecture
& "$J\jar.exe" xf ..\dist\serveur-bornes.jar META-INF/MANIFEST.MF
Get-Content META-INF\MANIFEST.MF
cd .. ; Remove-Item lecture -Recurse
```
**Tu dois voir :**
```
Main-Class: fr.voltoise.serveur.ServeurBornes
Class-Path: lib/postgresql-42.7.4.jar
```
**À comprendre :**
- `Main-Class` : la classe dont `java -jar` lance le `main`, sans qu'on ait à la nommer ;
- `Class-Path` : le pilote, cherché **à côté du .jar**, dans `lib\`. C'est pour ça que `dist\` contient `serveur-bornes.jar` ET `lib\postgresql-42.7.4.jar`. Si tu copies le .jar seul sur une autre machine, tu retombes sur l'erreur de l'exercice 2.

C'est exactement ce que fait `construire.sh` : compiler (exercice 1), écrire ce manifeste, puis `jar cfm dist/serveur-bornes.jar MANIFEST.MF -C build fr`.

### Exercice 5. Changer de port sans recompiler

Dans `essai.properties`, mets `port=5012`, relance (exercice 3), puis `python ..\client-python\borne.py CGY-01-B01 --port 5012`. La charge passe. Rien n'a été recompilé : c'est le critère « IP et ports paramétrables ».

### Exercice 6. Ajouter une commande toi-même

**Objectif :** la commande `PING` doit répondre `OK pong`, même avant HELLO.

**Indices :**
1. dans `DialogueBorne.dialoguer()`, regarde la condition qui envoie `ERR 403 identification requise` : qui a le droit de passer avant HELLO ?
2. regarde le `switch` : ajoute un `case "PING"` ;
3. écris une méthode `ping()` sur le modèle de `bye()`, mais qui renvoie `true` (le dialogue continue) ;
4. recompile (exercice 1), relance (exercice 3), teste avec ncat : `PING`.

Essaie seul. La solution est en fin de fiche.

**Pour aller plus loin :** ajoute `PING` aussi dans `FORMATS` de `borne.py`, sinon le mode `-i` refusera de l'envoyer. Pourquoi ?

### Exercice 7. Refaire le TP en salle, de zéro

Sur trois postes, dans l'ordre :
1. **poste serveur :** `git clone https://github.com/Patrickleondev/ProjetBD_SAE_Cergy`, puis `cd ProjetBD_SAE_Cergy/reseau/serveur-java`, `cp serveur.properties.exemple serveur.properties`, remplir `db.url=jdbc:postgresql://devwebdb2.etu:5432/<ta base>`, `db.user`, `db.password`, puis `cd dist` et `sh lancer_serveur.sh`. Note l'IP du poste avec `ip a` ;
2. **poste client :** `python3 borne.py CGY-01-B01 --hote <IP du serveur> --badge 1` ;
3. **poste de test :** `nc <IP du serveur> 5000`, et les commandes de la partie 5.

Si tu vois `ERR 503` : relis le journal du serveur. « Connection refused » veut dire que `db.url` ne pointe pas sur devwebdb2.etu ; « No suitable driver » veut dire que `lib/` n'est pas à côté du .jar.

Quand tu as fini, supprime tes essais : `Remove-Item build, essai.properties -Recurse`.

### Solution de l'exercice 6

```java
// dans dialoguer() : PING passe avant l'identification, comme HELLO et BYE
if (!commande.equals("HELLO") && !commande.equals("BYE") && !commande.equals("PING") && borne == null) {

// dans le switch
case "PING"   -> ping();

// nouvelle méthode
private boolean ping() throws IOException {
    envoyer("OK pong");
    return true;
}
```
Pour `borne.py` : `verifier()` refuse toute commande absente de `FORMATS`. Il faut ajouter `"PING": (None, "PING"),`.

**Ne garde pas ce changement dans le projet rendu** : il n'est ni dans le protocole du rapport ni dans les diagrammes. Pour revenir en arrière, recopie `DialogueBorne.java` depuis le zip de `rendus\`.
