<?php
// Requêtes de la page Incidents (gestionnaire) : déclarer (INSERT), clore (UPDATE).
defined('VOLTOISE') or exit;

const GRAVITES = ['mineur', 'majeur', 'critique'];

function incidents($filtre)
{
    $condition = [
        'ouverts' => 'WHERE i.date_resolution IS NULL',
        'resolus' => 'WHERE i.date_resolution IS NOT NULL',
        'tous'    => '',
    ][$filtre];

    // ORDER BY sur un ENUM suit l'ordre de déclaration du type : critique en dernier,
    // d'où le DESC pour avoir le plus grave en haut.
    return db_lignes(
        "SELECT i.id_incident, i.date_signalement, i.gravite, i.description, i.date_resolution,
                b.code_borne, b.etat, s.nom_station,
                FLOOR(EXTRACT(EPOCH FROM (COALESCE(i.date_resolution, LOCALTIMESTAMP)
                                          - i.date_signalement)) / 3600) AS heures
         FROM incident i
         JOIN borne b   ON b.id_borne   = i.id_borne
         JOIN station s ON s.id_station = b.id_station
         $condition
         ORDER BY i.date_resolution IS NOT NULL, i.gravite DESC, i.date_signalement DESC"
    );
}

function nb_incidents_ouverts()
{
    return (int) db_valeur("SELECT COUNT(*) FROM incident WHERE date_resolution IS NULL");
}

// Contrôle la saisie avant d'écrire : renvoie la liste des erreurs, vide si tout va bien.
function verifier_incident($id_borne, $gravite, $description)
{
    $erreurs = [];
    if (!db_valeur("SELECT 1 FROM borne WHERE id_borne = $1", [$id_borne])) {
        $erreurs['borne'] = 'Choisissez une borne de la liste.';
    }
    if (!in_array($gravite, GRAVITES, true)) {
        $erreurs['gravite'] = 'Choisissez une gravité.';
    }
    $longueur = mb_strlen($description);
    if ($longueur < 10) {
        $erreurs['description'] = 'Décrivez le problème en 10 caractères au moins.';
    } elseif ($longueur > 500) {
        $erreurs['description'] = '500 caractères au plus (' . $longueur . ' saisis).';
    }
    return $erreurs;
}

// INSERT de l'incident et, si demandé, mise hors service de la borne : les deux dans une
// transaction, pour ne jamais garder l'un sans l'autre. Une borne en charge n'est pas coupée.
function declarer_incident($id_borne, $gravite, $description, $mettre_hors_service)
{
    return db_transaction(function () use ($id_borne, $gravite, $description, $mettre_hors_service) {
        $id = db_valeur(
            "INSERT INTO incident (date_signalement, gravite, description, id_borne)
             VALUES (LOCALTIMESTAMP(0), $1::gravite_incident, $2, $3)
             RETURNING id_incident",
            [$gravite, $description, $id_borne]
        );
        $coupee = false;
        if ($mettre_hors_service) {
            $coupee = db_executer(
                "UPDATE borne SET etat = 'hors_service' WHERE id_borne = $1 AND etat <> 'occupee'",
                [$id_borne]
            ) === 1;
        }
        return ['id' => (int) $id, 'borne_coupee' => $coupee];
    });
}

// Clore un incident ; si la borne était hors service, on peut la remettre en service.
function clore_incident($id_incident, $remettre_en_service)
{
    return db_transaction(function () use ($id_incident, $remettre_en_service) {
        $id_borne = db_valeur(
            "UPDATE incident SET date_resolution = LOCALTIMESTAMP(0)
             WHERE id_incident = $1 AND date_resolution IS NULL
             RETURNING id_borne",
            [$id_incident]
        );
        if ($id_borne === null) {
            return false;
        }
        if ($remettre_en_service) {
            db_executer(
                "UPDATE borne SET etat = 'libre'
                 WHERE id_borne = $1 AND etat IN ('hors_service', 'maintenance')
                   AND NOT EXISTS (SELECT 1 FROM incident
                                   WHERE id_borne = $1 AND date_resolution IS NULL)",
                [$id_borne]
            );
        }
        return true;
    });
}
