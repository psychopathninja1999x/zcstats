<!DOCTYPE html>
<html lang="{{ match (app()->getLocale()) { 'tl' => 'fil', 'gly' => 'en-PH', default => str_replace('_', '-', app()->getLocale()) } }}">
<head>
    <meta charset="utf-8">
    <script>
        (function () {
            try {
                var k = 'zc-theme';
                var r = document.documentElement;
                var s = localStorage.getItem(k);
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var dark = s === 'dark' ? true : s === 'light' ? false : prefersDark;
                r.classList.toggle('dark', dark);
                r.style.colorScheme = dark ? 'dark' : 'light';
            } catch (e) { /* ignore */ }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('zcstats.meta_description') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" href="{{ asset('images/zcstatslogo.png') }}" type="image/png" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('images/zcstatslogo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body
    class="text-on-surface flex flex-col min-h-screen pb-40 md:pb-44"
    data-ptr-pull="{{ __('zcstats.ptr_pull') }}"
    data-ptr-release="{{ __('zcstats.ptr_release') }}"
>
    @yield('body')
</body>
</html>
