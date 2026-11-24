<?php
// Requêtes de l'espace client. Chaque fonction reçoit l'id du client connecté :
// un client ne voit et ne modifie jamais que ses propres données.
defined('VOLTOISE') or exit;

// Montant d'une charge, calculé comme le serveur Java : énergie × prix du kWh
// + minutes × prix de la minute, au tarif du client. Le montant n'est pas stocké.
// ::numeric : avant PostgreSQL 14, EXTRACT renvoie un double precision, et ROUND(x, 2)
// n'existe pas pour ce type (« function round(double precision, integer) does not exist »).
const SQL_MONTANT = "ROUND(sc.energie_kwh * t.prix_kwh
    + EXTRACT(EPOCH FROM (sc.fin - sc.debut))::numeric / 60 * t.prix_minute, 2)";

// L'héritage décide du type : une ligne dans PARTICULIER ou dans ENTREPRISE.
function fiche_client($id_client)
{
    return db_ligne(
        "SELECT c.id_client, c.nom, c.prenom, c.email, c.telephone, c.date_inscription,
                t.libelle_tarif, t.prix_kwh, t.prix_minute,
                CASE WHEN e.id_client IS NULL THEN 'particulier' ELSE 'entreprise' END AS type_client,
                e.raison_sociale, e.siret, e.nb_vehicules, p.civilite
         FROM client c
         JOIN tarif t            ON t.id_tarif  = c.id_tarif
         LEFT JOIN particulier p ON p.id_client = c.id_client
         LEFT JOIN entreprise e  ON e.id_client = c.id_client
         WHERE c.id_client = $1",
        [$id_client]
    );
}

function badges_client($id_client)
{
    return db_lignes(
        "SELECT b.id_badge, b.code_rfid, b.date_activation, b.actif,
                COUNT(sc.id_session) AS nb_charges,
                MAX(sc.debut) AS derniere_charge,
                BOOL_OR(sc.statut = 'en_cours') AS en_charge
         FROM badge b
         LEFT JOIN session_charge sc ON sc.id_badge = b.id_badge
         WHERE b.id_client = $1
         GROUP BY b.id_badge
         ORDER BY b.date_activation, b.id_badge",
        [$id_client]
    );
}

function charges_client($id_client, $mois = null)
{
    $filtre = $mois === null ? '' : "AND to_char(sc.debut, 'YYYY-MM') = $2";
    $params = $mois === null ? [$id_client] : [$id_client, $mois];
    return db_lignes(
        "SELECT sc.id_session, sc.debut, sc.fin, sc.energie_kwh, sc.statut,
                sc.ref_paiement, sc.date_paiement,
                EXTRACT(EPOCH FROM (sc.fin - sc.debut)) / 60 AS minutes,
                CASE WHEN sc.statut = 'terminee' THEN " . SQL_MONTANT . " END AS montant,
                b.code_borne, s.nom_station, s.ville, bd.code_rfid
         FROM session_charge sc
         JOIN badge bd   ON bd.id_badge  = sc.id_badge
         JOIN client c   ON c.id_client  = bd.id_client
         JOIN tarif t    ON t.id_tarif   = c.id_tarif
         JOIN borne b    ON b.id_borne   = sc.id_borne
         JOIN station s  ON s.id_station = b.id_station
         WHERE bd.id_client = $1 $filtre
         ORDER BY sc.debut DESC",
        $params
    );
}

function mois_avec_charges($id_client)
{
    return array_column(db_lignes(
        "SELECT DISTINCT to_char(sc.debut, 'YYYY-MM') AS mois
         FROM session_charge sc JOIN badge b ON b.id_badge = sc.id_badge
         WHERE b.id_client = $1 ORDER BY mois DESC",
        [$id_client]
    ), 'mois');
}

function bilan_client($id_client)
{
    return db_ligne(
        "SELECT COUNT(*) FILTER (WHERE sc.statut = 'terminee') AS nb_charges,
                COALESCE(SUM(sc.energie_kwh) FILTER (WHERE sc.statut = 'terminee'), 0) AS energie,
                COALESCE(SUM(" . SQL_MONTANT . ") FILTER (WHERE sc.statut = 'terminee'), 0) AS depense,
                COALESCE(SUM(" . SQL_MONTANT . ") FILTER (WHERE sc.statut = 'terminee'
                                                         AND sc.date_paiement IS NULL), 0) AS a_facturer,
                COUNT(*) FILTER (WHERE sc.statut = 'terminee' AND sc.date_paiement IS NULL) AS nb_a_facturer
         FROM session_charge sc
         JOIN badge b  ON b.id_badge  = sc.id_badge
         JOIN client c ON c.id_client = b.id_client
         JOIN tarif t  ON t.id_tarif  = c.id_tarif
         WHERE b.id_client = $1",
        [$id_client]
    );
}

// Modification demandée par le sujet (UPDATE) : un client bloque un badge perdu,
// ou le réactive. Refusé si le badge sert à une charge en cours.
// Renvoie null si c'est fait, sinon la raison du refus.
function changer_etat_badge($id_badge, $id_client, $actif)
{
    $badge = db_ligne(
        "SELECT b.actif,
                EXISTS (SELECT 1 FROM session_charge sc
                        WHERE sc.id_badge = b.id_badge AND sc.statut = 'en_cours') AS en_charge
         FROM badge b WHERE b.id_badge = $1 AND b.id_client = $2",
        [$id_badge, $id_client]
    );
    if ($badge === null) {
        return 'Ce badge ne fait pas partie de votre compte.';
    }
    if (!$actif && db_bool($badge['en_charge'])) {
        return 'Ce badge sert à une charge en cours : il sera bloquable à la fin de la charge.';
    }
    db_executer(
        "UPDATE badge SET actif = $1 WHERE id_badge = $2 AND id_client = $3",
        [$actif ? 't' : 'f', $id_badge, $id_client]
    );
    return null;
}
