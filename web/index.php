<?php
require __DIR__ . '/app/bootstrap.php';

$u = utilisateur();
rediriger($u === null ? 'connexion.php' : page_accueil($u['role']));
