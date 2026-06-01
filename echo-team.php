<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Echo Team - فريق العمل</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        body { 
            font-family: 'Tajawal', sans-serif; 
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        .team-card { 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .team-card:hover { 
            transform: translateY(-8px) scale(1.02); 
            box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.1), 0 15px 15px -5px rgba(0, 0, 0, 0.04); 
            background: rgba(255, 255, 255, 0.95);
        }
        .avatar-container {
            position: relative;
        }
        .avatar-container::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
            z-index: -1;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .team-card:hover .avatar-container::after {
            opacity: 1;
        }
        .gradient-text {
            background: linear-gradient(135deg, #4f46e5, #9333ea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="flex flex-col">
    <?php 
    $activePage = 'echo-team';
    if (isset($_SESSION['role'])) {
        require_once __DIR__ . '/includes/nav.php';
    }
    ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 w-full">
        <div class="text-center mb-20 relative">
            <h2 class="text-lg font-bold tracking-widest uppercase text-indigo-500 mb-2">Get to Know Us</h2>
            <p class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mb-6">
                <span class="gradient-text">Echo Team</span>
            </p>
            <div class="h-1 w-24 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 mx-auto rounded-full"></div>
            <p class="mt-8 max-w-2xl text-xl text-slate-600 mx-auto leading-relaxed">
                An elite group of developers and designers coming together to build an integrated medical system that combines high performance, data security, and stunning design.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
            
            <?php
            $team = [
                [
                    'name' => 'Zeyad Yasser',
                    'role' => 'Full Stack & Team Leader',
                    'desc' => 'Team leader, full stack developer, system architect, and general supervisor.',
                    'color' => 'indigo',
                    'icon' => 'bi-person-badge-fill'
                ],
                [
                    'name' => 'AbdEl-Rahman Gamal',
                    'role' => 'Back-End Developer & DB',
                    'desc' => 'Database and backend development expert, ensuring efficient data processing.',
                    'color' => 'emerald',
                    'icon' => 'bi-database-fill-gear'
                ],
                [
                    'name' => 'Amnaa Mohamed',
                    'role' => 'Back-End Developer & DB',
                    'desc' => 'Backend and database developer, specializing in securing systems and database design.',
                    'color' => 'teal',
                    'icon' => 'bi-server'
                ],
                [
                    'name' => 'Nada Taha',
                    'role' => 'Front-End Developer',
                    'desc' => 'Frontend developer, working on transforming designs into interactive user experiences.',
                    'color' => 'rose',
                    'icon' => 'bi-window-sidebar'
                ],
                [
                    'name' => 'Aya Shapan',
                    'role' => 'Front-End Developer',
                    'desc' => 'Frontend developer, attentive to UI details and building responsive components.',
                    'color' => 'pink',
                    'icon' => 'bi-palette-fill'
                ],
                [
                    'name' => 'Mohamed Ahmed',
                    'role' => 'Front-End & Documentation',
                    'desc' => 'Frontend developer and documentation lead, ensuring code integration and clear user guides.',
                    'color' => 'blue',
                    'icon' => 'bi-file-earmark-code-fill'
                ]
            ];

            foreach ($team as $member) {
                $color = $member['color'];
            ?>
            <div class="team-card rounded-3xl p-8 text-center">
                <div class="avatar-container w-28 h-28 mx-auto bg-white rounded-full flex items-center justify-center mb-6 shadow-md border-4 border-<?= $color ?>-50 text-<?= $color ?>-500">
                    <i class="bi <?= $member['icon'] ?> text-5xl"></i>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-800 mb-2"><?= $member['name'] ?></h3>
                <div class="inline-block bg-<?= $color ?>-100 text-<?= $color ?>-700 px-4 py-1.5 rounded-full text-sm font-bold mb-5 shadow-sm">
                    <?= $member['role'] ?>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed mb-8">
                    <?= $member['desc'] ?>
                </p>
                
                <div class="flex justify-center gap-5 pt-4 border-t border-slate-200/60">
                    <a href="#" class="text-slate-400 hover:text-[#181717] transition-all hover:scale-110" title="GitHub"><i class="bi bi-github text-xl"></i></a>
                    <a href="#" class="text-slate-400 hover:text-[#0A66C2] transition-all hover:scale-110" title="LinkedIn"><i class="bi bi-linkedin text-xl"></i></a>
                    <a href="#" class="text-slate-400 hover:text-[#EA4335] transition-all hover:scale-110" title="Email"><i class="bi bi-envelope-at-fill text-xl"></i></a>
                </div>
            </div>
            <?php } ?>

        </div>
    </main>

    <?php if (!isset($_SESSION['role'])): ?>
    <footer style="margin-top: auto; padding: 1.5rem; text-align: center; background-color: #1e293b; color: #cbd5e1; font-size: 0.9rem; font-family: 'Tajawal', sans-serif;">
        <p style="margin: 0; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 500;">
            All rights reserved &copy; <?= date('Y') ?> 
            <span style="color: #818cf8; font-weight: 800;">Echo Team</span>
            <i class="bi bi-suit-heart-fill text-rose-500"></i>
        </p>
    </footer>
    <?php endif; ?>
</body>
</html>
