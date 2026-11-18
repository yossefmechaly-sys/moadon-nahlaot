# Le Moadon – Formulaire d'inscription + admin

## Installation (cPanel > Gestionnaire de fichiers, ou SFTP)
1. Copier le dossier `moadon-nahlaot` dans `public_html/` du compte cPanel de jerusalem-info.com
   → https://jerusalem-info.com/moadon-nahlaot/
2. C'est tout : la base SQLite est créée automatiquement au premier envoi,
   dans `/home/UTILISATEUR/moadon-data/` (hors du site, inaccessible depuis le web).
   Prérequis : PHP ≥ 7.4 avec l'extension pdo_sqlite (activée par défaut sur cPanel/AlmaLinux ;
   sinon WHM > EasyApache 4 > PHP Extensions > pdo_sqlite).

## Admin
https://jerusalem-info.com/moadon-nahlaot/admin.php
Changer le mot de passe :
  php -r 'echo password_hash("NouveauMotDePasse", PASSWORD_DEFAULT), "\n";'
puis remplacer ADMIN_PASSWORD_HASH dans config.php.

## Notification e-mail (optionnel)
Renseigner NOTIFY_EMAIL dans config.php.

## Dépôt Git
`config.php` (qui contient le hash du mot de passe admin) n'est pas versionné.
Pour une nouvelle installation : copier `config.example.php` en `config.php` et y coller le hash.
