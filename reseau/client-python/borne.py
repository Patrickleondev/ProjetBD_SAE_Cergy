"""Client réseau : simule une borne de recharge Voltoise.

    python borne.py CGY-01-B01                  charge complète automatique
    python borne.py CGY-01-B01 --badge 4        même chose avec un badge bloqué
    python borne.py CGY-01-B01 -i               mode interactif, on tape les commandes
"""
import argparse
import random
import socket
import sys
import time

TAILLE_MAX_LIGNE = 1024


class Borne:
    def __init__(self, hote, port, code, delai=10):
        self.code = code
        self.sock = socket.create_connection((hote, port), timeout=delai)
        # makefile lit ligne par ligne : TCP ne garde pas les frontières de messages
        self.flux = self.sock.makefile("r", encoding="utf-8", newline="\n")

    def envoyer(self, message):
        """Envoie une ligne et renvoie (ok, reste de la réponse)."""
        print("->", message)
        self.sock.sendall((message + "\n").encode("utf-8"))
        reponse = self.flux.readline(TAILLE_MAX_LIGNE)
        if not reponse:
            raise ConnectionError("le serveur a fermé la connexion")
        reponse = reponse.rstrip("\n")
        print("<-", reponse)
        statut, _, reste = reponse.partition(" ")
        return statut == "OK", reste

    def fermer(self):
        self.flux.close()
        self.sock.close()


def charge_complete(borne, id_badge):
    ok, reponse = borne.envoyer("HELLO " + borne.code)
    if not ok:
        print("borne refusée par le serveur, arrêt")
        return

    etat = reponse.split()[1]
    if etat != "libre":
        print("la borne est en état", etat, ": pas de charge possible")
        borne.envoyer("BYE")
        return

    borne.envoyer("TARIF")

    ok, reponse = borne.envoyer("START %d" % id_badge)
    if not ok:
        # badge refusé : la borne affiche l'erreur et ne délivre pas de courant
        print("charge refusée :", reponse)
        borne.envoyer("BYE")
        return

    print("charge en cours...")
    time.sleep(2)
    energie = round(random.uniform(5, 40), 3)

    borne.envoyer("TARIF")
    ok, reponse = borne.envoyer("STOP %s" % energie)
    if ok:
        id_session, montant, mode = reponse.split()
        print("session %s terminée, %s kWh, %s euros" % (id_session, energie, montant))
        if mode == "CB":
            # particulier : le terminal de paiement de la borne encaisse, puis on prévient le serveur
            print("paiement par carte bancaire de %s euros..." % montant)
            time.sleep(1)
            reference = "CB-%08d" % random.randint(0, 99999999)
            print("paiement accepté, transaction", reference)
            borne.envoyer("PAYE %s %s" % (id_session, reference))
        else:
            # entreprise : rien à payer à la borne, la charge ira sur la facture du mois
            print("client entreprise : charge ajoutée à la facture mensuelle")

    borne.envoyer("BYE")


def interactif(borne):
    print("commandes : HELLO, START, STOP, PAYE, TARIF, STATUS, BYE (ligne vide pour quitter)")
    while True:
        ligne = input("> ").strip()
        if not ligne:
            break
        borne.envoyer(ligne)
        if ligne.upper() == "BYE":
            break


def main():
    p = argparse.ArgumentParser(description="borne de recharge simulée")
    p.add_argument("code", help="code de la borne, ex. CGY-01-B01")
    p.add_argument("--hote", default="127.0.0.1")
    p.add_argument("--port", type=int, default=5000)
    p.add_argument("--badge", type=int, default=1)
    p.add_argument("-i", "--interactif", action="store_true")
    args = p.parse_args()

    try:
        borne = Borne(args.hote, args.port, args.code)
    except OSError as e:
        print("connexion impossible à %s:%d : %s" % (args.hote, args.port, e))
        sys.exit(1)

    try:
        if args.interactif:
            interactif(borne)
        else:
            charge_complete(borne, args.badge)
    except (ConnectionError, socket.timeout) as e:
        print("connexion perdue :", e)
        sys.exit(2)
    finally:
        borne.fermer()


if __name__ == "__main__":
    main()
