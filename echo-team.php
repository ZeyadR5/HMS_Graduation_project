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
            content: ''; position: absolute; inset: -4px; border-radius: 16px;
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

        /* ── Flip Card ── */
        .flip-card { perspective: 1200px; cursor: pointer; position: relative; }
        .flip-card-inner {
            width: 100%; height: 500px; position: relative;
            transition: transform 0.75s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
            -webkit-transform-style: preserve-3d;
            will-change: transform;
        }
        .flip-card.flipped { z-index: 20; }
        .flip-card.flipped .flip-card-inner { transform: rotateY(180deg); }
        .flip-card-front, .flip-card-back {
            position: absolute; inset: 0; border-radius: 1.5rem;
            backface-visibility: hidden; -webkit-backface-visibility: hidden;
            overflow: hidden;
            transform: translateZ(0);
            -webkit-transform: translateZ(0);
        }
        .flip-card-front {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08);
            display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem;
        }
        .flip-card-back {
            transform: rotateY(180deg) translateZ(0);
            -webkit-transform: rotateY(180deg) translateZ(0);
            background: #fff;
            box-shadow: 0 25px 50px -12px rgba(79,70,229,0.2);
        }
        .back-link {
            display: flex; align-items: center; gap: 10px; padding: 5px 10px;
            border-radius: 12px; text-decoration: none; color: #374151;
            font-weight: 600; font-size: 0.875rem; transition: background 0.2s;
        }
        .back-link:hover { background: #f9fafb; color: #1f2937; }
        .back-link-icon {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        }
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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12" style="overflow:visible;">

                <!-- AbdEl-Rahman Gamal -->
                <div class="flip-card" onclick="flipCard(this)">
                  <div class="flip-card-inner">
                    <div class="flip-card-front text-center">
                      <div class="avatar-container mx-auto rounded-2xl border-4 border-white shadow-lg overflow-hidden bg-blue-50" style="width:190px;height:270px;">
                        <img src="/assets/images/Team/Abdulrahman.jpeg" alt="AbdEl-Rahman Gamal" class="w-full h-full object-contain">
                      </div>
                      <div class="mt-4 mb-1 inline-block px-5 py-1.5 rounded-full bg-blue-50 ring-1 ring-blue-200">
                        <h3 class="text-lg font-black text-blue-700">Abdelrahman Gamal</h3>
                      </div>
                      <p class="text-gray-500 font-semibold text-xs uppercase tracking-wider mt-2">Back-End Developer</p>
                      <!-- <p class="text-gray-300 text-xs mt-4"><i class="bi bi-arrow-repeat mr-1"></i>Click to flip</p> -->
                    </div>
                    <div class="flip-card-back">
                      <div class="h-2 bg-gradient-to-r from-blue-400 via-blue-500 to-indigo-500"></div>
                      <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                          <img src="/assets/images/echol.png" alt="Echo" style="height:36px;">
                          <span class="text-xs text-gray-400 font-bold uppercase tracking-wide">HMS Project</span>
                        </div>
                        <div class="flex flex-col items-center mb-2">
                          <div class="rounded-2xl overflow-hidden border-4 border-blue-100 shadow-md" style="width:130px;height:170px;">
                            <img src="/assets/images/Team/Abdulrahman.jpeg" class="w-full h-full object-contain">
                          </div>
                          <h3 class="mt-2 text-sm font-black text-gray-900 uppercase tracking-wide text-center">AbdEl-Rahman Gamal</h3>
                          <p class="text-blue-600 font-bold text-xs">Back-End Developer</p>
                          <!-- <p class="text-gray-400 text-xs">Database Engineer</p> -->
                        </div>
                        <div class="border-t border-gray-100 pt-2">
                          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                          <a href="https://web.whatsapp.com/send/?phone=%2B201011923048&text&type=phone_number&app_absent=0" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-green-100"><i class="bi bi-whatsapp text-green-500"></i></div>+201011923048
                          </a>
                          <a href="https://github.com/Abdog210/" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-gray-100"><i class="bi bi-github text-gray-800"></i></div>Abdog210
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>


                                <!-- Zeyad Yasser -->
                <div class="flip-card" onclick="flipCard(this)">
                  <div class="flip-card-inner">
                    <div class="flip-card-front text-center">
                      <div class="avatar-container mx-auto rounded-2xl border-4 border-white shadow-lg overflow-hidden bg-indigo-50" style="width:190px;height:270px;">
                        <img src="/assets/images/Team/Zeyad.png" alt="Zeyad Yasser" class="w-full h-full object-contain">
                      </div>
                      <div class="mt-4 mb-1 inline-block px-5 py-1.5 rounded-full bg-indigo-50 ring-1 ring-indigo-200">
                        <h3 class="text-lg font-black text-indigo-700">Zeyad Yasser</h3>
                      </div>
                      <p class="text-gray-500 font-semibold text-xs uppercase tracking-wider mt-2">Full Stack &amp; Team Leader</p>
                      <!-- <p class="text-gray-300 text-xs mt-4"><i class="bi bi-arrow-repeat mr-1"></i>Click to flip</p> -->
                    </div>
                    <div class="flip-card-back">
                      <div class="h-2 bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500"></div>
                      <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                          <img src="/assets/images/echol.png" alt="Echo" style="height:36px;">
                          <span class="text-xs text-gray-400 font-bold uppercase tracking-wide">HMS Project</span>
                        </div>
                        <div class="flex flex-col items-center mb-2">
                          <div class="rounded-2xl overflow-hidden border-4 border-indigo-100 shadow-md" style="width:130px;height:170px;">
                            <img src="/assets/images/Team/Zeyad.png" class="w-full h-full object-contain">
                          </div>
                          <h3 class="mt-2 text-sm font-black text-gray-900 uppercase tracking-wide text-center">Zeyad Yaser Abdallah</h3>
                          <p class="text-indigo-600 font-bold text-xs">Team Leader</p>
                          <p class="text-gray-400 text-xs">Full Stack Developer</p>
                        </div>
                        <div class="border-t border-gray-100 pt-2">
                          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                          <a href="https://web.whatsapp.com/send/?phone=%2B201024474059&text&type=phone_number&app_absent=0" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-green-100"><i class="bi bi-whatsapp text-green-500"></i></div>+201024474059
                          </a>
                          <a href="https://github.com/zeyadi9/" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-gray-100"><i class="bi bi-github text-gray-800"></i></div>zeyadi9
                          </a>
                          <a href="https://www.linkedin.com/in/zeyad-yasser-213312297?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-blue-100"><i class="bi bi-linkedin text-blue-600"></i></div>Zeyad Yasser
                          </a>
                          <a href="https://zeyadi9.github.io/Portfolio/" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-indigo-100"><i class="bi bi-globe2 text-indigo-600"></i></div>Portfolio
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Amnaa Mohamed -->
                <div class="flip-card" onclick="flipCard(this)">
                  <div class="flip-card-inner">
                    <div class="flip-card-front text-center">
                      <div class="avatar-container mx-auto rounded-2xl border-4 border-white shadow-lg overflow-hidden bg-purple-50" style="width:190px;height:270px;">
                        <img src="/assets/images/Team/Amnaa.jpeg" alt="Amnaa Mohamed" class="w-full h-full object-contain">
                      </div>
                      <div class="mt-4 mb-1 inline-block px-5 py-1.5 rounded-full bg-purple-50 ring-1 ring-purple-200">
                        <h3 class="text-lg font-black text-purple-700">Amnaa Mohamed</h3>
                      </div>
                      <p class="text-gray-500 font-semibold text-xs uppercase tracking-wider mt-2">Back-End Developer &amp; DB</p>
                      <!-- <p class="text-gray-300 text-xs mt-4"><i class="bi bi-arrow-repeat mr-1"></i>Click to flip</p> -->
                    </div>
                    <div class="flip-card-back">
                      <div class="h-2 bg-gradient-to-r from-purple-400 via-purple-500 to-pink-500"></div>
                      <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                          <img src="/assets/images/echol.png" alt="Echo" style="height:36px;">
                          <span class="text-xs text-gray-400 font-bold uppercase tracking-wide">HMS Project</span>
                        </div>
                        <div class="flex flex-col items-center mb-2">
                          <div class="rounded-2xl overflow-hidden border-4 border-purple-100 shadow-md" style="width:130px;height:170px;">
                            <img src="/assets/images/Team/Amnaa.jpeg" class="w-full h-full object-contain">
                          </div>
                          <h3 class="mt-2 text-sm font-black text-gray-900 uppercase tracking-wide text-center">Amnaa Mohamed</h3>
                          <p class="text-purple-600 font-bold text-xs">Back-End Developer</p>
                          <p class="text-gray-400 text-xs">Database Engineer</p>
                        </div>
                        <div class="border-t border-gray-100 pt-2">
                          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                          <a href="https://web.whatsapp.com/send/?phone=%2B20127977721&text&type=phone_number&app_absent=0" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-green-100"><i class="bi bi-whatsapp text-green-500"></i></div>+20127977721
                          </a>
                          <a href="https://www.linkedin.com/in/amnaa-salah-b3a28936b?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-blue-100"><i class="bi bi-linkedin text-blue-600"></i></div>Amnaa Salah
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Nada Taha -->
                <div class="flip-card" onclick="flipCard(this)">
                  <div class="flip-card-inner">
                    <div class="flip-card-front text-center">
                      <div class="avatar-container mx-auto rounded-2xl border-4 border-white shadow-lg overflow-hidden bg-rose-50" style="width:190px;height:270px;">
                        <img src="/assets/images/Team/Nada.jpeg" alt="Nada Taha" class="w-full h-full object-contain">
                      </div>
                      <div class="mt-4 mb-1 inline-block px-5 py-1.5 rounded-full bg-rose-50 ring-1 ring-rose-200">
                        <h3 class="text-lg font-black text-rose-700">Nada Taha</h3>
                      </div>
                      <p class="text-gray-500 font-semibold text-xs uppercase tracking-wider mt-2">Front-End Developer</p>
                      <!-- <p class="text-gray-300 text-xs mt-4"><i class="bi bi-arrow-repeat mr-1"></i>Click to flip</p> -->
                    </div>
                    <div class="flip-card-back">
                      <div class="h-2 bg-gradient-to-r from-rose-400 via-rose-500 to-orange-400"></div>
                      <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                          <img src="/assets/images/echol.png" alt="Echo" style="height:36px;">
                          <span class="text-xs text-gray-400 font-bold uppercase tracking-wide">HMS Project</span>
                        </div>
                        <div class="flex flex-col items-center mb-2">
                          <div class="rounded-2xl overflow-hidden border-4 border-rose-100 shadow-md" style="width:130px;height:170px;">
                            <img src="/assets/images/Team/Nada.jpeg" class="w-full h-full object-contain">
                          </div>
                          <h3 class="mt-2 text-sm font-black text-gray-900 uppercase tracking-wide text-center">Nada Taha</h3>
                          <p class="text-rose-600 font-bold text-xs">Front-End Developer</p>
                          <p class="text-gray-400 text-xs">UI / UX</p>
                        </div>
                        <div class="border-t border-gray-100 pt-2">
                          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                          <a href="https://web.whatsapp.com/send/?phone=%2B201144047035&text&type=phone_number&app_absent=0" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-green-100"><i class="bi bi-whatsapp text-green-500"></i></div>+201144047035
                          </a>
                          <a href="https://github.com/nadatahanadataha098-dotcom/" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-gray-100"><i class="bi bi-github text-gray-800"></i></div>nadataha098
                          </a>
                          <a href="https://www.linkedin.com/in/nada-taha-123b13261" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-blue-100"><i class="bi bi-linkedin text-blue-600"></i></div>Nada Taha
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Aya Shapan -->
                <div class="flip-card" onclick="flipCard(this)">
                  <div class="flip-card-inner">
                    <div class="flip-card-front text-center">
                      <div class="avatar-container mx-auto rounded-2xl border-4 border-white shadow-lg overflow-hidden bg-pink-50" style="width:190px;height:270px;">
                        <img src="/assets/images/Team/Aya.jpeg" alt="Aya Shapan" class="w-full h-full object-contain">
                      </div>
                      <div class="mt-4 mb-1 inline-block px-5 py-1.5 rounded-full bg-pink-50 ring-1 ring-pink-200">
                        <h3 class="text-lg font-black text-pink-700">Aya Shapan</h3>
                      </div>
                      <p class="text-gray-500 font-semibold text-xs uppercase tracking-wider mt-2">Front-End Developer</p>
                      <!-- <p class="text-gray-300 text-xs mt-4"><i class="bi bi-arrow-repeat mr-1"></i>Click to flip</p> -->
                    </div>
                    <div class="flip-card-back">
                      <div class="h-2 bg-gradient-to-r from-pink-400 via-pink-500 to-rose-400"></div>
                      <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                          <img src="/assets/images/echol.png" alt="Echo" style="height:36px;">
                          <span class="text-xs text-gray-400 font-bold uppercase tracking-wide">HMS Project</span>
                        </div>
                        <div class="flex flex-col items-center mb-2">
                          <div class="rounded-2xl overflow-hidden border-4 border-pink-100 shadow-md" style="width:130px;height:170px;">
                            <img src="/assets/images/Team/Aya.jpeg" class="w-full h-full object-contain">
                          </div>
                          <h3 class="mt-2 text-sm font-black text-gray-900 uppercase tracking-wide text-center">Aya Shapan</h3>
                          <p class="text-pink-600 font-bold text-xs">Front-End Developer</p>
                          <p class="text-gray-400 text-xs">UI / UX</p>
                        </div>
                        <div class="border-t border-gray-100 pt-2">
                          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                          <a href="https://web.whatsapp.com/send/?phone=%2B201101675105&text&type=phone_number&app_absent=0" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-green-100"><i class="bi bi-whatsapp text-green-500"></i></div>+201101675105
                          </a>
                          <a href="https://github.com/AyaShaban1/" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-gray-100"><i class="bi bi-github text-gray-800"></i></div>AyaShaban1
                          </a>
                          <a href="https://www.linkedin.com/in/aya-shaban-792a98335?utm_source=share_via&utm_content=profile&utm_medium=member_ios" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-blue-100"><i class="bi bi-linkedin text-blue-600"></i></div>Aya Shaban
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Mohamed Ahmed -->
                <div class="flip-card" onclick="flipCard(this)">
                  <div class="flip-card-inner">
                    <div class="flip-card-front text-center">
                      <div class="avatar-container mx-auto rounded-2xl border-4 border-white shadow-lg overflow-hidden bg-emerald-50" style="width:190px;height:270px;">
                        <img src="/assets/images/Team/Mohamed.jpeg" alt="Mohamed Ahmed" class="w-full h-full object-contain">
                      </div>
                      <div class="mt-4 mb-1 inline-block px-5 py-1.5 rounded-full bg-emerald-50 ring-1 ring-emerald-200">
                        <h3 class="text-lg font-black text-emerald-700">Mohamed Ahmed</h3>
                      </div>
                      <p class="text-gray-500 font-semibold text-xs uppercase tracking-wider mt-2">Front-End Developer</p>
                      <!-- <p class="text-gray-300 text-xs mt-4"><i class="bi bi-arrow-repeat mr-1"></i>Click to flip</p> -->
                    </div>
                    <div class="flip-card-back">
                      <div class="h-2 bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-500"></div>
                      <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                          <img src="/assets/images/echol.png" alt="Echo" style="height:36px;">
                          <span class="text-xs text-gray-400 font-bold uppercase tracking-wide">HMS Project</span>
                        </div>
                        <div class="flex flex-col items-center mb-2">
                          <div class="rounded-2xl overflow-hidden border-4 border-emerald-100 shadow-md" style="width:130px;height:170px;">
                            <img src="/assets/images/Team/Mohamed.jpeg" class="w-full h-full object-contain">
                          </div>
                          <h3 class="mt-2 text-sm font-black text-gray-900 uppercase tracking-wide text-center">Mohamed Ahmed</h3>
                          <p class="text-emerald-600 font-bold text-xs">Front-End Developer</p>
                          <p class="text-gray-400 text-xs">UI / UX</p>
                        </div>
                        <div class="border-t border-gray-100 pt-2">
                          <p class="text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Contact</p>
                          <a href="https://web.whatsapp.com/send/?phone=%2B201116584349&text&type=phone_number&app_absent=0" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-green-100"><i class="bi bi-whatsapp text-green-500"></i></div>+201116584349
                          </a>
                          <a href="https://github.com/mohamedahmed17122004-ai" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-gray-100"><i class="bi bi-github text-gray-800"></i></div>mohamedahmed
                          </a>
                          <a href="https://www.linkedin.com/in/mohamed-rabea-69099726a/" onclick="event.stopPropagation()" target="_blank" class="back-link">
                            <div class="back-link-icon bg-blue-100"><i class="bi bi-linkedin text-blue-600"></i></div>Mohamed Rabea
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

            </div>

        </div>
    </section>

    <!-- Project Supervisor Section -->
    <section class="py-20 bg-amber-50/50 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(251,191,36,0.1),transparent_60%)]"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-14">
                <span class="px-4 py-1.5 rounded-full bg-amber-100 text-amber-700 font-bold text-sm tracking-widest uppercase mb-4 inline-block ring-1 ring-amber-200">
                    <i class="bi bi-mortarboard-fill mr-1"></i> Academic Supervision
                </span>
                <h2 class="mt-4 text-3xl font-black tracking-tight text-gray-900">Project <span style="background:linear-gradient(135deg,#d97706,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Supervisor</span></h2>
                <p class="mt-3 text-gray-500 font-medium">Under the academic guidance and supervision of</p>
            </div>

            <div class="flex justify-center">
                <div class="bg-white rounded-3xl shadow-xl border border-amber-100 p-8 flex flex-col sm:flex-row items-center gap-8 max-w-xl w-full hover:shadow-2xl transition-all duration-300">
                    <!-- Avatar -->
                    <div class="rounded-2xl overflow-hidden flex-shrink-0 border-4 border-white ring-4 ring-amber-100 shadow-lg" style="width:140px;height:175px;">
                        <img src="/assets/images/Team/Sara 3.jpeg" alt="Dr. Sara" class="w-full h-full object-cover object-top">
                    </div>
                    <!-- Info -->
                    <div class="text-center sm:text-left">
                        <span class="inline-block px-3 py-1 rounded-full bg-amber-50 ring-1 ring-amber-200 text-amber-700 text-xs font-bold uppercase tracking-widest mb-3">
                            Project Supervisor
                        </span>
                        <h3 class="text-2xl font-black text-gray-900 mb-1">Dr. Sara</h3>
                        <p class="text-amber-600 font-semibold text-sm">Academic Supervisor — HMS Project</p>
                        <p class="text-gray-400 text-xs mt-2 leading-relaxed">Provided academic guidance and oversight throughout the development of the HMS graduation project.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Special Thanks Section -->
    <section class="py-20 bg-slate-50 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(99,102,241,0.07),transparent_60%)]"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-14">
                <span class="px-4 py-1.5 rounded-full bg-indigo-50 text-indigo-600 font-bold text-sm tracking-widest uppercase mb-4 inline-block ring-1 ring-indigo-200">
                    <i class="bi bi-stars mr-1"></i> Special Thanks
                </span>
                <h2 class="mt-4 text-3xl font-black tracking-tight text-gray-900">External <span class="gradient-text">Contributors</span></h2>
                <p class="mt-3 text-gray-500 font-medium">Individuals outside the university who contributed their expertise to the project</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-3xl mx-auto">

                <!-- Hatem Ahmed — AI -->
                <div class="bg-white rounded-3xl shadow-lg border border-teal-100 p-7 flex flex-col items-center text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="rounded-2xl overflow-hidden mb-5 border-4 border-white ring-4 ring-teal-100 shadow-lg" style="width:160px;height:200px;">
                        <img src="/assets/images/Team/Hatem.jpeg" alt="Hatem Ahmed" class="w-full h-full object-contain">
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full bg-teal-50 ring-1 ring-teal-200 text-teal-700 text-xs font-bold uppercase tracking-widest mb-3">
                        AI Contribution
                    </span>
                    <h3 class="text-xl font-black text-gray-900 mb-1">Eng. Hatem Ahmed</h3>
                    <p class="text-teal-600 font-semibold text-sm mb-3">AI Engineer</p>
                    <div class="w-10 h-0.5 bg-teal-200 rounded-full mb-3"></div>
                    <p class="text-gray-500 text-sm leading-relaxed">Contributed expertise in Artificial Intelligence, supporting the development and integration of the AI-powered triage and medical assistance features.</p>
                </div>

                <!-- Sara Nasr — Testing -->
                <div class="bg-white rounded-3xl shadow-lg border border-violet-100 p-7 flex flex-col items-center text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                    <div class="rounded-2xl overflow-hidden mb-5 border-4 border-white ring-4 ring-violet-100 shadow-lg" style="width:160px;height:200px;">
                        <img src="/assets/images/Team/Sara 1.jpeg" alt="Sara Nasr" class="w-full h-full object-contain">
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full bg-violet-50 ring-1 ring-violet-200 text-violet-700 text-xs font-bold uppercase tracking-widest mb-3">
                        QA & Testing
                    </span>
                    <h3 class="text-xl font-black text-gray-900 mb-1">Eng. Sara Nasr</h3>
                    <p class="text-violet-600 font-semibold text-sm mb-3">Software Tester</p>
                    <div class="w-10 h-0.5 bg-violet-200 rounded-full mb-3"></div>
                    <p class="text-gray-500 text-sm leading-relaxed">Conducted thorough software testing and quality assurance, ensuring system stability, performance, and reliability across all modules.</p>
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
        // Flip card function
        function flipCard(card) {
            document.querySelectorAll('.flip-card.flipped').forEach(c => {
                if (c !== card) c.classList.remove('flipped');
            });
            card.classList.toggle('flipped');
        }

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
