<?php
define('MOADON', 1);
require __DIR__ . '/lib.php';
start_session();
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$erreurs = [];
$v = ['nom'=>'','telephone'=>'','date_alya'=>'','teoudat_zeout'=>'','email'=>'','quartier'=>'','caisse'=>'','activites'=>[],'souhait'=>''];
$ok = isset($_GET['merci']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nom','telephone','date_alya','teoudat_zeout','email','quartier','caisse','souhait'] as $k) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $v['activites'] = array_values(array_intersect(all_activites(), (array)($_POST['activites'] ?? [])));
    $v['teoudat_zeout'] = preg_replace('/\D/', '', $v['teoudat_zeout']);

    if (!csrf_ok()) $erreurs[] = 'La session a expiré, merci de renvoyer le formulaire.';
    if (!empty($_POST['site_web'])) $erreurs[] = 'Envoi refusé.'; // piège anti-robots
    if ($v['nom'] === '') $erreurs[] = 'Merci d\'indiquer votre prénom et nom.';
    if (!preg_match('/^[0-9+\-\s().]{9,20}$/', $v['telephone'])) $erreurs[] = 'Le numéro de téléphone semble invalide.';
    if ($v['teoudat_zeout'] !== '' && !tz_valide($v['teoudat_zeout'])) $erreurs[] = 'Le numéro de Teoudat Zeout est invalide (vérifiez les 9 chiffres).';
    if ($v['email'] !== '' && (mb_strlen($v['email']) > 190 || !filter_var($v['email'], FILTER_VALIDATE_EMAIL))) $erreurs[] = 'L\'adresse e-mail semble invalide.';
    if ($v['caisse'] !== '' && !in_array($v['caisse'], CAISSES, true)) $erreurs[] = 'Caisse maladie invalide.';
    if (mb_strlen($v['souhait']) > 3000 || mb_strlen($v['nom']) > 150 || mb_strlen($v['quartier']) > 150 || mb_strlen($v['date_alya']) > 50) {
        $erreurs[] = 'Un des champs est trop long.';
    }

    if (!$erreurs) {
        if ($v['teoudat_zeout'] !== '') $v['teoudat_zeout'] = str_pad($v['teoudat_zeout'], 9, '0', STR_PAD_LEFT);
        $st = db()->prepare('INSERT INTO inscriptions (created_at,nom,telephone,date_alya,teoudat_zeout,email,quartier,caisse,activites,souhait,ip)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $st->execute([
            (new DateTime('now', new DateTimeZone('Asia/Jerusalem')))->format('Y-m-d H:i:s'),
            $v['nom'], $v['telephone'], $v['date_alya'], $v['teoudat_zeout'], $v['email'], $v['quartier'],
            $v['caisse'], json_encode($v['activites'], JSON_UNESCAPED_UNICODE), $v['souhait'],
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        if (NOTIFY_EMAIL !== '') {
            @mail(NOTIFY_EMAIL, '=?UTF-8?B?' . base64_encode('Moadon Nahlaot : nouvelle inscription') . '?=',
                "Nouvelle inscription de {$v['nom']} ({$v['telephone']}).\nConsultez l'admin pour le détail.",
                "Content-Type: text/plain; charset=UTF-8");
        }
        unset($_SESSION['csrf']);
        header('Location: ?merci=1', true, 303);
        exit;
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fiche d'inscription – Le Moadon Femmes Francophones Nahlaot</title>
<link rel="stylesheet" href="style.css?v=2">
</head>
<body>
<div class="page">
  <header>
    <h1>Le Moadon</h1>
    <div class="sous-titre">
      <span>Femmes Francophones</span><i>•</i><span>Nahlaot</span><i>•</i><span>Centre Wiener</span><i>•</i><span>Jérusalem</span>
    </div>
    <div class="bandeau">FICHE D'INSCRIPTION</div>
  </header>

<?php if ($ok): ?>
  <div class="succes">
    <div class="coeur">♥</div>
    <h2 style="display:inline-block">Merci pour votre participation !</h2>
    <p>Votre fiche a bien été enregistrée.<br>À très bientôt au Moadon.</p>
    <p><a href="./">Remplir une autre fiche</a></p>
  </div>
<?php else: ?>
  <p class="intro">Bonjour ! Afin de développer les activités pour les femmes francophones
    du centre-ville de Jérusalem, nous aimerions mieux connaître vos envies.</p>

  <?php if ($erreurs): ?>
    <div class="erreurs"><strong>Merci de corriger :</strong><ul><?php foreach ($erreurs as $e) echo '<li>' . h($e) . '</li>'; ?></ul></div>
  <?php endif; ?>

  <form method="post" action="./" novalidate id="f">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <div class="hp" aria-hidden="true"><label>Ne pas remplir <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></div>

    <h2>1.&nbsp; Vos coordonnées</h2>
    <div class="coord">
      <div class="ligne plein"><label for="nom">Prénom et nom <span class="obl">*</span> :</label>
        <input id="nom" name="nom" required maxlength="150" autocomplete="name" value="<?= h($v['nom']) ?>"></div>

      <div class="ligne"><label for="telephone">Téléphone portable <span class="obl">*</span> :</label>
        <input id="telephone" name="telephone" type="tel" required inputmode="tel" autocomplete="tel" placeholder="05X-XXX-XXXX" value="<?= h($v['telephone']) ?>"></div>
      <div class="ligne"><label for="date_alya">Date d'alya :</label>
        <input id="date_alya" name="date_alya" maxlength="50" placeholder="ex. 2015" value="<?= h($v['date_alya']) ?>"></div>

      <div class="ligne"><label for="quartier">Quartier :</label>
        <input id="quartier" name="quartier" maxlength="150" value="<?= h($v['quartier']) ?>"></div>
      <div class="ligne caisse"><span class="lbl"><strong>Votre caisse maladie :</strong></span>
        <div class="caisses">
          <?php foreach (CAISSES as $c): ?>
            <label class="check"><input type="radio" name="caisse" value="<?= h($c) ?>" <?= $v['caisse'] === $c ? 'checked' : '' ?>> <?= h($c) ?></label>
          <?php endforeach; ?>
        </div></div>

      <div class="ligne"><label for="teoudat_zeout">Teoudat Zeout :</label>
        <div class="avec-aide"><input id="teoudat_zeout" name="teoudat_zeout" inputmode="numeric" maxlength="9" pattern="\d{5,9}" placeholder="9 chiffres" value="<?= h($v['teoudat_zeout']) ?>">
        <div class="aide" id="tz-aide"></div></div></div>
      <div class="ligne"><label for="email">E-mail :</label>
        <input id="email" name="email" type="email" maxlength="190" autocomplete="email" placeholder="nom@exemple.com" value="<?= h($v['email']) ?>"></div>
    </div>

    <h2>2.&nbsp; Quelles activités vous intéressent ?</h2>
    <p class="consigne">Cochez toutes les activités qui vous intéressent.</p>
    <div class="activites">
      <?php foreach ([ACTIVITES_GAUCHE, ACTIVITES_DROITE] as $colonne): ?>
        <div class="col">
          <?php foreach ($colonne as $groupe => $liste): ?>
            <fieldset class="groupe">
              <legend><?= h($groupe) ?></legend>
              <?php foreach ($liste as $a): ?>
                <label class="check"><input type="checkbox" name="activites[]" value="<?= h($a) ?>" <?= in_array($a, $v['activites'], true) ? 'checked' : '' ?>> <?= h($a) ?></label>
              <?php endforeach; ?>
            </fieldset>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <h2>3.&nbsp; Un souhait ou une idée à partager ?</h2>
    <label class="consigne" for="souhait">Une autre activité que vous aimeriez retrouver au centre communautaire ?</label>
    <textarea id="souhait" class="lignes" name="souhait" maxlength="3000" rows="3"><?= h($v['souhait']) ?></textarea>

    <p class="legende"><span class="obl">*</span> champs obligatoires</p>
    <div class="envoyer"><button class="btn" type="submit">ENVOYER MA FICHE</button></div>
  </form>
<?php endif; ?>

  <footer>
    <div class="merci">MERCI POUR VOTRE PARTICIPATION !</div>
    <div>Le Moadon Femmes Francophones Nahlaot</div>
  </footer>
</div>
<script>
// Vérification instantanée du Teoudat Zeout
(function(){
  var i=document.getElementById('teoudat_zeout'), a=document.getElementById('tz-aide');
  if(!i) return;
  function ok(t){ if(!/^\d{5,9}$/.test(t)) return false; t=t.padStart(9,'0'); var s=0;
    for(var k=0;k<9;k++){var n=+t[k]*((k%2)+1); s+= n>9?n-9:n;} return s%10===0; }
  i.addEventListener('input',function(){
    i.value=i.value.replace(/\D/g,'').slice(0,9);
    if(i.value.length<9){a.textContent='';return;}
    a.textContent= ok(i.value)?'✓ Numéro valide':'⚠ Numéro invalide, vérifiez les chiffres';
    a.style.color= ok(i.value)?'#2e7d32':'#b3261e';
  });
})();
</script>
</body>
</html>
