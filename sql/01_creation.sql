-- Voltoise, réseau de bornes de recharge : script de création
-- L'héritage CLIENT -> PARTICULIER / ENTREPRISE décide du mode de paiement
-- (particulier : carte bancaire à la borne ; entreprise : facture mensuelle).
-- DDL puis jeu de données initial.
-- psql -U postgres -p 5433 -d voltoise -f 01_creation.sql

DROP TABLE IF EXISTS incident, session_charge, equiper, connecteur, borne, station,
    badge, entreprise, particulier, compte_web, client, tarif CASCADE;
DROP TYPE IF EXISTS type_client, type_courant, etat_borne, statut_session,
    gravite_incident, role_web;

CREATE TYPE type_courant     AS ENUM ('AC', 'DC');
CREATE TYPE etat_borne       AS ENUM ('libre', 'occupee', 'hors_service', 'maintenance');
CREATE TYPE statut_session   AS ENUM ('en_cours', 'terminee', 'interrompue');
CREATE TYPE gravite_incident AS ENUM ('mineur', 'majeur', 'critique');
CREATE TYPE role_web         AS ENUM ('client', 'gestionnaire');

-- Le réseau Voltoise est ouvert depuis le 1er janvier 2024 : aucune date métier
-- ne peut être antérieure, ni postérieure à aujourd'hui.
-- Les horodatages sont à la seconde (TIMESTAMP(0)) et comparés à LOCALTIMESTAMP(0) :
-- LOCALTIMESTAMP seul garde les microsecondes, et une valeur arrondie à la seconde
-- supérieure serait refusée comme « future » (le serveur insère LOCALTIMESTAMP).

-- ===================================================================
-- DDL
-- ===================================================================

CREATE TABLE tarif (
    id_tarif     SERIAL       PRIMARY KEY,
    libelle_tarif VARCHAR(30) NOT NULL UNIQUE
                 CHECK (libelle_tarif ~ '^[A-Z][A-Za-zÀ-ÿ0-9 -]{1,29}$'),
    prix_kwh     NUMERIC(5,4) NOT NULL CHECK (prix_kwh > 0 AND prix_kwh <= 2),
    prix_minute  NUMERIC(5,4) NOT NULL DEFAULT 0
                 CHECK (prix_minute >= 0 AND prix_minute <= 1),
    date_debut   DATE         NOT NULL CHECK (date_debut >= DATE '2024-01-01'),
    date_fin     DATE,
    CHECK (date_fin IS NULL OR date_fin > date_debut)
);

CREATE TABLE client (
    id_client        SERIAL       PRIMARY KEY,
    -- lettres (accents compris), séparées par une espace, une apostrophe ou un tiret :
    -- accepte « N'Diaye », « Le Goff », « Lefèvre » ; refuse chiffres et symboles
    nom              VARCHAR(50)  NOT NULL
                     CHECK (char_length(nom) >= 2
                        AND nom ~ '^[A-ZÀ-Ý][A-Za-zÀ-ÿ]*([ ''-][A-ZÀ-Ýa-zà-ÿ][A-Za-zÀ-ÿ]*)*$'),
    prenom           VARCHAR(50)  NOT NULL
                     CHECK (prenom ~ '^[A-ZÀ-Ý][a-zà-ÿ]+([ ''-][A-ZÀ-Ýa-zà-ÿ][a-zà-ÿ]+)*$'),
    email            VARCHAR(120) NOT NULL UNIQUE
                     CHECK (email ~ '^[a-z0-9]+([._-][a-z0-9]+)*@[a-z0-9]+([.-][a-z0-9]+)*\.[a-z]{2,6}$'),
    telephone        CHAR(10)     NOT NULL CHECK (telephone ~ '^0[1-79][0-9]{8}$'),
    date_inscription DATE         NOT NULL DEFAULT CURRENT_DATE
                     CHECK (date_inscription BETWEEN DATE '2024-01-01' AND CURRENT_DATE),
    id_tarif         INTEGER      NOT NULL REFERENCES tarif(id_tarif)
);

-- héritage : un client est soit un particulier, soit une entreprise (règle de gestion :
-- exactement une des deux tables filles). Le sous-type n'est stocké nulle part ailleurs :
-- c'est la présence de la ligne dans PARTICULIER ou ENTREPRISE qui le donne.
-- Il décide du paiement : un particulier paie à la borne (CB), une entreprise est
-- facturée en fin de mois sur son SIRET.
CREATE TABLE particulier (
    id_client      INTEGER     PRIMARY KEY REFERENCES client(id_client) ON DELETE CASCADE,
    civilite       VARCHAR(3)  NOT NULL CHECK (civilite IN ('M', 'Mme')),
    date_naissance DATE        NOT NULL
                   CHECK (date_naissance BETWEEN CURRENT_DATE - INTERVAL '110 years'
                                             AND CURRENT_DATE - INTERVAL '18 years')
);

CREATE TABLE entreprise (
    id_client      INTEGER      PRIMARY KEY REFERENCES client(id_client) ON DELETE CASCADE,
    raison_sociale VARCHAR(120) NOT NULL CHECK (char_length(raison_sociale) BETWEEN 2 AND 120),
    siret          CHAR(14)     NOT NULL UNIQUE CHECK (siret ~ '^[0-9]{14}$'),
    nb_vehicules   SMALLINT     NOT NULL CHECK (nb_vehicules BETWEEN 1 AND 5000)
);

CREATE TABLE badge (
    id_badge        SERIAL   PRIMARY KEY,
    code_rfid       CHAR(16) NOT NULL UNIQUE CHECK (code_rfid ~ '^[0-9A-F]{16}$'),
    date_activation DATE     NOT NULL DEFAULT CURRENT_DATE
                    CHECK (date_activation BETWEEN DATE '2024-01-01' AND CURRENT_DATE),
    actif           BOOLEAN  NOT NULL DEFAULT TRUE,
    id_client       INTEGER  NOT NULL REFERENCES client(id_client)
);

CREATE TABLE station (
    id_station  SERIAL       PRIMARY KEY,
    nom_station VARCHAR(60)  NOT NULL UNIQUE CHECK (char_length(nom_station) BETWEEN 3 AND 60),
    adresse     VARCHAR(120) NOT NULL CHECK (adresse ~ '^[0-9]{1,4}( ?(bis|ter))? [A-Za-zÀ-ÿ'' -]{3,}$'),
    code_postal CHAR(5)      NOT NULL CHECK (code_postal ~ '^(75|77|78|91|92|93|94|95)[0-9]{3}$'),
    ville       VARCHAR(60)  NOT NULL CHECK (ville ~ '^[A-ZÀ-Ý][A-Za-zÀ-ÿ'' -]+$'),
    photo       BYTEA        CHECK (photo IS NULL OR octet_length(photo) <= 2097152)
);

CREATE TABLE borne (
    id_borne               SERIAL       PRIMARY KEY,
    code_borne             VARCHAR(10)  NOT NULL UNIQUE
                           CHECK (code_borne ~ '^[A-Z]{3}-[0-9]{2}-B[0-9]{2}$'),
    type_courant           type_courant NOT NULL,
    puissance_max_kw       NUMERIC(4,1) NOT NULL,
    etat                   etat_borne   NOT NULL DEFAULT 'libre',
    date_mise_service      DATE         NOT NULL
                           CHECK (date_mise_service BETWEEN DATE '2024-01-01' AND CURRENT_DATE),
    derniere_communication TIMESTAMP(0)
                           CHECK (derniere_communication <= LOCALTIMESTAMP(0)),
    id_station             INTEGER      NOT NULL REFERENCES station(id_station),
    -- une borne AC délivre de 3,7 à 22 kW, une borne DC (rapide) de 50 à 350 kW
    CHECK ((type_courant = 'AC' AND puissance_max_kw BETWEEN 3.7 AND 22)
        OR (type_courant = 'DC' AND puissance_max_kw BETWEEN 50 AND 350)),
    CHECK (derniere_communication IS NULL OR derniere_communication >= date_mise_service)
);

CREATE TABLE connecteur (
    id_connecteur    SERIAL       PRIMARY KEY,
    libelle_connecteur VARCHAR(30) NOT NULL UNIQUE,
    norme            VARCHAR(20)  NOT NULL CHECK (norme ~ '^IEC [0-9]{5}(-[0-9]{1,2})?$'),
    puissance_prise_kw NUMERIC(4,1) NOT NULL CHECK (puissance_prise_kw BETWEEN 3.7 AND 400)
);

-- association N-N : une borne porte plusieurs types de prises
CREATE TABLE equiper (
    id_borne      INTEGER  REFERENCES borne(id_borne) ON DELETE CASCADE,
    id_connecteur INTEGER  REFERENCES connecteur(id_connecteur),
    nombre        SMALLINT NOT NULL DEFAULT 1 CHECK (nombre BETWEEN 1 AND 4),
    PRIMARY KEY (id_borne, id_connecteur)
);

CREATE TABLE session_charge (
    id_session  SERIAL         PRIMARY KEY,
    debut       TIMESTAMP(0)   NOT NULL
                CHECK (debut BETWEEN TIMESTAMP '2024-01-01 00:00' AND LOCALTIMESTAMP(0)),
    fin         TIMESTAMP(0),
    energie_kwh NUMERIC(6,3)   CHECK (energie_kwh BETWEEN 0 AND 300),
    statut      statut_session NOT NULL DEFAULT 'en_cours',
    id_borne    INTEGER        NOT NULL REFERENCES borne(id_borne),
    id_badge    INTEGER        NOT NULL REFERENCES badge(id_badge),
    -- paiement : CB à la borne pour un particulier (ref = n° de transaction),
    -- facture mensuelle pour une entreprise (ref = n° de facture) ; vide = pas encore payée
    date_paiement TIMESTAMP(0) CHECK (date_paiement <= LOCALTIMESTAMP(0)),
    ref_paiement  VARCHAR(20)  CHECK (ref_paiement ~ '^(CB|FAC)-[0-9A-Z-]{4,16}$'),
    CHECK ((date_paiement IS NULL) = (ref_paiement IS NULL)),
    CHECK (date_paiement IS NULL OR (statut = 'terminee' AND date_paiement >= fin)),
    -- une charge se termine après son début, et dure moins de 24 heures
    CHECK (fin IS NULL OR (fin >= debut AND fin <= debut + INTERVAL '24 hours')),
    -- « en cours » si et seulement si la fin n'est pas connue
    CHECK ((statut = 'en_cours') = (fin IS NULL)),
    -- une charge terminée normalement a forcément une énergie mesurée
    CHECK (statut <> 'terminee' OR energie_kwh IS NOT NULL)
);
-- au plus une charge en cours par borne, et par badge
CREATE UNIQUE INDEX une_charge_par_borne ON session_charge (id_borne) WHERE statut = 'en_cours';
CREATE UNIQUE INDEX une_charge_par_badge ON session_charge (id_badge) WHERE statut = 'en_cours';

CREATE TABLE incident (
    id_incident      SERIAL           PRIMARY KEY,
    date_signalement TIMESTAMP(0)     NOT NULL
                     CHECK (date_signalement BETWEEN TIMESTAMP '2024-01-01 00:00' AND LOCALTIMESTAMP(0)),
    gravite          gravite_incident NOT NULL,
    description      VARCHAR(500)     NOT NULL CHECK (char_length(description) >= 10),
    date_resolution  TIMESTAMP(0)     CHECK (date_resolution <= LOCALTIMESTAMP(0)),
    id_borne         INTEGER          NOT NULL REFERENCES borne(id_borne),
    CHECK (date_resolution IS NULL OR date_resolution >= date_signalement)
);

-- authentification du site web, à part de l'analyse métier (demandé par le sujet)
CREATE TABLE compte_web (
    id_compte SERIAL      PRIMARY KEY,
    login     VARCHAR(30) NOT NULL UNIQUE CHECK (login ~ '^[a-z][a-z0-9._]{2,29}$'),
    hash_mdp  CHAR(60)    NOT NULL CHECK (hash_mdp ~ '^\$2[aby]\$[0-9]{2}\$[./A-Za-z0-9]{53}$'),
    role      role_web    NOT NULL,
    id_client INTEGER     UNIQUE REFERENCES client(id_client) ON DELETE CASCADE,
    -- un compte « client » est rattaché à un client, un gestionnaire à aucun
    CHECK ((role = 'client') = (id_client IS NOT NULL))
);

-- ===================================================================
-- Jeu de données initial (le 8 octobre 2026)
-- ===================================================================

INSERT INTO tarif (libelle_tarif, prix_kwh, prix_minute, date_debut, date_fin) VALUES
    ('Public',          0.4900, 0.0500, '2024-01-01', NULL),
    ('Abonné',          0.3500, 0.0000, '2024-01-01', NULL),
    ('Flotte Pro',      0.3100, 0.0000, '2025-01-01', NULL),
    ('Lancement 2024',  0.2900, 0.0000, '2024-01-01', '2024-12-31');

INSERT INTO client (nom, prenom, email, telephone, date_inscription, id_tarif) VALUES
    ('Martin',   'Claire',    'claire.martin@exemple.fr',  '0612345678', '2024-03-14', 2),
    ('Benali',   'Yanis',     'yanis.benali@exemple.fr',   '0698765432', '2024-09-02', 1),
    ('Lefèvre',  'Sophie',    'sophie.lefevre@exemple.fr', '0745123698', '2025-01-20', 2),
    ('N''Diaye', 'Moussa',    'moussa.ndiaye@exemple.fr',  '0634567812', '2025-06-11', 1),
    ('Rousseau', 'Jean-Marc', 'jm.rousseau@exemple.fr',    '0623456789', '2026-02-03', 2),
    ('Durand',   'Paul',      'flotte@transports-durand.fr',  '0130303030', '2024-05-06', 3),
    ('Garcia',   'Inès',      'contact@taxis-vexin.fr',       '0134343434', '2025-03-17', 3),
    ('Morel',    'Lucas',     'parc@cergy-livraisons.fr',     '0139393939', '2026-04-01', 3);

INSERT INTO particulier (id_client, civilite, date_naissance) VALUES
    (1, 'Mme', '1988-07-22'),
    (2, 'M',   '2001-02-10'),
    (3, 'Mme', '1975-11-30'),
    (4, 'M',   '1993-05-04'),
    (5, 'M',   '1962-09-15');

INSERT INTO entreprise (id_client, raison_sociale, siret, nb_vehicules) VALUES
    (6, 'Transports Durand SAS',  '81234567800019', 24),
    (7, 'Taxis du Vexin SARL',    '52398761400027',  8),
    (8, 'Cergy Livraisons SAS',   '90012345600011', 12);

-- le badge 4 est bloqué (impayé) : c'est celui des tests du client réseau
INSERT INTO badge (code_rfid, date_activation, actif, id_client) VALUES
    ('04A3F19C22B18E70', '2024-03-14', TRUE,  1),
    ('04B7C2D19E0A3F51', '2024-09-02', TRUE,  2),
    ('04C1E5A7B2D93F08', '2025-01-20', TRUE,  3),
    ('04D9F3B6A1C27E44', '2025-06-11', FALSE, 4),
    ('04E2A8C5D7F1B930', '2026-02-03', TRUE,  5),
    ('04F6B1D4E9A3C752', '2024-05-06', TRUE,  6),
    ('0411C7E2A5B8D9F3', '2024-05-06', TRUE,  6),
    ('0422D8F3B6C9E1A4', '2025-03-17', TRUE,  7),
    ('0433E9A4C7D1F2B5', '2026-04-01', TRUE,  8),
    ('0444FA1B5D8E2C36', '2026-04-01', FALSE, 8),
    ('0455AB2C6E9F3D47', '2026-09-30', TRUE,  3);   -- second badge de Sophie Lefèvre, pas encore utilisé

INSERT INTO station (nom_station, adresse, code_postal, ville, photo) VALUES
    ('Cergy Préfecture',     '12 place des Colonnes',      '95000', 'Cergy',                 NULL),
    ('Cergy Saint-Christophe','3 avenue du Hazay',          '95800', 'Cergy',                 NULL),
    ('Pontoise Gare',        '1 place du Général de Gaulle','95300', 'Pontoise',              NULL),
    ('Saint-Ouen Centre',    '45 rue du Général Leclerc',  '95310', 'Saint-Ouen-l''Aumône',   NULL);

INSERT INTO borne (code_borne, type_courant, puissance_max_kw, etat, date_mise_service,
                   derniere_communication, id_station) VALUES
    ('CGY-01-B01', 'AC',  22.0, 'libre',        '2024-01-15', '2026-10-08 07:58:02', 1),
    ('CGY-01-B02', 'AC',  22.0, 'libre',        '2024-01-15', '2026-10-08 07:58:05', 1),
    ('CGY-01-B03', 'DC', 150.0, 'occupee',      '2024-06-03', '2026-10-08 09:12:40', 1),
    ('CGY-02-B01', 'AC',  11.0, 'libre',        '2024-09-10', '2026-10-08 08:01:17', 2),
    ('CGY-02-B02', 'DC',  50.0, 'hors_service', '2024-09-10', '2026-10-06 18:44:09', 2),
    ('PTS-01-B01', 'AC',  22.0, 'libre',        '2025-02-01', '2026-10-08 06:30:55', 3),
    ('PTS-01-B02', 'DC', 300.0, 'libre',        '2025-02-01', '2026-10-08 06:31:02', 3),
    ('SOA-01-B01', 'AC',   7.4, 'maintenance',  '2025-11-17', '2026-10-07 14:02:31', 4),
    ('SOA-01-B02', 'AC',   7.4, 'libre',        '2025-11-17', '2026-10-08 07:45:12', 4);

INSERT INTO connecteur (libelle_connecteur, norme, puissance_prise_kw) VALUES
    ('Type 2',        'IEC 62196-2',  43.0),
    ('CCS Combo 2',   'IEC 62196-3', 350.0),
    ('CHAdeMO',       'IEC 61851-23', 100.0),
    ('Prise E/F',     'IEC 60884-1',   3.7);

INSERT INTO equiper (id_borne, id_connecteur, nombre) VALUES
    (1, 1, 2), (1, 4, 1),
    (2, 1, 2),
    (3, 2, 2), (3, 3, 1),
    (4, 1, 2),
    (5, 2, 1), (5, 3, 1),
    (6, 1, 2),
    (7, 2, 2),
    (8, 1, 1), (8, 4, 1),
    (9, 1, 1);

INSERT INTO session_charge (debut, fin, energie_kwh, statut, id_borne, id_badge) VALUES
    ('2026-09-01 08:12:00', '2026-09-01 10:05:00', 31.200, 'terminee',    1, 1),
    ('2026-09-01 18:40:00', '2026-09-01 19:02:00', 28.450, 'terminee',    3, 6),
    ('2026-09-03 07:55:00', '2026-09-03 09:30:00', 18.700, 'terminee',    4, 2),
    ('2026-09-05 12:10:00', '2026-09-05 12:41:00', 41.800, 'terminee',    7, 8),
    ('2026-09-08 09:00:00', '2026-09-08 09:17:00',  4.050, 'interrompue', 2, 3),
    ('2026-09-12 14:22:00', '2026-09-12 16:48:00', 25.300, 'terminee',    6, 5),
    ('2026-09-15 06:45:00', '2026-09-15 07:20:00', 52.100, 'terminee',    3, 7),
    ('2026-09-18 20:05:00', '2026-09-18 22:40:00', 17.900, 'terminee',    9, 1),
    ('2026-09-22 11:30:00', '2026-09-22 11:52:00', 36.250, 'terminee',    7, 9),
    ('2026-09-25 08:00:00', '2026-09-25 10:15:00', 29.600, 'terminee',    1, 2),
    ('2026-09-29 17:10:00', '2026-09-29 17:35:00', 44.000, 'terminee',    3, 6),
    ('2026-10-01 09:40:00', '2026-10-01 11:05:00', 21.150, 'terminee',    4, 3),
    ('2026-10-03 13:00:00', '2026-10-03 13:28:00', 39.900, 'terminee',    7, 8),
    ('2026-10-05 07:30:00', '2026-10-05 09:50:00', 30.400, 'terminee',    2, 1),
    ('2026-10-06 18:20:00', '2026-10-06 18:31:00',  6.200, 'interrompue', 5, 7),
    ('2026-10-07 10:05:00', '2026-10-07 12:30:00', 26.700, 'terminee',    6, 5),
    ('2026-10-08 09:12:00', NULL,                   NULL,  'en_cours',    3, 9);

-- paiements : les particuliers ont payé par CB à la borne, juste après la charge ;
-- les entreprises ont reçu la facture de septembre (payée le 5 octobre),
-- leurs charges d'octobre attendent la facture de fin de mois.
UPDATE session_charge sc
SET date_paiement = sc.fin + INTERVAL '1 minute',
    ref_paiement  = 'CB-' || lpad(sc.id_session::text, 8, '0')
FROM badge b JOIN particulier p ON p.id_client = b.id_client
WHERE b.id_badge = sc.id_badge AND sc.statut = 'terminee';

UPDATE session_charge sc
SET date_paiement = TIMESTAMP '2026-10-05 10:00:00',
    ref_paiement  = 'FAC-202609-' || lpad(e.id_client::text, 3, '0')
FROM badge b JOIN entreprise e ON e.id_client = b.id_client
WHERE b.id_badge = sc.id_badge AND sc.statut = 'terminee' AND sc.fin < DATE '2026-10-01';

INSERT INTO incident (date_signalement, gravite, description, date_resolution, id_borne) VALUES
    ('2025-12-02 08:15:00', 'mineur',   'Écran tactile peu lisible en plein soleil',        '2025-12-04 10:00:00', 1),
    ('2026-09-08 09:20:00', 'majeur',   'Charge coupée après 17 minutes, disjoncteur déclenché', '2026-09-09 14:30:00', 2),
    ('2026-10-06 18:35:00', 'critique', 'Câble CCS sectionné, borne mise hors service',      NULL,                  5),
    ('2026-10-07 14:00:00', 'mineur',   'Lecteur de badge à remplacer (maintenance planifiée)', NULL,              8),
    ('2026-10-07 16:45:00', 'majeur',   'Trappe de la prise Type 2 bloquée en position ouverte', NULL,             6);

-- mots de passe de démonstration (communiqués à part), hachés par password_hash() de PHP
INSERT INTO compte_web (login, hash_mdp, role, id_client) VALUES
    ('admin.voltoise',    '$2y$12$PZMb/nXjn0aAGbIx9M37.OZiq.qKpNQax4CDvWx7bKqInsTW2vj/i', 'gestionnaire', NULL),
    ('claire.martin',     '$2y$12$J2Q8poXZyOTDU9yEhkbQyeEwUR9T66wHrPE9.US.6lZtcKf4QOYWy', 'client', 1),
    ('transports.durand', '$2y$12$4iCEmKLOpQSclC1BW3SY.uhrRysgSFQLqtdx4tKncBwZGrdJaSu/K', 'client', 6);
