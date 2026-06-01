<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Echo Team - HMS Project Overview</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" href="/assets/images/echol.png">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f8fafc; color: #0f172a; overflow-x: hidden; }
        
        .gradient-text { 
            background: linear-gradient(135deg, #4f46e5, #9333ea, #ec4899); 
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
        }
        
        .hero-bg {
            background-image: radial-gradient(circle at top right, rgba(99, 102, 241, 0.1), transparent 40%),
                              radial-gradient(circle at bottom left, rgba(236, 72, 153, 0.1), transparent 40%);
        }

        .glass-nav {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .feature-card, .team-card { 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            border: 1px solid rgba(255,255,255,0.5); 
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px); 
        }
        .feature-card:hover, .team-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 25px 30px -5px rgba(79, 70, 229, 0.1), 0 15px 15px -5px rgba(79, 70, 229, 0.05); 
            border-color: rgba(79, 70, 229, 0.2); 
        }

        .avatar-container { position: relative; }
        .avatar-container::after {
            content: ''; position: absolute; inset: -4px; border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7, #ec4899);
            z-index: -1; opacity: 0; transition: opacity 0.3s ease;
        }
        .team-card:hover .avatar-container::after { opacity: 1; }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-15px); }
            100% { transform: translateY(0px); }
        }
        .floating-icon { animation: float 6s ease-in-out infinite; }
    </style>
</head>
<body class="antialiased selection:bg-indigo-500 selection:text-white hero-bg">

    <!-- Standalone Navigation -->
    <nav class="fixed top-0 w-full z-50 glass-nav transition-all duration-300" id="navbar">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center text-white font-bold text-xl shadow-lg">
                        <i class="bi bi-hospital"></i>
                    </div>
                    <span class="font-black text-2xl tracking-tight text-gray-900">HMS <span class="text-indigo-600">Pro</span></span>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#about" class="text-gray-600 hover:text-indigo-600 font-semibold transition-colors">About Project</a>
                    <a href="#features" class="text-gray-600 hover:text-indigo-600 font-semibold transition-colors">Key Features</a>
                    <a href="#team" class="text-gray-600 hover:text-indigo-600 font-semibold transition-colors">Echo Team</a>
                </div>

                <div class="flex items-center gap-4">
                    <?php if(isset($_SESSION['role'])): ?>
                        <a href="/index.php" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl text-white bg-gray-900 hover:bg-black shadow-lg hover:shadow-xl transition-all active:scale-95">
                            <i class="bi bi-grid-1x2-fill mr-2"></i> Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/index.php" class="inline-flex items-center justify-center px-6 py-2.5 border border-transparent text-sm font-bold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 shadow-lg hover:shadow-indigo-500/30 transition-all active:scale-95">
                            Sign In
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden" id="hero">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="text-center max-w-4xl mx-auto">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 text-indigo-600 font-bold text-sm tracking-wide uppercase mb-8 ring-1 ring-indigo-200/50 shadow-sm">
                    <span class="relative flex h-2 w-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span></span>
                    Next-Gen Hospital Management
                </div>
                <h1 class="text-5xl md:text-7xl font-black tracking-tight text-gray-900 mb-8 leading-tight">
                    Transforming Healthcare with <span class="gradient-text">Smart Technology</span>
                </h1>
                <p class="text-xl md:text-2xl text-gray-500 mb-10 leading-relaxed font-medium">
                    A comprehensive, AI-powered system designed to streamline clinical workflows, enhance patient care, and empower medical professionals.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#about" class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold rounded-2xl text-white bg-gray-900 hover:bg-black shadow-xl hover:-translate-y-1 transition-all">
                        Discover the Project <i class="bi bi-arrow-down ml-2"></i>
                    </a>
                    <a href="#team" class="inline-flex items-center justify-center px-8 py-4 text-lg font-bold rounded-2xl text-gray-900 bg-white border-2 border-gray-100 hover:border-gray-200 hover:bg-gray-50 shadow-sm hover:-translate-y-1 transition-all">
                        Meet Echo Team
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- About Project Section -->
    <section id="about" class="py-24 bg-white relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div>
                    <h2 class="text-4xl font-black text-gray-900 mb-6 tracking-tight">The Vision Behind <span class="text-indigo-600">HMS Pro</span></h2>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">
                        Healthcare systems face immense pressure daily. Our vision was to build a <strong>Single Page Application (SPA)</strong> architecture that acts as a central nervous system for hospitals and clinics. It bridges the gap between administrative tasks and core medical duties.
                    </p>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        By integrating modern web technologies, AI assistance, and real-time data handling, HMS Pro eliminates bottlenecks, reduces patient waiting times, and ensures doctors have immediate access to structured medical histories.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-center gap-3 text-gray-800 font-bold">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center"><i class="bi bi-check-lg"></i></div> Fast, seamless SPA experience
                        </li>
                        <li class="flex items-center gap-3 text-gray-800 font-bold">
                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center"><i class="bi bi-check-lg"></i></div> Highly secure data encryption
                        </li>
                        <li class="flex items-center gap-3 text-gray-800 font-bold">
                            <div class="w-8 h-8 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center"><i class="bi bi-check-lg"></i></div> Scalable and fully responsive
                        </li>
                    </ul>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-tr from-indigo-500 to-purple-500 rounded-3xl transform rotate-3 scale-105 opacity-20 blur-xl"></div>
                    <div class="bg-gray-900 rounded-3xl p-8 relative shadow-2xl overflow-hidden">
                        <div class="flex items-center justify-between mb-8 border-b border-gray-800 pb-4">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                                <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                                <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            </div>
                            <span class="text-gray-500 text-xs font-mono">system_architecture.js</span>
                        </div>
                        <div class="font-mono text-sm text-gray-300 space-y-2">
                            <p><span class="text-purple-400">const</span> <span class="text-blue-400">system</span> = {</p>
                            <p class="pl-4">frontend: <span class="text-emerald-400">'Tailwind CSS, Vanilla JS SPA'</span>,</p>
                            <p class="pl-4">backend: <span class="text-emerald-400">'PHP 8.x, MySQL'</span>,</p>
                            <p class="pl-4">features: [</p>
                            <p class="pl-8 text-amber-300">'AI Medical Triage',</p>
                            <p class="pl-8 text-amber-300">'Real-time Queues',</p>
                            <p class="pl-8 text-amber-300">'Role-based Access'</p>
                            <p class="pl-4">],</p>
                            <p class="pl-4">status: <span class="text-emerald-400">'Deployed & Optimised'</span></p>
                            <p>}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-slate-50 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-4xl font-black text-gray-900 mb-6 tracking-tight">Core <span class="text-indigo-600">Features</span></h2>
                <p class="text-lg text-gray-500 font-medium">What makes HMS Pro stand out as a comprehensive medical management solution.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card rounded-3xl p-8">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-2xl mb-6 shadow-inner">
                        <i class="bi bi-robot"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">AI Patient Triage</h3>
                    <p class="text-gray-600 leading-relaxed">
                        An intelligent chatbot that analyzes patient symptoms and automatically recommends the correct medical specialization, saving administrative time.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card rounded-3xl p-8">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl mb-6 shadow-inner">
                        <i class="bi bi-display"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Live Queue Board</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Real-time patient flow management with live screens for waiting areas, distinguishing between normal and urgent cases instantly.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card rounded-3xl p-8">
                    <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-600 flex items-center justify-center text-2xl mb-6 shadow-inner">
                        <i class="bi bi-file-earmark-medical"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Smart Reports</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Doctors can generate highly structured medical reports and prescriptions using AI, ensuring clear communication and maintaining thorough clinical histories.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card rounded-3xl p-8">
                    <div class="w-14 h-14 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-2xl mb-6 shadow-inner">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Multi-Level Security</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Strict role-based access control (Super Admin, Admin, Doctor, Receptionist, Patient) with encrypted data handling and CSRF protection.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card rounded-3xl p-8">
                    <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center text-2xl mb-6 shadow-inner">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Fully Responsive</h3>
                    <p class="text-gray-600 leading-relaxed">
                        A mobile-first approach ensuring that doctors can write reports on tablets, receptionists can manage queues on desktops, and patients can book via phones.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card rounded-3xl p-8">
                    <div class="w-14 h-14 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-2xl mb-6 shadow-inner">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">Real-time Notifications</h3>
                    <p class="text-gray-600 leading-relaxed">
                        Instant alerts across the system for new appointments, status updates, and critical system changes to keep the entire staff synchronized.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="py-24 relative overflow-hidden">
        <div class="absolute top-0 right-0 -mt-20 -mr-20 w-80 h-80 bg-indigo-100 rounded-full blur-3xl opacity-50 z-0"></div>
        <div class="absolute bottom-0 left-0 -mb-20 -ml-20 w-80 h-80 bg-purple-100 rounded-full blur-3xl opacity-50 z-0"></div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-20">
                <span class="px-4 py-1.5 rounded-full bg-purple-50 text-purple-600 font-bold text-sm tracking-widest uppercase mb-4 inline-block ring-1 ring-purple-200">The Creators</span>
                <h2 class="mt-4 text-4xl leading-tight font-black tracking-tight text-gray-900 sm:text-5xl">
                    Meet <span class="gradient-text">Echo Team</span>
                </h2>
                <p class="mt-6 max-w-2xl text-lg text-gray-500 mx-auto font-medium">
                    The talented individuals who brought HMS Pro to life through collaboration and technical excellence.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                
                <!-- Zeyad Yasser -->
                <div class="team-card rounded-3xl p-8 text-center shadow-lg bg-white/60">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-indigo-50 flex items-center justify-center mb-6 text-indigo-600 border-4 border-white shadow-inner">
                        <span class="font-black text-4xl">ZY</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Zeyad Yasser</h3>
                    <p class="text-indigo-600 font-bold text-sm uppercase tracking-wider mb-6">Full Stack & Team Leader</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-gray-900 shadow hover:shadow-md transition-all"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-blue-600 shadow hover:shadow-md transition-all"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- AbdEl-Rahman Gamal -->
                <div class="team-card rounded-3xl p-8 text-center shadow-lg bg-white/60">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-blue-50 flex items-center justify-center mb-6 text-blue-600 border-4 border-white shadow-inner">
                        <span class="font-black text-4xl">AG</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">AbdEl-Rahman Gamal</h3>
                    <p class="text-blue-600 font-bold text-sm uppercase tracking-wider mb-6">Back-End Developer & DB</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-gray-900 shadow hover:shadow-md transition-all"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-blue-600 shadow hover:shadow-md transition-all"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Amnaa Mohamed -->
                <div class="team-card rounded-3xl p-8 text-center shadow-lg bg-white/60">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-purple-50 flex items-center justify-center mb-6 text-purple-600 border-4 border-white shadow-inner">
                        <span class="font-black text-4xl">AM</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Amnaa Mohamed</h3>
                    <p class="text-purple-600 font-bold text-sm uppercase tracking-wider mb-6">Back-End Developer & DB</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-gray-900 shadow hover:shadow-md transition-all"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-blue-600 shadow hover:shadow-md transition-all"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Nada Taha -->
                <div class="team-card rounded-3xl p-8 text-center shadow-lg bg-white/60">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-rose-50 flex items-center justify-center mb-6 text-rose-600 border-4 border-white shadow-inner">
                        <span class="font-black text-4xl">NT</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Nada Taha</h3>
                    <p class="text-rose-600 font-bold text-sm uppercase tracking-wider mb-6">Front-End Developer</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-gray-900 shadow hover:shadow-md transition-all"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-blue-600 shadow hover:shadow-md transition-all"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Aya Shapan -->
                <div class="team-card rounded-3xl p-8 text-center shadow-lg bg-white/60">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-pink-50 flex items-center justify-center mb-6 text-pink-600 border-4 border-white shadow-inner">
                        <span class="font-black text-4xl">AS</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Aya Shapan</h3>
                    <p class="text-pink-600 font-bold text-sm uppercase tracking-wider mb-6">Front-End Developer</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-gray-900 shadow hover:shadow-md transition-all"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-blue-600 shadow hover:shadow-md transition-all"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

                <!-- Mohamed Ahmed -->
                <div class="team-card rounded-3xl p-8 text-center shadow-lg bg-white/60">
                    <div class="avatar-container w-32 h-32 mx-auto rounded-full bg-emerald-50 flex items-center justify-center mb-6 text-emerald-600 border-4 border-white shadow-inner">
                        <span class="font-black text-4xl">MA</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900 mb-1 tracking-tight">Mohamed Ahmed</h3>
                    <p class="text-emerald-600 font-bold text-sm uppercase tracking-wider mb-6">Front-End & Documentation</p>
                    <div class="flex justify-center gap-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-gray-900 shadow hover:shadow-md transition-all"><i class="bi bi-github text-lg"></i></a>
                        <a href="#" class="w-10 h-10 rounded-full bg-white flex items-center justify-center text-gray-400 hover:text-blue-600 shadow hover:shadow-md transition-all"><i class="bi bi-linkedin text-lg"></i></a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Custom Footer for Standalone Page -->
    <footer class="bg-white border-t border-gray-100 py-10 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center text-white font-bold text-sm">
                    <i class="bi bi-hospital"></i>
                </div>
                <span class="font-bold text-gray-900">HMS <span class="text-indigo-600">Pro</span></span>
            </div>
            <p class="text-gray-500 font-medium text-sm text-center md:text-left">
                &copy; <?= date('Y') ?> HMS System. All rights reserved.
            </p>
            <div class="text-sm font-bold text-gray-500 flex items-center gap-1">
                Crafted with <i class="bi bi-heart-fill text-rose-500 animate-pulse mx-1"></i> by <span class="gradient-text ml-1 text-base">Echo Team</span>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Navbar blur effect on scroll
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 20) {
                nav.classList.add('shadow-md');
            } else {
                nav.classList.remove('shadow-md');
            }
        });
    </script>
</body>
</html>
