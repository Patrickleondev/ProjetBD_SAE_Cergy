# 7. Le projet expliqué à l'équipe

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

SAÉ Bases de données & Réseau - L3 Informatique, CY Cergy Paris Université
Encadrant : l'encadrant · Livraison finale : dimanche 29 novembre 2026, 23h59

---

> **Comment se servir de ce document.** Il est écrit pour être lu par quelqu'un qui
> n'a pas suivi la conception. Chaque partie tient en trois à cinq minutes à voix haute.
> Les encadrés « la question qui va tomber » sont les objections réelles : autant les
> avoir entendues ici plutôt qu'en soutenance.

---

## 1. Ce qu'on construit, en trois minutes

Une société d'exploitation de bornes de recharge pour véhicules électriques, répartie sur
plusieurs stations d'Île-de-France, n'a **aucune vision en temps réel de son parc**.

Concrètement, aujourd'hui :

- on apprend qu'une borne est en panne **quand un client appelle** ;
- la facturation est reconstituée **à la main**, à partir de relevés.

Notre travail est de lui livrer un système d'information complet. Il se décompose en deux
accès très différents, et c'est cette dualité qui structure tout le projet.

| | Qui l'utilise | Par quel moyen |
|---|---|---|
| **L'accès machine** | les bornes, qui n'ont pas de navigateur | un protocole que **nous inventons**, au-dessus de TCP |
| **L'accès humain** | les clients et les gestionnaires | un site web classique |

Les bornes s'identifient au démarrage, ouvrent et clôturent les sessions de charge,
interrogent le tarif en vigueur. Les clients consultent leurs sessions et leurs factures ;
les gestionnaires administrent le parc et suivent les incidents.

**Le périmètre :** gestion du parc (stations, bornes, connecteurs), des clients (comptes,
véhicules, badges, abonnements), des sessions de charge et de leur facturation, et du suivi
de maintenance (incidents, interventions).

**Ce qui est explicitement exclu :** le paiement en ligne réel, la cartographie, le
protocole normalisé OCPP de l'industrie, et la gestion des ressources humaines.

> **La question qui va tomber : « pourquoi ne pas utiliser OCPP ? »**
> Parce que le sujet demande que **nous** concevions un protocole applicatif. Utiliser un
> protocole existant nous ferait sauter l'exercice. On le mentionne dans le rapport pour
> montrer qu'on sait qu'il existe.

---

## 2. Les cinq machines, et la règle qui les gouverne

```
  borne (Python) ──── notre protocole, TCP 5000 ────► serveur applicatif (Java)
                                                              │ JDBC, TCP 5432
                                                              ▼
                                                        PostgreSQL
                                                              ▲
                                                              │ PDO, TCP 5432
  navigateur ──────── HTTPS, TCP 443 ────────────────► serveur web (PHP)
```

| Machine | Rôle | Langage |
|---|---|---|
| Client réseau | la borne de recharge, sans écran | Python 3.13 |
| Serveur applicatif | reçoit les messages des bornes | Java 17 |
| Base de données | stocke tout | PostgreSQL 16 |
| Serveur web | le site des clients et gestionnaires | PHP 8 + Apache |
| Navigateur | ce que voit l'utilisateur | HTML, CSS, JS |

### La règle, à savoir défendre par cœur

> **Aucun poste utilisateur ne parle directement à la base.** Ni la borne, ni le navigateur.
> Toute requête SQL part d'un serveur.

**Pourquoi.** Une borne est posée sur un parking public. Si elle détenait les identifiants
de la base, il suffirait de l'ouvrir avec un tournevis pour les lire - et on aurait accès
aux données de tous les clients. Le serveur applicatif sert de **sas** : il reçoit des
paramètres, il décide, et lui seul écrit dans la base.

### Le plan d'adressage

| Segment | Adresses | Qui s'y trouve |
|---|---|---|
| LAN utilisateurs | `192.168.10.0/24` | la borne, le poste du navigateur |
| Segment serveurs | `192.168.20.0/24` | applicatif, web, PostgreSQL |

```
Serveur applicatif   192.168.20.10   écoute sur 5000/TCP
Serveur web          192.168.20.20   écoute sur 443/TCP
```

### Deux schémas, deux lectures

Le fichier `architecture_reseau.drawio` a **deux onglets**, et il ne faut pas les confondre :

- **Architecture physique** - routeur, pare-feu, commutateurs, câbles. C'est le plan d'un
  installateur.
- **Flux applicatifs** - les mêmes machines sans les câbles, avec le protocole et le port
  sur chaque flèche. C'est le plan d'un développeur.

> Le sujet demande un « schéma d'architecture **contextualisé** » et précise de ne pas le
> confondre avec une architecture **logicielle**. On décrit donc des machines, des adresses
> et des protocoles - jamais nos classes Java ni nos fichiers PHP.

---

## 3. Pourquoi ces technologies, et pas d'autres

Trois choix seront discutés, autant connaître l'argument.

**Java pour le serveur applicatif.** Recommandé par le sujet, et c'est le seul des trois
langages autorisés qui donne accès à JDBC.

**Python pour le client.** Le sujet exige un langage **différent** de celui du serveur.
Écrire un simulateur de plusieurs bornes en parallèle y prend quelques lignes, ce qui sert
directement aux tests de robustesse.

**TCP et pas UDP.** Une session de charge s'ouvre, dure, puis se ferme. L'ordre des messages
et la garantie de livraison sont nécessaires. Avec UDP il faudrait réimplémenter
l'acquittement et la détection des pertes, sans aucun gain ici.

---

## 4. Le modèle de données - comment le lire

18 tables, 91 attributs. Trois zones :

```
LES CLIENTS          UTILISATEUR, PARTICULIER, ENTREPRISE, VEHICULE,
                     BADGE, COMPTE_WEB, ABONNEMENT, TARIF, FACTURE

L'EXPLOITATION       STATION, BORNE, BORNE_AC, BORNE_DC, CONNECTEUR,
                     EQUIPER, SESSION_CHARGE

LA MAINTENANCE       INCIDENT, TECHNICIEN, INTERVENTION
```

### Les quatre décisions de modélisation à connaître

**1. Deux héritages, tous deux en `XT`.**

```
      UTILISATEUR                        BORNE
           △                               △
    ┌──────┴──────┐                 ┌──────┴──────┐
PARTICULIER  ENTREPRISE         BORNE_AC     BORNE_DC
```

`X` pour *exclusion* : un client est particulier **ou** entreprise, jamais les deux.
`T` pour *totalité* : il n'existe pas de troisième cas.

Les sous-types ne portent **que** ce qui leur est propre : `date_naissance` pour un
particulier, `raison_sociale` et `siret` pour une entreprise. Tout le reste est dans le
surtype. Le discriminant `type_client` reste lui aussi dans le surtype : c'est lui qui dit,
pour une ligne donnée, dans quel sous-type aller chercher la suite.

> **`siret`** est le numéro d'identification d'un établissement d'entreprise en France,
> 14 chiffres. Les entreprises en ont un, les particuliers non - c'est exactement pourquoi
> il est dans le sous-type. Dans le surtype, la colonne serait vide pour la moitié des
> lignes.

**2. `COMPTE_WEB` est séparée de `UTILISATEUR`.** Deux raisons, toutes deux bonnes :

- la cardinalité est `(0,1)` - tous les clients n'ont pas de compte web, les fusionner
  laisserait trois colonnes nulles sur la plupart des lignes ;
- `hash_mdp` est une donnée d'**authentification**, pas une donnée métier. Les séparer
  permet de restreindre les droits d'accès à cette seule table.

**3. Deux associations N-N.** `EQUIPER` (une borne porte plusieurs types de connecteurs,
un type équipe plusieurs bornes) et `INTERVENIR` (plusieurs techniciens sur un incident,
un technicien sur plusieurs incidents).

**4. Une question encore ouverte, à trancher ensemble.** `INTERVENIR` doit-elle avoir une
clé composée `(id_incident, id_technicien)` ou une clé propre `id_intervention` ?

```
clé composée   →  interdit qu'un technicien intervienne DEUX FOIS sur le même incident
clé propre     →  autorise plusieurs interventions à des dates différentes
```

La question est **métier**, pas technique : un technicien peut-il revenir ? Sur une panne de
borne, oui - un diagnostic, puis une réparation. **Donc la clé propre.** Mais alors, en
rigueur Merise, `INTERVENIR` n'est plus une association : c'est une **entité**.
Les deux sont défendables ; l'incohérence ne l'est pas.

> **La question qui va tomber : « pourquoi trois tables pour l'héritage et pas une ? »**
> Parce que `VEHICULE`, `BADGE`, `ABONNEMENT`, `FACTURE` et `COMPTE_WEB` - cinq clés
> étrangères - pointent vers `UTILISATEUR`. Supprimer la table surtype obligerait chacune
> à viser deux tables au lieu d'une.

---

## 5. Le protocole réseau - ce qu'on a inventé

### Ce que le sujet exige

Cinq échanges, non négociables : un qui **identifie** le client, un qui **met à jour** une
table, un qui **extrait** des données, un qui **provoque des suites différentes** selon la
réponse, et un qui **termine proprement**.

### La forme

Un message = **une ligne de texte** : un mot-clé en majuscules, des paramètres séparés par
des espaces, un saut de ligne. Encodage UTF-8.

Le serveur répond **toujours**, sous une seule de ces deux formes :

```
OK  <données éventuelles>
ERR <code> <message lisible>
```

> **Pourquoi du texte et pas du binaire ?** Le sujet attend des tests avec **netcat**, en
> client *et* en serveur. Un protocole binaire serait intestable à la main.

> **Pourquoi toujours répondre, même en erreur ?** C'est ce qui rend l'échange robuste :
> le client n'attend jamais dans le vide.

### Les six messages

| Message | Ce qu'il fait |
|---|---|
| `HELLO <code_borne>` | la borne annonce son code |
| `START <id_badge> <horodatage>` | une charge commence |
| `STOP <id_session> <energie_kwh>` | une charge se termine |
| `TARIF` | la borne demande le tarif en vigueur |
| `STATUS <etat>` | la borne signale son état |
| `BYE` | fin de la conversation |

### Les trois scénarios, un par onglet du `.drawio`

**Scénario 1 - identification.** C'est l'échange « qui provoque des suites différentes » :

```
borne → serveur   HELLO CGY-05-B03
serveur → borne   OK 4471                 → la conversation continue
       ou         ERR 401 borne inconnue  → le serveur ferme
```

Le serveur en profite pour mettre à jour `derniere_communication`. Le site web peut alors
afficher « cette borne n'a plus donné signe de vie depuis 20 minutes ». **C'est le lien
concret entre la partie réseau et la partie web** - la question tombera là-dessus.

**Scénario 2 - mise à jour depuis le client.**

```
borne → serveur   START 310 2026-10-07T14:32:08
serveur           contrôle : badge existant, actif, borne libre
serveur → borne   OK 10452
       ou         ERR 403 badge bloqué
```

> **Le point important :** la borne envoie `310`, **jamais une requête SQL**. Le serveur
> construit la requête avec un `PreparedStatement`. Si quelqu'un branche un faux client,
> le pire qui arrive est un `ERR 400`.

**Scénario 3 - extraction puis fermeture propre.**

```
borne → serveur   TARIF
serveur → borne   OK 0.2200 0.0200
borne → serveur   BYE
serveur → borne   OK au revoir     puis fermeture du socket
```

Le serveur ne coupe pas la connexion sans avoir accusé réception du `BYE`.

### Ce qui sera testé - la robustesse

| Le cas | Ce que le serveur doit faire |
|---|---|
| le client disparaît au milieu | détecter une lecture de **zéro octet**, clôturer la session en `interrompue`, libérer la borne |
| message inconnu | `ERR 400`, **et continuer** - ne pas planter |
| message trop long | limite à 1024 octets par ligne, rejeter au-delà |
| deux bornes en même temps | accepter plusieurs connexions, un thread par client |
| message coupé en deux paquets | **lire jusqu'au saut de ligne, jamais paquet par paquet** |

> **Le dernier est le piège classique.** TCP transporte un flux d'octets, pas un flux de
> messages. Un message peut arriver en deux morceaux, et deux messages peuvent arriver
> collés. Qui code le serveur doit avoir cette phrase en tête.

---

## 6. Le planning, et ce qu'il reste

| Semaines | Version | Ce qui est attendu |
|---|---|---|
| 37-38 | v#1 | contexte, planning, dictionnaire initial, ébauche des échanges |
| **39-40** | **v#2** | **dictionnaire complet, MCD, MLD, 2 diagrammes applicatifs** |
| 41-42 | v#3 | script DDL, 4 à 5 scénarios réseau, jeu de données |
| 43-45 | v#4 | requêtes DML, client et serveur réseau, connexions à la base |
| 46-47 | v#5 | tests d'intégration et de robustesse avec netcat |
| 48 | **livraison** | **dimanche 29 novembre 2026, 23h59** |
| 50 | soutenance | 5 min et 5-6 slides pour la BD, 10 min à distance pour le réseau |

**La règle du rendu :** chaque version enrichit **le même** document. On n'écrit pas cinq
rapports, on en fait grandir un seul.

### Deux fils en parallèle

```
fil BASE DE DONNÉES    modèle → DDL → jeu de données → requêtes
fil RÉSEAU             protocole → client Python → serveur Java → tests netcat

           les deux se rejoignent en semaines 43-45,
           quand le serveur attaque réellement la base par JDBC
```

Les **TP 3 et 4 de réseau** tombent en semaines 41-42 : sockets, `bind`, `listen`, `accept`,
gestion de plusieurs clients. C'est exactement ce qu'il faut pour le serveur. **À prendre en
notes en pensant au projet, pas seulement au TP.**

### Ce qui est déjà fait

- le contexte, rédigé
- les choix techniques, justifiés
- le dictionnaire de données : 18 tables, 91 attributs, avec exemples
- le schéma d'architecture, deux vues
- trois diagrammes d'échange réseau
- le planning en diagramme de Gantt
- le MCD dans Looping
- le rapport, avec sommaire, numérotation et table des figures automatiques

### Ce qui reste, et ce que je propose de se partager

| Chantier | Quand | Ce que ça demande |
|---|---|---|
| Le MLD et le script DDL | v#3 | générer depuis Looping, puis reprendre à la main les `ENUM`, les `BYTEA` et les contraintes `CHECK` |
| Le jeu de données | v#3 | des `INSERT INTO` cohérents sur les 18 tables |
| Deux scénarios réseau de plus | v#3 | il en manque un ou deux sur les cinq |
| Le client Python | v#4 | un simulateur de borne, plus facile qu'il n'en a l'air |
| Le serveur Java | v#4 | le gros morceau : sockets, threads, JDBC |
| Le site web PHP | v#4-v#5 | consultation client + administration gestionnaire |
| Les requêtes SELECT | v#4 | 8 à 10, avec imbriquées, agrégats et `GROUP BY` |
| Les tests netcat | v#5 | les deux modes, client **et** serveur |
| Les deux vidéos de démo | livraison | à ne pas oublier, c'est un livrable |

> **Une règle qui coûte cher si on l'oublie :** le dictionnaire de données se modifie dans
> `rapport/donnees_projet.py`, **jamais** directement dans le Word. Le script régénère le
> document. Sinon on aura deux versions différentes dans quinze jours et personne ne saura
> laquelle fait foi.

---

## 7. Les cinq questions à savoir répondre sans hésiter

1. **Pourquoi la borne ne parle-t-elle pas directement à la base ?**
   Parce qu'elle est sur un parking public. Le serveur applicatif est un sas.

2. **Pourquoi `XT` sur les héritages ?**
   Exclusion : on ne peut pas être les deux. Totalité : il n'y a pas de troisième cas.

3. **Pourquoi du texte et pas du binaire ?**
   Parce que le sujet exige des tests avec netcat, et qu'un binaire est intestable à la main.

4. **Pourquoi TCP et pas UDP ?**
   Une session s'ouvre, dure et se ferme : il faut l'ordre et la garantie de livraison.

5. **Que se passe-t-il si une borne se débranche au milieu d'une charge ?**
   Lecture de zéro octet → session clôturée en `interrompue`, borne libérée. Une session
   restée `en_cours` fausserait la facturation **et** l'état du parc.

---

*Pour le détail de chaque partie, voir les fichiers 01 à 06 de ce dossier.*
