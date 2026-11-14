package fr.voltoise.serveur;

import java.math.BigDecimal;
import java.math.RoundingMode;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

// Tout le SQL du serveur est ici, et uniquement en requetes preparees.
// Une instance = une connexion JDBC, propre a une borne.
public class AccesBase implements AutoCloseable {

    private static String url, user, password;

    static void configurer(String url, String user, String password) {
        AccesBase.url = url;
        AccesBase.user = user;
        AccesBase.password = password;
    }

    record Borne(int id, String etat) {}
    record Tarif(String libelle, BigDecimal prixKwh, BigDecimal prixMinute) {}

    private final Connection cnx;

    AccesBase() throws SQLException {
        cnx = DriverManager.getConnection(url, user, password);
    }

    @Override
    public void close() throws SQLException {
        cnx.close();
    }

    Borne trouverBorne(String code) throws SQLException {
        String sql = "SELECT id_borne, etat FROM borne WHERE code_borne = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setString(1, code);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? new Borne(rs.getInt(1), rs.getString(2)) : null;
            }
        }
    }

    void signalerCommunication(int idBorne) throws SQLException {
        String sql = "UPDATE borne SET derniere_communication = LOCALTIMESTAMP WHERE id_borne = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, idBorne);
            ps.executeUpdate();
        }
    }

    // null si le badge n existe pas
    Boolean badgeActif(int idBadge) throws SQLException {
        String sql = "SELECT actif FROM badge WHERE id_badge = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, idBadge);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? rs.getBoolean(1) : null;
            }
        }
    }

    // Renvoie le numero de session, ou -1 si la borne n est plus libre.
    // FOR UPDATE verrouille la ligne de la borne jusqu au commit :
    // deux START simultanes sur la meme borne ne peuvent pas passer tous les deux.
    int ouvrirSession(int idBorne, int idBadge) throws SQLException {
        cnx.setAutoCommit(false);
        try {
            String etat;
            try (PreparedStatement ps = cnx.prepareStatement(
                    "SELECT etat FROM borne WHERE id_borne = ? FOR UPDATE")) {
                ps.setInt(1, idBorne);
                try (ResultSet rs = ps.executeQuery()) {
                    rs.next();
                    etat = rs.getString(1);
                }
            }
            if (!etat.equals("libre")) {
                cnx.rollback();
                return -1;
            }

            int idSession;
            try (PreparedStatement ps = cnx.prepareStatement(
                    "INSERT INTO session_charge (id_borne, id_badge, debut, statut) "
                    + "VALUES (?, ?, LOCALTIMESTAMP, 'en_cours') RETURNING id_session")) {
                ps.setInt(1, idBorne);
                ps.setInt(2, idBadge);
                try (ResultSet rs = ps.executeQuery()) {
                    rs.next();
                    idSession = rs.getInt(1);
                }
            }
            changerEtat(idBorne, "occupee");
            cnx.commit();
            return idSession;
        } catch (SQLException e) {
            cnx.rollback();
            throw e;
        } finally {
            cnx.setAutoCommit(true);
        }
    }

    // Termine la session et renvoie le montant a payer.
    BigDecimal fermerSession(int idSession, int idBorne, int idBadge, BigDecimal energie)
            throws SQLException {
        cnx.setAutoCommit(false);
        try {
            BigDecimal minutes;
            try (PreparedStatement ps = cnx.prepareStatement(
                    "UPDATE session_charge SET fin = LOCALTIMESTAMP, energie_kwh = ?, "
                    + "statut = 'terminee' WHERE id_session = ? "
                    + "RETURNING EXTRACT(EPOCH FROM (fin - debut)) / 60")) {
                ps.setBigDecimal(1, energie);
                ps.setInt(2, idSession);
                try (ResultSet rs = ps.executeQuery()) {
                    rs.next();
                    minutes = rs.getBigDecimal(1);
                }
            }
            changerEtat(idBorne, "libre");
            cnx.commit();

            Tarif t = tarifApplicable(idBadge);
            BigDecimal montant = energie.multiply(t.prixKwh());
            if (t.prixMinute() != null) {
                montant = montant.add(minutes.multiply(t.prixMinute()));
            }
            return montant.setScale(2, RoundingMode.HALF_UP);
        } catch (SQLException e) {
            cnx.rollback();
            throw e;
        } finally {
            cnx.setAutoCommit(true);
        }
    }

    // L heritage decide du paiement : le titulaire du badge est-il dans PARTICULIER ou
    // dans ENTREPRISE ? Particulier -> "CB" (il paie a la borne), entreprise -> "FACTURE".
    String modePaiement(int idBadge) throws SQLException {
        String sql = "SELECT CASE WHEN p.id_client IS NOT NULL THEN 'CB' "
                + "            WHEN e.id_client IS NOT NULL THEN 'FACTURE' END "
                + "FROM badge b "
                + "LEFT JOIN particulier p ON p.id_client = b.id_client "
                + "LEFT JOIN entreprise e  ON e.id_client = b.id_client "
                + "WHERE b.id_badge = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, idBadge);
            try (ResultSet rs = ps.executeQuery()) {
                // un client sans sous-type (regle de gestion violee) paie par CB par prudence
                return rs.next() && rs.getString(1) != null ? rs.getString(1) : "CB";
            }
        }
    }

    // Enregistre le paiement CB d une charge faite sur CETTE borne.
    // Renvoie 0 si c est fait, sinon le code d erreur du protocole :
    // 404 session inconnue sur cette borne, 409 pas payable a la borne (en cours,
    // interrompue, deja payee, ou client entreprise qui sera facture).
    int payerCB(int idSession, int idBorne, String reference) throws SQLException {
        String sql = "SELECT sc.statut, sc.date_paiement IS NOT NULL, e.id_client IS NOT NULL "
                + "FROM session_charge sc "
                + "JOIN badge b ON b.id_badge = sc.id_badge "
                + "LEFT JOIN entreprise e ON e.id_client = b.id_client "
                + "WHERE sc.id_session = ? AND sc.id_borne = ?";
        try (PreparedStatement ps = cnx.prepareStatement(sql)) {
            ps.setInt(1, idSession);
            ps.setInt(2, idBorne);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) {
                    return 404;
                }
                if (!rs.getString(1).equals("terminee") || rs.getBoolean(2) || rs.getBoolean(3)) {
                    return 409;
                }
            }
        }
        try (PreparedStatement ps = cnx.prepareStatement(
                "UPDATE session_charge SET date_paiement = LOCALTIMESTAMP, ref_paiement = ? "
                + "WHERE id_session = ?")) {
            ps.setString(1, reference);
            ps.setInt(2, idSession);
            ps.executeUpdate();
        }
        return 0;
    }

    // appele quand la borne disparait en pleine charge
    void interrompreSession(int idSession, int idBorne) throws SQLException {
        try (PreparedStatement ps = cnx.prepareStatement(
                "UPDATE session_charge SET fin = LOCALTIMESTAMP, statut = 'interrompue' "
                + "WHERE id_session = ? AND statut = 'en_cours'")) {
            ps.setInt(1, idSession);
            ps.executeUpdate();
        }
        changerEtat(idBorne, "libre");
    }

    void changerEtat(int idBorne, String etat) throws SQLException {
        try (PreparedStatement ps = cnx.prepareStatement(
                "UPDATE borne SET etat = ?::etat_borne, derniere_communication = LOCALTIMESTAMP "
                + "WHERE id_borne = ?")) {
            ps.setString(1, etat);
            ps.setInt(2, idBorne);
            ps.executeUpdate();
        }
    }

    // Tarif souscrit par le client titulaire du badge, s il est en vigueur, sinon le tarif public.
    // idBadge = 0 : pas de charge en cours, on donne le tarif public.
    Tarif tarifApplicable(int idBadge) throws SQLException {
        if (idBadge > 0) {
            String sql = "SELECT t.libelle_tarif, t.prix_kwh, t.prix_minute "
                    + "FROM badge b "
                    + "JOIN client c ON c.id_client = b.id_client "
                    + "JOIN tarif t ON t.id_tarif = c.id_tarif "
                    + "WHERE b.id_badge = ? AND t.date_debut <= CURRENT_DATE "
                    + "AND (t.date_fin IS NULL OR t.date_fin >= CURRENT_DATE)";
            try (PreparedStatement ps = cnx.prepareStatement(sql)) {
                ps.setInt(1, idBadge);
                try (ResultSet rs = ps.executeQuery()) {
                    if (rs.next()) {
                        return new Tarif(rs.getString(1), rs.getBigDecimal(2), rs.getBigDecimal(3));
                    }
                }
            }
        }
        String sql = "SELECT libelle_tarif, prix_kwh, prix_minute FROM tarif "
                + "WHERE libelle_tarif = 'Public' AND date_debut <= CURRENT_DATE "
                + "ORDER BY date_debut DESC LIMIT 1";
        try (PreparedStatement ps = cnx.prepareStatement(sql);
             ResultSet rs = ps.executeQuery()) {
            rs.next();
            return new Tarif(rs.getString(1), rs.getBigDecimal(2), rs.getBigDecimal(3));
        }
    }
}
