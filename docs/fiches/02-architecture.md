# 2. L'architecture, qui parle à qui

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

## Les cinq machines

| Machine | Rôle | Écrite en |
|---|---|---|
| Client réseau | la borne de recharge, sans écran | Python |
| Serveur applicatif | reçoit les messages des bornes | Java |
| Serveur de base de données | stocke tout | PostgreSQL |
| Serveur web | le site des clients et des gestionnaires | PHP |
| Navigateur | ce que voit l'utilisateur | HTML, CSS, JS |

## Les quatre liaisons, et une règle

```
  borne (Python) ──── protocole maison, TCP 5000 ────► serveur applicatif (Java)
                                                              │ JDBC, TCP 5432
                                                              ▼
                                                        PostgreSQL
                                                              ▲
                                                              │ PDO, TCP 5432
  navigateur ──────── HTTPS, TCP 443 ────────────────► serveur web (PHP)
```

La règle à retenir, et à savoir défendre : **aucun poste utilisateur ne parle directement
à la base**. Ni la borne, ni le navigateur. Toute requête SQL part d'un serveur.

Pourquoi ? Parce qu'une borne est sur un parking public. Si elle possédait les identifiants
de la base, il suffirait de l'ouvrir pour les lire, et on aurait accès à toutes les données
de tous les clients. Le serveur applicatif sert de sas : il reçoit des paramètres, il
décide, et lui seul écrit dans la base.

## Les deux vues du schéma

Le fichier `rapport/architecture_reseau.drawio` contient deux onglets. Ils ne racontent pas
la même chose, il ne faut pas les confondre.

**Architecture physique.** Internet, le routeur, le pare-feu, les commutateurs, les deux
segments réseau. Les traits gris sont des câbles. C'est le plan d'un installateur.

**Flux applicatifs.** Les mêmes machines, sans les câbles, avec des flèches qui portent le
protocole et le port. C'est le plan d'un développeur.

Le sujet demande un « schéma d'architecture contextualisé », et précise bien de ne pas le
confondre avec une architecture logicielle. On ne décrit donc pas nos classes Java ni nos
fichiers PHP, on décrit des machines, des adresses et des protocoles.

## Le plan d'adressage

À adapter si on déploie ailleurs, mais il faut des valeurs concrètes dans le rapport.

| Segment | Adresses | Qui s'y trouve |
|---|---|---|
| LAN utilisateurs | 192.168.10.0/24 | la borne, le poste du navigateur |
| Segment serveurs | 192.168.20.0/24 | serveur applicatif, serveur web, PostgreSQL |

| Machine | IP | Port en écoute |
|---|---|---|
| Serveur applicatif | 192.168.20.10 | 5000 TCP |
| Serveur web | 192.168.20.20 | 443 TCP |
| PostgreSQL | 192.168.20.30 | 5432 TCP |

Note : on avait mis un serveur DNS au départ, la prof a dit de ne pas aller jusque-là. Il
a été retiré des deux schémas.

## En développement, tout tourne sur nos machines

Les adresses ci-dessus décrivent la cible. Pour développer, chacun lance tout sur son PC :
le serveur applicatif écoute sur `localhost:5000`, PostgreSQL sur `localhost:5432`. Le
schéma reste vrai, seules les adresses changent. Ça se dit en une phrase dans le rapport et
ça évite la question « vous avez vraiment cinq machines ? ».
