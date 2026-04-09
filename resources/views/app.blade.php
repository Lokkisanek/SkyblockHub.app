<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <meta name="description" content="SkyblockHub is a Hypixel SkyBlock intelligence dashboard for Bazaar flips, NPC arbitrage, crafting opportunities, profile stats, mayor tracking, and event timers.">
        <meta name="theme-color" content="#0f172a">
        <link rel="icon" type="image/webp" href="{{ asset('favicon.webp') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.webp') }}">
        <meta property="og:site_name" content="SkyblockHub">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ asset('img/logo-white.webp') }}">
        <meta name="twitter:card" content="summary_large_image">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
