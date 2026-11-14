<?php
// Modèle de configuration. Copier ce fichier en config.local.php (jamais versionné)
// et remplir les accès à la base. C'est un fichier PHP : même déposé dans le dossier
// web, son contenu n'est jamais affiché par le navigateur.
defined('VOLTOISE') or exit;

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => '5433',
        'dbname'   => 'voltoise',
        'user'     => 'postgres',
        'password' => 'A_REMPLIR',
    ],
    // true en local pour voir les erreurs PHP ; false sur le serveur de démonstration
    'debug' => false,
];
