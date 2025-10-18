<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-950 text-slate-200">
    <div class="space-y-4 text-center">
        <h1 class="text-4xl font-semibold">403</h1>
        <p class="text-sm text-slate-400">Bu paneli görüntüleme yetkiniz bulunmuyor.</p>
        <a href="{{ route('login') }}" class="inline-flex items-center rounded-full bg-sky-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-400">Login sayfasına dön</a>
    </div>
</body>
</html>
