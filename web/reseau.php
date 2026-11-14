<?php
require __DIR__ . '/app/bootstrap.php';

exiger_role('gestionnaire');

if (est_post()) {
    verifier_csrf();
    $id_borne = filter_input(INPUT_POST, 'id_borne', FILTER_VALIDATE_INT);
    $etat = isset($_POST['etat']) ? (string) $_POST['etat'] : '';
    if ($id_borne) {
        $refus = changer_etat_borne($id_borne, $etat);
        $refus === null
            ? message('succes', 'État de la borne mis à jour : ' . mb_strtolower(ETATS_BORNE[$etat]) . '.')
            : message('erreur', $refus);
    }
    // on revient sur la même vue filtrée
    $retour = isset($_POST['retour']) && preg_match('/^[a-z_=&%0-9A-Z.\-+]*$/', $_POST['retour']) ? $_POST['retour'] : '';
    rediriger('reseau.php' . ($retour !== '' ? '?' . $retour : ''));
}

$villes = villes();
$ville = isset($_GET['ville']) && in_array($_GET['ville'], $villes, true) ? $_GET['ville'] : '';
$etat = isset($_GET['etat']) && isset(ETATS_BORNE[$_GET['etat']]) ? $_GET['etat'] : '';
$bornes = bornes($ville, $etat);
$compte = compte_par_etat();
$requete = http_build_query(array_filter(['ville' => $ville, 'etat' => $etat]));

// regroupement par station pour l'affichage
$stations = [];
foreach ($bornes as $b) {
    $stations[$b['id_station']]['station'] = $b;
    $stations[$b['id_station']]['bornes'][] = $b;
}

entete('Réseau', 'reseau');
?>
<header class="tete">
  <div>
    <p class="tete__sur">Réseau Voltoise</p>
    <h1>Bornes</h1>
    <p class="tete__sous"><?= array_sum($compte) ?> bornes dans <?= count($villes) ?> villes. L'état « en charge » est posé par le serveur réseau au début d'une charge.</p>
  </div>
</header>

<nav class="pastilles" aria-label="Filtrer par état">
  <a href="reseau.php<?= $ville ? '?' . e(http_build_query(['ville' => $ville])) : '' ?>"
     class="pastille"<?= $etat === '' ? ' aria-current="true"' : '' ?>>Toutes <b><?= array_sum($compte) ?></b></a>
  <?php foreach (ETATS_BORNE as $cle => $libelle): ?>
    <a href="reseau.php?<?= e(http_build_query(array_filter(['ville' => $ville, 'etat' => $cle]))) ?>"
       class="pastille pastille--<?= e($cle) ?>"<?= $etat === $cle ? ' aria-current="true"' : '' ?>><?= e($libelle) ?> <b><?= $compte[$cle] ?></b></a>
  <?php endforeach; ?>
</nav>

<form class="filtres" method="get" action="reseau.php">
  <?php if ($etat): ?><input type="hidden" name="etat" value="<?= e($etat) ?>"><?php endif; ?>
  <label class="champ champ--ligne">
    <span>Ville</span>
    <select name="ville" data-envoi-auto>
      <option value="">Toutes</option>
      <?php foreach ($villes as $v): ?>
        <option<?= $v === $ville ? ' selected' : '' ?>><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="bouton" type="submit">Filtrer</button></noscript>
</form>

<?php if (!$stations): ?>
  <p class="vide">Aucune borne ne correspond à ce filtre.</p>
<?php endif; ?>

<?php foreach ($stations as $st):
    $s = $st['station']; ?>
  <section class="station">
    <header class="station__tete">
      <h2><?= e($s['nom_station']) ?></h2>
      <p><?= e($s['adresse'] . ', ' . $s['code_postal'] . ' ' . $s['ville']) ?></p>
    </header>
    <div class="bornes">
      <?php foreach ($st['bornes'] as $b): ?>
        <article class="borne borne--<?= e($b['etat']) ?>">
          <div class="borne__haut">
            <?= plaque($b['code_borne']) ?>
            <?= etat_borne($b['etat']) ?>
          </div>
          <p class="borne__puissance">
            <b><?= e(nombre($b['puissance_max_kw'], $b['puissance_max_kw'] == (int) $b['puissance_max_kw'] ? 0 : 1)) ?></b> kW
            <span class="courant courant--<?= e(strtolower($b['type_courant'])) ?>"><?= $b['type_courant'] === 'DC' ? 'Rapide DC' : 'Normale AC' ?></span>
          </p>
          <p class="borne__prises"><?= e($b['prises']) ?></p>
          <ul class="borne__faits">
            <?php if ($b['charge_depuis']): ?>
              <li>En charge depuis le <?= e(date_fr($b['charge_depuis'], true)) ?></li>
            <?php endif; ?>
            <?php if ((int) $b['incidents_ouverts'] > 0): ?>
              <li><a href="incidents.php"><?= (int) $b['incidents_ouverts'] ?> incident<?= (int) $b['incidents_ouverts'] > 1 ? 's' : '' ?> ouvert<?= (int) $b['incidents_ouverts'] > 1 ? 's' : '' ?></a></li>
            <?php endif; ?>
            <li class="sous">Dernier signal : <?= $b['derniere_communication'] ? e(date_fr($b['derniere_communication'], true)) : 'jamais' ?></li>
          </ul>
          <?php if ($b['etat'] !== 'occupee'): ?>
            <form class="borne__action" method="post" action="reseau.php">
              <?= champ_csrf() ?>
              <input type="hidden" name="id_borne" value="<?= (int) $b['id_borne'] ?>">
              <input type="hidden" name="retour" value="<?= e($requete) ?>">
              <label class="visuellement-cache" for="etat-<?= (int) $b['id_borne'] ?>">Nouvel état de <?= e($b['code_borne']) ?></label>
              <select id="etat-<?= (int) $b['id_borne'] ?>" name="etat">
                <?php foreach (ETATS_MANUELS as $em): ?>
                  <option value="<?= e($em) ?>"<?= $em === $b['etat'] ? ' selected' : '' ?>><?= e(ETATS_BORNE[$em]) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="bouton bouton--petit" type="submit">Appliquer</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach;
pied();
