<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px; color: #374151; }
        .body p { line-height: 1.7; margin: 0 0 16px; }
        .credentials { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 20px 24px; margin: 24px 0; }
        .credentials p { margin: 6px 0; font-size: 15px; }
        .credentials strong { display: inline-block; width: 160px; color: #6b7280; }
        .credentials span { color: #111827; font-weight: 600; }
        .btn-wrap { text-align: center; margin: 32px 0 8px; }
        .btn { display: inline-block; background: #1a56db; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 15px; font-weight: 600; }
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
            <p>Un compte a été créé pour vous sur <strong>{{ config('app.name') }}</strong>. Voici vos identifiants de connexion temporaires :</p>

            <div class="credentials">
                @if($user->email)
                <p><strong>Email :</strong> <span>{{ $user->email }}</span></p>
                @endif
                @if($user->username)
                <p><strong>Nom d'utilisateur :</strong> <span>{{ $user->username }}</span></p>
                @endif
                <p><strong>Téléphone :</strong> <span>{{ $user->phone_one }}</span></p>
                <p><strong>Mot de passe temporaire :</strong> <span>{{ $temporaryPassword }}</span></p>
            </div>

            <p>Pour des raisons de sécurité, vous devez <strong>changer votre mot de passe</strong> dès votre première connexion.</p>

            <div class="btn-wrap">
                <a href="{{ $loginUrl }}" class="btn">Accéder à mon compte</a>
            </div>

            <div class="warning">
                <strong>Important :</strong> Ne partagez jamais vos identifiants. Si vous n'êtes pas à l'origine de cette création de compte, contactez immédiatement votre administrateur.
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.
        </div>
    </div>
</body>
</html>
