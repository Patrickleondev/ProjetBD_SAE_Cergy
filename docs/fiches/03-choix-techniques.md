# 3. Les choix techniques, et pourquoi

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

Le sujet impose peu de choses et laisse le reste libre. Chaque case laissée libre est une
question de soutenance. Voici nos réponses.

## Ce que le sujet impose

- PostgreSQL, pas d'autre SGBDR
- le client réseau et le serveur réseau dans **deux langages différents**, tous deux pris
  parmi C, Java et Python
- le site web en PHP, ou en Python après accord
- au moins 10 tables, entre 40 et 50 attributs à titre indicatif
- des associations 1-N, des associations N-N, et de l'héritage
- 8 à 10 requêtes SELECT, dont des imbriquées, des agrégats, un GROUP BY
- des tests avec **netcat**, en client et en serveur

## Serveur applicatif en Java

Parce que **JDBC n'existe que dans le monde Java**. Le sujet recommande « ODBC ou JDBC
selon les cas ». En choisissant Java, on utilise JDBC sans avoir à justifier quoi que ce
soit.

Si on avait pris Python côté serveur, on aurait utilisé `psycopg2`, qui est le bon pilote
techniquement, mais qui n'est ni ODBC ni JDBC. Il aurait fallu l'écrire et le défendre dans
la section « choix techniques ». Faisable, mais pourquoi se créer une discussion.

Pilote : `postgresql-42.x.jar`, à mettre dans le classpath.

## Client réseau en Python

D'abord parce qu'il **doit** être différent du serveur, c'est la règle.

Ensuite parce qu'on va devoir simuler plusieurs bornes en même temps pour les tests de
robustesse. En Python, lancer dix bornes en parallèle tient en quinze lignes avec
`threading`. En Java, c'est plus long à écrire pour le même résultat.

## TCP et pas UDP

Une session de charge s'ouvre, dure une heure, puis se ferme. L'ordre des messages compte :
recevoir « fin de charge » avant « début de charge » n'a aucun sens. Et un message perdu,
c'est une facture fausse.

TCP garantit l'ordre et la livraison. En UDP, le sujet nous obligerait à gérer nous-mêmes
les pertes et les désynchronisations, donc à réimplémenter la moitié de TCP, pour aucun
gain.

**Attention au piège** : TCP garantit que les octets arrivent dans l'ordre, pas qu'ils
arrivent groupés comme on les a envoyés. Deux `send` peuvent arriver en un seul `recv`, ou
l'inverse. C'est pour ça que notre protocole termine chaque message par un `\n` et qu'on lit
ligne par ligne. Sans ça, ça marche sur nos machines et ça casse au premier vrai test.

## Un protocole en texte, une commande par ligne

Le sujet attend des tests avec netcat, en client comme en serveur. Avec un protocole
binaire, impossible de taper une commande à la main.

Avec du texte, on peut faire ça, et c'est exactement ce qu'on montrera dans la vidéo :

```
nc 192.168.20.10 5000
HELLO CGY-05-B03
OK 4471
```

Encodage UTF-8, un message par ligne, fin de ligne `\n`, champs séparés par des espaces.

## Accès à la base, toujours en requêtes préparées

Côté Java, `PreparedStatement`. Côté PHP, `PDO` avec des paramètres nommés.

Jamais de concaténation de chaînes pour construire du SQL. C'est ce qui rend vraie la
phrase qu'on met dans le rapport : « le client envoie des paramètres, jamais du SQL ». Un
client qui enverrait `CGY-05-B03'; DROP TABLE session_charge; --` ne casserait rien.

## L'authentification du site web

Le sujet demande une table d'utilisateurs « a priori indépendante de votre analyse », avec
login, mot de passe chiffré et gestion des sessions.

On a donc deux notions distinctes, et c'est volontaire :

- `UTILISATEUR`, le client de l'opérateur, avec son nom, ses véhicules, ses factures ;
- `COMPTE_WEB`, un compte de connexion rattaché à un utilisateur, avec son login et
  l'empreinte de son mot de passe.

Mot de passe haché avec `password_hash()` en PHP, qui utilise bcrypt. Jamais de mot de
passe en clair ni de MD5, même « pour tester ».

**Ce compte ne concerne que le web.** Le sujet le dit explicitement : pas d'authentification
dans la partie réseau. La borne s'**identifie** (elle dit qui elle est), elle ne
s'**authentifie** pas (elle ne prouve rien). Cette distinction est dans le sujet, elle
tombera en question.

## Récapitulatif à recopier en soutenance

| Composant | Choix | Raison en une phrase |
|---|---|---|
| SGBDR | PostgreSQL 16 | imposé, et il a les types dont on a besoin |
| Serveur applicatif | Java 17 | seul langage autorisé qui donne accès à JDBC |
| Client réseau | Python 3.13 | différent du serveur, et rapide pour simuler des bornes |
| Transport | TCP | une session s'ouvre et se ferme, l'ordre compte |
| Accès base | JDBC, PreparedStatement | pas d'injection SQL possible |
| Site web | PHP 8, PDO pgsql | recommandation du sujet |
| Format des messages | texte, une ligne par message | testable à la main avec netcat |
