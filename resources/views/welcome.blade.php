<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Learning Platform</title>

    {{-- The iL mark from the login screen. "?v=" busts browsers' long-lived favicon cache. --}}
    <link rel="icon" href="/favicon.ico?v=2" sizes="48x48">
    <link rel="icon" href="/favicon.png?v=2" type="image/png" sizes="192x192">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<div id="app"></div>

</body>
</html>