# 13 : La séance en salle de TP, pas à pas, sur trois postes

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

**Il y aura bien une version 5 :** c'est le rapport définitif, rendu le 29/11. Cette fiche couvre la séance en salle : refaire tous les tests depuis le début sur trois postes, faire les captures pour la v5 et la vidéo réseau, et mettre le site en ligne sur `devweb.etu` pour avoir le lien à rendre.

Les anciennes photos du 19/10 ne servent plus : celles de cette séance les remplacent.

---

## 1. Les trois postes

```
  POSTE CLIENT                POSTE SERVEUR                     INTRANET
  borne.py (Python) ──TCP 5000──► serveur Java ──TCP 5432──► base PostgreSQL
                                     ▲                          (devwebdb2.etu)
  POSTE TEST                         │                               ▲
  nc (client ou serveur) ────────────┘                               │
  navigateur : site PHP sur devweb.etu, psql pour vérifier ──────────┘
```

| Poste | Ce qui tourne | Son rôle |
|---|---|---|
| **SERVEUR** | le serveur Java, journal visible en grand | reçoit les commandes, seul à écrire dans la base pour les bornes |
| **CLIENT** | `borne.py` | une vraie borne, qui envoie toujours des commandes correctes |
| **TEST** | `nc`, `psql`, le navigateur | envoie ce qu'une borne n'enverrait jamais, joue le serveur pour tester la borne, vérifie la base et le site |

Note dès le début, sur un papier, l'IP du poste SERVEUR (`IP_S`) et celle du poste TEST (`IP_T`).

## 2. Avant de partir de chez toi

- [ ] Le code est sur GitHub : https://github.com/Patrickleondev/ProjetBD_SAE_Cergy (public, pas besoin de te connecter).
- [ ] Si tu as ajouté le `catch (BindException e)` (fiche 12, partie 9) : `sh reseau/serveur-java/construire.sh` dans Git Bash, puis `git add`, `git commit`, `git push`. Sinon, T17 montrera le problème tel quel, et c'est un résultat honnête.
- [ ] Ton mot de passe de l'intranet, sur toi, pas dans un fichier.
- [ ] Une clé USB ou ton adresse mail, pour récupérer les captures.

## 3. Sur les trois postes : récupérer le code (5 min)

```bash
cd ~
git clone https://github.com/Patrickleondev/ProjetBD_SAE_Cergy.git voltoise
cd voltoise
java -version          # SERVEUR : 17 ou plus (la salle a OpenJDK 21)
python3 --version      # CLIENT
nc -h 2>&1 | head -2   # TEST : « OpenBSD netcat » ou « [v1.10] »
psql --version         # SERVEUR et TEST
hostname -I            # la première adresse = l'IP du poste
```

Selon la version de netcat (poste TEST) :
- **OpenBSD :** écouter avec `nc -l 5000` ;
- **traditionnel :** écouter avec `nc -l -p 5000`.

## 4. Poste SERVEUR : la base (10 min)

Repars d'une base propre (le script commence par des `DROP`) :

```bash
cd ~/voltoise
psql -h devwebdb2.etu -U <base> -d <base> -f sql/01_creation.sql
psql -h devwebdb2.etu -U <base> -d <base> -f sql/03_tests_contraintes.sql
psql -h devwebdb2.etu -U <base> -d <base> -c "SELECT code_borne, etat FROM borne ORDER BY 1;"
```

**Change le mot de passe par défaut de ta base.** Tous les étudiants connaissent `A123456*` : n'importe qui pourrait modifier ta base, et le site y écrit. Dans `psql`, tape `\password`, puis choisis un nouveau mot de passe. C'est lui que tu mettras dans les fichiers de configuration plus bas.

```bash
psql -h devwebdb2.etu -U <base> -d <base>
\password
\q
```

| Capture | Ce qu'elle montre |
|---|---|
| `B01_creation.png` | la fin de `01_creation.sql`, sans erreur |
| `B02_tests_contraintes.png` | les lignes `OK` des tests de contraintes |
| `B03_bornes_avant.png` | l'état des bornes avant les tests |

## 5. Poste SERVEUR : lancer le serveur Java (5 min)

```bash
cd ~/voltoise/reseau/serveur-java
cp serveur.properties.exemple serveur.properties
nano serveur.properties
```

Dans `nano`, remplace les trois lignes `db.` par :

```
db.url=jdbc:postgresql://devwebdb2.etu:5432/<base>
db.user=<base>
db.password=<ton nouveau mot de passe>
```

Enregistre avec Ctrl+O puis Entrée, quitte avec Ctrl+X, puis :

```bash
sh dist/lancer_serveur.sh
```

Attendu : `serveur en ecoute sur le port 5000`. Garde ce terminal grand et visible pendant toute la séance : chaque ligne reçue (`<-`) et envoyée (`->`) s'y affiche.

**Tout de suite après**, sur le poste TEST, ouvre une connexion et ne tape rien : `nc IP_S 5000`. Laisse-la ouverte. Au bout de 5 minutes, le serveur la fermera : c'est le test T13 (client trop lent), qui se fait pendant que tu enchaînes les autres.

## 6. Les tests, dans l'ordre

Le jeu de données :
- **Badges :** 1 = particulier actif, 4 = bloqué, 6 = entreprise.
- **Bornes :** `CGY-01-B03` est occupée ; `CGY-01-B01`, `CGY-01-B02`, `CGY-02-B01`, `PTS-01-B01` et `SOA-01-B02` sont libres.

Pour chaque test :
- fais la capture du poste indiqué et celle du journal du serveur ;
- note la réponse exacte ;
- si elle diffère de l'attendu, ne corrige rien sur place : note-la, c'est un résultat.

### A. Fonctionnement, avec la vraie borne (poste CLIENT)

| Test | Commande (poste CLIENT) | Attendu | Captures |
|---|---|---|---|
| T01 charge d'un particulier | `python3 reseau/client-python/borne.py CGY-01-B01 --hote IP_S --badge 1` | HELLO, TARIF public, START, TARIF abonné, STOP `... CB`, PAYE `OK ... payee`, BYE | `T01_client.png`, `T01_serveur.png` |
| T02 charge d'une entreprise | `... borne.py CGY-01-B02 --hote IP_S --badge 6` | STOP `... FACTURE`, pas de PAYE | `T02_client.png`, `T02_serveur.png` |
| T03 badge bloqué | `... borne.py CGY-02-B01 --hote IP_S --badge 4` | `ERR 403`, puis BYE | `T03_client.png` |
| T04 borne inconnue | `... borne.py XXX-99 --hote IP_S` | `ERR 401`, le serveur ferme | `T04_client.png`, `T04_serveur.png` |
| T05 borne occupée | `... borne.py CGY-01-B03 --hote IP_S` | HELLO répond `occupee`, la borne s'arrête | `T05_client.png` |
| T06 deux bornes en même temps | `python3 reseau/client-python/borne.py PTS-01-B01 --hote IP_S & python3 reseau/client-python/borne.py SOA-01-B02 --hote IP_S --badge 6 & wait` | deux dialogues aux numéros différents dans le journal, les deux aboutissent | `T06_serveur.png` |

### B. netcat en client, face au serveur (poste TEST)

| Test | Ce que tu tapes après `nc IP_S 5000` | Attendu | Capture |
|---|---|---|---|
| T07 commande avant identification | `START 1` | `ERR 403` | `T07.png` |
| T08 commande inconnue | `HELLO CGY-02-B01`, puis `BONJOUR` | `ERR 400`, tracée dans le journal | `T08.png` |
| T09 ordre incorrect | (même connexion) `STOP 10` sans START | `ERR 409` | `T09.png` |
| T10 entreprise qui veut payer | Ctrl+C, relance `nc IP_S 5000`, puis `HELLO CGY-02-B01`, `START 6`, `STOP 15` (réponse `OK <id> <montant> FACTURE`), puis `PAYE <id> CB-1234` avec le premier nombre de la réponse | `FACTURE`, puis `ERR 409` au PAYE | `T10.png` |
| T11 ligne trop longue | `python3 -c "print('A'*2000)" \| nc IP_S 5000` | `ERR 413`, fermeture | `T11.png`, `T11_serveur.png` |
| T12 coupure en pleine charge | `HELLO CGY-01-B01`, `START 1`, puis Ctrl+C | journal : session `interrompue`, borne `libre` | `T12_serveur.png` |

Après T12, vérifie la base depuis le poste TEST :

```bash
psql -h devwebdb2.etu -U <base> -d <base> -c "SELECT id_session, statut FROM session_charge ORDER BY id_session DESC LIMIT 3;"
psql -h devwebdb2.etu -U <base> -d <base> -c "SELECT code_borne, etat FROM borne WHERE code_borne = 'CGY-01-B01';"
```

Capture : `T12_base.png`.

**T13 (client trop lent) :** la connexion ouverte à la partie 5 doit avoir été fermée par le serveur. Capture du journal : `T13_serveur.png`.

### C. netcat en serveur, face à la borne (TEST et CLIENT)

Arrête le serveur Java (Ctrl+C sur le poste SERVEUR). Sur le poste TEST : `nc -l 5000` (ou `nc -l -p 5000`).

| Test | Poste CLIENT | Sur le poste TEST | Attendu | Capture |
|---|---|---|---|---|
| T14 la borne suit le protocole | `borne.py CGY-01-B01 --hote IP_T` | à chaque ligne reçue, tape dans l'ordre : `OK 1 libre`, `OK 0.49 0.05 Public`, `OK 99`, `OK 0.35 0.00 Abonné`, `OK 99 4.20 CB`, `OK 99 payee`, `OK au revoir` | la borne va jusqu'au bout | `T14_test.png`, `T14_client.png` |
| T15 serveur trop lent | `borne.py CGY-01-B01 --hote IP_T` | ne réponds rien | après 10 s : `connexion perdue : timed out` | `T15_client.png` |
| T16 serveur qui coupe | `borne.py CGY-01-B01 --hote IP_T` | réponds `OK 1 libre`, puis Ctrl+C | `connexion perdue : le serveur a fermé la connexion` | `T16_client.png` |

### D. Les erreurs réseau courantes

Relance le serveur Java (`sh dist/lancer_serveur.sh`).

| Test | Où | Commande | Attendu | Capture |
|---|---|---|---|---|
| T17 port déjà pris | SERVEUR, 2e terminal | `sh dist/lancer_serveur.sh` | un message clair (ou la pile Java si tu n'as pas corrigé : note-le) | `T17.png` |
| T18 serveur absent | CLIENT, serveur arrêté | `borne.py CGY-01-B01 --hote IP_S` | `connexion impossible ... refused` | `T18.png` |
| T19 nom inconnu | CLIENT | `borne.py CGY-01-B01 --hote serveur.inexistant` | `connexion impossible ...` | `T19.png` |
| T20 base arrêtée | impossible à simuler sur l'intranet | | à expliquer à l'oral : `ERR 503` | |

## 7. Mettre le site en ligne sur devweb.etu

### 7.1 Trouver où publier (5 min)

Sur n'importe quel poste :

```bash
ls -d ~/public_html ~/www ~/html 2>/dev/null
```

Si un de ces dossiers existe, vérifie qu'il est bien publié par `devweb.etu`, et quelle version de PHP y tourne :

```bash
echo '<?php echo "ok " . phpversion();' > ~/public_html/test.php
```

Ouvre `https://devweb.etu/~<login>/test.php` dans le navigateur.

- **Tu vois `ok 8.x` ou `ok 7.4` :** c'est bon, passe à la suite.
- **Tu vois `ok 7.2` ou moins :** le site ne marchera pas, il demande PHP 7.3 au moins. Dis-le-moi.
- **Erreur 404 ou rien :** l'adresse ou le dossier ne sont pas les bons. Demande à l'encadrant où déposer les pages et à quelle adresse, et ne cherche pas plus loin seul.

Ensuite, supprime le test : `rm ~/public_html/test.php`.

### 7.2 Copier le site et le configurer (10 min)

```bash
cp -r ~/voltoise/web ~/public_html/voltoise
cd ~/public_html/voltoise/app
cp config.exemple.php config.local.php
nano config.local.php
```

Dans `nano`, remplis :

```php
'host'     => 'devwebdb2.etu',
'port'     => '5432',
'dbname'   => '<base>',
'user'     => '<base>',
'password' => '<ton nouveau mot de passe>',
```

Laisse `'debug' => false`. Enregistre et quitte.

### 7.3 Les droits des fichiers

Le serveur web doit pouvoir lire les pages, mais personne d'autre ne doit lire ton mot de passe :

```bash
chmod 711 ~ ~/public_html
chmod -R o+rX ~/public_html/voltoise
chmod 600 ~/public_html/voltoise/app/config.local.php
```

Ouvre `https://devweb.etu/~<login>/voltoise/`.

- **La page de connexion s'affiche :** c'est bon.
- **Erreur 500 ou « Configuration absente » :** le serveur web ne peut pas lire `config.local.php` avec les droits 600. Fais `chmod 644 ~/public_html/voltoise/app/config.local.php` et recharge. Le fichier est alors lisible par les autres comptes de la machine : c'est pour ça qu'il fallait changer le mot de passe par défaut à la partie 4.
- **La page dit que la base ne répond pas :** vérifie host, dbname, user et password dans `config.local.php`.

### 7.4 Vérifier le site, et faire ses captures

Le mot de passe des trois comptes est `<mot de passe de démo>`.

| Test | Compte | À faire | Attendu | Capture |
|---|---|---|---|---|
| W01 connexion refusée | `claire.martin` | mauvais mot de passe | « Identifiant ou mot de passe incorrect. » | `W01.png` |
| W02 espace client | `claire.martin` | se connecter | fiche, tarif, badge, 3 charges payées par carte | `W02.png` |
| W03 modification (UPDATE) | `claire.martin` | bloquer le badge, confirmer | message vert ; dans `psql` : `SELECT actif FROM badge WHERE id_badge = 1;` donne `f` | `W03_site.png`, `W03_base.png` |
| W04 effet réseau | poste CLIENT | `borne.py CGY-01-B01 --hote IP_S --badge 1` | `ERR 403 badge bloque` : le site et le serveur partagent la base | `W04.png` |
| W05 réactiver | `claire.martin` | réactiver le badge | message vert, `actif` revient à `t` | |
| W06 entreprise | `transports.durand` | se connecter | compte entreprise, SIRET, montant de la prochaine facture | `W06.png` |
| W07 gestionnaire | `admin.voltoise` | page Réseau, filtre « Hors service » | une borne (CGY-02-B02) | `W07.png` |
| W08 incident (INSERT) | `admin.voltoise` | déclarer un incident sur `CGY-02-B01`, cocher « hors service » | message vert ; la borne passe hors service sur la page Réseau | `W08.png` |
| W09 clore (UPDATE) | `admin.voltoise` | clore cet incident, « remettre en service » coché | la borne redevient libre | `W09.png` |
| W10 chiffres | `admin.voltoise` | page Chiffres, mois de septembre 2026 | 10 charges terminées et 1 interrompue, énergie par station, facturation des entreprises | `W10.png` |
| W11 cloisonnement | `claire.martin` | taper l'adresse `.../voltoise/reseau.php` | renvoyée vers son espace | |

**Le lien à rendre sur Moodle** (« URL de votre site web de démonstration ») est l'adresse qui a marché en 7.3, par exemple `https://devweb.etu/~<login>/voltoise/`. Note aussi dans le rapport les trois comptes de démonstration et leur mot de passe, pour que l'enseignant puisse tester.

Après les tests, recharge la base propre (partie 4, première commande) pour que l'enseignant trouve les données de départ.

## 8. Captures et vidéos

### Les captures
- **Captures d'écran plutôt que photos de l'écran :** la touche Impr. écran du poste les enregistre dans `~/Images`.
- **Les nommer tout de suite** comme dans les tableaux.
- **En fin de séance :** les envoyer par mail ou les copier sur la clé.

### La vidéo réseau (en salle, sur les trois postes)

Vérifie si `ffmpeg` existe (`which ffmpeg`). Si oui, chaque poste filme son propre écran pendant le test :

```bash
ffmpeg -f x11grab -framerate 25 -i :0.0 -t 180 ~/video_SERVEUR.mp4
```

Lance-le sur les trois postes en même temps, puis assemble les vidéos côte à côte dans Clipchamp. Sans `ffmpeg`, filme les trois écrans au téléphone, posé et immobile.

| Temps | Plan | Tests |
|---|---|---|
| 0:00-0:15 | les trois postes, un titre par poste, le diagramme d'échange | |
| 0:15-0:50 | CLIENT + SERVEUR : charge complète, puis entreprise | T01, T02 |
| 0:50-1:15 | CLIENT + SERVEUR : erreurs métier | T03, T04 |
| 1:15-1:50 | TEST + SERVEUR : netcat en client, hors protocole, ligne trop longue | T07, T08, T11 |
| 1:50-2:15 | TEST + SERVEUR : coupure en pleine charge, puis la base qui le confirme | T12 |
| 2:15-2:40 | TEST + CLIENT : netcat en serveur, puis serveur trop lent | T14, T15 |
| 2:40-3:00 | erreurs réseau | T17, T18 |

### La vidéo BD (chez toi ou en salle)

| Temps | Plan |
|---|---|
| 0:00-0:20 | pgAdmin ou `psql` : les 12 tables, les types ENUM |
| 0:20-0:50 | le MCD, puis `client`, `particulier`, `entreprise` : l'héritage |
| 0:50-1:20 | `03_tests_contraintes.sql` : les mauvaises données refusées |
| 1:20-2:00 | trois requêtes : une imbriquée, une avec `GROUP BY`, la facturation des entreprises |
| 2:00-2:45 | le site : connexion, puis W03 (blocage du badge) et sa vérification dans la base, puis W08 (incident) |
| 2:45-3:00 | le site : la page Chiffres |

## 9. Avant de partir de la salle

Ton mot de passe est écrit dans `~/voltoise/reseau/serveur-java/serveur.properties`. Efface le clone sur les trois postes :

```bash
rm -rf ~/voltoise
```

Garde `~/public_html/voltoise` : c'est le site en ligne. Déconnecte-toi de chaque poste.

## 10. Ce que tu m'envoies après la séance

1. Les captures, avec leur nom.
2. Les tests dont la réponse a différé de l'attendu.
3. Le lien du site qui marche, ou la réponse de l'encadrant sur l'hébergement.

Avec ça, je t'aide à écrire la partie Tests et la partie Site de la v5, et à remplir la grille critériée.
