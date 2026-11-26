# Fin de la démonstration : le site montre ce que les bornes viennent d'écrire dans la base.
# Ouvre Edge, avec un curseur rouge et un cercle à chaque clic pour que la vidéo les montre.
#   python demo_site.py
import os
import time

from playwright.sync_api import sync_playwright

SITE = "http://localhost:8000/"
CURSEUR = """
document.addEventListener('DOMContentLoaded', () => {
  const c = document.createElement('div');
  c.style.cssText = 'position:fixed;z-index:99999;width:18px;height:18px;border-radius:50%;'
    + 'background:rgba(220,30,30,.85);border:2px solid #fff;pointer-events:none;'
    + 'transform:translate(-50%,-50%);left:' + (window._x||-50) + 'px;top:' + (window._y||-50) + 'px';
  document.body.appendChild(c);
  document.addEventListener('mousemove', e => { c.style.left = e.clientX + 'px'; c.style.top = e.clientY + 'px'; });
  document.addEventListener('mousedown', e => {
    const r = document.createElement('div');
    r.style.cssText = 'position:fixed;z-index:99998;width:50px;height:50px;border-radius:50%;'
      + 'border:3px solid rgba(220,30,30,.9);pointer-events:none;transform:translate(-50%,-50%);'
      + 'left:' + e.clientX + 'px;top:' + e.clientY + 'px;transition:all .5s;';
    document.body.appendChild(r);
    setTimeout(() => { r.style.width = '90px'; r.style.height = '90px'; r.style.opacity = '0'; }, 10);
    setTimeout(() => r.remove(), 600);
  });
});
"""


def cliquer(page, cible):
    cible.scroll_into_view_if_needed()
    time.sleep(0.4)
    boite = cible.bounding_box()
    page.mouse.move(boite["x"] + boite["width"] / 2, boite["y"] + boite["height"] / 2, steps=18)
    time.sleep(0.3)
    page.mouse.down()
    page.mouse.up()
    time.sleep(0.4)


def choisir(page, liste, valeur):
    """Liste déroulante : le curseur va dessus, puis on choisit la valeur."""
    liste.scroll_into_view_if_needed()
    boite = liste.bounding_box()
    page.mouse.move(boite["x"] + boite["width"] / 2, boite["y"] + boite["height"] / 2, steps=20)
    time.sleep(0.4)
    liste.select_option(label=valeur)
    time.sleep(1.5)


def connecter(page, login):
    page.goto(SITE + "connexion.php")
    time.sleep(1.2)
    cliquer(page, page.locator("input[name=login]"))
    page.keyboard.type(login, delay=45)
    cliquer(page, page.locator("input[name=mot_de_passe]"))
    page.keyboard.type(os.environ["VOLTOISE_MDP"], delay=40)
    cliquer(page, page.get_by_role("button", name="Se connecter"))
    page.wait_for_load_state()
    time.sleep(1.8)


def deconnecter(page):
    page.evaluate("() => window.scrollTo({top: 0, behavior: 'smooth'})")
    time.sleep(1)
    cliquer(page, page.get_by_role("button", name="Déconnexion"))
    time.sleep(1)


def defiler(page, pixels, pause=1.8):
    """Défilement fluide (scroll smooth du navigateur), puis le temps de lire."""
    page.evaluate("p => window.scrollBy({top: p, behavior: 'smooth'})", pixels)
    time.sleep(1.2 + pause)


def tout_en_bas(page):
    page.evaluate("() => window.scrollTo({top: document.body.scrollHeight, behavior: 'smooth'})")
    time.sleep(2.8)


def main():
    with sync_playwright() as p:
        navigateur = p.chromium.launch(channel="msedge", headless=False,
                                       args=["--start-maximized", "--lang=fr",
                                             "--disable-features=Translate,TranslateUI,msEdgeTranslate"])
        page = navigateur.new_context(no_viewport=True, locale="fr-FR").new_page()
        page.add_init_script(CURSEUR)
        page.on("dialog", lambda d: d.accept())      # « Bloquer ce badge ? » : on confirme

        # client particulier : la charge payée par carte au test 1, le filtre par mois, bloquer puis réactiver le badge
        connecter(page, "claire.martin")
        page.mouse.move(640, 400, steps=10)
        tout_en_bas(page)                              # le logo CY, une seule fois
        choisir(page, page.locator("select[name=mois]"), "Novembre 2026")
        page.wait_for_load_state()
        time.sleep(1.5)
        cliquer(page, page.get_by_role("button", name="Bloquer"))
        page.wait_for_load_state()
        time.sleep(1.8)
        cliquer(page, page.get_by_role("button", name="Réactiver"))
        page.wait_for_load_state()
        time.sleep(1.5)
        deconnecter(page)

        # client entreprise : deux badges, les charges qui attendent la facture du mois
        connecter(page, "transports.durand")
        page.mouse.move(640, 400, steps=10)
        defiler(page, 600)
        deconnecter(page)

        # gestionnaire : filtrer les bornes, en passer une en maintenance
        connecter(page, "admin.voltoise")
        cliquer(page, page.get_by_role("link", name="Hors service"))
        page.wait_for_load_state()
        time.sleep(1.5)
        cliquer(page, page.get_by_role("link", name="Toutes"))
        page.wait_for_load_state()
        time.sleep(1)
        borne = page.locator("form.borne__action:has(input[name=id_borne][value='9'])")
        choisir(page, borne.locator("select[name=etat]"), "Maintenance")
        cliquer(page, borne.get_by_role("button", name="Appliquer"))
        page.wait_for_load_state()
        time.sleep(1.8)

        # déclarer un incident qui met la borne hors service (INSERT + UPDATE dans une transaction)
        cliquer(page, page.get_by_role("link", name="Incidents", exact=True))
        page.wait_for_load_state()
        time.sleep(1.5)
        formulaire = page.locator("form:has(input[name=action][value=declarer])")
        choisir(page, formulaire.locator("select[name=id_borne]"), "PTS-01-B02 · Pontoise Gare")
        cliquer(page, formulaire.locator("label:has(input[name=gravite][value=majeur])"))
        cliquer(page, formulaire.locator("textarea[name=description]"))
        page.keyboard.type("Ecran de la borne eteint, paiement impossible", delay=25)
        cliquer(page, formulaire.locator("label:has(input[name=hors_service])"))
        cliquer(page, formulaire.get_by_role("button", name="Enregistrer l'incident"))
        page.wait_for_load_state()
        time.sleep(2.2)

        # les chiffres du mois
        cliquer(page, page.get_by_role("link", name="Chiffres"))
        page.wait_for_load_state()
        time.sleep(2.2)
        defiler(page, 500)
        navigateur.close()


if __name__ == "__main__":
    main()
