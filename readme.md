# Voltoise - SAÉ BD & réseau

Réseau de bornes de recharge. Projet de SAÉ BD & réseau, L3 Informatique.

## Arborescence

```
sql/01_creation.sql      DDL (types ENUM, 11 tables métier + compte_web) + jeu de données
sql/02_requetes.sql      les 10 questions des utilisateurs et leurs requêtes SELECT
sql/03_tests_contraintes.sql  48 tests des contraintes (aucune donnée modifiée)
reseau/serveur-java/     serveur applicatif, Java 17 + JDBC (projet Maven NetBeans)
  dist/                  serveur déjà compilé + pilote PostgreSQL, prêt à lancer
reseau/client-python/    client réseau : une borne simulée, Python 3
web/                     site PHP (en cours)
```

## 1. Base de données

PostgreSQL 18, port **5433** sur la machine de développement.

```
psql -U postgres -p 5433 -c "CREATE DATABASE voltoise"
psql -U postgres -p 5433 -d voltoise -f sql/01_creation.sql
psql -U postgres -p 5433 -d voltoise -f sql/02_requetes.sql
psql -U postgres -p 5433 -d voltoise -f sql/03_tests_contraintes.sql   # une ligne « OK » par test
```

Le script de création commence par des `DROP`, on peut le relancer pour repartir d'un jeu de données propre.

## 2. Serveur applicatif (Java)

1. Copier `reseau/serveur-java/serveur.properties.exemple` en `serveur.properties` et y mettre l'URL de la base et le mot de passe (ce fichier n'est pas versionné).
2. NetBeans : Fichier > Ouvrir un projet > `reseau/serveur-java`. Maven télécharge le pilote PostgreSQL au premier build.
3. Run. Le serveur écoute sur le port 5000.

Sans NetBeans (salle de TP) : `sh reseau/serveur-java/dist/lancer_serveur.sh`. Après une modification du code : `sh reseau/serveur-java/construire.sh` reconstruit `dist/serveur-bornes.jar`.

| Classe | Rôle |
|---|---|
| `ServeurBornes` | accepte les connexions, un thread par borne (50 au plus) |
| `DialogueBorne` | le protocole : lit une ligne, répond, garde l'état de la borne |
| `AccesBase` | tout le SQL, uniquement en `PreparedStatement` |

## 3. Client (Python)

```
cd reseau/client-python
python borne.py CGY-01-B01                charge complète automatique
python borne.py CGY-01-B02 --badge 4      badge bloqué
python borne.py CGY-01-B01 -i             mode interactif
```

## 4. Protocole

TCP, texte UTF-8, une commande par ligne terminée par `\n`. Réponse toujours `OK ...` ou `ERR <code> <message>`.

| Commande | Réponse si succès | Erreurs possibles |
|---|---|---|
| `HELLO <code_borne>` | `OK <n° dialogue> <etat>` | 400, 401 (puis fermeture), 409 |
| `TARIF` | `OK <prix_kwh> <prix_minute> <libellé>` | |
| `START <id_badge>` | `OK <id_session>` | 400, 403 badge bloqué, 404, 409 |
| `STOP <energie_kwh>` | `OK <id_session> <montant> CB\|FACTURE` | 400, 409 |
| `PAYE <id_session> CB-<ref>` | `OK <id_session> payee` | 400, 404 session inconnue sur cette borne, 409 non payable (entreprise, déjà payée, pas terminée) |
| `STATUS libre\|hors_service\|maintenance` | `OK <etat>` | 400, 409 |
| `BYE` | `OK au revoir` puis fermeture | |

Toute commande autre que `HELLO` ou `BYE` avant l'identification reçoit `ERR 403`.

`TARIF` ne répond pas la même chose selon le moment : borne libre, tarif public ; charge en cours, tarif de l'abonnement du client.

**Le paiement dépend de l'héritage** (v4) : après `STOP`, le serveur regarde si le titulaire du badge est dans `PARTICULIER` ou dans `ENTREPRISE`. Particulier : réponse `CB`, la borne encaisse par carte puis envoie `PAYE`. Entreprise : réponse `FACTURE`, rien à payer à la borne, la charge va sur la facture mensuelle (question Q11).

## 5. Robustesse prévue

| Situation | Comportement du serveur |
|---|---|
| borne coupée en pleine charge | session passée `interrompue`, borne remise `libre` |
| ligne de plus de 1024 octets | `ERR 413`, fermeture |
| 5 minutes sans message | fermeture, même nettoyage |
| deux `START` en même temps sur une borne | `SELECT ... FOR UPDATE`, un seul passe |
| base arrêtée | `ERR 503`, le serveur continue d'accepter |
| erreur SQL | `ERR 500`, le détail reste dans le log serveur |

## 6. Tester avec netcat

netcat n'est pas livré avec Windows : `scoop install netcat`, ou `ncat` fourni avec Nmap.

```
nc 127.0.0.1 5000          netcat en client, face au serveur Java
nc -l -p 5000              netcat en serveur, pour lire ce qu'envoie borne.py
```
