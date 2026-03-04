<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Giriş - ZOLM</title>
    
    <!-- Tailwind CDN Fallback (development) -->
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
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="h-full bg-gray-50">
    <div class="min-h-full flex flex-col items-center justify-center px-4">
        <!-- Domain -->
        <div class="absolute top-8 right-8 text-sm text-gray-400">
            /ai.zemuretim.online
        </div>

        <!-- Logo -->
        <h1 class="text-5xl font-bold text-gray-900 tracking-tight mb-12">
            zolm
        </h1>

        <!-- Login Card -->
        <div class="w-full max-w-md bg-white rounded-lg border border-gray-200 shadow-sm p-8">
            <h2 class="text-xl font-semibold text-center text-gray-900 mb-8">
                Giriş Yap
            </h2>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-600"><?php echo e($errors->first()); ?></p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <form method="POST" action="<?php echo e(route('login')); ?>" class="space-y-6">
                <?php echo csrf_field(); ?>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                        E-posta
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo e(old('email')); ?>"
                        required 
                        autofocus
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-shadow"
                        placeholder="ornek@email.com"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        Şifre
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900 focus:border-transparent transition-shadow"
                        placeholder="••••••••"
                    >
                </div>

                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="remember" 
                        name="remember"
                        class="w-4 h-4 border-gray-300 rounded text-gray-900 focus:ring-gray-900"
                    >
                    <label for="remember" class="ml-2 text-sm text-gray-600">
                        Beni hatırla
                    </label>
                </div>

                <button 
                    type="submit"
                    class="w-full py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                >
                    Giriş Yap
                </button>
            </form>
        </div>

        <!-- AI Animation Text -->
        <div class="mt-12 flex items-center space-x-2 text-sm text-gray-400">
            <div class="flex space-x-1">
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-pulse"></span>
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></span>
                <span class="w-2 h-2 bg-gray-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></span>
            </div>
            <span># arka planda ai motor çalışıyor...</span>
        </div>
    </div>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/auth/login.blade.php ENDPATH**/ ?>