<?php
// Connexion, rôles, jeton anti-CSRF et expiration de session.
defined('VOLTOISE') or exit;

const INACTIVITE_MAX = 1800;        // 30 minutes sans action : déconnexion
const ESSAIS_MAX = 5;               // au-delà, attente avant un nouvel essai
const ATTENTE_APRES_ESSAIS = 60;    // secondes

// Hachage d'un mot de passe quelconque : on le vérifie quand le login n'existe pas,
// pour que la réponse prenne le même temps que pour un login existant.
const HACHAGE_LEURRE = '$2y$12$PZMb/nXjn0aAGbIx9M37.OZiq.qKpNQax4CDvWx7bKqInsTW2vj/i';

function utilisateur()
{
    return isset($_SESSION['utilisateur']) ? $_SESSION['utilisateur'] : null;
}

function page_accueil($role)
{
    return $role === 'gestionnaire' ? 'reseau.php' : 'espace.php';
}

// Renvoie null si la connexion réussit, sinon le message à afficher.
function connecter($login, $mot_de_passe)
{
    $maintenant = time();
    if (!empty($_SESSION['bloque_jusqua']) && $_SESSION['bloque_jusqua'] > $maintenant) {
        $reste = $_SESSION['bloque_jusqua'] - $maintenant;
        return "Trop d'essais. Patientez $reste secondes.";
    }

    $compte = db_ligne(
        "SELECT cw.id_compte, cw.login, cw.hash_mdp, cw.role, cw.id_client,
                c.prenom, c.nom, e.raison_sociale
         FROM compte_web cw
         LEFT JOIN client c     ON c.id_client = cw.id_client
         LEFT JOIN entreprise e ON e.id_client = cw.id_client
         WHERE cw.login = $1",
        [mb_strtolower(trim($login))]
    );

    $hachage = $compte ? trim($compte['hash_mdp']) : HACHAGE_LEURRE;
    $valide = password_verify($mot_de_passe, $hachage) && $compte !== null;

    if (!$valide) {
        $_SESSION['essais'] = (isset($_SESSION['essais']) ? $_SESSION['essais'] : 0) + 1;
        if ($_SESSION['essais'] >= ESSAIS_MAX) {
            $_SESSION['essais'] = 0;
            $_SESSION['bloque_jusqua'] = $maintenant + ATTENTE_APRES_ESSAIS;
        }
        return 'Identifiant ou mot de passe incorrect.';
    }

    // nouvel identifiant de session : celui d'avant la connexion ne vaut plus rien
    session_regenerate_id(true);
    unset($_SESSION['essais'], $_SESSION['bloque_jusqua']);

    if ($compte['role'] === 'gestionnaire') {
        $nom = 'Gestion du réseau';
    } elseif ($compte['raison_sociale'] !== null) {
        $nom = $compte['raison_sociale'];
    } else {
        $nom = $compte['prenom'] . ' ' . $compte['nom'];
    }

    $_SESSION['utilisateur'] = [
        'id_compte' => (int) $compte['id_compte'],
        'login'     => $compte['login'],
        'role'      => $compte['role'],
        'id_client' => $compte['id_client'] === null ? null : (int) $compte['id_client'],
        'nom'       => $nom,
    ];
    $_SESSION['derniere_action'] = $maintenant;
    return null;
}

function deconnecter()
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
}

function verifier_inactivite()
{
    if (utilisateur() === null) {
        return;
    }
    $derniere = isset($_SESSION['derniere_action']) ? $_SESSION['derniere_action'] : 0;
    if (time() - $derniere > INACTIVITE_MAX) {
        deconnecter();
        session_start();
        message('info', 'Session expirée après 30 minutes sans activité. Reconnectez-vous.');
        rediriger('connexion.php');
    }
    $_SESSION['derniere_action'] = time();
}

// À appeler en haut de chaque page protégée.
function exiger_role($role)
{
    $u = utilisateur();
    if ($u === null) {
        rediriger('connexion.php');
    }
    if ($u['role'] !== $role) {
        rediriger(page_accueil($u['role']));
    }
    return $u;
}

function jeton_csrf()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function champ_csrf()
{
    return '<input type="hidden" name="csrf" value="' . e(jeton_csrf()) . '">';
}

// Tout formulaire POST doit renvoyer le jeton de la session : un site tiers ne le connaît pas.
function verifier_csrf()
{
    $recu = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $recu)) {
        page_erreur(400, 'Formulaire expiré', 'Rechargez la page puis recommencez.');
    }
}

function est_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}
