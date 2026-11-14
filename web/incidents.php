<?php
require __DIR__ . '/app/bootstrap.php';

exiger_role('gestionnaire');

$filtres = ['ouverts' => 'Ouverts', 'resolus' => 'Résolus', 'tous' => 'Tous'];
$filtre = isset($_GET['voir']) && isset($filtres[$_GET['voir']]) ? $_GET['voir'] : 'ouverts';

$saisie = ['id_borne' => '', 'gravite' => '', 'description' => '', 'hors_service' => false];
$erreurs = [];

if (est_post()) {
    verifier_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'declarer') {
        $saisie = [
            'id_borne'     => (int) filter_input(INPUT_POST, 'id_borne', FILTER_VALIDATE_INT),
            'gravite'      => isset($_POST['gravite']) ? (string) $_POST['gravite'] : '',
            'description'  => trim(isset($_POST['description']) ? (string) $_POST['description'] : ''),
            'hors_service' => !empty($_POST['hors_service']),
        ];
        $erreurs = verifier_incident($saisie['id_borne'], $saisie['gravite'], $saisie['description']);
        if (!$erreurs) {
            $r = declarer_incident($saisie['id_borne'], $saisie['gravite'], $saisie['description'], $saisie['hors_service']);
            $texte = 'Incident n° ' . $r['id'] . ' enregistré.';
            if ($saisie['hors_service']) {
                $texte .= $r['borne_coupee'] ? ' La borne est passée hors service.'
                    : ' La borne est en charge : elle n\'a pas été coupée.';
            }
            message('succes', $texte);
            rediriger('incidents.php');
        }
        // sinon : on réaffiche le formulaire avec la saisie et les erreurs
    } elseif ($action === 'clore') {
        $id = filter_input(INPUT_POST, 'id_incident', FILTER_VALIDATE_INT);
        if ($id && clore_incident($id, !empty($_POST['remettre']))) {
            message('succes', 'Incident n° ' . $id . ' clos.');
        } else {
            message('erreur', 'Cet incident est déjà clos ou n\'existe pas.');
        }
        rediriger('incidents.php?voir=' . $filtre);
    }
}

$liste = incidents($filtre);
$bornes = liste_bornes_courte();
$nb_ouverts = nb_incidents_ouverts();

entete('Incidents', 'incidents');
?>
<header class="tete">
  <div>
    <p class="tete__sur">Maintenance</p>
    <h1>Incidents</h1>
    <p class="tete__sous"><?= $nb_ouverts === 0 ? 'Aucun incident ouvert.' : $nb_ouverts . ' incident' . ($nb_ouverts > 1 ? 's' : '') . ' à traiter, le plus grave en premier.' ?></p>
  </div>
</header>

<div class="deux-colonnes">
  <section class="bloc">
    <nav class="onglets" aria-label="Filtrer les incidents">
      <?php foreach ($filtres as $cle => $libelle): ?>
        <a href="incidents.php?voir=<?= e($cle) ?>"<?= $cle === $filtre ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if (!$liste): ?>
      <p class="vide">Rien à afficher ici.</p>
    <?php endif; ?>

    <ol class="incidents">
      <?php foreach ($liste as $i):
          $ouvert = $i['date_resolution'] === null; ?>
        <li class="incident<?= $ouvert ? '' : ' incident--clos' ?>">
          <div class="incident__tete">
            <?= gravite($i['gravite']) ?>
            <?= plaque($i['code_borne']) ?>
            <span class="sous"><?= e($i['nom_station']) ?></span>
            <span class="incident__num">n° <?= (int) $i['id_incident'] ?></span>
          </div>
          <p class="incident__texte"><?= e($i['description']) ?></p>
          <p class="incident__quand">
            Signalé le <?= e(date_fr($i['date_signalement'], true)) ?>
            <?php if ($ouvert): ?>
              · ouvert depuis <?= e(anciennete($i['heures'])) ?>
              · borne <?= mb_strtolower(ETATS_BORNE[$i['etat']]) ?>
            <?php else: ?>
              · clos le <?= e(date_fr($i['date_resolution'], true)) ?>
            <?php endif; ?>
          </p>
          <?php if ($ouvert): ?>
            <form class="incident__action" method="post" action="incidents.php?voir=<?= e($filtre) ?>"
                  data-confirmer="Clore l'incident n° <?= (int) $i['id_incident'] ?> ?">
              <?= champ_csrf() ?>
              <input type="hidden" name="action" value="clore">
              <input type="hidden" name="id_incident" value="<?= (int) $i['id_incident'] ?>">
              <?php if (in_array($i['etat'], ['hors_service', 'maintenance'], true)): ?>
                <label class="case"><input type="checkbox" name="remettre" value="1" checked> Remettre la borne en service</label>
              <?php endif; ?>
              <button class="bouton bouton--petit" type="submit">Clore</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="bloc">
    <form class="carte" method="post" action="incidents.php?voir=<?= e($filtre) ?>" novalidate>
      <h2>Déclarer un incident</h2>
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="declarer">

      <label class="champ<?= isset($erreurs['borne']) ? ' champ--erreur' : '' ?>">
        <span>Borne</span>
        <select name="id_borne" required>
          <option value="">Choisir…</option>
          <?php foreach ($bornes as $b): ?>
            <option value="<?= (int) $b['id_borne'] ?>"<?= (int) $b['id_borne'] === $saisie['id_borne'] ? ' selected' : '' ?>><?= e($b['code_borne'] . ' · ' . $b['nom_station']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($erreurs['borne'])): ?><small><?= e($erreurs['borne']) ?></small><?php endif; ?>
      </label>

      <fieldset class="champ<?= isset($erreurs['gravite']) ? ' champ--erreur' : '' ?>">
        <legend>Gravité</legend>
        <div class="choix">
          <?php foreach (GRAVITES as $g): ?>
            <label class="choix__item choix__item--<?= e($g) ?>">
              <input type="radio" name="gravite" value="<?= e($g) ?>"<?= $saisie['gravite'] === $g ? ' checked' : '' ?>>
              <span><?= e(ucfirst($g)) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($erreurs['gravite'])): ?><small><?= e($erreurs['gravite']) ?></small><?php endif; ?>
      </fieldset>

      <label class="champ<?= isset($erreurs['description']) ? ' champ--erreur' : '' ?>">
        <span>Ce qui ne va pas</span>
        <textarea name="description" rows="4" maxlength="500" required data-compteur
                  placeholder="Exemple : écran noir, la borne ne lit plus les badges"><?= e($saisie['description']) ?></textarea>
        <?php if (isset($erreurs['description'])): ?><small><?= e($erreurs['description']) ?></small><?php endif; ?>
      </label>

      <label class="case"><input type="checkbox" name="hors_service" value="1"<?= $saisie['hors_service'] ? ' checked' : '' ?>> Mettre la borne hors service</label>

      <button class="bouton bouton--plein" type="submit">Enregistrer l'incident</button>
    </form>
  </section>
</div>
<?php
pied();
