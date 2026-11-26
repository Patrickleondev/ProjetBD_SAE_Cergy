# 1. Le contexte, expliqué simplement

> **Note :** les identifiants (logins, mots de passe, adresses IP, noms de bases, noms des membres) sont volontairement remplacés par `<...>` dans ce dépôt public, pour ne pas exposer de données sensibles.

## Ce qu'on construit

Un système d'information pour **Voltoise**, opérateur de **bornes de recharge de
véhicules électriques** implanté en Val-d'Oise et en Île-de-France. La société possède
plusieurs stations, chaque station a plusieurs bornes, et des clients viennent y recharger
leur voiture en présentant un badge.

> **Le nom** : *Volt* + *Oise*. L'ancrage territorial n'est pas décoratif - il explique la
> taille du réseau modélisé et pourquoi les stations sont à Cergy. La plupart des opérateurs
> français réels sont nés régionaux avant de s'étendre.
>
> Domaine `voltoise.fr` · e-mails `prenom.nom@voltoise.fr` · zone DNS `voltoise.org` côté TP
> réseau. Les codes de bornes (`CGY-05-B03`) restent fondés sur la commune.

Voltoise a trois problèmes aujourd'hui :

1. il ne sait pas en temps réel quelles bornes fonctionnent et lesquelles sont en panne ;
2. il ne sait pas ce qui se passe pendant une charge, donc il facture à la main ;
3. ses clients n'ont aucun moyen de consulter leurs consommations.

Notre solution répond aux trois.

## Pourquoi ce sujet et pas un autre

Le sujet du projet est libre. On aurait pu prendre une bibliothèque ou une billetterie.
On a choisi celui-ci pour une raison précise, et il faut savoir la dire en soutenance.

Le sujet impose un **client réseau** qui parle à un **serveur applicatif** avec un
protocole qu'on invente. Dans une bibliothèque, ce client n'a aucune raison d'exister :
un site web suffirait, et le correcteur le remarque.

Ici, le client réseau **est la borne elle-même**. Une borne n'a pas de navigateur, elle est
sur un parking, elle doit dire au serveur « je suis allumée », « une charge commence »,
« une charge est finie, voilà combien d'énergie j'ai délivré ». Le protocole devient
évident, on ne l'invente pas pour faire plaisir au sujet.

## Les deux façons d'utiliser le système

C'est le point le plus important à comprendre, tout le reste en découle.

**La voie réseau.** Les bornes parlent au serveur applicatif en TCP, avec notre protocole.
Personne n'est devant, ça tourne tout seul, jour et nuit.

**La voie web.** Les clients et les gestionnaires ouvrent un navigateur. Les clients
consultent leurs sessions et leurs factures. Les gestionnaires suivent l'état du parc et
les incidents.

Les deux voies écrivent et lisent **dans la même base PostgreSQL**. C'est là que le projet
devient cohérent : quand une borne annonce une panne par le réseau, le gestionnaire la voit
apparaître sur le site web. Sans base commune, on aurait deux projets sans rapport.

## Ce que le projet couvre

- le parc : stations, bornes, types de connecteurs
- les clients : comptes, véhicules, badges, abonnements
- les sessions de charge et leur facturation
- la maintenance : incidents et interventions des techniciens

## Ce que le projet ne couvre pas

Il faut l'écrire dans le rapport, ça montre qu'on a réfléchi au périmètre au lieu de
promettre n'importe quoi.

- le paiement en ligne réel, aucune transaction bancaire
- la carte géographique des stations, on stocke les coordonnées sans les afficher
- **OCPP**, le protocole normalisé qu'utilise vraiment l'industrie : on invente le nôtre,
  c'est l'exercice demandé
- la gestion du personnel de l'opérateur, hors techniciens de maintenance

## Si on nous demande en trente secondes

« On gère un réseau de bornes de recharge. Les bornes, qui n'ont pas d'écran ni de
navigateur, dialoguent avec notre serveur en TCP avec un protocole qu'on a défini : elles
s'identifient, ouvrent une session de charge, la referment et demandent leur tarif. En
parallèle, un site web laisse les clients consulter leurs factures et les gestionnaires
surveiller le parc. Les deux écrivent dans la même base PostgreSQL. »
