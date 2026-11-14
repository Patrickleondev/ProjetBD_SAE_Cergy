<?php
require __DIR__ . '/app/bootstrap.php';

if (utilisateur() !== null) {
    rediriger(page_accueil(utilisateur()['role']));
}

$erreur = null;
$login = '';
if (est_post()) {
    verifier_csrf();
    $login = isset($_POST['login']) ? (string) $_POST['login'] : '';
    $mdp = isset($_POST['mot_de_passe']) ? (string) $_POST['mot_de_passe'] : '';
    if ($login === '' || $mdp === '') {
        $erreur = 'Saisissez votre identifiant et votre mot de passe.';
    } else {
        $erreur = connecter($login, $mdp);
        if ($erreur === null) {
            rediriger(page_accueil(utilisateur()['role']));
        }
    }
}

// Le réseau en direct, pour le panneau de gauche : rien de personnel, juste l'état des bornes.
$etats = compte_par_etat();
$stations = db_lignes(
    "SELECT s.nom_station, s.ville, COUNT(b.id_borne) AS bornes,
            COUNT(b.id_borne) FILTER (WHERE b.etat = 'libre') AS libres
     FROM station s JOIN borne b ON b.id_station = s.id_station
     GROUP BY s.id_station ORDER BY s.id_station"
);
// positions des stations le long du tracé de la rivière (même ordre que dans la base)
$points = [[70, 250], [150, 175], [255, 140], [345, 70]];

entete('Connexion', '', false);
?>
<div class="accueil">
  <section class="accueil__reseau" aria-label="État du réseau Voltoise">
    <a class="marque marque--claire" href="connexion.php">
      <img src="assets/img/logo.svg" alt="" width="40" height="40">
      <span class="marque__nom">volt<b>oise</b></span>
    </a>

    <div class="accueil__texte">
      <h1>Bornes de recharge<br>le long de l'Oise</h1>
      <p>Cergy, Pontoise, Saint-Ouen-l'Aumône : <?= array_sum($etats) ?> bornes,
         dont <?= $etats['libre'] ?> libres en ce moment.</p>
    </div>

    <svg class="ligne" viewBox="0 0 420 300" role="img"
         aria-label="Les <?= count($stations) ?> stations du réseau, le long de l'Oise">
      <path class="ligne__riviere" d="M10 290 C 80 250, 90 200, 150 175 S 230 150, 255 140 S 330 100, 345 70 S 380 20, 410 10"/>
      <?php foreach ($stations as $i => $s):
          if (!isset($points[$i])) { break; }
          list($x, $y) = $points[$i];
          $droite = $i % 2 === 0; ?>
        <g class="ligne__station<?= (int) $s['libres'] === 0 ? ' ligne__station--pleine' : '' ?>">
          <circle cx="<?= $x ?>" cy="<?= $y ?>" r="9"/>
          <text x="<?= $droite ? $x + 18 : $x - 18 ?>" y="<?= $y - 4 ?>"
                text-anchor="<?= $droite ? 'start' : 'end' ?>"><?= e($s['nom_station']) ?></text>
          <text class="ligne__detail" x="<?= $droite ? $x + 18 : $x - 18 ?>" y="<?= $y + 13 ?>"
                text-anchor="<?= $droite ? 'start' : 'end' ?>"><?= (int) $s['libres'] ?>/<?= (int) $s['bornes'] ?> libres</text>
        </g>
      <?php endforeach; ?>
    </svg>

    <ul class="accueil__legende">
      <?php foreach ($etats as $etat => $n): ?>
        <li><?= etat_borne($etat) ?> <b><?= $n ?></b></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="accueil__formulaire">
    <form class="carte connexion" method="post" action="connexion.php" novalidate>
      <h2>Se connecter</h2>
      <p class="connexion__aide">Clients : vos badges et vos charges. Gestionnaires : l'état du réseau et les incidents.</p>
      <?= champ_csrf() ?>
      <?php if ($erreur !== null): ?>
        <p class="message message--erreur" role="alert"><?= e($erreur) ?></p>
      <?php endif; ?>
      <label class="champ">
        <span>Identifiant</span>
        <input name="login" type="text" autocomplete="username" autocapitalize="none"
               spellcheck="false" required maxlength="30" value="<?= e($login) ?>" autofocus>
      </label>
      <label class="champ">
        <span>Mot de passe</span>
        <input name="mot_de_passe" type="password" autocomplete="current-password" required>
      </label>
      <button class="bouton bouton--plein" type="submit">Se connecter</button>
    </form>
  </section>
</div>
<?php
pied();
