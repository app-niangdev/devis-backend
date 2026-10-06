<?php

return [
    // Application Android distribuée hors Play Store : l'app compare sa version à celles-ci au démarrage.
    // À chaque nouvel APK publié, monter MOBILE_LATEST_VERSION (proposition de mise à jour) ;
    // monter MOBILE_MIN_VERSION seulement si les anciennes versions ne doivent plus fonctionner (mise à jour obligatoire).
    'latest_version' => env('MOBILE_LATEST_VERSION', '1.0.0'),
    'min_version' => env('MOBILE_MIN_VERSION', '1.0.0'),

    // Lien de téléchargement de l'APK
    'android_url' => env('MOBILE_ANDROID_URL', 'https://app-devis.niangdev.com/devis.apk'),

    // Nouveautés affichées dans la proposition de mise à jour (facultatif)
    'release_notes' => env('MOBILE_RELEASE_NOTES'),
];
