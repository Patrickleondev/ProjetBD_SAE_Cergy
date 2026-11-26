# Démonstration de la partie réseau, déroulée toute seule en moins de 3 minutes.
# Le serveur doit tourner (lancer_tout.ps1 -Reinitialiser) : son journal s'affiche dans la fenêtre SERVEUR.
#   python demo_reseau.py
import os
import subprocess
import sys
import time

ICI = os.path.dirname(os.path.abspath(__file__))
SAE = os.path.dirname(ICI)
BORNE = os.path.join(SAE, "reseau", "client-python", "borne.py")
JAVA = r"C:\Program Files\Android\Android Studio\jbr\bin\java.exe"
JAR = os.path.join(SAE, "reseau", "serveur-java", "dist", "serveur-bornes.jar")
PSQL = r"C:\Program Files\PostgreSQL\18\bin\psql.exe"
os.system("")                                   # active les couleurs dans la console Windows
os.environ["PYTHONIOENCODING"] = "utf-8"

JAUNE, CYAN, GRIS, FIN = "\033[1;33m", "\033[1;36m", "\033[90m", "\033[0m"


def titre(n, texte):
    print("\n" + JAUNE + "=" * 55)
    print(" %d. %s" % (n, texte))
    print("=" * 55 + FIN, flush=True)
    time.sleep(4.5)


def commande(texte):
    print(CYAN + "$ " + texte + FIN, flush=True)
    time.sleep(1.5)


def borne(*args):
    commande("python borne.py " + " ".join(args))
    subprocess.run([sys.executable, BORNE, *args])
    time.sleep(4.5)


def netcat(lignes, couper=False, delai=2.8):
    """ncat en client : chaque ligne est affichée comme si on la tapait, la réponse suit."""
    commande("ncat 127.0.0.1 5000")
    nc = subprocess.Popen(["ncat", "127.0.0.1", "5000"], stdin=subprocess.PIPE)
    time.sleep(0.5)
    for ligne in lignes:
        print(GRIS + "> " + FIN + ligne, flush=True)
        nc.stdin.write((ligne + "\n").encode())
        nc.stdin.flush()
        time.sleep(delai)
    if couper:
        print(JAUNE + "  [Ctrl+C : le client disparaît en pleine charge]" + FIN, flush=True)
        nc.kill()
    else:
        nc.stdin.close()
        nc.wait(timeout=5)
    time.sleep(1.5)


def sql(requete):
    commande("psql : " + requete)
    subprocess.run([PSQL, "-U", "postgres", "-h", "localhost", "-p", "54329", "-d", "voltoise", "-c", requete])
    time.sleep(2.5)


print(JAUNE + "Voltoise : démonstration du protocole borne <-> serveur" + FIN)
print("Protocole texte sur TCP, port 5000, une commande par ligne ; le serveur répond OK ... ou ERR code.")
print("A gauche : la borne (Python) et netcat. A droite : le journal du serveur (Java).")
time.sleep(5)

titre(1, "Charge complète d'un particulier : HELLO, TARIF, START, STOP, PAYE, BYE")
borne("CGY-01-B01", "--badge", "1")

titre(2, "Echange alternatif : client entreprise (FACTURE), puis badge bloqué (ERR 403)")
borne("CGY-01-B02", "--badge", "6")
borne("CGY-01-B01", "--badge", "4")

titre(3, "netcat en client : commandes hors ordre et inconnues")
netcat(["START 1", "HELLO CGY-02-B01", "BONJOUR", "STOP 10", "BYE"])

titre(4, "Coupure du client pendant une charge")
netcat(["HELLO PTS-01-B01", "START 2"], couper=True)
sql("SELECT s.id_session, s.statut, b.code_borne, b.etat FROM session_charge s JOIN borne b USING (id_borne) "
    "ORDER BY s.id_session DESC LIMIT 1")

titre(5, "netcat en serveur : la borne suit le protocole")
reponses = "OK 1001 libre\nOK 0.4900 0.0500 Public\nOK 42\nOK 0.3500 0.0000 Abonné\nOK 42 9.18 CB\nOK 42 payee\nOK au revoir\n"
commande("ncat -l 5001   (réponses préparées)   puis   python borne.py CGY-01-B01 --port 5001")
serveur_nc = subprocess.Popen(["ncat", "-l", "5001"], stdin=subprocess.PIPE, stdout=subprocess.PIPE)
serveur_nc.stdin.write(reponses.encode("utf-8"))
serveur_nc.stdin.flush()
time.sleep(1)
subprocess.run([sys.executable, BORNE, "CGY-01-B01", "--port", "5001"], stdout=subprocess.DEVNULL)
recu = serveur_nc.communicate(timeout=5)[0].decode("utf-8", "replace")
print(GRIS + "ce que netcat a reçu de la borne :" + FIN)
print(recu)
time.sleep(5)

titre(6, "Erreurs de connexion : port déjà pris, port fermé, hôte inconnu")
commande("java -jar serveur-bornes.jar   (un 2e serveur sur le port 5000)")
subprocess.run([JAVA, "-jar", JAR, os.path.join(ICI, "serveur-local.properties")])
time.sleep(2)
borne("CGY-01-B01", "--port", "5999")
borne("CGY-01-B01", "--hote", "serveur-inexistant.voltoise")

titre(7, "Ligne de 2 000 caractères : ERR 413 et fermeture")
commande("python -c \"print('A'*2000)\" | ncat 127.0.0.1 5000")
subprocess.run(["ncat", "127.0.0.1", "5000"], input=("A" * 2000 + "\n").encode())
time.sleep(3)

print("\n" + JAUNE + "Fin de la démonstration." + FIN)
