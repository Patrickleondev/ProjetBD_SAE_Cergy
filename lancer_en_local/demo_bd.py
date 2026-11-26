# Démonstration de la partie BD, déroulée toute seule en 3 minutes environ.
# 1. la base dans psql : tables, types énumérés, contraintes, deux requêtes ;
# 2. le site à gauche, et à droite la requête qui montre ce que chaque action a changé dans la base.
# La base doit tourner (lancer_tout.ps1 -Reinitialiser).
#   python demo_bd.py
import ctypes
import os
import subprocess
import time

from playwright.sync_api import sync_playwright

from demo_site import CURSEUR, choisir, cliquer, connecter, deconnecter, defiler

PSQL = r"C:\Program Files\PostgreSQL\18\bin\psql.exe"
os.system("")
JAUNE, CYAN, FIN = "\033[1;33m", "\033[1;36m", "\033[0m"
user32 = ctypes.windll.user32


def titre(texte, pause=4):
    print("\n" + JAUNE + "=" * 50 + "\n " + texte + "\n" + "=" * 50 + FIN, flush=True)
    time.sleep(pause)


def sql(requete, pause=4.5):
    """Affiche la requête puis son résultat, comme si on la tapait dans psql."""
    print(CYAN + "voltoise=# " + requete + FIN, flush=True)
    time.sleep(1)
    subprocess.run([PSQL, "-U", "postgres", "-h", "localhost", "-p", "54329", "-d", "voltoise",
                    "-q", "-c", requete], env=dict(os.environ, PGOPTIONS="-c client_min_messages=warning"))
    time.sleep(pause)


def console(x, y, largeur, hauteur):
    """Place la fenêtre de cette démo (titre « DEMO BD »)."""
    h = user32.FindWindowW(None, "DEMO BD")
    if h:
        user32.MoveWindow(h, x, y, largeur, hauteur, True)


# ---------------------------------------------------------------- 1. la base
console(0, 0, 1280, 690)
print(JAUNE + "Voltoise : démonstration de la base de données" + FIN)
print("PostgreSQL, base chargée par sql/01_creation.sql (jeu de données du 8 octobre 2026).")
time.sleep(3)

titre("1. Les 12 tables et les types énumérés")
sql("\\dt")
sql("SELECT t.typname AS type, string_agg(e.enumlabel, ', ' ORDER BY e.enumsortorder) AS valeurs "
    "FROM pg_type t JOIN pg_enum e ON e.enumtypid = t.oid GROUP BY t.typname")

titre("2. Les contraintes refusent les données fausses")
sql("INSERT INTO client(nom, prenom, email, telephone, id_tarif) "
    "VALUES ('Dupont', 'Luc', 'luc.exemple.fr', '0611223344', 1);", 1.8)
sql("UPDATE session_charge SET fin = debut + INTERVAL '30 hours' WHERE id_session = 1;", 1.8)
sql("UPDATE compte_web SET hash_mdp = 'MotDePasseEnClair' WHERE id_compte = 1;", 2.5)

titre("3. Requête avec GROUP BY : énergie par station en septembre 2026")
sql("SELECT s.nom_station AS station, COUNT(sc.id_session) AS nb_charges, SUM(sc.energie_kwh) AS kwh "
    "FROM station s JOIN borne b ON b.id_station = s.id_station "
    "JOIN session_charge sc ON sc.id_borne = b.id_borne "
    "WHERE sc.statut = 'terminee' AND sc.debut >= DATE '2026-09-01' AND sc.debut < DATE '2026-10-01' "
    "GROUP BY s.id_station, s.nom_station ORDER BY kwh DESC")

titre("4. Requête imbriquée : les bornes qui n'ont jamais eu d'incident")
sql("SELECT b.code_borne, b.etat FROM borne b "
    "WHERE NOT EXISTS (SELECT 1 FROM incident i WHERE i.id_borne = b.id_borne) ORDER BY b.code_borne")

titre("5. Héritage : ce que chaque ENTREPRISE doit sur sa facture")
sql("SELECT e.raison_sociale, COUNT(sc.id_session) AS charges, "
    "ROUND(SUM(sc.energie_kwh * t.prix_kwh + EXTRACT(EPOCH FROM (sc.fin - sc.debut))::numeric / 60 "
    "* t.prix_minute), 2) AS a_facturer "
    "FROM entreprise e JOIN client c ON c.id_client = e.id_client JOIN tarif t ON t.id_tarif = c.id_tarif "
    "JOIN badge b ON b.id_client = e.id_client JOIN session_charge sc ON sc.id_badge = b.id_badge "
    "WHERE sc.statut = 'terminee' AND sc.date_paiement IS NULL GROUP BY e.id_client, e.raison_sociale", 3.5)

# ---------------------------------------------------------------- 2. le site et son effet sur la base
titre("6. Le site : chaque action, puis la base", 1.5)
console(830, 0, 450, 690)

with sync_playwright() as p:
    navigateur = p.chromium.launch(channel="msedge", headless=False,
                                   args=["--window-position=0,0", "--window-size=830,690", "--lang=fr",
                                         "--disable-features=Translate,TranslateUI,msEdgeTranslate"])
    page = navigateur.new_context(no_viewport=True, locale="fr-FR").new_page()
    page.add_init_script(CURSEUR)
    page.on("dialog", lambda d: d.accept())      # « Bloquer ce badge ? » : on confirme

    # particulier : bloquer son badge = UPDATE badge SET actif = false
    titre("Client particulier : bloquer son badge", 0.5)
    connecter(page, "claire.martin")
    page.mouse.move(400, 400, steps=10)
    cliquer(page, page.get_by_role("button", name="Bloquer"))
    page.wait_for_load_state()
    time.sleep(1)
    sql("SELECT id_badge, actif FROM badge WHERE id_client = 1;", 2.5)
    cliquer(page, page.get_by_role("button", name="Réactiver"))
    page.wait_for_load_state()
    sql("SELECT id_badge, actif FROM badge WHERE id_client = 1;", 1.5)
    choisir(page, page.locator("select[name=mois]"), "Septembre 2026")
    page.wait_for_load_state()
    time.sleep(1.5)
    deconnecter(page)

    # entreprise : rien n'est payé à la borne, les charges attendent la facture
    titre("Client entreprise : charges en attente de facture", 0.5)
    connecter(page, "transports.durand")
    page.mouse.move(400, 400, steps=10)
    defiler(page, 550, pause=1)
    sql("SELECT id_session, statut, ref_paiement FROM session_charge WHERE id_badge IN (6, 7) "
        "ORDER BY id_session;", 2.5)
    deconnecter(page)

    # gestionnaire : changer l'état d'une borne = UPDATE borne SET etat
    titre("Gestionnaire : borne en maintenance", 0.5)
    connecter(page, "admin.voltoise")
    cliquer(page, page.get_by_role("link", name="Hors service"))
    page.wait_for_load_state()
    time.sleep(2)
    cliquer(page, page.get_by_role("link", name="Toutes"))
    page.wait_for_load_state()
    time.sleep(1)
    borne = page.locator("form.borne__action:has(input[name=id_borne][value='9'])")
    choisir(page, borne.locator("select[name=etat]"), "Maintenance")
    cliquer(page, borne.get_by_role("button", name="Appliquer"))
    page.wait_for_load_state()
    sql("SELECT code_borne, etat FROM borne WHERE id_borne = 9;", 2)

    # déclarer un incident : INSERT incident + UPDATE borne, dans une seule transaction
    titre("Gestionnaire : déclarer un incident", 0.5)
    cliquer(page, page.get_by_role("link", name="Incidents", exact=True))
    page.wait_for_load_state()
    time.sleep(1)
    formulaire = page.locator("form:has(input[name=action][value=declarer])")
    choisir(page, formulaire.locator("select[name=id_borne]"), "PTS-01-B02 · Pontoise Gare")
    cliquer(page, formulaire.locator("label:has(input[name=gravite][value=majeur])"))
    cliquer(page, formulaire.locator("textarea[name=description]"))
    page.keyboard.type("Ecran de la borne eteint", delay=30)
    cliquer(page, formulaire.locator("label:has(input[name=hors_service])"))
    cliquer(page, formulaire.get_by_role("button", name="Enregistrer l'incident"))
    page.wait_for_load_state()
    sql("SELECT i.id_incident, i.gravite, b.code_borne, b.etat FROM incident i "
        "JOIN borne b USING (id_borne) ORDER BY i.id_incident DESC LIMIT 1;", 2.5)

    # clore l'incident : UPDATE incident (date de résolution) + la borne redevient libre
    titre("Gestionnaire : clore l'incident", 0.5)
    cliquer(page, page.locator("form.incident__action:has(input[name=id_incident][value='6'])")
            .get_by_role("button", name="Clore"))
    page.wait_for_load_state()
    sql("SELECT i.id_incident, i.date_resolution, b.code_borne, b.etat FROM incident i "
        "JOIN borne b USING (id_borne) WHERE i.id_incident = 6;", 2.5)

    # extraction : les chiffres du mois (agrégats, GROUP BY, héritage)
    titre("Gestionnaire : les chiffres du mois", 0.5)
    cliquer(page, page.get_by_role("link", name="Chiffres"))
    page.wait_for_load_state()
    time.sleep(3)
    defiler(page, 450, pause=2.5)
    defiler(page, 450, pause=3)
    navigateur.close()

console(0, 0, 1280, 690)
print("\n" + JAUNE + "Fin de la démonstration." + FIN)
