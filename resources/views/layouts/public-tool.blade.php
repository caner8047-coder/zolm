<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? 'ZOLM pazaryeri araçları' }}">
    <title>{{ $title ?? 'ZOLM Araçları' }} - ZOLM</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }
        @media (max-width: 768px) {
            button, a, input, select {
                min-height: 44px;
            }
            input, select {
                font-size: 16px;
            }
        }
    </style>
</head>
<body class="min-h-full overflow-x-hidden bg-slate-50 font-sans text-slate-900">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex min-h-16 max-w-[1440px] items-center justify-between gap-4 px-4 lg:px-6">
            <a href="{{ url('/') }}" class="flex items-baseline gap-1.5 text-slate-900">
                <span class="text-xl font-bold">zolm</span>
                <span class="font-mono text-xs text-slate-400">araçlar</span>
            </a>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('mp.pricing-simulator') }}" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Gelişmiş simülatör
                    </a>
                @else
                    <a href="{{ route('login') }}" class="rounded-[6px] border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Giriş
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-[1440px] px-4 py-5 text-xs leading-5 text-slate-500 lg:px-6">
            Sonuçlar bilgilendirme amaçlı tahmindir. Güncel komisyon, vergi ve sözleşme koşullarınızı kontrol edin.
        </div>
    </footer>

    @livewireScripts
</body>
</html>
