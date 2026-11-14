<?php
// Requêtes de la page Chiffres (gestionnaire). Elles reprennent les questions de
// sql/02_requetes.sql, avec le mois en paramètre au lieu d'une date écrite en dur.
defined('VOLTOISE') or exit;

function mois_disponibles()
{
    return array_column(db_lignes(
        "SELECT DISTINCT to_char(debut, 'YYYY-MM') AS mois FROM session_charge ORDER BY mois DESC"
    ), 'mois');
}

function indicateurs($mois)
{
    return db_ligne(
        "SELECT COUNT(*) FILTER (WHERE sc.statut = 'terminee') AS charges,
                COUNT(*) FILTER (WHERE sc.statut = 'interrompue') AS interrompues,
                COALESCE(SUM(sc.energie_kwh) FILTER (WHERE sc.statut = 'terminee'), 0) AS energie,
                COALESCE(SUM(" . SQL_MONTANT . ") FILTER (WHERE sc.statut = 'terminee'), 0) AS recette,
                COUNT(DISTINCT b.id_client) AS clients
         FROM session_charge sc
         JOIN badge b  ON b.id_badge  = sc.id_badge
         JOIN client c ON c.id_client = b.id_client
         JOIN tarif t  ON t.id_tarif  = c.id_tarif
         WHERE to_char(sc.debut, 'YYYY-MM') = $1",
        [$mois]
    );
}

// Q2 : charges et énergie par station sur le mois (agrégats, GROUP BY).
// LEFT JOIN pour garder les stations sans charge ce mois-là.
function energie_par_station($mois)
{
    return db_lignes(
        "SELECT s.nom_station, s.ville,
                COUNT(sc.id_session) AS charges,
                COALESCE(SUM(sc.energie_kwh), 0) AS energie
         FROM station s
         JOIN borne b ON b.id_station = s.id_station
         LEFT JOIN session_charge sc ON sc.id_borne = b.id_borne
              AND sc.statut = 'terminee' AND to_char(sc.debut, 'YYYY-MM') = $1
         GROUP BY s.id_station, s.nom_station, s.ville
         ORDER BY energie DESC, s.nom_station",
        [$mois]
    );
}

// Q10 : utilisation de chaque tarif, toutes périodes.
function usage_tarifs()
{
    return db_lignes(
        "SELECT t.libelle_tarif, t.prix_kwh,
                COUNT(sc.id_session) AS charges, COALESCE(SUM(sc.energie_kwh), 0) AS energie
         FROM tarif t
         LEFT JOIN client c          ON c.id_tarif  = t.id_tarif
         LEFT JOIN badge b           ON b.id_client = c.id_client
         LEFT JOIN session_charge sc ON sc.id_badge = b.id_badge AND sc.statut = 'terminee'
         GROUP BY t.id_tarif, t.libelle_tarif, t.prix_kwh
         ORDER BY charges DESC, t.libelle_tarif"
    );
}

// Q11 : ce qu'il reste à facturer à chaque entreprise (héritage + agrégat).
function a_facturer_entreprises()
{
    return db_lignes(
        "SELECT e.raison_sociale, e.siret, COUNT(sc.id_session) AS charges,
                SUM(" . SQL_MONTANT . ") AS montant
         FROM entreprise e
         JOIN client c          ON c.id_client = e.id_client
         JOIN tarif t           ON t.id_tarif  = c.id_tarif
         JOIN badge b           ON b.id_client = e.id_client
         JOIN session_charge sc ON sc.id_badge = b.id_badge
         WHERE sc.statut = 'terminee' AND sc.date_paiement IS NULL
         GROUP BY e.id_client, e.raison_sociale, e.siret
         ORDER BY montant DESC"
    );
}

// Q5 : bornes qui n'ont jamais eu d'incident (requête imbriquée NOT EXISTS).
function bornes_sans_incident()
{
    return db_lignes(
        "SELECT b.code_borne, s.nom_station, b.date_mise_service
         FROM borne b JOIN station s ON s.id_station = b.id_station
         WHERE NOT EXISTS (SELECT 1 FROM incident i WHERE i.id_borne = b.id_borne)
         ORDER BY b.code_borne"
    );
}
