<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation de mot de passe — {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px; color: #374151; }
        .body p { line-height: 1.7; margin: 0 0 16px; }
        .code-wrap { text-align: center; margin: 32px 0; }
        .code { display: inline-block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px 32px; font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #1a56db; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 4px; margin-top: 24px; font-size: 13px; color: #92400e; }
        .footer { background: #f9fafb; padding: 24px 40px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>
        <div class="body">
            <p>Bonjour <strong>{{ $user->full_name }}</strong>,</p>
            <p>Vous avez demandé la réinitialisation de votre mot de passe. Utilisez le code ci-dessous pour poursuivre :</p>

            <div class="code-wrap">
                <span class="code">{{ $code }}</span>
            </div>

            <p>Ce code expire dans 15 minutes.</p>

            <div class="warning">
                <strong>Important :</strong> Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail et votre mot de passe restera inchangé.
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.
        </div>
    </div>
</body>
</html>
