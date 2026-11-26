<?php
// Affichage : échappement, formats français, messages, gabarit commun des pages.
defined('VOLTOISE') or exit;

// Tout texte venu de la base ou de l'utilisateur passe par e() avant d'être affiché.
function e($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rediriger($page)
{
    header('Location: ' . $page, true, 303);
    exit;
}

function message($type, $texte)
{
    $_SESSION['messages'][] = ['type' => $type, 'texte' => $texte];
}

function messages()
{
    $liste = isset($_SESSION['messages']) ? $_SESSION['messages'] : [];
    unset($_SESSION['messages']);
    return $liste;
}

function euros($montant)
{
    return number_format((float) $montant, 2, ',', "\u{202F}") . "\u{00A0}€";
}

function nombre($valeur, $decimales = 0)
{
    return number_format((float) $valeur, $decimales, ',', "\u{202F}");
}

function kwh($valeur)
{
    return nombre($valeur, 1) . "\u{00A0}kWh";
}

const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
              'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function date_fr($horodatage, $avec_heure = false)
{
    if ($horodatage === null || $horodatage === '') {
        return '';
    }
    $t = strtotime($horodatage);
    $texte = date('j', $t) . ' ' . MOIS[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
    return $avec_heure ? $texte . ', ' . date('H\hi', $t) : $texte;
}

// Date courte pour les tableaux : « 5 oct. 2026, 07h30 »
function date_courte($horodatage)
{
    $abreges = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.',
                'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $t = strtotime($horodatage);
    return date('j', $t) . ' ' . $abreges[(int) date('n', $t) - 1] . ' ' . date('Y', $t) . ', ' . date('H\hi', $t);
}

// « Octobre 2026 » en titre, « octobre 2026 » dans une phrase
function mois_fr($aaaa_mm, $majuscule = true)
{
    list($a, $m) = explode('-', $aaaa_mm);
    $mois = MOIS[(int) $m - 1] . ' ' . $a;
    return $majuscule ? ucfirst($mois) : $mois;
}

function pluriel($n, $singulier, $pluriel = null)
{
    $n = (int) $n;
    return $n . ' ' . ($n > 1 ? ($pluriel === null ? $singulier . 's' : $pluriel) : $singulier);
}

// Ancienneté d'un incident : en heures le premier jour, puis en jours
function anciennete($heures)
{
    $heures = (int) $heures;
    if ($heures < 1) {
        return "moins d'une heure";
    }
    if ($heures < 48) {
        return pluriel($heures, 'heure');
    }
    return pluriel(intdiv($heures, 24), 'jour');
}

function duree($minutes)
{
    $minutes = (int) round($minutes);
    if ($minutes < 60) {
        return $minutes . "\u{00A0}min";
    }
    return intdiv($minutes, 60) . "\u{00A0}h\u{00A0}" . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
}

const ETATS_BORNE = [
    'libre'        => 'Libre',
    'occupee'      => 'En charge',
    'hors_service' => 'Hors service',
    'maintenance'  => 'Maintenance',
];

function etat_borne($etat)
{
    $libelle = isset(ETATS_BORNE[$etat]) ? ETATS_BORNE[$etat] : $etat;
    return '<span class="etat etat--' . e($etat) . '">' . e($libelle) . '</span>';
}

// Code de borne affiché comme une plaque de signalétique : CGY · 01 · B01
function plaque($code)
{
    $morceaux = explode('-', $code);
    return '<span class="plaque" title="' . e($code) . '">'
        . implode('<i aria-hidden="true"></i>', array_map('e', $morceaux)) . '</span>';
}

function gravite($g)
{
    $libelles = ['mineur' => 'Mineur', 'majeur' => 'Majeur', 'critique' => 'Critique'];
    return '<span class="gravite gravite--' . e($g) . '">' . e($libelles[$g]) . '</span>';
}

function page_erreur($code, $titre, $detail)
{
    http_response_code($code);
    entete($titre, '', false);
    echo '<section class="vide vide--erreur"><p class="vide__code">Erreur ' . (int) $code . '</p>'
        . '<h1>' . e($titre) . '</h1><p>' . e($detail) . '</p>'
        . '<p><a class="bouton" href="index.php">Revenir à l\'accueil</a></p></section>';
    pied();
    exit;
}

function liens_navigation($role)
{
    if ($role === 'gestionnaire') {
        return [
            'reseau'        => ['reseau.php', 'Réseau'],
            'incidents'     => ['incidents.php', 'Incidents'],
            'statistiques'  => ['statistiques.php', 'Chiffres'],
        ];
    }
    return ['espace' => ['espace.php', 'Mon espace']];
}

// $actif : clé du lien de navigation en cours ; $avec_nav : false sur la page de connexion
function entete($titre, $actif = '', $avec_nav = true)
{
    $u = $avec_nav ? utilisateur() : null;
    ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> · Voltoise</title>
<link rel="icon" href="assets/img/logo.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap">
<link rel="stylesheet" href="assets/css/voltoise.css">
<script src="assets/js/voltoise.js" defer></script>
</head>
<body class="<?= $u ? 'avec-nav' : 'sans-nav' ?>">
<?php if ($u): ?>
<header class="barre">
  <a class="marque" href="<?= e(page_accueil($u['role'])) ?>" aria-label="Voltoise, accueil">
    <img src="assets/img/logo.svg" alt="" width="34" height="34">
    <span class="marque__nom">volt<b>oise</b></span>
  </a>
  <button class="barre__menu" type="button" aria-expanded="false" aria-controls="navigation">Menu</button>
  <nav id="navigation" class="barre__nav" aria-label="Navigation principale">
    <?php foreach (liens_navigation($u['role']) as $cle => $lien): ?>
      <a href="<?= e($lien[0]) ?>"<?= $cle === $actif ? ' aria-current="page"' : '' ?>><?= e($lien[1]) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="barre__compte">
    <span class="barre__qui">
      <span class="barre__nom"><?= e($u['nom']) ?></span>
      <span class="barre__role"><?= $u['role'] === 'gestionnaire' ? 'Gestionnaire' : 'Client' ?></span>
    </span>
    <form method="post" action="deconnexion.php">
      <?= champ_csrf() ?>
      <button class="lien-bouton" type="submit">Déconnexion</button>
    </form>
  </div>
</header>
<?php endif; ?>
<main class="page" id="contenu">
<?php foreach (messages() as $m): ?>
  <p class="message message--<?= e($m['type']) ?>" role="status"><?= e($m['texte']) ?></p>
<?php endforeach;
}

function pied()
{
    ?>
</main>
<footer class="pied">
  <img src="assets/img/cy.png" alt="CY Cergy Paris Université" width="96" height="32">
</footer>
</body>
</html>
<?php
}
