<?php
if (!defined('MOADON')) { http_response_code(403); exit; }
require __DIR__ . '/config.php';

const CAISSES = ['Maccabi', 'Clalit', 'Meuhedet', 'Leumit'];

// Groupes d'activités, dans l'ordre d'affichage : colonne de gauche puis colonne de droite
const ACTIVITES_GAUCHE = [
    'Expression et scène' => [
        'Chorale',
        'Théâtre',
        'Atelier du rire',
        'Prise de parole en public',
        'Chant en groupe',
    ],
    'Nature et fleurs' => [
        'Composition florale',
        'Jardinage',
    ],
    'Activités manuelles' => [
        'Peinture sur soie et verre',
        'Dessin et aquarelle',
        'Calligraphie',
        'Sculpture',
        "Peinture à l'huile",
        'Création de bijoux',
    ],
];
const ACTIVITES_DROITE = [
    'Sport et bien-être' => [
        'Yoga',
        'Tai chi',
        'Gymnastique',
        'Entraînement de la mémoire',
    ],
    'Conférences et ateliers' => [
        'Naturopathie et alimentation',
        'Pâtisserie',
        'Conférences',
        'Bible, anglais, arabe, médecine, nutrition',
    ],
    'Culture israélienne' => [
        'Danse folklorique',
        'Histoire juive ancienne et moderne',
        'Bridge et jeux de société',
        'Visites guidées et excursions',
        'Compétences numériques',
        "Cours d'hébreu (Oulpan)",
    ],
];
const ACTIVITES = ACTIVITES_GAUCHE + ACTIVITES_DROITE;

// Anciens intitulés (première version du formulaire) => intitulé actuel
const ACTIVITES_ANCIENNES = [
    'Danses folkloriques israéliennes' => 'Danse folklorique',
    'Dessin / peinture' => 'Dessin et aquarelle',
    'Conférences (Bible, anglais, arabe, médecine, nutrition, etc.)' => 'Bible, anglais, arabe, médecine, nutrition',
];

function all_activites(): array {
    return array_merge(...array_values(ACTIVITES));
}

// Activités d'une inscription, avec les anciens intitulés convertis
function activites_de(?string $json): array {
    $l = json_decode($json ?: '[]', true) ?: [];
    return array_values(array_unique(array_map(fn($a) => ACTIVITES_ANCIENNES[$a] ?? $a, $l)));
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = DATA_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        // Repli : dossier protégé à l'intérieur de l'application
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
    }
    if (strpos(realpath($dir), realpath(__DIR__)) === 0 && !file_exists($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    $pdo = new PDO('sqlite:' . $dir . '/moadon.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('CREATE TABLE IF NOT EXISTS inscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        created_at TEXT NOT NULL,
        nom TEXT NOT NULL,
        telephone TEXT NOT NULL,
        date_alya TEXT,
        teoudat_zeout TEXT,
        quartier TEXT,
        caisse TEXT,
        activites TEXT,
        souhait TEXT,
        ip TEXT
    )');
    // Migration : colonne email ajoutée le 19/11/2026
    $cols = array_column($pdo->query('PRAGMA table_info(inscriptions)')->fetchAll(), 'name');
    if (!in_array('email', $cols, true)) $pdo->exec('ALTER TABLE inscriptions ADD COLUMN email TEXT');
    return $pdo;
}

// Vérification du chiffre de contrôle du Teoudat Zeout (9 chiffres)
function tz_valide(string $tz): bool {
    if (!preg_match('/^\d{5,9}$/', $tz)) return false;
    $tz = str_pad($tz, 9, '0', STR_PAD_LEFT);
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $n = (int)$tz[$i] * (($i % 2) + 1);
        $sum += $n > 9 ? $n - 9 : $n;
    }
    return $sum % 10 === 0;
}

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_name('moadon_sess');
        session_start();
    }
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function csrf_ok(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

function flowers_svg(string $cls = ''): string {
    // Bouquet décoratif (cercles colorés façon fleurs)
    $f = function ($x, $y, $c) {
        $p = '';
        foreach ([[0,-9],[9,0],[0,9],[-9,0],[6,-6],[6,6],[-6,6],[-6,-6]] as $i => $d) {
            if ($i > 3) break;
            $p .= "<circle cx='" . ($x + $d[0]) . "' cy='" . ($y + $d[1]) . "' r='7' fill='$c'/>";
        }
        return $p . "<circle cx='$x' cy='$y' r='4.5' fill='#fbd96a'/>";
    };
    $s = $f(22, 22, '#f4a9c4') . $f(48, 30, '#fbc4d4') . $f(30, 52, '#f8c3a8') . $f(58, 58, '#f4a9c4') . $f(72, 34, '#dbe5f3');
    return "<svg class='flowers $cls' viewBox='0 0 90 80' aria-hidden='true'>$s</svg>";
}
