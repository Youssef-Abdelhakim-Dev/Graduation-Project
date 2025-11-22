// ================================
// === ADVANCED EXAM MANAGER ======
// ================================
class AdvancedExamManager {
    constructor({ maxTabSwitch = 3, inactivityLimit = 120000, maxAttempts = 1 } = {}) {
        this.activeTimers = {};
        this.debounceTimeouts = {};
        this.blurCount = 0;
        this.examLocked = false;
        this.lastActivity = Date.now();
        this.enteredExams = new Set();
        this.MAX_BLUR_ATTEMPTS = maxTabSwitch;
        this.INACTIVITY_LIMIT = inactivityLimit;
        this.MAX_EXAM_ATTEMPTS = maxAttempts;

        this.initAntiCheat();
        this.initInactivityMonitor();
        this.initPastePrevention();
        this.initFocusDetection();
        this.initOfflineMonitor();
    }

    // ================================
    async startExam(examId, duration) {
        try {
            if (this.enteredExams.has(examId)) return;
            this.enteredExams.add(examId);
            const lockedExams = JSON.parse(localStorage.getItem("lockedExams") || "[]");
        if (lockedExams.includes(examId)) {
            Swal.fire({
    icon: 'error',
    title: 'Exam Locked',
    html: `
    <div class="p-6 bg-gradient-to-r from-red-400 via-pink-500 to-purple-400 rounded-xl shadow-xl text-center animate__animated animate__fadeIn" role="alert" aria-live="assertive">
       
        <section>
            <p class="text-white text-lg mb-2">
                You cannot take this exam again because you tried to cheat.
            </p>
            <p class="text-white text-lg">
                Please contact your instructor if you believe this is an error.
            </p>
        </section>
        <footer class="mt-4">
            <button onclick="window.location.reload()" class="px-4 py-2 bg-white text-red-600 rounded hover:bg-gray-200 transition">
                Reload Page
            </button>
        </footer>
    </div>
    `,
    showConfirmButton: false,
    showCloseButton: true
});

            return;
        }
            const checkResp = await fetch(`fetch_questions.php?exam_id=${examId}&check=1&token=${window._CSRF}`, { cache: "no-store" });
            if (!checkResp.ok) throw new Error(`Check failed: ${checkResp.status}`);
            const checkData = await checkResp.json();

            if (checkData.taken) {
                Swal.fire({
                    icon: 'info',
                    title: 'Already Attempted!',
                    html: `<div class="p-6 bg-gradient-to-r from-purple-400 via-pink-500 to-red-400 rounded-xl shadow-xl text-center animate__animated animate__fadeIn" role="alert" aria-live="assertive">
    <header>
        <h2 class="text-2xl font-extrabold text-white mb-4 drop-shadow-lg">
            You have already taken this exam
        </h2>
    </header>
    <section>
        <p class="text-white mb-2 text-lg">
            <strong class="bg-green-200 text-green-800 px-2 py-1 rounded-full">Score:</strong>
            <span class="font-semibold text-green-900">${checkData.score}</span>
        </p>
        <p class="text-white text-lg">
            <strong class="bg-blue-200 text-blue-800 px-2 py-1 rounded-full">Percentage:</strong>
            <span class="font-semibold text-blue-900">${checkData.percentage}%</span>
        </p>
    </section>
</div>
`,
                    confirmButtonText: 'OK'
                });
                return;
            }

            this.hideExamList(examId);
            await this.loadQuestions(examId);
            this.startTimer(examId, duration);

            Swal.fire({ icon: 'success', title: 'Exam Started', text: 'Good luck!', timer: 2000, showConfirmButton: false });

        } catch (err) {
            console.error(err);
            this.showErrorContainer(examId, `Failed to start exam: ${err.message}`);
        }
    }

    async loadQuestions(examId) {
        const container = document.getElementById(`questions_${examId}`);
        if (!container) return;

        const isLoaded = container.dataset.loaded === "true";
        const isLoading = container.dataset.loading === "true";
        if (isLoaded) {
            container.style.display = container.style.display === 'none' ? 'block' : 'none';
            return;
        }
        if (isLoading) return;

        container.dataset.loading = "true";
        container.innerHTML = `<p class='loading text-gray-500'>Loading questions...</p>`;

        try {
            let html = "";
            if (!navigator.onLine) {
                html = localStorage.getItem(`exam_${examId}`) || "<p class='text-red-500'>Offline, no cached questions</p>";
            } else {
                const response = await fetch(`fetch_questions.php?exam_id=${examId}`, { cache: "no-store" });
                if (!response.ok) throw new Error(`Failed to load questions: ${response.status}`);
                html = await response.text();
                localStorage.setItem(`exam_${examId}`, html);
            }
            container.innerHTML = html;
            container.dataset.loaded = "true";
        } catch (err) {
            this.showErrorContainer(examId, `Error: ${err.message}`);
        } finally {
            container.dataset.loading = "false";
        }
    }

    startTimer(examId, durationMinutes) {
        if (this.activeTimers[examId]) clearInterval(this.activeTimers[examId]);

        let timeLeft = durationMinutes * 60;
        let elapsed = 0;

        const timerEl = document.getElementById(`timer_${examId}`);
        let spentEl = document.getElementById(`time_spent_${examId}`);
        if (!spentEl && timerEl) {
            spentEl = document.createElement('div');
            spentEl.id = `time_spent_${examId}`;
            timerEl.parentNode.appendChild(spentEl);
        }

        const updateDisplay = () => {
    if (!timerEl) return;

    // Calculate minutes and seconds
    const minutes = Math.floor(timeLeft / 60).toString().padStart(2, '0');
    const seconds = (timeLeft % 60).toString().padStart(2, '0');
    const elapsedMinutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
    const elapsedSeconds = (elapsed % 60).toString().padStart(2, '0');

    // Timer with styled HTML
    timerEl.innerHTML = `
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-weight:bold; font-size:1.2em; color:#f87171;">⏱</span>
            <span style="font-family:monospace; font-size:1.2em; color:#2563eb;">
                ${minutes}<span style="color:#fbbf24">:</span>${seconds}
            </span>
        </div>
    `;

    if (spentEl) {
        spentEl.innerHTML = `
            <div style="font-size:0.9em; color:#10b981;">
                Time Spent: <span style="font-family:monospace;">${elapsedMinutes}:${elapsedSeconds}</span>
            </div>
        `;
    }
};


        updateDisplay();

        this.activeTimers[examId] = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(this.activeTimers[examId]);
                delete this.activeTimers[examId];
                if (timerEl) timerEl.textContent = "Time's up!";
                this.submitExam(examId, elapsed);
            } else {
                timeLeft--; elapsed++;
                updateDisplay();
            }
        }, 1000);
    }

    async submitExam(examId, timeSpent) {
        if (this.examLocked) return;
        if (this.activeTimers[examId]) { clearInterval(this.activeTimers[examId]); delete this.activeTimers[examId]; }

        const form = document.getElementById(`exam_${examId}`);
        if (!form) return;

        const container = document.getElementById(`questions_${examId}`);
        const formData = new FormData(form);
        formData.append("exam_id", examId);
        formData.append("time_spent", timeSpent);
        formData.append("csrf", window._CSRF);

        if (container) container.innerHTML = `<p class='loading'>Submitting exam...</p>`;

        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 20000);

            const response = await fetch("fetch_questions.php", { method: "POST", body: formData, signal: controller.signal });
            clearTimeout(timeout);

            if (!response.ok) throw new Error(`Submission failed: ${response.status}`);
            const html = await response.text();
            if (container) container.innerHTML = `<div class='success-message p-2 bg-green-100 rounded'>${html}</div>`;
        } catch (err) {
            this.showErrorContainer(examId, `Submission error: ${err.message}`);
        }
    }

    autoSaveAnswer(questionId, examId) {
        clearTimeout(this.debounceTimeouts[questionId]);
        this.debounceTimeouts[questionId] = setTimeout(async () => {
            const input = document.querySelector(`[name="answer_${questionId}"]:checked`);
            if (!input) return;
            const answer = input.value.trim();
            if (!answer) return;
            try {
                const res = await fetch("auto_save.php", {
                    method: "POST",
                    body: JSON.stringify({ questionId, examId, answer }),
                    headers: { "Content-Type": "application/json" }
                });
                const data = await res.json();
                if (data.status !== 'success') Swal.fire("Error", "Auto-save failed!", "error");
            } catch (err) { console.error("Auto-save error:", err); }
        }, 800);
    }

    initAntiCheat() {
    const showWarning = (title, text) => {
        Swal.fire({
            icon: 'warning',
            title,
            text,
            toast: true,
            position: 'top-end',
            timer: 3000,
            showConfirmButton: false,
            showCloseButton: true,
            timerProgressBar: true
        });
    };

    const markExamLocked = () => {
        this.examLocked = true;
        document.querySelectorAll('input, textarea, button, select').forEach(el => el.disabled = true);
        const examForms = document.querySelectorAll('form[id^="exam_"]');
        examForms.forEach(form => {
            const examId = parseInt(form.id.replace('exam_', ''), 10);
            const lockedExams = JSON.parse(localStorage.getItem("lockedExams") || "[]");
            if (!lockedExams.includes(examId)) lockedExams.push(examId);
            localStorage.setItem("lockedExams", JSON.stringify(lockedExams));
        });
    };

    const lockExam = reason => {
        markExamLocked();
        Swal.fire({
            icon: 'error',
            title: 'Exam Locked!',
            text: reason,
            allowOutsideClick: false,
            allowEscapeKey: false,
            confirmButtonText: 'Reload'
        }).then(() => window.location.reload());
    };

    // ===============================
    // Tab switch detection
    window.addEventListener("blur", () => {
        this.blurCount++;
        if (this.blurCount >= this.MAX_BLUR_ATTEMPTS) lockExam("Exam locked due to tab switching.");
        else showWarning("Tab Switch Detected", `Attempt ${this.blurCount} of ${this.MAX_BLUR_ATTEMPTS}`);
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            this.blurCount++;
            if (this.blurCount >= this.MAX_BLUR_ATTEMPTS) lockExam("Exam locked due to tab switching.");
        }
    });

    // ===============================
    // Forbidden shortcuts
    document.addEventListener('keydown', e => {
        const key = e.key.toLowerCase();
        // Ctrl+Shift+I/J/C (DevTools), Ctrl+U/S (View source / Save)
        if ((e.ctrlKey && e.shiftKey && ['i','j','c'].includes(key)) || (e.ctrlKey && ['u','s'].includes(key))) {
            e.preventDefault();
            lockExam("Forbidden key detected.");
        }
    });

    // ===============================
    // Right-click, paste, cut, printscreen
    document.addEventListener('contextmenu', e => { e.preventDefault(); showWarning("Right-click Disabled", "Context menu is not allowed."); });
    document.addEventListener('paste', e => { e.preventDefault(); lockExam("Pasting not allowed."); });
    document.addEventListener('cut', e => { e.preventDefault(); lockExam("Cutting not allowed."); });
    document.addEventListener('keyup', e => { if (e.key === 'PrintScreen' || e.code === 'PrintScreen') lockExam("Screenshot detected."); });
}


    initInactivityMonitor() {
        ['mousemove','keydown','click','scroll'].forEach(evt => window.addEventListener(evt,()=>this.lastActivity=Date.now()));
        setInterval(()=>{
            if(!this.examLocked && Date.now()-this.lastActivity>this.INACTIVITY_LIMIT)
                Swal.fire({icon:'warning', title:'Inactivity', text:'You have been inactive.', timer:5000, showConfirmButton:false});
        },30000);
    }

    initPastePrevention() {
        document.querySelectorAll('textarea,input').forEach(el => {
            el.addEventListener('paste', e => { e.preventDefault(); Swal.fire("Cheating Alert!","Pasting disabled.","error"); });
        });
    }
    initFocusDetection() {
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            this.blurCount++;

            // Find currently active exam(s)
            const activeForms = document.querySelectorAll('form[id^="exam_"]');
            activeForms.forEach(form => {
                const examId = parseInt(form.id.replace('exam_', ''), 10);
                if (this.blurCount >= this.MAX_BLUR_ATTEMPTS) {
                    // Mark exam as locked in localStorage
                    const lockedExams = JSON.parse(localStorage.getItem("lockedExams") || "[]");
                    if (!lockedExams.includes(examId)) lockedExams.push(examId);
                    localStorage.setItem("lockedExams", JSON.stringify(lockedExams));

                    // Lock exam without reload yet
                    this.lockExam(`Exam ${examId} locked due to tab switching.`, examId);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tab Switch Detected',
                        text: `Attempt ${this.blurCount} of ${this.MAX_BLUR_ATTEMPTS}`,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            });
        }
    });
}

    initOfflineMonitor() {
        window.addEventListener('offline', ()=>Swal.fire("Connection Lost!","Check your internet.","warning"));
        window.addEventListener('online', ()=>Swal.fire("Back Online","You are reconnected.","success"));
    }

    lockExam(reason) {
        this.examLocked = true;
        document.querySelectorAll('input,textarea,button').forEach(el=>el.disabled=true);
        Swal.fire({icon:'error', title:'Exam Locked!', text:reason, allowOutsideClick:false, allowEscapeKey:false, confirmButtonText:'Reload'}).then(()=>window.location.reload());
    }

    hideExamList(examId) {
        document.querySelectorAll('.exam-list,.timer,.time-spent').forEach(el=>el.classList.add('hidden'));
    }

    showErrorContainer(examId, message) {
        const container=document.getElementById(`questions_${examId}`);
        if(container) container.innerHTML=`<p class='error text-red-500'>${message}</p>`;
    }
}

// ================================
// === INIT MANAGER ===============
const examManager = new AdvancedExamManager();

// Global helpers
function startExam(examId,duration){examManager.startExam(examId,duration);}
function submitExam(examId,timeSpent){examManager.submitExam(examId,timeSpent);}
function autoSaveAnswer(questionId,examId){examManager.autoSaveAnswer(questionId,examId);}