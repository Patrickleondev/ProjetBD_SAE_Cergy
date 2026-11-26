# 4. Les échanges réseau, notre protocole

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

## Ce que le sujet exige

Trois fonctionnalités minimales, non négociables :

1. un échange dans les deux sens, robuste, entre le client et le serveur ;
2. dans le protocole, au minimum une **mise à jour** d'une table depuis le client, et une
   **extraction** de données renvoyée au client ;
3. une **fermeture propre** de l'échange.

Et comme on est en TCP, cinq échanges sont attendus :

- un échange qui **identifie** le client (identifier, pas authentifier)
- un échange qui met à jour une table
- un échange qui renvoie des données de la base au client
- un échange qui, selon la réponse, provoque des suites différentes
- un échange qui termine la conversation

## Notre vocabulaire

Un message = une ligne de texte = un mot-clé en majuscules, puis des paramètres séparés par
des espaces, puis `\n`.

Le serveur répond toujours. Deux formes de réponse seulement :

```
OK  <données éventuelles>
ERR <code> <message lisible>
```

Toujours répondre, même en cas d'erreur, c'est ce qui rend le protocole robuste : le
client n'attend jamais dans le vide.

## Les messages

| Message | Sens | Ce qu'il fait |
|---|---|---|
| `HELLO <code_borne>` | borne → serveur | la borne annonce son code |
| `START <id_badge> <horodatage>` | borne → serveur | une charge commence |
| `STOP <id_session> <energie_kwh>` | borne → serveur | une charge se termine |
| `TARIF` | borne → serveur | la borne demande le tarif en vigueur |
| `STATUS <etat>` | borne → serveur | la borne signale son état |
| `BYE` | borne → serveur | fin de la conversation |

## Les scénarios, un par onglet dans `echanges_reseau.drawio`

### Scénario 1, identification

```
borne → serveur   HELLO CGY-05-B03
serveur → base    SELECT id_borne FROM borne WHERE code_borne = ?
base → serveur    une ligne, ou rien
serveur → borne   OK 4471            (numéro de session de dialogue)
       ou         ERR 401 borne inconnue
```

C'est l'échange « qui provoque différentes suites » demandé par le sujet : si la borne est
inconnue, le serveur ferme, sinon la conversation continue.

Le serveur en profite pour mettre à jour `derniere_communication`. Le site web peut alors
afficher « cette borne n'a plus donné signe de vie depuis 20 minutes ». C'est le lien
concret entre la partie réseau et la partie web.

### Scénario 2, mise à jour depuis le client

```
borne → serveur   START 310 2026-10-07T14:32:08
serveur           contrôle : le badge existe, il est actif, la borne est libre
serveur → base    INSERT INTO session_charge (...)
base → serveur    id_session = 10452
serveur → borne   OK 10452
       ou         ERR 403 badge bloqué
```

C'est la **mise à jour d'une table depuis le client**, exigence explicite du sujet.

Point important : la borne envoie `310`, pas une requête SQL. Le serveur construit la
requête avec un `PreparedStatement`. Si quelqu'un branche un faux client et envoie
n'importe quoi à la place de `310`, le pire qui arrive est un `ERR 400`.

### Scénario 3, extraction puis fermeture

```
borne → serveur   TARIF
serveur → base    SELECT prix_kwh, prix_minute FROM tarif WHERE ...
base → serveur    0.2200 / 0.0200
serveur → borne   OK 0.2200 0.0200
borne → serveur   BYE
serveur → borne   OK au revoir
                  puis fermeture du socket
```

C'est l'**extraction de données** et la **fermeture propre**. Le serveur ne coupe pas la
connexion sans avoir accusé réception du `BYE`.

## La robustesse, ce qui sera testé

Le sujet demande explicitement des tests de robustesse. Ce qu'il faut prévoir :

**Le client se déconnecte au milieu.** Le serveur doit détecter un `recv` qui renvoie zéro
octet, clôturer la session en base avec le statut `interrompue`, et libérer la borne. Une
session `en_cours` qui ne se ferme jamais fausse tout le reste.

**Un message inconnu.** Répondre `ERR 400 commande inconnue` et continuer, ne pas planter.

**Un message trop long.** Fixer une limite, par exemple 1024 octets par ligne, et rejeter
au-delà. Sans limite, un client malveillant fait grossir la mémoire du serveur jusqu'à le
faire tomber.

**Deux bornes en même temps.** Le serveur doit accepter plusieurs connexions. Un thread par
client suffit à notre échelle.

**Un message coupé en deux paquets.** Voir le piège TCP expliqué dans le fichier 03 : on lit
ligne par ligne, jamais paquet par paquet.

## Les tests avec netcat

Le sujet dit « l'utilisation de netcat aussi bien en mode client qu'en mode serveur est
attendue ». Donc les deux :

**netcat en client**, pour parler à notre serveur Java à la main :

```
nc 127.0.0.1 5000
HELLO CGY-05-B03
```

**netcat en serveur**, pour voir ce que notre client Python envoie vraiment :

```
nc -l -p 5000
```

Le deuxième est très utile au début : on lance le client Python contre netcat et on lit les
octets bruts. C'est la seule façon de vérifier qu'on envoie bien ce qu'on croit envoyer.

## Le calendrier de cette partie

- semaines 39-40 : deux diagrammes applicatifs, déjà faits
- semaines 41-42 : quatre ou cinq scénarios complets, il en manque un ou deux
- semaines 43-45 : écrire le client et le serveur
- semaines 46-47 : tests d'intégration et de robustesse

Le TP 3 et le TP 4 de réseau tombent au bon moment. Ce qu'on y apprend, sockets, `bind`,
`listen`, `accept`, gestion de plusieurs clients, c'est exactement ce qu'il faut ici. À
prendre en notes en pensant au projet, pas seulement au TP.
