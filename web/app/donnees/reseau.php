<?php
// Requêtes de la page Réseau (gestionnaire) : stations, bornes, prises, état.
defined('VOLTOISE') or exit;

// États qu'un gestionnaire peut poser à la main. « occupee » ne se pose jamais d'ici :
// seul le serveur réseau le fait, au START d'une charge.
const ETATS_MANUELS = ['libre', 'maintenance', 'hors_service'];

function villes()
{
    return array_column(db_lignes("SELECT DISTINCT ville FROM station ORDER BY ville"), 'ville');
}

function compte_par_etat()
{
    $compte = array_fill_keys(array_keys(ETATS_BORNE), 0);
    foreach (db_lignes("SELECT etat, COUNT(*) AS n FROM borne GROUP BY etat") as $l) {
        $compte[$l['etat']] = (int) $l['n'];
    }
    return $compte;
}

// Les bornes, avec leurs prises (string_agg), la charge en cours et les incidents ouverts.
function bornes($ville = '', $etat = '')
{
    $conditions = [];
    $params = [];
    if ($ville !== '') {
        $params[] = $ville;
        $conditions[] = 's.ville = $' . count($params);
    }
    if ($etat !== '') {
        $params[] = $etat;
        $conditions[] = 'b.etat = $' . count($params) . '::etat_borne';
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    return db_lignes(
        "SELECT b.id_borne, b.code_borne, b.type_courant, b.puissance_max_kw, b.etat,
                b.derniere_communication, s.id_station, s.nom_station, s.adresse,
                s.code_postal, s.ville,
                (SELECT string_agg(c.libelle_connecteur || ' ×' || e.nombre, ', '
                                   ORDER BY c.libelle_connecteur)
                 FROM equiper e JOIN connecteur c ON c.id_connecteur = e.id_connecteur
                 WHERE e.id_borne = b.id_borne) AS prises,
                (SELECT COUNT(*) FROM incident i
                 WHERE i.id_borne = b.id_borne AND i.date_resolution IS NULL) AS incidents_ouverts,
                (SELECT sc.debut FROM session_charge sc
                 WHERE sc.id_borne = b.id_borne AND sc.statut = 'en_cours') AS charge_depuis
         FROM borne b
         JOIN station s ON s.id_station = b.id_station
         $where
         ORDER BY s.ville, s.nom_station, b.code_borne",
        $params
    );
}

function liste_bornes_courte()
{
    return db_lignes(
        "SELECT b.id_borne, b.code_borne, s.nom_station
         FROM borne b JOIN station s ON s.id_station = b.id_station
         ORDER BY b.code_borne"
    );
}

// Renvoie null si l'état a changé, sinon la raison du refus.
function changer_etat_borne($id_borne, $etat)
{
    if (!in_array($etat, ETATS_MANUELS, true)) {
        return 'État non autorisé.';
    }
    $n = db_executer(
        "UPDATE borne SET etat = $1::etat_borne
         WHERE id_borne = $2 AND etat <> 'occupee'",
        [$etat, $id_borne]
    );
    if ($n === 0) {
        return 'Cette borne est en charge : son état changera à la fin de la charge.';
    }
    return null;
}
