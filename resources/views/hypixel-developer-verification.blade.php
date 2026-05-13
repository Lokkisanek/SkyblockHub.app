<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hypixel API — site verification · SkyblockHub</title>
    <meta name="robots" content="noindex, follow">
    <style>
        body { font-family: system-ui, sans-serif; background: #0b1220; color: #e2e8f0; line-height: 1.6; margin: 0; padding: 2rem; max-width: 52rem; }
        code { background: #1e293b; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.9em; }
        a { color: #6ee7b7; }
        h1 { font-size: 1.35rem; margin-top: 0; }
        ul { padding-left: 1.25rem; }
    </style>
</head>
<body>
    <h1>SkyblockHub — Hypixel Public API (ownership)</h1>
    <p><strong>Production URL:</strong> <a href="{{ url('/') }}">{{ config('app.url') }}</a></p>
    <p><strong>Minecraft username</strong> associated with this API application (Hypixel account submitting the key request): <strong>Lokkisanecek</strong></p>

    <h2 style="font-size:1.05rem;margin-top:1.75rem;">Verification methods implemented</h2>
    <ul>
        <li>
            <strong>Meta tag (when a token is configured):</strong> the same HTML shell as the rest of the app includes a verification meta tag if <code>HYPIXEL_SITE_VERIFICATION</code> is set in the server environment. View HTML source on any page served through Inertia (e.g. the homepage).
        </li>
        <li>
            <strong>Plain-text file:</strong> if <code>HYPIXEL_SITE_VERIFICATION</code> is non-empty, its value is also served at
            <a href="{{ url('/hypixel-verification.txt') }}"><code>/hypixel-verification.txt</code></a> (for automated checks). If unset, that URL returns 404 until you paste the token from the Hypixel Developer Dashboard into <code>.env</code>.
        </li>
    </ul>

    <p style="margin-top:1.5rem;font-size:0.9rem;color:#94a3b8;">
        This page is static information for reviewers. It does not expose any API key.
    </p>
</body>
</html>
