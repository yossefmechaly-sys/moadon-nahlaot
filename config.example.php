<?php
// ============================================================
//  Le Moadon – Femmes Francophones Nahlaot : configuration
// ============================================================
if (!defined('MOADON')) { http_response_code(403); exit; }

// Mot de passe de l'admin (hash bcrypt).
// Pour le changer, sur le serveur :
//   php -r 'echo password_hash("NouveauMotDePasse", PASSWORD_DEFAULT), "\n";'
// puis collez le résultat ci-dessous.
define('ADMIN_PASSWORD_HASH', 'COLLER_ICI_LE_HASH_DU_MOT_DE_PASSE');

// Dossier où est stockée la base SQLite.
// Par défaut : HORS de public_html (ex. /home/USER/moadon-data), donc inaccessible depuis le web.
define('DATA_DIR', dirname(__DIR__, 2) . '/moadon-data');

// Optionnel : adresse e-mail qui reçoit une notification à chaque inscription ('' = désactivé)
define('NOTIFY_EMAIL', '');
