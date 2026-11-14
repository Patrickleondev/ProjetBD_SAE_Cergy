<?php
require __DIR__ . '/app/bootstrap.php';

exiger_role('gestionnaire');

$mois_dispo = mois_disponibles();
if (!$mois_dispo) {
    entete('Chiffres', 'statistiques');
    echo '<p class="vide">Aucune charge enregistrée pour le moment.</p>';
    pied();
    exit;
}
$mois = isset($_GET['mois']) && in_array($_GET['mois'], $mois_dispo, true) ? $_GET['mois'] : $mois_dispo[0];

$ind = indicateurs($mois);
$stations = energie_par_station($mois);
$tarifs = usage_tarifs();
$factures = a_facturer_entreprises();
$sans_incident = bornes_sans_incident();
$max_energie = max(1, max(array_map(function ($s) { return (float) $s['energie']; }, $stations)));

entete('Chiffres', 'statistiques');
?>
<header class="tete">
  <div>
    <p class="tete__sur">Activité du réseau</p>
    <h1><?= e(mois_fr($mois)) ?></h1>
  </div>
  <form class="filtres" method="get" action="statistiques.php">
    <label class="champ champ--ligne">
      <span>Mois</span>
      <select name="mois" data-envoi-auto>
        <?php foreach ($mois_dispo as $m): ?>
          <option value="<?= e($m) ?>"<?= $m === $mois ? ' selected' : '' ?>><?= e(mois_fr($m)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="bouton" type="submit">Afficher</button></noscript>
  </form>
</header>

<section class="chiffres" aria-label="Indicateurs du mois">
  <div class="chiffre"><span class="chiffre__valeur"><?= (int) $ind['charges'] ?></span><span class="chiffre__nom">charges terminées<?= (int) $ind['interrompues'] ? ', ' . (int) $ind['interrompues'] . ' interrompue' . ((int) $ind['interrompues'] > 1 ? 's' : '') : '' ?></span></div>
  <div class="chiffre"><span class="chiffre__valeur"><?= e(kwh($ind['energie'])) ?></span><span class="chiffre__nom">délivrés</span></div>
  <div class="chiffre"><span class="chiffre__valeur"><?= e(euros($ind['recette'])) ?></span><span class="chiffre__nom">facturés aux clients</span></div>
  <div class="chiffre"><span class="chiffre__valeur"><?= (int) $ind['clients'] ?></span><span class="chiffre__nom">clients venus charger</span></div>
</section>

<div class="deux-colonnes">
  <section class="bloc">
    <div class="bloc__tete"><h2>Énergie par station</h2></div>
    <ul class="barres">
      <?php foreach ($stations as $s): ?>
        <li>
          <span class="barres__nom"><?= e($s['nom_station']) ?></span>
          <span class="barres__piste"><span class="barres__barre" data-largeur="<?= round((float) $s['energie'] / $max_energie * 100) ?>"></span></span>
          <span class="barres__valeur"><?= e(kwh($s['energie'])) ?> <span class="sous"><?= e(pluriel($s['charges'], 'charge')) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="bloc">
    <div class="bloc__tete"><h2>Tarifs</h2><p>Depuis l'ouverture, charges terminées.</p></div>
    <table class="tableau">
      <thead><tr><th scope="col">Tarif</th><th scope="col" class="nombre">Prix du kWh</th><th scope="col" class="nombre">Charges</th><th scope="col" class="nombre">Énergie</th></tr></thead>
      <tbody>
        <?php foreach ($tarifs as $t): ?>
          <tr>
            <td><?= e($t['libelle_tarif']) ?></td>
            <td class="nombre"><?= e(nombre($t['prix_kwh'], 2)) ?> €</td>
            <td class="nombre"><?= (int) $t['charges'] ?></td>
            <td class="nombre"><?= e(kwh($t['energie'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<div class="deux-colonnes">
  <section class="bloc">
    <div class="bloc__tete"><h2>À facturer aux entreprises</h2><p>Charges terminées pas encore payées : elles iront sur la facture de fin de mois.</p></div>
    <?php if (!$factures): ?>
      <p class="vide">Tout est facturé.</p>
    <?php else: ?>
      <table class="tableau">
        <thead><tr><th scope="col">Entreprise</th><th scope="col" class="nombre">Charges</th><th scope="col" class="nombre">Montant</th></tr></thead>
        <tbody>
          <?php foreach ($factures as $f): ?>
            <tr>
              <td><?= e($f['raison_sociale']) ?><span class="sous mono">SIRET <?= e($f['siret']) ?></span></td>
              <td class="nombre"><?= (int) $f['charges'] ?></td>
              <td class="nombre"><?= e(euros($f['montant'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="bloc">
    <div class="bloc__tete"><h2>Bornes sans aucun incident</h2><p>Depuis leur mise en service.</p></div>
    <ul class="liste-plaques">
      <?php foreach ($sans_incident as $b): ?>
        <li><?= plaque($b['code_borne']) ?> <span class="sous"><?= e($b['nom_station']) ?>, en service depuis <?= e(mois_fr(substr($b['date_mise_service'], 0, 7), false)) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
<?php
pied();
