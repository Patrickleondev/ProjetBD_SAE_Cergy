-- Tests des contraintes du script 01_creation.sql.
-- psql -d voltoise -f 03_tests_contraintes.sql
-- Chaque test s'exécute dans une transaction annulée : la base n'est pas modifiée.
-- Résultat attendu : une ligne « OK » par test (REFUSÉ quand il doit l'être, ACCEPTÉ sinon).

CREATE OR REPLACE FUNCTION pg_temp.essai(nom TEXT, requete TEXT, doit_passer BOOLEAN)
RETURNS TEXT AS $$
BEGIN
    BEGIN
        EXECUTE requete;
        RAISE EXCEPTION 'annulation' USING ERRCODE = 'P0001';
    EXCEPTION
        WHEN raise_exception THEN
            RETURN (CASE WHEN doit_passer THEN 'OK      accepté : ' ELSE 'ÉCHEC   accepté à tort : ' END) || nom;
        WHEN OTHERS THEN
            RETURN (CASE WHEN doit_passer THEN 'ÉCHEC   refusé à tort : ' ELSE 'OK      refusé  : ' END)
                   || nom || '   [' || SQLERRM || ']';
    END;
END;
$$ LANGUAGE plpgsql;

SELECT pg_temp.essai(t.nom, t.req, t.ok) AS resultat FROM (VALUES
 -- client : noms, e-mail, téléphone, dates
 ('nom avec chiffre',        $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dup0nt','Luc','luc@exemple.fr','0611223344',1)$q$, FALSE),
 ('nom d''une lettre',       $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('D','Luc','luc@exemple.fr','0611223344',1)$q$, FALSE),
 ('nom « Le Goff »',         $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Le Goff','Luc','luc@exemple.fr','0611223344',1)$q$, TRUE),
 ('prénom en minuscules',    $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','luc','luc@exemple.fr','0611223344',1)$q$, FALSE),
 ('e-mail sans @',           $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','luc.exemple.fr','0611223344',1)$q$, FALSE),
 ('e-mail en majuscules',    $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','Luc@Exemple.fr','0611223344',1)$q$, FALSE),
 ('e-mail « a..b@x.fr »',    $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','a..b@x.fr','0611223344',1)$q$, FALSE),
 ('e-mail déjà utilisé',     $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','claire.martin@exemple.fr','0611223344',1)$q$, FALSE),
 ('téléphone avec espaces',  $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','luc@exemple.fr','06 11 22 33',1)$q$, FALSE),
 ('téléphone en 08',         $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','luc@exemple.fr','0811223344',1)$q$, FALSE),
 ('inscription dans le futur',$q$INSERT INTO client(nom,prenom,email,telephone,date_inscription,id_tarif) VALUES ('Dupont','Luc','luc@exemple.fr','0611223344',CURRENT_DATE+1,1)$q$, FALSE),
 ('inscription en 2019',     $q$INSERT INTO client(nom,prenom,email,telephone,date_inscription,id_tarif) VALUES ('Dupont','Luc','luc@exemple.fr','0611223344','2019-05-01',1)$q$, FALSE),
 ('tarif inexistant',        $q$INSERT INTO client(nom,prenom,email,telephone,id_tarif) VALUES ('Dupont','Luc','luc@exemple.fr','0611223344',99)$q$, FALSE),
 -- héritage
 ('particulier mineur',      $q$UPDATE particulier SET date_naissance = CURRENT_DATE - INTERVAL '17 years' WHERE id_client = 1$q$, FALSE),
 ('particulier né en 1900',  $q$UPDATE particulier SET date_naissance = '1900-01-01' WHERE id_client = 1$q$, FALSE),
 ('civilité « Mlle »',       $q$UPDATE particulier SET civilite = 'Mlle' WHERE id_client = 1$q$, FALSE),
 ('SIRET de 13 chiffres',    $q$UPDATE entreprise SET siret = '8123456780001' WHERE id_client = 6$q$, FALSE),
 ('SIRET avec une lettre',   $q$UPDATE entreprise SET siret = '8123456780001A' WHERE id_client = 6$q$, FALSE),
 -- badge
 ('RFID en minuscules',      $q$UPDATE badge SET code_rfid = '04a3f19c22b18e70' WHERE id_badge = 1$q$, FALSE),
 ('badge activé demain',     $q$UPDATE badge SET date_activation = CURRENT_DATE + 1 WHERE id_badge = 1$q$, FALSE),
 -- station
 ('code postal hors IDF',    $q$UPDATE station SET code_postal = '69001' WHERE id_station = 1$q$, FALSE),
 ('adresse sans numéro',     $q$UPDATE station SET adresse = 'place des Colonnes' WHERE id_station = 1$q$, FALSE),
 ('photo de 3 Mo',           $q$UPDATE station SET photo = decode(repeat('00', 3145728), 'hex') WHERE id_station = 1$q$, FALSE),
 -- borne
 ('code borne mal formé',    $q$UPDATE borne SET code_borne = 'CGY-1-B1' WHERE id_borne = 1$q$, FALSE),
 ('borne AC de 150 kW',      $q$UPDATE borne SET puissance_max_kw = 150 WHERE id_borne = 1$q$, FALSE),
 ('borne DC de 22 kW',       $q$UPDATE borne SET puissance_max_kw = 22 WHERE id_borne = 3$q$, FALSE),
 ('communication future',    $q$UPDATE borne SET derniere_communication = LOCALTIMESTAMP + INTERVAL '1 hour' WHERE id_borne = 1$q$, FALSE),
 ('communication avant mise en service', $q$UPDATE borne SET derniere_communication = '2023-12-31 10:00' WHERE id_borne = 1$q$, FALSE),
 ('état inconnu',            $q$UPDATE borne SET etat = 'cassee' WHERE id_borne = 1$q$, FALSE),
 -- connecteurs
 ('5 prises du même type',   $q$UPDATE equiper SET nombre = 5 WHERE id_borne = 1 AND id_connecteur = 1$q$, FALSE),
 ('norme fantaisiste',       $q$UPDATE connecteur SET norme = 'TYPE2' WHERE id_connecteur = 1$q$, FALSE),
 -- sessions
 ('fin avant le début',      $q$UPDATE session_charge SET fin = debut - INTERVAL '1 minute' WHERE id_session = 1$q$, FALSE),
 ('charge de 30 heures',     $q$UPDATE session_charge SET fin = debut + INTERVAL '30 hours' WHERE id_session = 1$q$, FALSE),
 ('énergie de 500 kWh',      $q$UPDATE session_charge SET energie_kwh = 500 WHERE id_session = 1$q$, FALSE),
 ('énergie négative',        $q$UPDATE session_charge SET energie_kwh = -1 WHERE id_session = 1$q$, FALSE),
 ('terminée sans fin',       $q$UPDATE session_charge SET fin = NULL WHERE id_session = 1$q$, FALSE),
 ('en cours avec une fin',   $q$UPDATE session_charge SET statut = 'en_cours' WHERE id_session = 1$q$, FALSE),
 ('terminée sans énergie',   $q$UPDATE session_charge SET energie_kwh = NULL WHERE id_session = 1$q$, FALSE),
 ('2 charges sur une borne', $q$INSERT INTO session_charge(debut,id_borne,id_badge) VALUES (LOCALTIMESTAMP, 3, 1)$q$, FALSE),
 ('charge sur une borne libre', $q$INSERT INTO session_charge(debut,id_borne,id_badge) VALUES (LOCALTIMESTAMP, 1, 1)$q$, TRUE),
 ('début dans le futur',     $q$INSERT INTO session_charge(debut,id_borne,id_badge) VALUES (LOCALTIMESTAMP + INTERVAL '1 day', 1, 1)$q$, FALSE),
 ('payée sans référence',    $q$UPDATE session_charge SET date_paiement = fin + INTERVAL '1 minute' WHERE id_session = 13$q$, FALSE),
 ('référence mal formée',    $q$UPDATE session_charge SET ref_paiement = 'cheque' WHERE id_session = 1$q$, FALSE),
 ('payée avant la fin',      $q$UPDATE session_charge SET date_paiement = debut WHERE id_session = 1$q$, FALSE),
 ('charge interrompue payée',$q$UPDATE session_charge SET date_paiement = fin, ref_paiement = 'CB-00000005' WHERE id_session = 5$q$, FALSE),
 ('paiement CB valide',      $q$UPDATE session_charge SET date_paiement = fin + INTERVAL '2 minutes', ref_paiement = 'CB-12345678' WHERE id_session = 1$q$, TRUE),
 -- incidents
 ('description trop courte', $q$UPDATE incident SET description = 'panne' WHERE id_incident = 1$q$, FALSE),
 ('résolu avant signalé',    $q$UPDATE incident SET date_resolution = date_signalement - INTERVAL '1 hour' WHERE id_incident = 1$q$, FALSE),
 -- tarifs et comptes web
 ('prix au kWh nul',         $q$UPDATE tarif SET prix_kwh = 0 WHERE id_tarif = 1$q$, FALSE),
 ('tarif fini avant de commencer', $q$UPDATE tarif SET date_fin = '2023-06-01' WHERE id_tarif = 1$q$, FALSE),
 ('mot de passe en clair',   $q$UPDATE compte_web SET hash_mdp = 'MotDePasseEnClair' WHERE id_compte = 1$q$, FALSE),
 ('gestionnaire rattaché à un client', $q$UPDATE compte_web SET id_client = 2 WHERE id_compte = 1$q$, FALSE),
 ('login avec espace',       $q$UPDATE compte_web SET login = 'claire martin' WHERE id_compte = 2$q$, FALSE)
) AS t(nom, req, ok);
