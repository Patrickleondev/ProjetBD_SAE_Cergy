<?php
require __DIR__ . '/app/bootstrap.php';

// Déconnexion en POST avec jeton : un lien piégé ne peut pas déconnecter quelqu'un.
if (!est_post()) {
    rediriger('index.php');
}
verifier_csrf();
deconnecter();
session_start();
message('info', 'Vous êtes déconnecté.');
rediriger('connexion.php');
