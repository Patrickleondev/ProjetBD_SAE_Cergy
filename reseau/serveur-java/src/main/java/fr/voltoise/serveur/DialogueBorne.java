package fr.voltoise.serveur;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.math.BigDecimal;
import java.net.Socket;
import java.net.SocketTimeoutException;
import java.nio.charset.StandardCharsets;
import java.sql.SQLException;
import java.util.Set;

// Une instance par borne connectee, executee dans son propre thread.
public class DialogueBorne implements Runnable {

    private static final int TAILLE_MAX_LIGNE = 1024;
    private static final int INACTIVITE_MAX_MS = 5 * 60 * 1000;
    private static final Set<String> ETATS_AUTORISES = Set.of("libre", "hors_service", "maintenance");

    private final Socket socket;
    private final int numero;
    private final String origine;

    private InputStream in;
    private OutputStream out;
    private AccesBase base;

    // etat du dialogue
    private AccesBase.Borne borne;      // null tant que HELLO n a pas reussi
    private String codeBorne;
    private int sessionEnCours = 0;     // 0 = pas de charge
    private int badgeEnCours = 0;

    DialogueBorne(Socket socket, int numero) {
        this.socket = socket;
        this.numero = numero;
        this.origine = socket.getInetAddress().getHostAddress() + ":" + socket.getPort();
    }

    @Override
    public void run() {
        log("connexion de " + origine);
        try (socket) {
            socket.setSoTimeout(INACTIVITE_MAX_MS);
            in = socket.getInputStream();
            out = socket.getOutputStream();
            // try-with-resources ferme avant catch et finally : libererBorne()
            // doit donc passer dans le bloc interieur, tant que la base est ouverte
            try (AccesBase b = new AccesBase()) {
                base = b;
                try {
                    dialoguer();
                } finally {
                    libererBorne();
                }
            } catch (SQLException e) {
                log("base inaccessible : " + e.getMessage());
                envoyer("ERR 503 service indisponible");
            }
        } catch (SocketTimeoutException e) {
            log("inactivite trop longue, fermeture");
        } catch (IOException e) {
            log("connexion perdue : " + e.getMessage());
        }
        log("fin du dialogue");
    }

    private void dialoguer() throws IOException {
        String ligne;
        while ((ligne = lireLigne()) != null) {
            log("<- " + ligne);
            String[] mots = ligne.trim().split("\\s+");
            String commande = mots[0].toUpperCase();

            if (!commande.equals("HELLO") && !commande.equals("BYE") && borne == null) {
                envoyer("ERR 403 identification requise");
                continue;
            }

            try {
                boolean continuer = switch (commande) {
                    case "HELLO"  -> hello(mots);
                    case "START"  -> start(mots);
                    case "STOP"   -> stop(mots);
                    case "PAYE"   -> paye(mots);
                    case "TARIF"  -> tarif();
                    case "STATUS" -> status(mots);
                    case "BYE"    -> bye();
                    default -> {
                        envoyer("ERR 400 commande inconnue");
                        yield true;
                    }
                };
                if (!continuer) {
                    return;
                }
            } catch (SQLException e) {
                // le detail reste dans le log du serveur, jamais chez le client
                log("erreur SQL : " + e.getMessage());
                envoyer("ERR 500 erreur interne");
            }
        }
        log("la borne a ferme la connexion sans BYE");
    }

    // HELLO <code_borne>
    private boolean hello(String[] mots) throws IOException, SQLException {
        if (borne != null) {
            envoyer("ERR 409 deja identifiee");
            return true;
        }
        if (mots.length != 2 || !mots[1].matches("[A-Z0-9-]{1,12}")) {
            envoyer("ERR 400 usage : HELLO <code_borne>");
            return true;
        }
        AccesBase.Borne trouvee = base.trouverBorne(mots[1]);
        if (trouvee == null) {
            envoyer("ERR 401 borne inconnue");
            return false;
        }
        borne = trouvee;
        codeBorne = mots[1];
        base.signalerCommunication(borne.id());
        envoyer("OK " + numero + " " + borne.etat());
        return true;
    }

    // START <id_badge>
    private boolean start(String[] mots) throws IOException, SQLException {
        if (sessionEnCours != 0) {
            envoyer("ERR 409 charge deja en cours");
            return true;
        }
        Integer idBadge = entier(mots);
        if (idBadge == null) {
            envoyer("ERR 400 usage : START <id_badge>");
            return true;
        }
        Boolean actif = base.badgeActif(idBadge);
        if (actif == null) {
            envoyer("ERR 404 badge inconnu");
            return true;
        }
        if (!actif) {
            envoyer("ERR 403 badge bloque");
            return true;
        }
        int idSession = base.ouvrirSession(borne.id(), idBadge);
        if (idSession < 0) {
            envoyer("ERR 409 borne indisponible");
            return true;
        }
        sessionEnCours = idSession;
        badgeEnCours = idBadge;
        envoyer("OK " + idSession);
        return true;
    }

    // STOP <energie_kwh>
    private boolean stop(String[] mots) throws IOException, SQLException {
        if (sessionEnCours == 0) {
            envoyer("ERR 409 aucune charge en cours");
            return true;
        }
        BigDecimal energie;
        try {
            energie = new BigDecimal(mots.length == 2 ? mots[1] : "x");
        } catch (NumberFormatException e) {
            energie = null;
        }
        if (energie == null || energie.signum() < 0 || energie.compareTo(new BigDecimal("9999")) >= 0) {
            envoyer("ERR 400 usage : STOP <energie_kwh>");
            return true;
        }
        BigDecimal montant = base.fermerSession(sessionEnCours, borne.id(), badgeEnCours, energie);
        // CB : la borne doit encaisser puis envoyer PAYE ; FACTURE : rien a payer a la borne
        String mode = base.modePaiement(badgeEnCours);
        envoyer("OK " + sessionEnCours + " " + montant + " " + mode);
        sessionEnCours = 0;
        badgeEnCours = 0;
        return true;
    }

    // PAYE <id_session> <reference_cb> : le terminal CB de la borne a accepte le paiement
    private boolean paye(String[] mots) throws IOException, SQLException {
        if (mots.length != 3 || !mots[1].matches("\\d{1,9}") || !mots[2].matches("CB-[0-9A-Z-]{4,16}")) {
            envoyer("ERR 400 usage : PAYE <id_session> CB-<reference>");
            return true;
        }
        int idSession = Integer.parseInt(mots[1]);
        int code = base.payerCB(idSession, borne.id(), mots[2]);
        switch (code) {
            case 0 -> envoyer("OK " + idSession + " payee");
            case 404 -> envoyer("ERR 404 session inconnue sur cette borne");
            default -> envoyer("ERR 409 session non payable a la borne");
        }
        return true;
    }

    // TARIF : le tarif du client en charge, ou le tarif public si la borne est libre
    private boolean tarif() throws IOException, SQLException {
        AccesBase.Tarif t = base.tarifApplicable(badgeEnCours);
        BigDecimal minute = t.prixMinute() == null ? BigDecimal.ZERO : t.prixMinute();
        envoyer("OK " + t.prixKwh() + " " + minute + " " + t.libelle());
        return true;
    }

    // STATUS <etat>
    private boolean status(String[] mots) throws IOException, SQLException {
        if (mots.length != 2 || !ETATS_AUTORISES.contains(mots[1])) {
            envoyer("ERR 400 usage : STATUS libre|hors_service|maintenance");
            return true;
        }
        if (sessionEnCours != 0) {
            envoyer("ERR 409 charge en cours, faire STOP d'abord");
            return true;
        }
        base.changerEtat(borne.id(), mots[1]);
        envoyer("OK " + mots[1]);
        return true;
    }

    private boolean bye() throws IOException {
        libererBorne();
        envoyer("OK au revoir");
        return false;
    }

    // Si la borne part en pleine charge, la session ne doit pas rester "en_cours" pour toujours.
    private void libererBorne() {
        if (sessionEnCours == 0) {
            return;
        }
        try {
            base.interrompreSession(sessionEnCours, borne.id());
            log("session " + sessionEnCours + " marquee interrompue");
        } catch (SQLException e) {
            log("impossible de clore la session " + sessionEnCours + " : " + e.getMessage());
        }
        sessionEnCours = 0;
        badgeEnCours = 0;
    }

    // Lit jusqu a \n, sans jamais depasser TAILLE_MAX_LIGNE octets.
    // readLine() de BufferedReader n a pas de limite : un client qui n envoie
    // jamais de \n ferait grossir la memoire du serveur indefiniment.
    private String lireLigne() throws IOException {
        ByteArrayOutputStream tampon = new ByteArrayOutputStream();
        int octet;
        while ((octet = in.read()) != -1) {
            if (octet == '\n') {
                String ligne = tampon.toString(StandardCharsets.UTF_8);
                // netcat sous Windows envoie \r\n
                return ligne.endsWith("\r") ? ligne.substring(0, ligne.length() - 1) : ligne;
            }
            if (tampon.size() >= TAILLE_MAX_LIGNE) {
                envoyer("ERR 413 ligne trop longue");
                throw new IOException("ligne de plus de " + TAILLE_MAX_LIGNE + " octets");
            }
            tampon.write(octet);
        }
        return null;
    }

    private Integer entier(String[] mots) {
        if (mots.length != 2 || !mots[1].matches("\\d{1,9}")) {
            return null;
        }
        return Integer.valueOf(mots[1]);
    }

    private void envoyer(String message) throws IOException {
        out.write((message + "\n").getBytes(StandardCharsets.UTF_8));
        out.flush();
        log("-> " + message);
    }

    private void log(String message) {
        String qui = codeBorne != null ? codeBorne : origine;
        ServeurBornes.log("#" + numero + " " + qui + " " + message);
    }
}
