<?php
// Chargé en premier par chaque page : configuration, en-têtes de sécurité, session,
// puis les briques du site. Les pages ne font jamais de SQL elles-mêmes : elles passent
// par les fonctions de app/donnees/.
define('VOLTOISE', true);
define('APP_DIR', __DIR__);

$fichier_config = APP_DIR . '/config.local.php';
if (!is_file($fichier_config)) {
    http_response_code(500);
    exit('Configuration absente : copier app/config.exemple.php en app/config.local.php.');
}
$GLOBALS['config'] = require $fichier_config;

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Le navigateur n'exécute que nos propres scripts et n'affiche pas le site dans un cadre.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
    . "style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; "
    . "script-src 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('voltoise');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => dirname($_SERVER['SCRIPT_NAME']) === '/' ? '/' : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();

require APP_DIR . '/db.php';
require APP_DIR . '/vue.php';
require APP_DIR . '/securite.php';
require APP_DIR . '/donnees/clients.php';
require APP_DIR . '/donnees/reseau.php';
require APP_DIR . '/donnees/incidents.php';
require APP_DIR . '/donnees/statistiques.php';

set_exception_handler(function ($e) {
    error_log('[voltoise] ' . $e->getMessage());
    if ($e instanceof BaseIndisponible) {
        page_erreur(503, 'La base de données ne répond pas', 'Réessayez dans un instant.');
    }
    // en mode debug, on affiche le détail : sur devweb, le journal d'Apache n'est pas lisible
    $detail = !empty($GLOBALS['config']['debug'])
        ? get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ', ligne ' . $e->getLine() . ')'
        : 'Le détail a été enregistré dans le journal du serveur.';
    page_erreur(500, 'Une erreur est survenue', $detail);
});

// La base est en UTF-8 : une saisie dans un autre encodage est refusée ici, avec un message,
// au lieu de faire échouer la requête SQL plus loin.
array_walk_recursive($_GET, 'verifier_encodage');
array_walk_recursive($_POST, 'verifier_encodage');
function verifier_encodage($valeur)
{
    if (is_string($valeur) && !mb_check_encoding($valeur, 'UTF-8')) {
        page_erreur(400, 'Saisie illisible', 'Le texte envoyé n\'est pas en UTF-8. Recopiez-le depuis le navigateur.');
    }
}

verifier_inactivite();
