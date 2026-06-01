<?php
session_start();
require_once __DIR__ . '/includes/auth.php'; // Only if you want it to be protected, let's allow everyone or just keep session
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
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; }
        .team-card { transition: all 0.3s ease; }
        .team-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    </style>
</head>
<body>
    <div class="min-h-full flex flex-col">
        <?php 
        // We can include nav if needed, or just a simple header
        $activePage = 'echo-team';
        if (isset($_SESSION['role'])) {
            require_once __DIR__ . '/includes/nav.php';
        } else {
            // Simple header for non-logged in
            echo '<header class="bg-white shadow-sm p-4 text-center"><h1 class="text-2xl font-bold text-indigo-600">Echo Team</h1></header>';
        }
        ?>

        <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
            <div class="text-center mb-16">
                <h2 class="text-base text-indigo-600 font-semibold tracking-wide uppercase">تعرف علينا</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">فريق ايكو تيم - Echo Team</p>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 mx-auto">
                    نحن فريق متكامل من المطورين والمصممين الشغوفين ببناء حلول تقنية مبتكرة لتسهيل إدارة الأنظمة الطبية.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                
                <!-- Member 1 -->
                <div class="team-card bg-white rounded-2xl p-8 text-center shadow-md border border-gray-100">
                    <div class="w-32 h-32 mx-auto rounded-full bg-indigo-100 flex items-center justify-center mb-6 text-indigo-500">
                        <i class="bi bi-person-circle text-6xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">عضو الفريق 1</h3>
                    <p class="text-indigo-600 font-medium mb-4">Full Stack Developer</p>
                    <p class="text-gray-500 text-sm mb-6">مطور ويب متكامل متخصص في بناء الأنظمة المعقدة.</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="text-gray-400 hover:text-indigo-600 transition-colors"><i class="bi bi-github text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors"><i class="bi bi-linkedin text-xl"></i></a>
                        <a href="mailto:email@example.com" class="text-gray-400 hover:text-rose-600 transition-colors"><i class="bi bi-envelope-fill text-xl"></i></a>
                    </div>
                </div>

                <!-- Member 2 -->
                <div class="team-card bg-white rounded-2xl p-8 text-center shadow-md border border-gray-100">
                    <div class="w-32 h-32 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-6 text-emerald-500">
                        <i class="bi bi-person-circle text-6xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">عضو الفريق 2</h3>
                    <p class="text-emerald-600 font-medium mb-4">UI/UX Designer</p>
                    <p class="text-gray-500 text-sm mb-6">مصمم واجهات المستخدم وتجربة المستخدم لضمان سهولة الاستخدام.</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="text-gray-400 hover:text-emerald-600 transition-colors"><i class="bi bi-behance text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors"><i class="bi bi-linkedin text-xl"></i></a>
                        <a href="mailto:email@example.com" class="text-gray-400 hover:text-rose-600 transition-colors"><i class="bi bi-envelope-fill text-xl"></i></a>
                    </div>
                </div>

                <!-- Member 3 -->
                <div class="team-card bg-white rounded-2xl p-8 text-center shadow-md border border-gray-100">
                    <div class="w-32 h-32 mx-auto rounded-full bg-rose-100 flex items-center justify-center mb-6 text-rose-500">
                        <i class="bi bi-person-circle text-6xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">عضو الفريق 3</h3>
                    <p class="text-rose-600 font-medium mb-4">Mobile App Developer</p>
                    <p class="text-gray-500 text-sm mb-6">مطور تطبيقات الهواتف الذكية ومسؤول عن تكامل النظام.</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="text-gray-400 hover:text-rose-600 transition-colors"><i class="bi bi-github text-xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors"><i class="bi bi-linkedin text-xl"></i></a>
                        <a href="mailto:email@example.com" class="text-gray-400 hover:text-rose-600 transition-colors"><i class="bi bi-envelope-fill text-xl"></i></a>
                    </div>
                </div>

            </div>
        </main>
    </div>
</body>
</html>
