<?php
define('MOADON', 1);
require __DIR__ . '/lib.php';
start_session();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$self = basename(__FILE__);
$msg = '';

// ---------- Connexion / déconnexion ----------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header("Location: $self");
    exit;
}

if (empty($_SESSION['admin'])) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        // Anti force brute : délai croissant après des échecs
        $fails = $_SESSION['fails'] ?? 0;
        if ($fails > 0) sleep(min(2 * $fails, 10));
        if (csrf_ok() && password_verify($_POST['password'], ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['fails'] = 0;
            header("Location: $self");
            exit;
        }
        $_SESSION['fails'] = $fails + 1;
        $err = 'Mot de passe incorrect.';
    }
    ?><!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Admin – Le Moadon</title><link rel="stylesheet" href="style.css?v=2"></head>
    <body><div class="page" style="max-width:420px;margin-top:80px">
      <header><h1 style="font-size:2rem">Le Moadon</h1><div class="lieu">Espace administration</div></header>
      <?php if ($err): ?><div class="erreurs"><?= h($err) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
        <div class="champ"><label for="pw">Mot de passe</label><input id="pw" type="password" name="password" autofocus required></div>
        <div class="envoyer"><button class="btn">Se connecter</button></div>
      </form>
    </div></body></html><?php
    exit;
}

// ---------- Actions (connecté) ----------
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM inscriptions WHERE id = ?')->execute([(int)$_POST['delete']]);
    header("Location: $self?deleted=1");
    exit;
}
if (isset($_GET['deleted'])) $msg = 'Inscription supprimée.';

$q = trim($_GET['q'] ?? '');
$filtreAct = $_GET['act'] ?? '';
$where = []; $params = [];
if ($q !== '') {
    $where[] = '(nom LIKE ? OR telephone LIKE ? OR teoudat_zeout LIKE ? OR email LIKE ? OR quartier LIKE ? OR souhait LIKE ?)';
    array_push($params, ...array_fill(0, 6, "%$q%"));
}
if ($filtreAct !== '' && in_array($filtreAct, all_activites(), true)) {
    // inclut aussi les anciens intitulés de la même activité
    $noms = array_merge([$filtreAct], array_keys(ACTIVITES_ANCIENNES, $filtreAct, true));
    $where[] = '(' . implode(' OR ', array_fill(0, count($noms), 'activites LIKE ?')) . ')';
    foreach ($noms as $n) $params[] = '%' . json_encode($n, JSON_UNESCAPED_UNICODE) . '%';
}
$sql = 'SELECT * FROM inscriptions' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

// Export CSV (compatible Excel)
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="moadon-inscriptions-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $acts = all_activites();
    fputcsv($out, array_merge(['N°','Date','Prénom et nom','Téléphone',"Date d'alya",'Teoudat Zeout','E-mail','Quartier','Caisse maladie'], $acts, ['Souhait / idée']), ';');
    foreach ($rows as $r) {
        $ra = activites_de($r['activites']);
        $cols = array_map(fn($a) => in_array($a, $ra, true) ? 'X' : '', $acts);
        fputcsv($out, array_merge([$r['id'],$r['created_at'],$r['nom'],$r['telephone'],$r['date_alya'],$r['teoudat_zeout'],$r['email'],$r['quartier'],$r['caisse']], $cols, [$r['souhait']]), ';');
    }
    exit;
}

// Statistiques par activité (sur toutes les inscriptions)
$total = (int)$pdo->query('SELECT COUNT(*) FROM inscriptions')->fetchColumn();
$stats = array_fill_keys(all_activites(), 0);
foreach ($pdo->query('SELECT activites FROM inscriptions') as $r) {
    foreach (activites_de($r['activites']) as $a) if (isset($stats[$a])) $stats[$a]++;
}
arsort($stats);
$max = max(1, max($stats));
?><!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Admin – Inscriptions Le Moadon</title>
<link rel="stylesheet" href="style.css?v=2">
<style>
.page{max-width:1200px}
.barre{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;margin-bottom:10px}
.barre form{display:flex;gap:8px;flex-wrap:wrap}
.barre input,.barre select{font:inherit;padding:7px 10px;border:1.5px solid var(--ligne);border-radius:8px;background:#fff}
.btn.sm{padding:7px 18px;font-size:.9rem;text-decoration:none}
.btn.gris{background:#8d8d8d}
table{width:100%;border-collapse:collapse;font-size:.9rem}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid var(--ligne);vertical-align:top}
th{background:var(--rose-pale);color:var(--rose-fonce);position:sticky;top:0}
tr:hover td{background:#fff6f9}
.tags span{display:inline-block;background:var(--rose-pale);color:var(--rose-fonce);border-radius:10px;padding:1px 8px;margin:1px 2px;font-size:.8rem}
.del{background:none;border:none;color:#b3261e;cursor:pointer;font-size:1rem}
.stats{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:4px 30px;font-size:.88rem}
.stat{display:flex;align-items:center;gap:8px}
.stat .nom{flex:0 0 210px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.stat .b{height:10px;background:var(--rose);border-radius:5px}
.ok{background:#e8f5e9;border:1px solid #b7dfb9;color:#1b5e20;border-radius:8px;padding:8px 14px;margin-bottom:12px}
.scroll{overflow-x:auto}
</style></head>
<body><div class="page">
  <div class="barre">
    <div><h1 style="font-size:1.8rem;margin:0">Le Moadon – Inscriptions</h1>
      <span class="lieu"><?= $total ?> inscription<?= $total > 1 ? 's' : '' ?> au total</span></div>
    <div><a class="btn sm" href="?<?= h(http_build_query(['q'=>$q,'act'=>$filtreAct,'export'=>1])) ?>">⬇ Export Excel (CSV)</a>
      <a class="btn sm gris" href="?logout=1">Déconnexion</a></div>
  </div>
  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>

  <h2>Intérêt par activité</h2>
  <div class="stats">
    <?php foreach ($stats as $a => $n): ?>
      <div class="stat"><span class="nom" title="<?= h($a) ?>"><?= h($a) ?></span>
        <span class="b" style="width:<?= round(120 * $n / $max) ?>px"></span><strong><?= $n ?></strong></div>
    <?php endforeach; ?>
  </div>

  <h2>Liste des inscriptions</h2>
  <div class="barre">
    <form method="get">
      <input name="q" placeholder="Rechercher (nom, tél., TZ, e-mail…)" value="<?= h($q) ?>">
      <select name="act"><option value="">Toutes les activités</option>
        <?php foreach (all_activites() as $a): ?><option <?= $a === $filtreAct ? 'selected' : '' ?>><?= h($a) ?></option><?php endforeach; ?>
      </select>
      <button class="btn sm">Filtrer</button>
      <?php if ($q !== '' || $filtreAct !== ''): ?><a class="btn sm gris" href="<?= $self ?>">Réinitialiser</a><?php endif; ?>
    </form>
    <span class="lieu"><?= count($rows) ?> résultat(s)</span>
  </div>
  <div class="scroll">
  <table>
    <tr><th>#</th><th>Date</th><th>Nom</th><th>Téléphone</th><th>Alya</th><th>Teoudat Zeout</th><th>E-mail</th><th>Quartier</th><th>Caisse</th><th>Activités</th><th>Souhait</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td style="white-space:nowrap"><?= h(substr($r['created_at'], 0, 16)) ?></td>
        <td><strong><?= h($r['nom']) ?></strong></td>
        <td style="white-space:nowrap"><a href="tel:<?= h($r['telephone']) ?>"><?= h($r['telephone']) ?></a></td>
        <td><?= h($r['date_alya']) ?></td>
        <td><?= h($r['teoudat_zeout']) ?></td>
        <td><?php if ($r['email']): ?><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a><?php endif; ?></td>
        <td><?= h($r['quartier']) ?></td>
        <td><?= h($r['caisse']) ?></td>
        <td class="tags"><?php foreach (activites_de($r['activites']) as $a) echo '<span>' . h($a) . '</span>'; ?></td>
        <td><?= nl2br(h($r['souhait'])) ?></td>
        <td><form method="post" onsubmit="return confirm('Supprimer l\'inscription de <?= h(addslashes($r['nom'])) ?> ?')">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <button class="del" name="delete" value="<?= (int)$r['id'] ?>" title="Supprimer">🗑</button></form></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="12" style="text-align:center;color:#888;padding:30px">Aucune inscription pour le moment.</td></tr><?php endif; ?>
  </table>
  </div>
</div></body></html>
