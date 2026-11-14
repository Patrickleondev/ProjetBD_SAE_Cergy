// Petites aides d'interface. Le site marche sans JavaScript : ce fichier ne fait qu'ajouter
// une confirmation, l'envoi automatique des filtres, le menu mobile et les barres du graphique.
document.addEventListener('DOMContentLoaded', function () {
  // demande de confirmation avant une action qui modifie la base
  document.querySelectorAll('form[data-confirmer]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.dataset.confirmer)) ev.preventDefault();
    });
  });

  // un filtre (liste déroulante) s'applique dès qu'on le change
  document.querySelectorAll('select[data-envoi-auto]').forEach(function (select) {
    select.addEventListener('change', function () { select.form.submit(); });
  });

  // compteur de caractères sous un champ texte limité
  document.querySelectorAll('textarea[data-compteur]').forEach(function (zone) {
    var compteur = document.createElement('small');
    compteur.className = 'compteur';
    zone.insertAdjacentElement('afterend', compteur);
    var maj = function () { compteur.textContent = zone.value.length + ' / ' + zone.maxLength; };
    zone.addEventListener('input', maj);
    maj();
  });

  // largeur des barres : posée ici car la politique de sécurité interdit les styles en ligne
  document.querySelectorAll('[data-largeur]').forEach(function (barre) {
    barre.style.width = Math.max(2, Number(barre.dataset.largeur)) + '%';
  });

  // menu sur petit écran
  var bouton = document.querySelector('.barre__menu');
  if (bouton) {
    bouton.addEventListener('click', function () {
      var ouvert = bouton.getAttribute('aria-expanded') === 'true';
      bouton.setAttribute('aria-expanded', String(!ouvert));
      document.body.classList.toggle('menu-ouvert', !ouvert);
    });
  }
});
