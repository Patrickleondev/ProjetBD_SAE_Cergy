# 8. Comprendre de bout en bout, de la prise électrique au code

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

Ce fichier se lit dans l'ordre. Chaque partie se termine par un **point de contrôle** :
quelques questions auxquelles tu réponds à voix haute, sans relire. Si tu bloques sur une
question, tu relis la partie. Tu ne passes pas à la suivante avant.

```
Partie 1   la recharge dans la vraie vie          aucun ordinateur
Partie 2   des objets réels aux tables            la base de données
Partie 3   le voyage d'une charge                 les cinq machines
Partie 4   le lancement, pas à pas                sur ton PC
Partie 5   lire le code, dans le bon ordre        Java et Python
```

---

## Partie 1. La recharge dans la vraie vie

### 1.1 Le problème de départ : deux courants différents

Une batterie de voiture stocke du **courant continu** (DC, *direct current*) : le courant
va toujours dans le même sens, comme dans une pile.

Le réseau électrique, EDF, la prise de ta chambre, fournit du **courant alternatif** (AC,
*alternating current*) : le courant change de sens 50 fois par seconde.

On ne peut donc pas brancher une batterie directement sur le réseau. Il faut un
**convertisseur** qui transforme l'AC en DC. Toute la différence entre les bornes vient
d'une seule question : **où se trouve ce convertisseur ?**

```
BORNE AC (lente)                          BORNE DC (rapide)

réseau AC                                 réseau AC
   │                                         │
 [borne] ne fait que laisser passer        [borne] contient le convertisseur,
   │     l'AC, en sécurité                   │     gros, cher, refroidi
   │ AC                                      │ DC
 [voiture] son chargeur embarqué           [voiture] le DC va directement
   │       convertit AC -> DC                │       à la batterie
 batterie                                  batterie
```

- **Borne AC** : le convertisseur est **dans la voiture** (on l'appelle le chargeur
  embarqué). Il est petit, parce qu'il faut le transporter partout. Donc la puissance est
  limitée : 7, 11, 22 kW. La borne est simple et bon marché, on la met sur un parking de
  centre-ville où les gens restent deux heures.
- **Borne DC** : le convertisseur est **dans la borne**. Il peut être énorme. Donc 50,
  150, 350 kW. La borne coûte des dizaines de milliers d'euros, on la met sur une aire
  d'autoroute où les gens restent vingt minutes.

C'est exactement pour ça que le modèle a un **héritage** `BORNE` → `BORNE_AC` / `BORNE_DC`.
Les deux sont des bornes, mais elles n'ont pas les mêmes caractéristiques techniques :

| Sous-type | Attribut propre | Pourquoi celui-là |
|---|---|---|
| `BORNE_AC` | `nb_phases` (1 ou 3) | la puissance AC dépend du nombre de phases, voir 1.2 |
| `BORNE_DC` | `tension_max_v` (400, 500, 920 V) | les voitures récentes à 800 V exigent une borne qui monte assez haut |

### 1.2 Deux unités qu'il ne faut jamais confondre : kW et kWh

C'est **la** confusion qui fait perdre des points en soutenance.

| | Unité | Ce que c'est | Analogie avec l'eau |
|---|---|---|---|
| **Puissance** | kW | la vitesse à laquelle l'énergie passe | le débit du robinet, en litres par minute |
| **Énergie** | kWh | la quantité d'énergie | le nombre de litres dans le seau |

`énergie = puissance × temps`. Une borne de 22 kW pendant 1 heure délivre au maximum
22 kWh.

Dans la base :

- `borne.puissance_max_kw` : le débit maximal de la borne, en **kW**
- `vehicule.capacite_batterie_kwh` : la taille du seau, en **kWh**
- `session_charge.energie_kwh` : ce qui a coulé pendant la charge, en **kWh**
- `tarif.prix_kwh` : on paie la **quantité**, pas le débit

D'où viennent les puissances AC, pour pouvoir répondre si on te demande « pourquoi 22 » :

```
monophasé   230 V × 32 A          ≈  7,4 kW
triphasé    √3 × 400 V × 16 A     ≈ 11 kW
triphasé    √3 × 400 V × 32 A     ≈ 22 kW
```

**La puissance réelle est le minimum des trois maillons.** Une Tesla Model 3 a un chargeur
embarqué de 11 kW. Branchée sur une borne AC de 22 kW, elle charge à 11 kW. Pas plus. Ce
n'est pas la borne qui décide seule.

Ordre de grandeur, à savoir faire de tête : une Renault Zoe (batterie de 52 kWh) qui passe
de 20 % à 80 % reçoit 60 % × 52 ≈ 31 kWh. Sur 22 kW, ça fait 31 / 22 ≈ 1 h 25.

### 1.3 Le connecteur, c'est la forme de la prise

Une voiture a **une** prise (un « socle ») d'un certain type. Une borne porte souvent
**plusieurs** câbles ou prises, parfois de types différents. Pour charger, il faut que les
formes correspondent.

| Connecteur | Courant | Qui l'utilise |
|---|---|---|
| **Type 2** | AC | le standard européen, presque toutes les voitures |
| **CCS Combo 2** | DC | un Type 2 avec deux grosses broches DC en dessous. Le standard rapide européen |
| **CHAdeMO** | DC | standard japonais, Nissan Leaf. En voie de disparition |
| **Domestique E/F** | AC | la prise de maison, 3,7 kW, très lente, dépannage |

C'est la table `CONNECTEUR`. Et comme une borne porte plusieurs types de prise et qu'un
type de prise équipe plusieurs bornes, c'est une **association N-N**, la table `EQUIPER`,
avec `nombre` : combien de prises de ce type sur cette borne.

Le lien `VEHICULE` → `CONNECTEUR` dit quelle prise la voiture accepte. C'est ce qui permet la
requête R9 : « quelles bornes libres ont une prise compatible avec ma voiture ».

> **Simplification à connaître.** Une voiture équipée CCS accepte aussi le Type 2 en AC,
> puisque le haut de la prise est identique. Notre modèle ne garde qu'un connecteur par
> véhicule. C'est un choix, dis-le si on te pose la question, ne le découvre pas devant le
> jury.

### 1.4 Station, borne, prise : trois niveaux

```
STATION     un lieu        "Osny Parc", une adresse, des coordonnées GPS
  └ BORNE   un appareil    "OSN-04-B01", 350 kW, DC, un état
      └ prises (EQUIPER)   2 prises CCS Combo 2
```

Une station contient plusieurs bornes, une borne est dans une seule station :
**association 1-N**, `BORNE.id_station`.

Le code `OSN-04-B01` se lit : ville Osny, station 04, borne 01. C'est ce code qui est
imprimé sur la borne, et c'est avec lui qu'elle se présente au serveur.

### 1.5 Le badge : qui paie ?

Le client passe une carte **RFID** (sans contact, comme un pass Navigo) devant la borne. La
puce contient un identifiant unique, `code_rfid`, 16 caractères hexadécimaux.

La borne ne sait pas qui est le client. Elle lit un numéro, et elle **demande au serveur
central** si ce numéro a le droit de charger. Si le client ne paie plus, l'opérateur passe
le badge à `actif = false` dans la base, et toutes les bornes le refusent instantanément,
sans qu'on touche à aucune borne.

Un utilisateur peut avoir plusieurs badges (une entreprise en donne un par chauffeur) :
association 1-N, `BADGE.id_utilisateur`. Regarde Transports Dupont dans le jeu de données :
deux badges, deux utilitaires.

### 1.6 Ce qui se passe physiquement pendant une charge

Voici une vraie charge, dans l'ordre. **Apprends cette séquence**, tout le reste du projet
en découle.

```
 1. le client se gare et branche le câble
 2. il passe son badge
 3. la borne demande au serveur central : ce badge est-il autorisé ?
 4. le serveur vérifie dans la base et répond oui ou non
 5. si oui : la borne VERROUILLE la prise (on ne peut plus l'arracher)
    et ferme son contacteur, un gros interrupteur : le courant passe
 6. la borne et la voiture se mettent d'accord sur la puissance
    (sur le Type 2, un fil de signal indique à la voiture le courant maximal permis)
 7. l'énergie passe ; le COMPTEUR de la borne mesure les kWh
 8. batterie pleine, ou le client repasse son badge pour arrêter
 9. la borne ouvre le contacteur, déverrouille la prise
10. elle envoie au serveur l'énergie mesurée par son compteur
11. le serveur calcule le prix ; la facture viendra en fin de mois
```

Trois conséquences directes sur notre projet :

- **Étape 7 : c'est la borne qui mesure l'énergie.** Le serveur est à des kilomètres, il ne
  voit pas passer les électrons. C'est pour ça que `STOP` porte l'énergie : `STOP 32.321`.
  Dans la réalité, ce compteur est certifié (norme MID), justement parce qu'on facture sur
  sa mesure.
- **Étape 11 : on ne facture pas chaque charge.** On regroupe toutes les charges du mois sur
  une facture. C'est pour ça que `session_charge.id_facture` peut être **NULL** : une charge
  de septembre n'a pas encore de facture, elle en aura une le 1er octobre. Regarde le jeu de
  données, les sessions de septembre ont `NULL`, celles d'août ont une facture.
- **Le prix a deux parties.** `prix_kwh` pour l'énergie, et `prix_minute` pour le temps
  passé branché. Ces frais de temps existent vraiment chez les opérateurs : sans eux, les
  gens laissent leur voiture pleine sur la borne toute la journée et bloquent les autres.

### 1.7 La borne n'est pas toujours disponible

L'état d'une borne, `etat_borne` : `libre`, `occupee`, `hors_service`, `maintenance`.

- `occupee` : une charge est en cours. C'est le serveur qui la met dans cet état quand il
  accepte un `START`, et qui la remet `libre` au `STOP`.
- `hors_service` : panne. En général, un **incident** a été signalé.
- `maintenance` : un technicien est dessus.

Un incident concerne une borne (1-N, `INCIDENT.id_borne`). Plusieurs techniciens peuvent
intervenir sur le même incident, un technicien traite plusieurs incidents : N-N,
`INTERVENIR`, qui porte en plus la date et la durée de l'intervention.

`habilitation` (`B2V`, `BR`, `BC`) : ce sont les vraies habilitations électriques françaises
(norme NF C 18-510). On n'a pas le droit d'ouvrir une borne sans elles.

### 1.8 Comment les vraies bornes parlent au serveur

Dans l'industrie, les bornes utilisent un protocole standard, **OCPP** (*Open Charge Point
Protocol*). Notre protocole en est une version très simplifiée. Si tu connais cette
correspondance, tu montres au jury que ton protocole n'est pas inventé au hasard.

| OCPP 1.6 | Chez nous | Moment |
|---|---|---|
| `BootNotification` | `HELLO` | la borne démarre et se présente |
| `Authorize` + `StartTransaction` | `START` | badge passé, début de charge |
| `StopTransaction` (avec le compteur) | `STOP` | fin de charge, avec l'énergie mesurée |
| `StatusNotification` | `STATUS` | changement d'état, panne |
| `Heartbeat` | `derniere_communication` | « je suis toujours en vie » |

### Point de contrôle 1

Réponds à voix haute, sans relire :

1. Pourquoi une borne DC peut-elle être beaucoup plus puissante qu'une borne AC ?
2. Une Zoe est restée 2 heures sur une borne de 22 kW et a reçu 18 kWh. Pourquoi pas 44 kWh ?
   Donne deux raisons possibles.
3. Pourquoi le message `STOP` contient-il l'énergie, au lieu que le serveur la calcule ?
4. Pourquoi `id_facture` peut-il être NULL dans `SESSION_CHARGE` ?
5. Pourquoi `EQUIPER` est-elle une N-N et pas une 1-N ?

---

## Partie 2. Des objets réels aux tables

Maintenant que tu connais les objets, chaque table n'est plus un nom abstrait. Pour chaque
lien, la question à se poser est toujours la même : **dans un sens, combien au maximum ?
dans l'autre sens, combien au maximum ?**

| Lien | Un côté | L'autre côté | Donc | Se traduit par |
|---|---|---|---|---|
| station, borne | une station a plusieurs bornes | une borne est dans une station | 1-N | `borne.id_station` |
| utilisateur, badge | un client a plusieurs badges | un badge appartient à un client | 1-N | `badge.id_utilisateur` |
| utilisateur, véhicule | un client a plusieurs véhicules | un véhicule a un propriétaire | 1-N | `vehicule.id_utilisateur` |
| véhicule, connecteur | une voiture a une prise | un type de prise sur plein de voitures | N-1 | `vehicule.id_connecteur` |
| borne, connecteur | une borne a plusieurs types | un type sur plusieurs bornes | **N-N** | table `EQUIPER` |
| session, borne | une session sur une borne | une borne, des milliers de sessions | N-1 | `session_charge.id_borne` |
| session, badge | une session, un badge | un badge, plein de sessions | N-1 | `session_charge.id_badge` |
| session, facture | une session, 0 ou 1 facture | une facture regroupe plusieurs sessions | N-1, optionnel | `id_facture` NULL possible |
| abonnement, tarif | un abonnement, un tarif | un tarif partagé par plein d'abonnés | N-1 | `abonnement.id_tarif` |
| incident, technicien | un incident, plusieurs techniciens | un technicien, plusieurs incidents | **N-N** | table `INTERVENIR` |

**La règle mécanique du passage au relationnel**, que tu vois appliquée partout ci-dessus :

- en 1-N, la clé étrangère va **du côté N** (la borne porte l'id de sa station, pas
  l'inverse, sinon une station devrait stocker une liste de bornes dans une seule case) ;
- en N-N, on crée une **table de liaison** dont la clé primaire est le couple des deux clés.

**L'héritage**, deux fois :

```
UTILISATEUR  ──  PARTICULIER (date_naissance)
             └─  ENTREPRISE  (raison_sociale, siret)

BORNE        ──  BORNE_AC (nb_phases)
             └─  BORNE_DC (tension_max_v)
```

La table fille a pour clé primaire **la même valeur** que la table mère, qui est aussi clé
étrangère vers elle. L'utilisateur 6 existe dans `UTILISATEUR` (nom, email) et dans
`ENTREPRISE` (siret), avec le même numéro 6. C'est ce que dit `CP, CE` dans ton dictionnaire.

**Les types ENUM**, exigés par le sujet. Au lieu d'un `VARCHAR` où l'on pourrait écrire
`"ocupée"` avec une faute, un `ENUM` n'accepte que les valeurs listées. La base refuse
toute autre valeur, même si le code Java a un bug.

### Ouvre le jeu de données et raconte-le

Ouvre `sql/01_creation.sql`, descends aux `INSERT`, et raconte l'histoire de Julie Martin
(utilisateur 1) : son badge, sa voiture, son abonnement, ses charges d'août, sa facture.
Puis celle de Hugo Leroy (utilisateur 4). Pourquoi son badge est-il inutile ?

### Point de contrôle 2

1. Dans une 1-N, de quel côté va la clé étrangère, et pourquoi pas l'autre ?
2. Pourquoi `EQUIPER` a-t-elle une colonne `nombre`, et pourquoi n'est-elle pas dans `BORNE` ?
3. Dans `ENTREPRISE`, `id_utilisateur` est à la fois CP et CE. Explique chacun des deux rôles.
4. Qu'apporte `etat_borne` en ENUM par rapport à un `VARCHAR(50)` ?

---

## Partie 3. Le voyage d'une charge à travers les machines

On suit **une seule charge**, du badge jusqu'à la ligne dans la base.

```
  [borne.py]                [serveur Java]                 [PostgreSQL]
  client réseau             ServeurBornes                  base voltoise
                            DialogueBorne                  
                            AccesBase                      
      │                          │                              │
      │ ouvre une connexion TCP  │                              │
      │ ──────────────────────►  │ accept(), crée un thread     │
      │                          │                              │
      │ HELLO CGY-01-B01         │                              │
      │ ──────────────────────►  │ SELECT id_borne, etat ...    │
      │                          │ ───────────────────────────► │
      │                          │ ◄─────────── (1, 'libre')    │
      │ ◄───── OK 1001 libre     │                              │
      │                          │                              │
      │ START 2                  │ SELECT actif FROM badge ...  │
      │ ──────────────────────►  │ BEGIN                        │
      │                          │ SELECT etat ... FOR UPDATE   │
      │                          │ INSERT session 'en_cours'    │
      │                          │ UPDATE borne 'occupee'       │
      │                          │ COMMIT ────────────────────► │
      │ ◄─────────────── OK 13   │                              │
      │                          │                              │
      │   ... la voiture charge, la borne compte les kWh ...    │
      │                          │                              │
      │ STOP 32.321              │ UPDATE session 'terminee'    │
      │ ──────────────────────►  │ UPDATE borne 'libre'         │
      │                          │ SELECT tarif de l'abonnement │
      │ ◄──────────── OK 13 7.11 │ calcule 32.321 × 0.22        │
      │                          │                              │
      │ BYE                      │                              │
      │ ──────────────────────►  │                              │
      │ ◄─────── OK au revoir    │ ferme le socket              │
```

Ce que tu dois voir sur ce schéma :

- **La borne n'écrit jamais de SQL.** Elle envoie `START 2`, un mot et un nombre. C'est le
  serveur qui fabrique la requête. Une borne est sur un parking public : si elle contenait
  le mot de passe de la base, il suffirait de l'ouvrir pour le lire.
- **Le serveur garde l'état de chaque borne.** Après `START`, il se souvient que la borne 1
  a la session 13 en cours. C'est pour ça que `STOP` n'a pas besoin du numéro de session, et
  qu'une borne ne peut pas arrêter la charge d'une autre.
- **`START` est une transaction.** Créer la session et passer la borne à `occupee` doivent
  réussir ensemble ou échouer ensemble. Si le courant coupe entre les deux, on ne veut pas
  une session sans borne occupée.
- **`TARIF` répond différemment selon le moment.** Avant `START`, la borne est libre : tarif
  public, 0,39 €. Pendant la charge du badge 2 : tarif de son abonnement, 0,22 €. C'est
  l'échange « qui, suivant la réponse, provoque différents types d'échanges » demandé par
  le sujet.

### Et quand ça se passe mal

| Ce qui arrive | Ce que fait le serveur | Pourquoi c'est important |
|---|---|---|
| badge bloqué | `ERR 403`, rien n'est écrit | la borne ne délivre pas de courant |
| borne inconnue | `ERR 401`, fermeture | quelqu'un se fait passer pour une borne |
| la borne est débranchée pendant la charge | session `interrompue`, borne `libre` | sinon la borne resterait `occupee` à vie |
| une ligne de 5000 caractères | `ERR 413`, fermeture | sinon on peut remplir la mémoire du serveur |
| la base est arrêtée | `ERR 503`, le serveur tourne toujours | une panne de base ne tue pas le serveur |

### Point de contrôle 3

1. Pourquoi la borne n'a-t-elle jamais le mot de passe de la base ?
2. Pourquoi `STOP` n'envoie-t-il pas le numéro de session ?
3. Que se passerait-il sans transaction dans `START`, si le serveur plantait entre l'INSERT
   et l'UPDATE ?
4. Cite l'échange qui répond différemment selon la situation.

---

## Partie 4. Le lancement, pas à pas

Trois fenêtres à la fin : **PowerShell pour la base**, **NetBeans pour le serveur**,
**PowerShell pour la borne**. À chaque étape, je te dis ce que tu dois voir. Si tu ne le
vois pas, tu t'arrêtes et tu regardes le tableau de dépannage en 4.7.

### 4.1 Préparer la console

Ouvre PowerShell et tape :

```powershell
chcp 65001
$env:PGCLIENTENCODING = "UTF8"
$psql = "C:\Program Files\PostgreSQL\18\bin\psql.exe"
cd D:\All\Licence_Cergy\Semestre_5\05_SAE_BD_Reseau
```

Les deux premières lignes disent à la console et à psql que le fichier SQL est en UTF-8.
Sans elles, « Préfecture » arrive dans la base sous la forme `PrÃ©fecture`.

### 4.2 Créer la base

```powershell
& $psql -U postgres -p 5433 -c "CREATE DATABASE voltoise"
```

Il te demande ton mot de passe `postgres`. **Tu dois voir** : `CREATE DATABASE`.

`-p 5433` : ton PostgreSQL n'écoute pas sur le port par défaut (5432), mais sur 5433. Sans
ce `-p`, tu as « Connection refused ».

### 4.3 Remplir la base

```powershell
& $psql -U postgres -p 5433 -d voltoise -f sql\01_creation.sql
```

**Tu dois voir** une suite de `CREATE TYPE`, `CREATE TABLE`, `INSERT 0 8`... Le `8` est le
nombre de lignes insérées. Au tout premier lancement, les `DROP` affichent des `NOTICE ...
does not exist, skipping` : c'est normal, il n'y avait rien à supprimer.

Vérifie à la main, en entrant dans psql :

```powershell
& $psql -U postgres -p 5433 -d voltoise
```

```sql
\dt                                  -- tu dois voir 19 tables
\dT                                  -- tu dois voir les 6 types ENUM
SELECT code_borne, etat FROM borne;  -- 10 bornes
\q
```

Puis lance les requêtes :

```powershell
& $psql -U postgres -p 5433 -d voltoise -f sql\02_requetes.sql
```

**Tu dois voir** dix tableaux de résultats. Ce sont eux que tu captureras pour le rapport.

### 4.4 Démarrer le serveur dans NetBeans

1. Ouvre `reseau\serveur-java\serveur.properties` et remplace `A_REMPLIR` par ton mot de
   passe PostgreSQL.
2. NetBeans : **File > Open Project**, choisis le dossier `reseau\serveur-java`. L'icône
   est celle de Maven, c'est normal.
3. Clic droit sur le projet > **Build**. La première fois, Maven télécharge le pilote
   PostgreSQL depuis Internet. **Tu dois voir** : `BUILD SUCCESS`.
4. Clic droit > **Run**. Si NetBeans te demande la classe principale, choisis
   `fr.voltoise.serveur.ServeurBornes`.
5. Si Windows affiche une alerte du pare-feu pour Java, autorise les réseaux privés.

**Tu dois voir**, dans la fenêtre Output :

```
[21:40:12] serveur en ecoute sur le port 5000
```

Le serveur attend. Il ne rend pas la main : c'est voulu, il tourne en boucle sur `accept()`.
Laisse NetBeans ouvert.

### 4.5 Lancer une borne

Ouvre un **deuxième** PowerShell :

```powershell
cd D:\All\Licence_Cergy\Semestre_5\05_SAE_BD_Reseau\reseau\client-python
python borne.py CGY-01-B01 --badge 2
```

**Tu dois voir** côté borne :

```
-> HELLO CGY-01-B01
<- OK 1001 libre
-> TARIF
<- OK 0.3900 0.0500 Public
-> START 2
<- OK 13
...
<- OK 13 7.11
-> BYE
<- OK au revoir
```

Et **en même temps**, dans NetBeans, les mêmes messages vus depuis le serveur, avec `<-`
pour ce qu'il reçoit et `->` pour ce qu'il envoie. Mets les deux fenêtres côte à côte :
c'est exactement ce que tu montreras dans la vidéo.

Puis vérifie que la base a bougé, dans le premier PowerShell :

```powershell
& $psql -U postgres -p 5433 -d voltoise -c "SELECT id_session, id_borne, statut, energie_kwh FROM session_charge ORDER BY id_session DESC LIMIT 3"
```

**Tu dois voir** ta session 13, `terminee`, avec l'énergie envoyée par la borne. C'est la
preuve que la chaîne complète fonctionne : Python, réseau, Java, JDBC, PostgreSQL.

Essaie ensuite, un par un, en regardant le log du serveur à chaque fois :

```powershell
python borne.py CGY-01-B02 --badge 4     # badge bloqué
python borne.py XXX-99                   # borne inconnue
python borne.py CGY-02-B01               # borne en maintenance
python borne.py CGY-01-B01 -i            # tu tapes toi-même les commandes
```

En mode `-i`, fais une charge à la main (`HELLO`, `START 1`, puis ferme brutalement la
fenêtre PowerShell), et vérifie dans psql que la session est passée à `interrompue`.

### 4.6 netcat

D'abord l'installer : `scoop install netcat` (Scoop est déjà sur ton PC).

**netcat à la place de la borne**, face au serveur Java :

```powershell
nc 127.0.0.1 5000
```

Puis tu tapes `HELLO CGY-01-B01`, Entrée, et tu vois la réponse. Tu es devenu la borne.

**netcat à la place du serveur**, arrête d'abord le serveur dans NetBeans (le carré rouge),
puis :

```powershell
nc -l -p 5000
```

Dans l'autre fenêtre, lance `python borne.py CGY-01-B01`. netcat affiche `HELLO CGY-01-B01`
et attend : c'est **toi** le serveur maintenant, tape `OK 1 libre` et Entrée. Tu vois
exactement les octets que ton client envoie, sans rien entre les deux.

### 4.7 Dépannage

| Message | Cause | Solution |
|---|---|---|
| `Connection refused` dans psql | mauvais port | ajoute `-p 5433` |
| `password authentication failed` | mauvais mot de passe | celui choisi à l'installation de PostgreSQL |
| `PrÃ©fecture` dans les résultats | encodage | refais 4.1, puis relance `01_creation.sql` |
| `serveur.properties (Le fichier spécifié est introuvable)` | NetBeans ne lance pas depuis le bon dossier | vérifie que tu as ouvert `reseau\serveur-java` et pas un dossier au-dessus |
| `No suitable driver found` | le pilote n'a pas été téléchargé | Build à nouveau avec Internet |
| `Address already in use` | le serveur tourne déjà (lancé deux fois) | arrête tous les Run dans NetBeans, en bas à droite |
| `ERR 503` côté borne | le serveur n'arrive pas à joindre la base | regarde le log serveur : mot de passe ou port dans `serveur.properties` |
| `connexion impossible à 127.0.0.1:5000` | le serveur n'est pas lancé | étape 4.4 |

---

## Partie 5. Lire le code, dans le bon ordre

Ne lis pas les fichiers dans l'ordre alphabétique. Suis le trajet d'un message.

**Étape 1. `borne.py`, fonction `charge_complete`.** C'est la séquence de la partie 1.6
écrite en Python. Lis-la en parallèle de ton schéma de la partie 3. Puis `envoyer` : une
ligne part, une ligne revient.

**Étape 2. `ServeurBornes.java`.** Vingt lignes utiles. Le serveur lit sa configuration,
ouvre le port, et boucle : chaque `accept()` renvoie un socket, confié à un thread. Question
à te poser : que se passe-t-il si on supprime le pool de threads et qu'on appelle
`dialoguer` directement dans la boucle ? Qu'arrive-t-il à la deuxième borne ?

**Étape 3. `DialogueBorne.java`, dans cet ordre** : `dialoguer` (la boucle et le `switch`),
puis `hello`, `start`, `stop`, `bye`, puis `lireLigne`, et enfin `run` et `libererBorne`.

**Étape 4. `AccesBase.java`** : `trouverBorne` d'abord (la requête préparée la plus simple),
puis `ouvrirSession` (la transaction).

Pour chaque méthode, pose-toi trois questions : **qu'est-ce qui entre, qu'est-ce qui sort,
qu'est-ce qui change dans la base.**

### Les quatre questions de soutenance

Réponds par écrit, sur papier, **avant** de lire les réponses en dessous.

1. Pourquoi `lireLigne()` ne se sert pas de `BufferedReader.readLine()` ?
2. À quoi sert `FOR UPDATE` dans `ouvrirSession` ?
3. Pourquoi `libererBorne()` est-il dans le bloc **intérieur** de `run()` ?
4. Que fait `?::etat_borne` dans `changerEtat` ?

### Point de contrôle final

Ferme tous les fichiers. Sur une feuille blanche, dessine le schéma de la partie 3 de
mémoire, avec les requêtes SQL. Si tu y arrives, tu comprends le projet. Si tu n'y arrives
pas, tu sais exactement quelle partie relire.

---

<details>
<summary>Réponses aux quatre questions de soutenance, à ne lire qu'après avoir écrit les tiennes</summary>

1. `readLine()` lit jusqu'au prochain `\n` **sans limite de taille**. Un client malveillant
   qui envoie des octets sans jamais de `\n` ferait grossir la mémoire du serveur jusqu'à le
   faire tomber. `lireLigne()` s'arrête à 1024 octets et ferme la connexion.

2. Deux connexions peuvent annoncer le même code de borne (une borne qui redémarre alors
   que l'ancienne connexion n'est pas encore fermée, ou un faux client). Si les deux
   envoient `START` au même instant sans verrou, les deux lisent `libre`, et les deux
   créent une session sur la même borne. `FOR UPDATE` verrouille la ligne de la borne : la deuxième transaction attend
   que la première ait fini, puis lit `occupee` et reçoit `ERR 409`.

3. Un `try (ressource)` ferme la ressource **avant** d'exécuter ses propres `catch` et
   `finally`. Si `libererBorne()` était dans le `finally` extérieur, la connexion à la base
   serait déjà fermée, l'UPDATE échouerait, et la session resterait `en_cours` pour toujours.

4. Le paramètre est envoyé comme du texte, `'libre'`. La colonne est de type `etat_borne`.
   PostgreSQL refuse de comparer ou d'affecter un texte à un ENUM sans conversion explicite.
   `::etat_borne` est la conversion (le « cast ») en syntaxe PostgreSQL. Si le texte n'est
   pas une valeur de l'ENUM, la base refuse avec une erreur.

</details>
