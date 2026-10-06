<?php

return [
    // Nombre de jours avant l'échéance à partir duquel on prévient les utilisateurs à la connexion
    'warning_days' => (int) env('SUBSCRIPTION_WARNING_DAYS', 5),

    // Période d'essai offerte à la création d'une entreprise (0 = aucune, l'entreprise est bloquée
    // tant qu'un administrateur n'a pas saisi d'abonnement)
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 30),

    'default_currency' => env('SUBSCRIPTION_CURRENCY', 'XOF'),

    // Contact affiché aux gestionnaires pour renouveler (application mobile)
    'contact' => [
        'name' => env('SUBSCRIPTION_CONTACT_NAME', 'Administrateur Devis'),
        'phone' => env('SUBSCRIPTION_CONTACT_PHONE'),
        'whatsapp' => env('SUBSCRIPTION_CONTACT_WHATSAPP'),
        'payment_methods' => env('SUBSCRIPTION_PAYMENT_METHODS', 'Wave, Orange Money'),
    ],
];
