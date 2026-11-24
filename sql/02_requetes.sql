-- Voltoise : les 10 questions des utilisateurs, et leurs requêtes SELECT (version 4)
-- psql -U postgres -p 5433 -d voltoise -f 02_requetes.sql
-- La version 2 est conservée dans versions/v2/sql/.

-- Q1. (client) « Je suis à Cergy : quelles bornes sont libres en ce moment,
--     et quelles prises proposent-elles ? »
--     tables : borne, station, equiper, connecteur
SELECT b.code_borne, s.nom_station AS station, b.type_courant, b.puissance_max_kw,
       string_agg(c.libelle_connecteur || ' x' || e.nombre, ', ' ORDER BY c.libelle_connecteur) AS prises
FROM borne b
JOIN station s    ON s.id_station    = b.id_station
JOIN equiper e    ON e.id_borne      = b.id_borne
JOIN connecteur c ON c.id_connecteur = e.id_connecteur
WHERE b.etat = 'libre' AND s.ville = 'Cergy'
GROUP BY b.id_borne, b.code_borne, s.nom_station, b.type_courant, b.puissance_max_kw
ORDER BY b.puissance_max_kw DESC;

-- Q2. (gestionnaire) « En septembre 2026, combien de charges terminées et combien
--     de kWh chaque station a-t-elle délivrés ? »
--     agrégats, GROUP BY ; tables : station, borne, session_charge
SELECT s.nom_station AS station, COUNT(sc.id_session) AS nb_charges,
       SUM(sc.energie_kwh) AS energie_kwh
FROM station s
JOIN borne b           ON b.id_station = s.id_station
JOIN session_charge sc ON sc.id_borne  = b.id_borne
WHERE sc.statut = 'terminee'
  AND sc.debut >= DATE '2026-09-01' AND sc.debut < DATE '2026-10-01'
GROUP BY s.id_station, s.nom_station
ORDER BY energie_kwh DESC;

-- Q3. (cliente Claire Martin) « Combien m'ont coûté mes charges de septembre 2026 ? »
--     montant calculé (non stocké) : énergie x prix du kWh + minutes x prix de la minute
--     tables : client, badge, session_charge, tarif
SELECT sc.id_session, sc.debut::date AS jour, sc.energie_kwh, t.libelle_tarif AS tarif,
       ROUND(sc.energie_kwh * t.prix_kwh
             + EXTRACT(EPOCH FROM (sc.fin - sc.debut))::numeric / 60 * t.prix_minute, 2) AS montant_eur
FROM client c
JOIN badge b           ON b.id_client  = c.id_client
JOIN session_charge sc ON sc.id_badge  = b.id_badge
JOIN tarif t           ON t.id_tarif   = c.id_tarif
WHERE c.email = 'claire.martin@exemple.fr'
  AND sc.statut = 'terminee'
  AND sc.debut >= DATE '2026-09-01' AND sc.debut < DATE '2026-10-01'
ORDER BY sc.debut;

-- Q4. (gestionnaire) « Quels clients ont consommé plus de 60 kWh depuis leur inscription ? »
--     GROUP BY avec HAVING ; tables : client, badge, session_charge
--     le type du client se lit dans l'héritage : a-t-il une ligne dans ENTREPRISE ?
SELECT c.nom, c.prenom,
       CASE WHEN e.id_client IS NULL THEN 'particulier' ELSE 'entreprise' END AS type_client,
       SUM(sc.energie_kwh) AS total_kwh
FROM client c
LEFT JOIN entreprise e ON e.id_client = c.id_client
JOIN badge b           ON b.id_client = c.id_client
JOIN session_charge sc ON sc.id_badge = b.id_badge
GROUP BY c.id_client, c.nom, c.prenom, e.id_client
HAVING SUM(sc.energie_kwh) > 60
ORDER BY total_kwh DESC;

-- Q5. (gestionnaire) « Quelles bornes n'ont jamais eu le moindre incident ? »
--     requête imbriquée NOT EXISTS ; tables : borne, station, incident
SELECT b.code_borne, s.nom_station AS station, b.date_mise_service
FROM borne b
JOIN station s ON s.id_station = b.id_station
WHERE NOT EXISTS (SELECT 1 FROM incident i WHERE i.id_borne = b.id_borne)
ORDER BY b.code_borne;

-- Q6. (technicien) « Quels incidents sont encore ouverts, du plus grave au moins grave,
--     depuis combien d'heures, et la borne est-elle encore utilisable ? »
--     tables : incident, borne, station
SELECT i.id_incident, b.code_borne, s.nom_station AS station, i.gravite,
       FLOOR(EXTRACT(EPOCH FROM (LOCALTIMESTAMP - i.date_signalement)) / 3600)
           AS heures_ouvert,
       b.etat AS etat_borne
FROM incident i
JOIN borne b   ON b.id_borne   = i.id_borne
JOIN station s ON s.id_station = b.id_station
WHERE i.date_resolution IS NULL
ORDER BY i.gravite DESC, i.date_signalement;

-- Q7. (gestionnaire) « Quelles charges ont délivré plus d'énergie que la moyenne ? »
--     requête imbriquée dans le WHERE, agrégat ; tables : session_charge, borne
SELECT sc.id_session, b.code_borne, sc.debut, sc.energie_kwh
FROM session_charge sc
JOIN borne b ON b.id_borne = sc.id_borne
WHERE sc.energie_kwh > (SELECT AVG(energie_kwh) FROM session_charge WHERE statut = 'terminee')
ORDER BY sc.energie_kwh DESC;

-- Q8. (service client) « Quelles entreprises ont au moins un badge bloqué,
--     et quelle est la taille de leur flotte ? »
--     héritage (table fille entreprise) + requête imbriquée IN ; tables : entreprise, client, badge
SELECT e.raison_sociale, e.siret, e.nb_vehicules, c.email
FROM entreprise e
JOIN client c ON c.id_client = e.id_client
WHERE e.id_client IN (SELECT id_client FROM badge WHERE NOT actif)
ORDER BY e.raison_sociale;

-- Q9. (marketing) « Quels particuliers ont un badge actif qui n'a encore jamais servi ? »
--     héritage (table fille particulier) + NOT EXISTS ; tables : particulier, client, badge, session_charge
SELECT p.civilite, c.nom, c.prenom, b.code_rfid, b.date_activation
FROM particulier p
JOIN client c ON c.id_client = p.id_client
JOIN badge b  ON b.id_client = c.id_client
WHERE b.actif
  AND NOT EXISTS (SELECT 1 FROM session_charge sc WHERE sc.id_badge = b.id_badge)
ORDER BY c.nom;

-- Q10. (direction) « Quel tarif est le plus utilisé, en nombre de charges et en kWh ? »
--      agrégats, GROUP BY, requête imbriquée >= ALL ; tables : tarif, client, badge, session_charge
SELECT t.libelle_tarif, COUNT(sc.id_session) AS nb_charges, SUM(sc.energie_kwh) AS energie_kwh
FROM tarif t
JOIN client c          ON c.id_tarif  = t.id_tarif
JOIN badge b           ON b.id_client = c.id_client
JOIN session_charge sc ON sc.id_badge = b.id_badge
GROUP BY t.id_tarif, t.libelle_tarif
HAVING COUNT(sc.id_session) >= ALL (SELECT COUNT(sc2.id_session)
                                    FROM client c2
                                    JOIN badge b2           ON b2.id_client = c2.id_client
                                    JOIN session_charge sc2 ON sc2.id_badge = b2.id_badge
                                    GROUP BY c2.id_tarif);

-- Q11. (comptabilité) « Combien faut-il facturer à chaque entreprise pour ses charges
--      pas encore payées ? »  C'est la facture mensuelle : elle n'existe QUE pour le
--      sous-type ENTREPRISE (un particulier paie à la borne).
--      héritage (table fille entreprise) + agrégat ; tables : entreprise, badge, session_charge, tarif, client
SELECT e.raison_sociale, e.siret, COUNT(sc.id_session) AS nb_charges,
       ROUND(SUM(sc.energie_kwh * t.prix_kwh
                 + EXTRACT(EPOCH FROM (sc.fin - sc.debut))::numeric / 60 * t.prix_minute), 2) AS a_facturer_eur
FROM entreprise e
JOIN client c          ON c.id_client = e.id_client
JOIN tarif t           ON t.id_tarif  = c.id_tarif
JOIN badge b           ON b.id_client = e.id_client
JOIN session_charge sc ON sc.id_badge = b.id_badge
WHERE sc.statut = 'terminee' AND sc.date_paiement IS NULL
GROUP BY e.id_client, e.raison_sociale, e.siret
ORDER BY a_facturer_eur DESC;
