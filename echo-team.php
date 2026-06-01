<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Echo Team - About Us</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; }
        .team-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.5); backdrop-filter: blur(10px); }
        .team-card:hover { transform: translateY(-8px); box-shadow: 0 25px 30px -5px rgba(79, 70, 229, 0.15), 0 15px 15px -5px rgba(79, 70, 229, 0.08); border-color: rgba(79, 70, 229, 0.3); }
        .avatar-container { position: relative; }
        .avatar-container::after {
            content: ''; position: absolute; inset: -4px; border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
            z-index: -1; opacity: 0; transition: opacity 0.3s ease;
        }
        .team-card:hover .avatar-container::after { opacity: 1; }
        .gradient-text { background: linear-gradient(135deg, #4f46e5, #9333ea); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body>
    <div class="min-h-full flex flex-col">
        <?php 
        if (isset($_SESSION['role'])) {
            $activePage = 'echo-team';
            require_once __DIR__ . '/includes/nav.php';
        } else {
            echo '<header class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-50 p-4 border-b border-gray-100">
                    <div class="max-w-7xl mx-auto flex justify-between items-center">
                        <h1 class="text-2xl font-black gradient-text tracking-tight flex items-center gap-2">
                            <i class="bi bi-layers-half text-indigo-600"></i> Echo Team
                        </h1>
                        <a href="/index.php" class="text-sm font-semibold text-gray-500 hover:text-indigo-600 transition-colors">Back to Home</a>
                    </div>
                  </header>';
        }
        ?>

        <main class="flex-grow w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-20">
                <span class="px-4 py-1.5 rounded-full bg-indigo-50 text-indigo-600 font-bold text-sm tracking-widest uppercase mb-4 inline-block ring-1 ring-indigo-200">Meet The Minds</span>
                <h2 class="mt-4 text-4xl leading-tight font-black tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">
                    The <span class="gradient-text">Echo Team</span>
                </h2>
                <p class="mt-6 max-w-2xl text-lg text-gray-500 mx-auto font-medium">
                    We are a group of passionate developers dedicated to crafting seamless, innovative, and robust digital solutions for modern healthcare management.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                
                <!-- Zeyad Yasser -->
                <div class="team-card bg-white/70 rounded-3xl p-8 text-center shadow-lg" dir="ltr">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-indigo-50 flex items-center justify-center mb-6 text-indigo-600 border-4 border-white shadow-inner">
                        <i class="bi bi-person-fill text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Zeyad Yasser</h3>
                    <p class="text-indigo-600 font-bold text-sm uppercase tracking-wider mb-6">Full Stack & Team Leader</p>
                    <div class="flex justify-center gap-5">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-900 hover:text-white transition-all shadow-sm"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- AbdEl-Rahman Gamal -->
                <div class="team-card bg-white/70 rounded-3xl p-8 text-center shadow-lg" dir="ltr">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-blue-50 flex items-center justify-center mb-6 text-blue-600 border-4 border-white shadow-inner">
                        <i class="bi bi-person-fill text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">AbdEl-Rahman Gamal</h3>
                    <p class="text-blue-600 font-bold text-sm uppercase tracking-wider mb-6">Back-End Developer & DB</p>
                    <div class="flex justify-center gap-5">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-900 hover:text-white transition-all shadow-sm"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Amnaa Mohamed -->
                <div class="team-card bg-white/70 rounded-3xl p-8 text-center shadow-lg" dir="ltr">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-purple-50 flex items-center justify-center mb-6 text-purple-600 border-4 border-white shadow-inner">
                        <i class="bi bi-person-fill text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Amnaa Mohamed</h3>
                    <p class="text-purple-600 font-bold text-sm uppercase tracking-wider mb-6">Back-End Developer & DB</p>
                    <div class="flex justify-center gap-5">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-900 hover:text-white transition-all shadow-sm"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Nada Taha -->
                <div class="team-card bg-white/70 rounded-3xl p-8 text-center shadow-lg" dir="ltr">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-rose-50 flex items-center justify-center mb-6 text-rose-600 border-4 border-white shadow-inner">
                        <i class="bi bi-person-fill text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Nada Taha</h3>
                    <p class="text-rose-600 font-bold text-sm uppercase tracking-wider mb-6">Front-End Developer</p>
                    <div class="flex justify-center gap-5">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-900 hover:text-white transition-all shadow-sm"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Aya Shapan -->
                <div class="team-card bg-white/70 rounded-3xl p-8 text-center shadow-lg" dir="ltr">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-pink-50 flex items-center justify-center mb-6 text-pink-600 border-4 border-white shadow-inner">
                        <i class="bi bi-person-fill text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Aya Shapan</h3>
                    <p class="text-pink-600 font-bold text-sm uppercase tracking-wider mb-6">Front-End Developer</p>
                    <div class="flex justify-center gap-5">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-900 hover:text-white transition-all shadow-sm"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Mohamed Ahmed -->
                <div class="team-card bg-white/70 rounded-3xl p-8 text-center shadow-lg" dir="ltr">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-emerald-50 flex items-center justify-center mb-6 text-emerald-600 border-4 border-white shadow-inner">
                        <i class="bi bi-person-fill text-6xl"></i>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Mohamed Ahmed</h3>
                    <p class="text-emerald-600 font-bold text-sm uppercase tracking-wider mb-6">Front-End & Documentation</p>
                    <div class="flex justify-center gap-5">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-gray-900 hover:text-white transition-all shadow-sm"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
