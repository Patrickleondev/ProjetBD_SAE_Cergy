# 15. Mettre le site sur devweb.etu, et les trois pannes du 24/11

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

**Ce qu'on a appris en salle le 24/11/2026.** Cette fiche remplace la partie 7 de la fiche 13 :
le site ne se publie **pas** depuis le `public_html` de la salle.

---

## 1. Ce qu'il faut savoir avant de commencer

**devweb.etu a son propre disque.** Le dossier personnel de la salle (`/home/student/40/...`) n'est
**pas** celui que publie le serveur web. Les fichiers qu'on met dans `~/public_html` en salle ne
sont donc jamais visibles sur le site.

**On envoie les fichiers avec sftp.** devweb.etu refuse `ssh` (« This service allows sftp
connections only ») et accepte seulement `sftp`.

**Une fois connecté en sftp, ton espace est enfermé :**
- `/` est ta racine (`pwd` affiche `/`) ;
- il contient déjà un dossier **`public_html`**, qui t'appartient avec le groupe `www-data` (le
  serveur web) ;
- il est déjà bien réglé : **on ne fait ni `mkdir` ni `chmod` dessus**.

**L'adresse du site** se construit avec le login de la salle, `<login>` :
```
https://devweb.etu/~<login>/
```

---

## 2. Publier le site, pas à pas

### Étape 1 : préparer les fichiers sur le poste de la salle

```bash
cp -r ~/voltoise/web ~/public_html/voltoise          # si ce n'est pas déjà fait
cd ~/public_html/voltoise/app
cp config.exemple.php config.local.php
nano config.local.php                                # host devwebdb2.etu, port 5432,
                                                     # dbname <base>,
                                                     # user <base>, mot de passe
chmod -R o+rX ~/public_html/voltoise                 # lisible par le serveur web
```

**Pourquoi le `chmod` :** les fichiers sont créés lisibles par toi seul (`-rw-------`). Le serveur
web tourne sous un autre compte. Sans ce `chmod`, il répond **Forbidden (403)**.

### Étape 2 : envoyer avec sftp

```bash
cd ~/public_html
sftp <login>@devweb.etu
```
À l'invite `sftp>` :
```
cd public_html
put -rp voltoise
ls -l
bye
```
- **`-r`** envoie le dossier et tout ce qu'il contient.
- **`-p`** garde les droits (644 pour les fichiers, 755 pour les dossiers).
- Avec `ls -l`, on vérifie que `voltoise` est en `drwxr-xr-x`.

### Étape 3 : vérifier

Ouvre `https://devweb.etu/~<login>/voltoise/` : la page de connexion s'affiche.

**Pour tester juste PHP** (utile la première fois), envoie un petit fichier :
```bash
echo '<?php echo "ok " . phpversion();' > ~/public_html/test.php
chmod 644 ~/public_html/test.php
```
Puis, en sftp : `cd public_html`, `put -p test.php`. Ouvre `.../~<login>/test.php` : il doit
afficher `ok 8.x`. Supprime-le ensuite en sftp avec `rm test.php`.

### Pour mettre à jour un seul fichier plus tard

On modifie le fichier sur le poste de la salle, puis on le renvoie :
```
sftp <login>@devweb.etu
cd public_html
put -p voltoise/espace.php voltoise/espace.php
bye
```

**Les commandes sftp utiles :**

| commande | effet |
|---|---|
| `ls -l` | liste le dossier **distant** (sur devweb) |
| `lls -l` | liste le dossier **local** (en salle) |
| `cd`, `lcd` | changer de dossier, distant ou local |
| `put -p fichier` | envoyer un fichier |
| `put -rp dossier` | envoyer un dossier entier |
| `rm fichier` | supprimer un fichier distant |
| `chmod 644 fichier` | changer les droits d'un fichier distant |
| `bye` | quitter |

---

## 3. Les trois pannes de la séance, et comment les reconnaître

### Panne 1 : `ERR 503 service indisponible` à chaque commande du client

**Ce que ça veut dire :** le serveur Java fonctionne, mais **il n'arrive pas à se connecter à la
base**. Le réseau et le client ne sont pas en cause.

**Où lire la cause :** dans le terminal du serveur, sur la ligne `base inaccessible : ...`. Le
24/11, elle disait :
```
Connection to <IP>:5433 refused
```
`db.url` contenait l'adresse **du poste de la salle**, où aucun PostgreSQL ne tourne.

**La correction**, dans `reseau/serveur-java/serveur.properties` :
```
db.url=jdbc:postgresql://devwebdb2.etu:5432/<base>
db.user=<base>
db.password=<mot de passe de la base>
```
Puis on vérifie avec `psql`, et on **relance** le serveur (il ne lit sa configuration qu'au
démarrage) :
```bash
psql -h devwebdb2.etu -U <base> -d <base> -c "SELECT count(*) FROM borne;"
cd ~/voltoise/reseau/serveur-java/dist && sh lancer_serveur.sh
```

### Panne 2 : `ERR 404 session inconnue sur cette borne` au PAYE

**Ce que ça veut dire :** le nombre tapé après `PAYE` n'est pas l'id d'une session de cette borne.

**Le piège :** le nombre renvoyé par `HELLO` est le **numéro du dialogue** (le `#1003` du
journal), pas l'id de la session. L'id de la session est **la réponse au START** (on le retrouve en
premier dans la réponse au STOP) :
```
HELLO CGY-01-B01       → OK 1003 libre        (numéro du dialogue)
START 1                → OK 42                (42 = id de la session)
STOP 15                → OK 42 7.40 CB
PAYE 42 CB-1234        → OK 42 payee
```
- **La référence CB** s'invente, mais elle doit avoir 4 à 16 caractères après `CB-` (chiffres ou
  majuscules). `CB-1` est refusé avec `ERR 400`.
- **Le PAYE se fait dans la même connexion** que le START et le STOP.
- **Pour retrouver un id dans la base :** `SELECT id_session, id_borne, statut, ref_paiement FROM
  session_charge ORDER BY id_session DESC LIMIT 5;`

### Panne 3 : Forbidden sur le site, même sur `test.php`

**Ce que ça veut dire :** les fichiers étaient dans le `public_html` **de la salle**, qui n'est pas
publié. Les droits n'étaient pas le vrai problème.

**La correction :** envoyer les fichiers par sftp dans le `public_html` de devweb.etu (partie 2).

### Panne 4 : « Erreur 500, une erreur est survenue » sur une page du site

**Ce que ça veut dire :** une requête SQL ou une ligne PHP a échoué. Le site écrit le détail dans
le journal d'Apache, **illisible sur devweb**.

**Voir le vrai message** (le code est sur GitHub depuis le 24/11, commit « Show the error detail
in debug mode ») :
1. `'debug' => true` dans `config.local.php` ;
2. on renvoie ce fichier par sftp et on recharge la page : le message exact s'affiche, avec le
   fichier et la ligne ;
3. une fois corrigé, `'debug' => false`, et on renvoie le fichier.

**Le cas du 24/11 (connexion de claire.martin, puis 500 sur `espace.php`) :** la connexion marchait,
donc la base répondait. La cause probable : le serveur de la fac a un PostgreSQL **antérieur à
la version 14**, où `EXTRACT(EPOCH ...)` renvoie un `double precision`. `ROUND(x, 2)` n'existe pas
pour ce type. Chez moi (PostgreSQL 18), tout marchait. **Corrigé sur GitHub** (commit « Cast EXTRACT
to numeric ») : on écrit `EXTRACT(...)::numeric`. Pour connaître la version de la fac :
`psql -h devwebdb2.etu -U <base> -d <base> -c "SELECT version();"`.

---

## 3 bis. Récupérer une correction faite sur mon PC (GitHub)

Le poste de la salle n'a **que ce qui est sur GitHub**. Quand une correction est faite chez moi,
elle est poussée sur GitHub, puis en salle :

```bash
cd ~/voltoise
git pull                                             # récupère la correction
cp -r ~/voltoise/web/. ~/public_html/voltoise/       # met à jour la copie à envoyer
chmod -R o+rX ~/public_html/voltoise
```
Le `cp` **n'écrase pas** `config.local.php` : ce fichier n'est pas sur GitHub, il n'existe que dans
`public_html`. Ensuite, on renvoie ce qui a changé par sftp (partie 2), par exemple
`put -p voltoise/app/bootstrap.php voltoise/app/bootstrap.php`, ou tout le site avec `put -rp voltoise`.

---

## 4. La liste de contrôle d'une séance

1. `serveur.properties` pointe sur **devwebdb2.etu:5432**. On le teste avec `psql`, puis on lance
   le serveur.
2. Le client voit `HELLO`, puis `TARIF`, et aucun `ERR 503`.
3. Pour les tests `nc`, on prend l'id de session **du START**, avec une référence `CB-1234`.
4. Le site est sur devweb **par sftp**, dans `public_html`, avec les droits `-rp`.
5. En fin de séance, on efface les mots de passe du poste de la salle :
   - `serveur.properties` ;
   - `config.local.php` dans `~/public_html` en salle.

   La copie sur devweb, elle, reste.
