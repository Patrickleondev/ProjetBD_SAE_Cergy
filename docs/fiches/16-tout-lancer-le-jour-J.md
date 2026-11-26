# 16. Tout lancer, de zéro, en salle ou le jour de la soutenance

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

**À qui sert cette fiche :** à toi, seul devant un poste de la salle, sans rien d'installé. Elle
suit **l'ordre exact** dans lequel on démarre, et vient de ce qu'on a vu marcher le 24/11/2026.

**Le principe :**
- le **code** est sur GitHub ;
- la **base** est sur `devwebdb2.etu` ;
- le **site** est déjà en ligne sur `devweb.etu`.

En salle, on ne fait que **récupérer le code** et **lancer le serveur Java**. Le site ne se
redéploie que s'il a changé.

```
  POSTE CLIENT                POSTE SERVEUR                     INTRANET
  borne.py ──────TCP 5000──────► serveur Java ──TCP 5432──► base (devwebdb2.etu)
  POSTE TEST                         ▲                          ▲
  nc, psql, navigateur ──────────────┘                          │
  site PHP : https://devweb.etu/~<login>/voltoise/ ───────────┘
```

---

## 0. À avoir sur soi

- [ ] **Le mot de passe de la base** (celui choisi avec `\password`), retenu ou sur papier, jamais
  dans un fichier.
- [ ] **Le mot de passe de ta session de la fac** (pour sftp).
- [ ] **Les comptes du site :** `claire.martin`, `transports.durand`, `admin.voltoise`, tous avec
  le mot de passe `<mot de passe de démo>`.
- [ ] **Cette fiche**, sur le téléphone (`PDF_telephone/SAE`).

---

## 1. Récupérer le code (2 min, sur chaque poste utilisé)

```bash
cd ~
git clone https://github.com/Patrickleondev/ProjetBD_SAE_Cergy.git voltoise
```
**Si le dossier `~/voltoise` existe déjà** (une séance précédente) : `cd ~/voltoise && git pull`.

**Note l'IP du poste SERVEUR** : sur ce poste, `hostname -I`, puis prends la **première** adresse.
On l'appelle **IP_S** dans la suite. Écris-la sur un papier.

---

## 2. Vérifier la base (2 min, poste SERVEUR)

```bash
psql -h devwebdb2.etu -U <base> -d <base> -c "SELECT code_borne, etat FROM borne ORDER BY 1;"
```
**Attendu :** la liste des bornes.

**Pour repartir de données propres** (conseillé avant une démonstration) :
```bash
cd ~/voltoise
psql -h devwebdb2.etu -U <base> -d <base> -f sql/01_creation.sql
```
**Attention :** ce script **efface tout** et remet les données de départ (bornes, clients, comptes
du site). Tout ce que tu as fait avant disparaît. Le site, lui, continue de marcher, puisqu'il lit
la même base.

---

## 3. Lancer le serveur Java (3 min, poste SERVEUR)

```bash
cd ~/voltoise/reseau/serveur-java
cp serveur.properties.exemple serveur.properties
nano serveur.properties
```
Les trois lignes `db.` doivent être **exactement** :
```
db.url=jdbc:postgresql://devwebdb2.etu:5432/<base>
db.user=<base>
db.password=<mot de passe de la base>
```
Enregistre avec Ctrl+O puis Entrée, quitte avec Ctrl+X, puis :
```bash
cd dist
sh lancer_serveur.sh
```
**Attendu :** `serveur en ecoute sur le port 5000`. **Garde ce terminal grand et visible** : il
affiche chaque message reçu (`<-`) et envoyé (`->`). C'est lui qu'on montre au jury.

**Les pièges :**
- **Ne mets jamais l'IP du poste dans `db.url`.** C'est l'erreur du 24/11, qui donnait `ERR 503`.
- **Le serveur ne lit `serveur.properties` qu'au démarrage.** Après une modification, il faut
  Ctrl+C puis relancer.

---

## 4. Faire une charge avec la borne (1 min, poste CLIENT)

```bash
cd ~/voltoise
python3 reseau/client-python/borne.py CGY-01-B01 --hote IP_S --badge 1
```
**Attendu :** HELLO, TARIF, START, STOP `... CB`, PAYE `OK ... payee`, BYE. Le terminal du
serveur montre tout le dialogue.

**Les autres démonstrations rapides :**

| commande (après `borne.py`) | ce qu'elle montre |
|---|---|
| `CGY-01-B02 --hote IP_S --badge 6` | une entreprise : `FACTURE`, pas de paiement à la borne |
| `CGY-02-B01 --hote IP_S --badge 4` | un badge bloqué : `ERR 403` |
| `XXX-99 --hote IP_S` | une borne inconnue : `ERR 401`, le serveur ferme |

---

## 5. Parler au serveur à la main avec netcat (poste TEST)

```bash
nc IP_S 5000
```
Puis on tape, ligne par ligne (l'id de session est **la réponse au START**) :
```
HELLO CGY-01-B02          → OK 1004 libre      (1004 = numéro du dialogue, pas la session)
START 1                   → OK 42              (42 = id de la session)
STOP 15                   → OK 42 7.40 CB
PAYE 42 CB-1234           → OK 42 payee        (CB- suivi de 4 à 16 chiffres ou majuscules)
BYE                       → OK au revoir
```
**Pour montrer la robustesse :**
- `START 1` avant `HELLO` donne `ERR 403` ;
- `BONJOUR` donne `ERR 400` ;
- `STOP 10` sans START donne `ERR 409`.

---

## 6. Le site (poste TEST, navigateur)

Ouvre **`https://devweb.etu/~<login>/voltoise/`**.

| compte | ce qu'on montre |
|---|---|
| `claire.martin` | son espace, ses charges ; bloquer le badge, puis refaire la charge du § 4 : `ERR 403` (le site et le serveur partagent la base) ; réactiver |
| `transports.durand` | compte entreprise, SIRET, montant de la prochaine facture |
| `admin.voltoise` | page Réseau ; déclarer un incident (la borne passe hors service), le clore ; page Chiffres |

**Le site n'est à renvoyer que si le code a changé** (après un `git pull`) :
```bash
mkdir -p ~/public_html
cp -r ~/voltoise/web/. ~/public_html/voltoise/
chmod -R o+rX ~/public_html/voltoise
cd ~/public_html
sftp <login>@devweb.etu
```
puis, à l'invite `sftp>` :
```
cd public_html
put -rp voltoise
bye
```
`config.local.php` est déjà sur devweb : il n'est pas sur GitHub, et `put` ne l'efface pas. Si tu
pars d'un poste neuf sans ce fichier en salle, ce n'est pas grave, la copie de devweb reste.

---

## 7. Le déroulé de la démonstration (environ 10 min)

1. **Le schéma** des trois postes (en haut de cette fiche), en 30 secondes.
2. **La charge d'un particulier** avec `borne.py` (§ 4), en montrant le journal du serveur.
3. **L'entreprise** (`FACTURE`) et **le badge bloqué** (`ERR 403`).
4. **netcat** (§ 5) : un dialogue complet à la main, puis deux erreurs (`ERR 403`, `ERR 400`).
5. **Le site :** claire.martin bloque son badge, puis la borne est refusée (le lien base, site et
   réseau). Elle le réactive.
6. **admin.voltoise :** un incident déclaré puis clos, et la page Chiffres.
7. **La base :** `psql`, avec la dernière session (`SELECT id_session, statut, ref_paiement FROM
   session_charge ORDER BY id_session DESC LIMIT 3;`).

---

## 8. Si quelque chose ne marche pas

| symptôme | cause | quoi faire |
|---|---|---|
| `ERR 503 service indisponible` | le serveur Java ne joint pas la base | lire `base inaccessible : ...` dans son terminal ; vérifier `db.url` (§ 3) ; tester avec `psql` (§ 2) ; relancer |
| `connexion impossible ... refused` (client) | serveur arrêté, ou mauvaise IP_S | serveur lancé ? `hostname -I` sur le SERVEUR |
| `ERR 404 session inconnue` au PAYE | mauvais id | prendre la réponse au **START** (§ 5) |
| `ERR 400` au PAYE | référence trop courte | `CB-1234`, pas `CB-1` |
| `HELLO` répond `occupee` | une charge précédente est restée ouverte | prendre une autre borne, ou recharger la base (§ 2) |
| le port 5000 est déjà pris | un serveur tourne déjà | Ctrl+C dans l'autre terminal, ou fermer l'ancien terminal |
| site : Forbidden | fichiers non lisibles, ou pas sur devweb | `chmod -R o+rX`, puis `put -rp` (§ 6) |
| site : Erreur 500 | une requête ou une ligne PHP a échoué | `'debug' => true` dans `config.local.php`, le renvoyer, lire le message ; remettre `false` après |
| site : « la base ne répond pas » | mauvais accès dans `config.local.php` | host `devwebdb2.etu`, port `5432`, dbname, user, mot de passe |

---

## 9. Comment on a débogué le 24/11 : la méthode, puis les 5 pannes

### La méthode, en 4 questions

On ne corrige jamais au hasard. À chaque panne, on s'est posé les mêmes questions, dans cet ordre :

1. **Qui a écrit ce message ?** Le client, le serveur Java, PostgreSQL, Apache ou notre site
   PHP ? Un message qui commence par `ERR` vient de **notre serveur Java**. Une page « Une erreur
   est survenue » vient de **notre site**. Un « 403 Forbidden » sans mise en page vient
   d'**Apache**.
2. **Où est le vrai message ?** Celui que voit l'utilisateur est volontairement vague. Le détail
   est ailleurs :
   - pour le serveur Java : la ligne avant `->`, dans son terminal ;
   - pour PHP : le journal d'Apache, ou l'écran en mode debug ;
   - pour la base : la réponse de `psql`.
3. **Qu'est-ce qui marche encore ?** Ça situe la panne. Si le client reçoit une réponse, le réseau
   marche. Si la connexion au site marche, la base répond.
4. **Un test qui isole un seul élément :** `psql` seul pour la base, `nc` seul pour le serveur, un
   `test.php` d'une ligne pour Apache et PHP.

### Panne 1 : `ERR 503 service indisponible` à chaque commande

| question | ce qu'on a fait | ce qu'on a appris |
|---|---|---|
| qui écrit `ERR 503` ? | recherche de « 503 » dans le code | `DialogueBorne.java` : il l'envoie quand `new AccesBase()` échoue, donc quand **la connexion à la base** échoue |
| où est le détail ? | le terminal du serveur, ligne `base inaccessible : ...` | `Connection to <IP>:5433 refused` |
| qu'est-ce qui marche ? | le client reçoit `ERR 503` | le réseau client → serveur marche ; seul serveur → base ne marche pas |
| la cause | <IP> est **le poste lui-même** (le client vient de cette IP), sans PostgreSQL | `db.url` était faux |
| la correction | `db.url=jdbc:postgresql://devwebdb2.etu:5432/<base>`, test `psql`, puis relance | plus de 503 |

### Panne 2 : `ERR 404 session inconnue` au PAYE

| question | ce qu'on a fait | ce qu'on a appris |
|---|---|---|
| qui écrit ce message ? | lecture de `paye()` et de `payerCB()` | 404 si aucune session n'a **cet id sur cette borne** |
| le serveur a-t-il un bug ? | relecture du SQL : la même borne au START et au PAYE | non, c'est l'id tapé qui est faux |
| la cause | le nombre renvoyé par `HELLO` est le numéro du dialogue | le vrai id est la réponse au **START** |
| deuxième piège | la regex de la référence | `CB-` + 4 à 16 caractères ; `CB-1` donne `ERR 400` |

### Panne 3 : Forbidden, même sur `test.php`

| question | ce qu'on a fait | ce qu'on a appris |
|---|---|---|
| 403 ou 404 ? | 403 : le serveur trouve quelque chose mais refuse | on a d'abord pensé aux droits |
| les droits ? | `ls -ld ~ ~/public_html` : 711, déjà bons ; `chmod 644` sur le fichier | toujours 403 : ce n'était pas ça |
| le serveur voit-il nos fichiers ? | `ssh <login>@devweb.etu` | « sftp only » : **devweb a son propre disque** |
| la correction | `sftp`, `cd public_html`, `put -rp voltoise` | le site s'affiche |

**La leçon :** quand une correction logique ne change rien, l'hypothèse est fausse. On remonte d'un
cran : ici, « est-ce le bon serveur ? ».

### Panne 4 : Erreur 500 sur une page du site, sans détail

| question | ce qu'on a fait | ce qu'on a appris |
|---|---|---|
| qui écrit la page ? | c'est notre page d'erreur (`bootstrap.php`) | le détail part dans le journal d'Apache, illisible sur devweb |
| comment voir le détail ? | le gestionnaire d'erreurs affiche maintenant le message si `'debug' => true` | poussé sur GitHub, récupéré en salle par `git pull` |

### Panne 5 : Erreur 500 sur « Mon espace » (claire.martin)

| question | ce qu'on a fait | ce qu'on a appris |
|---|---|---|
| qu'est-ce qui marche ? | la page de connexion accepte le mot de passe | la base répond, PHP marche |
| qu'est-ce qui change entre les deux pages ? | `espace.php` calcule le montant des charges | le calcul `ROUND(... EXTRACT(EPOCH ...) ..., 2)` |
| pourquoi chez moi ça marche ? | ma base est en PostgreSQL 18, celle de la fac est plus ancienne | avant la version 14, `EXTRACT` renvoie un `double precision`, et `ROUND(x, 2)` n'existe pas pour ce type |
| la correction | `EXTRACT(...)::numeric`, poussée sur GitHub | à confirmer en salle avec `SELECT version();` |

**La leçon :** ce qui marche sur ma machine peut casser sur le serveur, à cause d'une **version
différente**. Le premier réflexe est de comparer les versions (`SELECT version();`, `php -v`,
`java -version`).

### Le circuit d'une correction

```
mon PC : je corrige, je teste, git commit, git push
   ↓
salle : cd ~/voltoise && git pull
   ↓
salle : cp -r ~/voltoise/web/. ~/public_html/voltoise/   (pour le site)
        ou relancer le serveur Java                       (pour le serveur)
   ↓
devweb : sftp, put -p du fichier modifié                   (pour le site)
   ↓
on refait le test qui avait échoué
```

---

## 10. Avant de partir

- **Le mot de passe est écrit en clair dans `serveur.properties`.** Efface le clone :
  `rm -rf ~/voltoise`.
- **Efface aussi la copie du site en salle** (elle contient `config.local.php`) :
  `rm -rf ~/public_html/voltoise`. Le site en ligne, sur devweb, **reste**.
- **Si tu as fait des tests,** recharge la base propre (§ 2), pour que l'enseignant trouve les
  données de départ.
- **Déconnecte-toi** de chaque poste.
