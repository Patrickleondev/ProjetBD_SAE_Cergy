<?php
require __DIR__ . '/app/bootstrap.php';

$u = exiger_role('client');
$id_client = $u['id_client'];

// Bloquer ou réactiver un badge (UPDATE), puis retour sur la page (motif POST, redirection, GET).
if (est_post()) {
    verifier_csrf();
    $id_badge = filter_input(INPUT_POST, 'id_badge', FILTER_VALIDATE_INT);
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($id_badge && in_array($action, ['bloquer', 'reactiver'], true)) {
        $refus = changer_etat_badge($id_badge, $id_client, $action === 'reactiver');
        if ($refus === null) {
            message('succes', $action === 'bloquer'
                ? 'Badge bloqué : les bornes le refuseront dès la prochaine présentation.'
                : 'Badge réactivé : il fonctionne de nouveau sur toutes les bornes.');
        } else {
            message('erreur', $refus);
        }
    }
    rediriger('espace.php');
}

$client = fiche_client($id_client);
$badges = badges_client($id_client);
$bilan = bilan_client($id_client);
$mois_dispo = mois_avec_charges($id_client);
$mois = isset($_GET['mois']) && in_array($_GET['mois'], $mois_dispo, true) ? $_GET['mois'] : null;
$charges = charges_client($id_client, $mois);
$entreprise = $client['type_client'] === 'entreprise';

entete('Mon espace', 'espace');
?>
<header class="tete">
  <div>
    <p class="tete__sur"><?= $entreprise ? 'Compte entreprise' : 'Compte particulier' ?> · client depuis <?= e(date_fr($client['date_inscription'])) ?></p>
    <h1><?= e($entreprise ? $client['raison_sociale'] : $client['prenom'] . ' ' . $client['nom']) ?></h1>
    <?php if ($entreprise): ?>
      <p class="tete__sous">SIRET <span class="mono"><?= e($client['siret']) ?></span> · flotte de <?= (int) $client['nb_vehicules'] ?> véhicules · contact <?= e($client['prenom'] . ' ' . $client['nom']) ?></p>
    <?php else: ?>
      <p class="tete__sous"><?= e($client['email']) ?></p>
    <?php endif; ?>
  </div>
  <div class="tarif">
    <span class="tarif__nom">Tarif <?= e($client['libelle_tarif']) ?></span>
    <span class="tarif__prix"><?= e(nombre($client['prix_kwh'], 2)) ?> €<small>/kWh</small></span>
    <?php if ((float) $client['prix_minute'] > 0): ?>
      <span class="tarif__plus">+ <?= e(nombre($client['prix_minute'], 2)) ?> € par minute branchée</span>
    <?php endif; ?>
  </div>
</header>

<section class="chiffres" aria-label="Bilan">
  <div class="chiffre"><span class="chiffre__valeur"><?= (int) $bilan['nb_charges'] ?></span><span class="chiffre__nom">charges terminées</span></div>
  <div class="chiffre"><span class="chiffre__valeur"><?= e(kwh($bilan['energie'])) ?></span><span class="chiffre__nom">rechargés au total</span></div>
  <div class="chiffre"><span class="chiffre__valeur"><?= e(euros($bilan['depense'])) ?></span><span class="chiffre__nom">dépensés au total</span></div>
  <?php if ($entreprise): ?>
    <div class="chiffre chiffre--accent"><span class="chiffre__valeur"><?= e(euros($bilan['a_facturer'])) ?></span><span class="chiffre__nom"><?= (int) $bilan['nb_a_facturer'] === 0 ? 'aucune charge en attente de facture' : 'sur la prochaine facture (' . e(pluriel($bilan['nb_a_facturer'], 'charge')) . ')' ?></span></div>
  <?php endif; ?>
</section>

<section class="bloc">
  <div class="bloc__tete">
    <h2>Badges</h2>
    <p>Un badge perdu ou volé se bloque ici, sans attendre le service client.</p>
  </div>
  <ul class="badges">
    <?php foreach ($badges as $b):
        $actif = db_bool($b['actif']); ?>
      <li class="badge<?= $actif ? '' : ' badge--bloque' ?>">
        <div class="badge__puce" aria-hidden="true"></div>
        <div class="badge__infos">
          <span class="mono badge__code"><?= e(chunk_split(trim($b['code_rfid']), 4, ' ')) ?></span>
          <span class="badge__detail">
            <?= $actif ? 'Actif' : 'Bloqué' ?> · activé le <?= e(date_fr($b['date_activation'])) ?> ·
            <?= (int) $b['nb_charges'] === 0 ? 'jamais utilisé'
                : (int) $b['nb_charges'] . ' charge' . ((int) $b['nb_charges'] > 1 ? 's' : '') . ', la dernière le ' . e(date_fr($b['derniere_charge'])) ?>
          </span>
        </div>
        <form method="post" action="espace.php"
              data-confirmer="<?= $actif ? 'Bloquer ce badge ? Les bornes le refuseront.' : 'Réactiver ce badge ?' ?>">
          <?= champ_csrf() ?>
          <input type="hidden" name="id_badge" value="<?= (int) $b['id_badge'] ?>">
          <input type="hidden" name="action" value="<?= $actif ? 'bloquer' : 'reactiver' ?>">
          <?php if ($actif && db_bool($b['en_charge'])): ?>
            <span class="etat etat--occupee">En charge</span>
          <?php else: ?>
            <button class="bouton <?= $actif ? 'bouton--danger' : '' ?>" type="submit"><?= $actif ? 'Bloquer' : 'Réactiver' ?></button>
          <?php endif; ?>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="bloc">
  <div class="bloc__tete">
    <h2>Charges</h2>
    <?php if ($mois_dispo): ?>
      <form class="filtres" method="get" action="espace.php">
        <label class="champ champ--ligne">
          <span>Mois</span>
          <select name="mois" data-envoi-auto>
            <option value="">Tous</option>
            <?php foreach ($mois_dispo as $m): ?>
              <option value="<?= e($m) ?>"<?= $m === $mois ? ' selected' : '' ?>><?= e(mois_fr($m)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <noscript><button class="bouton" type="submit">Afficher</button></noscript>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$charges): ?>
    <p class="vide">Aucune charge <?= $mois ? 'en ' . e(mois_fr($mois, false)) : 'pour le moment' ?>.</p>
  <?php else: ?>
    <div class="tableau-defilant">
      <table class="tableau">
        <thead><tr>
          <th scope="col">Date</th><th scope="col">Borne</th><th scope="col">Durée</th>
          <th scope="col" class="nombre">Énergie</th><th scope="col" class="nombre">Montant</th><th scope="col">Paiement</th>
        </tr></thead>
        <tbody>
        <?php foreach ($charges as $c): ?>
          <tr>
            <td class="date"><?= e(date_courte($c['debut'])) ?></td>
            <td><?= plaque($c['code_borne']) ?><span class="sous"><?= e($c['nom_station']) ?></span></td>
            <td><?= $c['statut'] === 'en_cours' ? '<span class="etat etat--occupee">En cours</span>' : e(duree($c['minutes'])) ?></td>
            <td class="nombre"><?= $c['energie_kwh'] === null ? '' : e(kwh($c['energie_kwh'])) ?></td>
            <td class="nombre"><?= $c['montant'] === null ? '' : e(euros($c['montant'])) ?></td>
            <td>
              <?php if ($c['statut'] === 'interrompue'): ?>
                <span class="sous">Interrompue, non facturée</span>
              <?php elseif ($c['statut'] === 'en_cours'): ?>
                <span class="sous">Après la charge</span>
              <?php elseif ($c['ref_paiement'] !== null): ?>
                <?= strpos($c['ref_paiement'], 'CB-') === 0 ? 'Carte bancaire' : 'Facture' ?>
                <span class="sous mono"><?= e($c['ref_paiement']) ?></span>
              <?php else: ?>
                <?= $entreprise ? 'Prochaine facture' : '<span class="gravite gravite--majeur">À régler</span>' ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php
pied();
