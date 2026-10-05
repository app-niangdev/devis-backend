<?php

return [
    // Durée de validité d'un code (secondes)
    'ttl' => (int) env('OTP_TTL', 300),

    // Nombre d'essais avant invalidation du code
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),

    // Délai minimal entre deux envois (secondes) et nombre de renvois autorisés
    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),
    'max_resend' => (int) env('OTP_MAX_RESEND', 3),

    // Durée du jeton remis après un code valide pour choisir le mot de passe (secondes)
    'reset_ttl' => (int) env('OTP_RESET_TTL', 600),

    // Nom affiché dans les messages WhatsApp
    'app_name' => env('OTP_APP_NAME', env('APP_NAME', 'Devis')),
];
