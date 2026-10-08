<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $schoolName }}</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f6f6f4; color: #1f2933; }
        main { max-width: 32rem; margin: 0 auto; padding: 4rem 1.5rem; }
        h1 { margin: 0 0 1rem; font-size: 1.5rem; }
        p { line-height: 1.6; margin: 0 0 1rem; }
        .contact { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #d9d9d4; font-size: 0.95rem; }
        .contact p { margin: 0 0 0.25rem; }
        a { color: #1f4e79; }
        .button { display: inline-block; margin-top: 0.75rem; padding: 0.55rem 1rem; background: #1f4e79; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <h1>{{ $schoolName }}</h1>
        <p>{{ $message }}</p>

        @if ($providerName !== '' || $providerEmail || $providerPhone || $whatsappUrl)
            <div class="contact">
                @if ($providerName !== '')
                    <p><strong>{{ $providerName }}</strong></p>
                @endif
                @if ($providerEmail)
                    <p>Email: <a href="mailto:{{ $providerEmail }}">{{ $providerEmail }}</a></p>
                @endif
                @if ($providerPhone)
                    <p>Telepon: {{ $providerPhone }}</p>
                @endif
                @if ($whatsappUrl)
                    <a class="button" href="{{ $whatsappUrl }}" rel="noopener">Hubungi via WhatsApp</a>
                @endif
            </div>
        @endif
    </main>
</body>
</html>
