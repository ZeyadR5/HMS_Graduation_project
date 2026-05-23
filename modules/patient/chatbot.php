<?php
require_once __DIR__ . '/../../includes/auth.php';

$patientName = $_SESSION['username'] ?? 'المريض';
?>
<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Echo HMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#0ea5e9',
                        secondary: '#14b8a6',
                    },
                    fontFamily: {
                        sans: ['Inter', 'Outfit', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .glass-panel { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); border: 1px solid rgba(226, 232, 240, 0.8); }
        #chatMessages::-webkit-scrollbar { width: 6px; }
        #chatMessages::-webkit-scrollbar-track { background: transparent; }
        #chatMessages::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .msg-bot { @apply bg-white text-slate-800 rounded-2xl rounded-tl-none py-3 px-4 shadow-sm border border-slate-100; }
        .msg-user { @apply bg-primary text-white rounded-2xl rounded-tr-none py-3 px-4 shadow-md; }
    </style>
</head>
<body class="h-screen flex flex-col">
    <?php $activePage = 'ai-assistant'; require_once __DIR__ . '/../../includes/nav.php'; ?>

    <div class="flex-1 flex flex-col max-w-5xl mx-auto w-full px-4 py-4 overflow-hidden">
        <main class="flex-1 flex flex-col glass-panel rounded-3xl shadow-xl overflow-hidden relative">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-white shadow-lg">
                        <i class="bi bi-robot font-bold text-xl"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-slate-800 leading-tight">Echo AI Assistant</h2>
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">Online & Secure</span>
                        </div>
                    </div>
                </div>
                <button onclick="clearChat()" class="text-xs text-slate-400 hover:text-rose-500 transition-colors flex items-center gap-1.5">
                    <i class="bi bi-trash3"></i> مسح المحادثة
                </button>
            </div>

            <!-- Messages Area -->
            <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/30">
                <!-- Welcome Message -->
                <div class="flex justify-start">
                    <div class="flex items-start gap-3 max-w-[85%]">
                        <div class="msg-bot">
                            <div class="text-sm leading-relaxed">
                                أهلاً بك يا <strong><?php echo htmlspecialchars($patientName); ?></strong>! 👋<br>
                                أنا مساعدك الذكي في مستشفى Echo HMS. كيف يمكنني مساعدتك اليوم؟
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Typing Indicator -->
            <div id="typing" class="hidden px-6 py-2">
                <div class="flex items-center gap-2 text-slate-400 text-xs italic">
                    <div class="flex gap-1">
                        <span class="w-1 h-1 bg-slate-300 rounded-full animate-bounce"></span>
                        <span class="w-1 h-1 bg-slate-300 rounded-full animate-bounce [animation-delay:0.2s]"></span>
                        <span class="w-1 h-1 bg-slate-300 rounded-full animate-bounce [animation-delay:0.4s]"></span>
                    </div>
                    جاري المعالجة...
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="px-6 py-3 flex gap-2 overflow-x-auto no-scrollbar border-t border-slate-50">
                <button onclick="sendQuick('ما هي مواعيدي القادمة؟')" class="whitespace-nowrap px-3 py-1.5 rounded-full bg-white border border-slate-200 text-xs text-slate-600 hover:border-primary hover:text-primary transition-all shadow-sm">📅 مواعيدي</button>
                <button onclick="sendQuick('متى متاح دكتور حمادة؟')" class="whitespace-nowrap px-3 py-1.5 rounded-full bg-white border border-slate-200 text-xs text-slate-600 hover:border-primary hover:text-primary transition-all shadow-sm">👨‍⚕️ مواعيد الأطباء</button>
                <button onclick="sendQuick('تحضيرات تحليل السكر')" class="whitespace-nowrap px-3 py-1.5 rounded-full bg-white border border-slate-200 text-xs text-slate-600 hover:border-primary hover:text-primary transition-all shadow-sm">🔬 تحضيرات التحاليل</button>
            </div>

            <!-- Input Area -->
            <div class="p-6 bg-white border-t border-slate-100">
                <div class="relative flex items-end gap-3 bg-slate-50 rounded-2xl p-2 focus-within:bg-white focus-within:ring-2 focus-within:ring-primary/20 transition-all border border-slate-200">
                    <textarea 
                        id="chatInput"
                        rows="1"
                        placeholder="اسألني عن المواعيد، التحاليل، أو تعليمات الطبيب..."
                        class="flex-1 bg-transparent border-none focus:ring-0 text-sm py-2 px-3 resize-none max-h-32"
                    ></textarea>
                    <button 
                        id="sendBtn"
                        class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary/90 transition-all transform active:scale-95 shadow-md disabled:bg-slate-300"
                    >
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <p class="mt-3 text-[10px] text-center text-slate-400 flex items-center justify-center gap-1">
                    <i class="bi bi-shield-lock-fill"></i> يتم مسح سجل المحادثة تلقائياً عند تسجيل الخروج لخصوصيتك.
                </p>
            </div>
        </main>
    </div>

    <script>
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const typing = document.getElementById('typing');

    // History Logic
    let chatHistory = JSON.parse(sessionStorage.getItem('hms_chat_history') || '[]');
    let isSending = false;

    // Load history visually
    if (chatHistory.length > 0) {
        chatHistory.forEach(msg => {
            addMessage(msg.content, msg.role === 'user' ? 'user' : 'bot');
        });
    }

    chatInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    chatInput.addEventListener('input', () => {
        chatInput.style.height = 'auto';
        chatInput.style.height = (chatInput.scrollHeight) + 'px';
    });

    function addMessage(text, type) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `flex ${type === 'user' ? 'justify-end' : 'justify-start'} animate-in fade-in slide-in-from-bottom-2 duration-300`;
        
        let bubbleClass = type === 'user' ? 'msg-user max-w-[85%]' : 'msg-bot max-w-[85%]';
        if (type === 'warning') bubbleClass = 'bg-amber-50 text-amber-900 rounded-2xl py-3 px-4 shadow-sm border border-amber-200 max-w-[85%]';

        msgDiv.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="${bubbleClass}">
                    <div class="text-sm leading-relaxed">${text.replace(/\n/g, '<br>')}</div>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(msgDiv);
        scrollToBottom();
    }

    function clearChat() {
        if (!confirm('هل تريد مسح سجل المحادثة بالكامل؟')) return;
        sessionStorage.removeItem('hms_chat_history');
        chatHistory = [];
        window.location.reload();
    }

    function sendQuick(text) {
        chatInput.value = text;
        sendMessage();
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (!text || isSending) return;

        isSending = true;
        sendBtn.disabled = true;
        chatInput.value = '';
        chatInput.style.height = 'auto';

        addMessage(text, 'user');
        typing.classList.remove('hidden');
        scrollToBottom();

        try {
            const res = await fetch('/modules/patient/chatbot-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ message: text, history: chatHistory })
            });
            const data = await res.json();
            typing.classList.add('hidden');

            if (data.error) {
                addMessage('عذراً، حدث خطأ غير متوقع. يرجى المحاولة لاحقاً.', 'warning');
            } else if (data.type === 'diagnostic_blocked') {
                addMessage(data.reply, 'warning');
            } else {
                addMessage(data.reply, 'bot');
                chatHistory.push({role:'user', content: text});
                chatHistory.push({role:'assistant', content: data.reply});
                if (chatHistory.length > 20) chatHistory = chatHistory.slice(-20);
                sessionStorage.setItem('hms_chat_history', JSON.stringify(chatHistory));
            }
        } catch (err) {
            typing.classList.add('hidden');
            addMessage('حدث خطأ في الاتصال بالخادم.', 'warning');
        }
        
        isSending = false;
        sendBtn.disabled = false;
        chatInput.focus();
    }

    scrollToBottom();
    </script>
</body>
</html>
