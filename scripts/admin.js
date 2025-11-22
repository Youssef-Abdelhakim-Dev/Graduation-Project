
// =========================
class Sanitizer {
    static text(str = "") {
        const div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }
}

// =========================
// Utility: DOM Helpers
// =========================
class DOM {
    static show(id) {
        document.getElementById(id).classList.remove("hidden");
    }
    static hide(id) {
        document.getElementById(id).classList.add("hidden");
    }
    static on(selector, event, handler) {
        document.querySelectorAll(selector).forEach(el =>
            el.addEventListener(event, handler)
        );
    }
    static $(sel) {
        return document.querySelector(sel);
    }
    static $all(sel) {
        return document.querySelectorAll(sel);
    }
}

// =========================
// Utility: Internet Speed
// =========================
class Internet {
    static isSlow() {
        if (!navigator.connection) return false;
        return navigator.connection.downlink < 1; // <1mbps slow
    }
}

// =========================
// Utility: Fetch Wrapper
// ===========================================
// Custom Error Types
// ===========================================
class APIError extends Error {
    constructor(message, code = "API_ERROR") {
        super(message);
        this.name = "APIError";
        this.code = code;
    }
}

class NetworkError extends APIError {
    constructor(message = "Network request failed") {
        super(message, "NETWORK_ERROR");
    }
}

class TimeoutError extends APIError {
    constructor(message = "Request timed out") {
        super(message, "TIMEOUT");
    }
}


// ===========================================
// Request Cache Storage
// ===========================================
class APICache {
    static get(key) {
        return localStorage.getItem(`cache_${key}`);
    }
    static set(key, value) {
        localStorage.setItem(`cache_${key}`, JSON.stringify(value));
    }
    static remove(key) {
        localStorage.removeItem(`cache_${key}`);
    }
}


// ===========================================
// Offline Queue (Requests saved when internet is down)
// ===========================================
class OfflineQueue {
    static push(request) {
        let q = JSON.parse(localStorage.getItem("offline_queue") || "[]");
        q.push(request);
        localStorage.setItem("offline_queue", JSON.stringify(q));
    }

    static getAll() {
        return JSON.parse(localStorage.getItem("offline_queue") || "[]");
    }

    static clear() {
        localStorage.removeItem("offline_queue");
    }
}


// ===========================================
// Networking Helper (Retries, backoff, timeout)
// ===========================================
class API {
    static BASE_URL = ""; // Can be updated dynamically
    static RETRIES = 3;
    static BACKOFF_FACTOR = 600; // ms
    static TIMEOUT = 8000;
    static DEV_MODE = true; // Enhanced logging
    static CSRF_TOKEN = "";

    static setBaseURL(url) {
        API.BASE_URL = url;
    }

    static setCSRF(token) {
        API.CSRF_TOKEN = token;
    }

    // =====================================================
    // Fetch Wrapper — Most Advanced Version
    // =====================================================
    static async send(formData, {
        endpoint = "",
        cacheKey = null,
        useCache = false,
        retry = API.RETRIES
    } = {}) {

        if (!navigator.onLine) {
            OfflineQueue.push({ endpoint, form: [...formData] });
            throw new NetworkError("You are offline. Request saved.");
        }

        const url = API.BASE_URL + endpoint;

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), API.TIMEOUT);

        const headers = {
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-Token": API.CSRF_TOKEN,
        };

        // ===============================================
        // Cache logic
        // ===============================================
        if (useCache && cacheKey && APICache.get(cacheKey)) {
            try {
                return JSON.parse(APICache.get(cacheKey));
            } catch {}
        }

        try {
            UI.showLoader("Processing...");

            const response = await fetch(url, {
                method: "POST",
                body: formData,
                signal: controller.signal,
                headers,
            });

            clearTimeout(timer);
            UI.hideLoader();

            if (!response.ok) {
                if (response.status >= 500 && retry > 0) {
                    await this.#backoff(API.RETRIES - retry);
                    return API.send(formData, { endpoint, retry: retry - 1 });
                }
                throw new APIError(`Server Error: ${response.status}`);
            }

            const json = await response.json();

            if (cacheKey) APICache.set(cacheKey, json);

            return API.sanitizeResponse(json);

        } catch (err) {
            UI.hideLoader();

            // Timeout
            if (err.name === "AbortError") {
                if (retry > 0) {
                    await this.#backoff(API.RETRIES - retry);
                    return API.send(formData, { endpoint, retry: retry - 1 });
                }
                throw new TimeoutError();
            }

            // Offline after beginning request
            if (!navigator.onLine) {
                OfflineQueue.push({ endpoint, form: [...formData] });
                throw new NetworkError("Lost connection. Request saved.");
            }

            // Retry on any recoverable error
            if (retry > 0) {
                await this.#backoff(API.RETRIES - retry);
                return API.send(formData, { endpoint, retry: retry - 1 });
            }

            UI.alert("Error", err.message, "error");

            if (API.DEV_MODE) console.error("API ERROR:", err);

            throw err;
        }
    }

    // =====================================================
    // Clamp duplicate requests — Avoid race conditions
    // =====================================================
    static #pending = new Map();

    static async requestOnce(key, requestFn) {
        if (API.#pending.has(key)) {
            return API.#pending.get(key);
        }
        const promise = requestFn().finally(() => API.#pending.delete(key));
        API.#pending.set(key, promise);
        return promise;
    }

    // =====================================================
    // Exponential backoff for retries
    // =====================================================
    static async #backoff(step) {
        const delay = step * step * API.BACKOFF_FACTOR;
        return new Promise(res => setTimeout(res, delay));
    }

    // =====================================================
    // Remove malicious properties from JSON
    // =====================================================
    static sanitizeResponse(obj) {
        const clean = (val) => {
            if (typeof val === "string") {
                return val.replace(/[<>]/g, ""); // Prevent XSS from server
            }
            if (Array.isArray(val)) return val.map(clean);
            if (typeof val === "object" && val !== null) {
                const safe = {};
                for (const k in val) safe[k] = clean(val[k]);
                return safe;
            }
            return val;
        };
        return clean(obj);
    }

    // =====================================================
    // Process the offline queue after internet returns
    // =====================================================
    static async processOfflineQueue() {
        const queue = OfflineQueue.getAll();
        if (!queue.length) return;

        for (const req of queue) {
            const fd = new FormData();
            req.form.forEach(([k, v]) => fd.append(k, v));
            await API.send(fd, { endpoint: req.endpoint });
        }

        OfflineQueue.clear();
    }
}

// ===========================================
// Auto-run offline queue when internet returns
// ===========================================
window.addEventListener("online", () => {
    API.processOfflineQueue();
});

// =========================
// Utility: UI Handlers
// =========================
class UI {
    static alert(title, text, icon) {
        Swal.fire({ title, text, icon });
    }

    static confirm({ title, text, icon = "warning" }) {
        return Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText: "Yes",
            cancelButtonText: "Cancel"
        });
    }

    static showLoader(text) {
        Swal.fire({
            title: text,
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false,
            allowEscapeKey: false
        });
    }

    static hideLoader() {
        Swal.close();
    }
}

// =========================
// Component: Form Toggle
// =========================
class FormToggle {
    constructor(studentBtn, teacherBtn) {
        DOM.on(studentBtn, "click", () => this.show("student"));
        DOM.on(teacherBtn, "click", () => this.show("teacher"));
    }

    show(type) {
        const isStudent = type === "student";

        DOM[isStudent ? "show" : "hide"]("studentForm");
        DOM[isStudent ? "hide" : "show"]("teacherForm");

        DOM[isStudent ? "show" : "hide"]("studentsTableDiv");
        DOM[isStudent ? "hide" : "show"]("teachersTableDiv");
    }
}

// =========================
// Component: CRUD Handler
// =========================
class CRUD {
    static initForms() {
        DOM.$all("form").forEach(form => {
            form.addEventListener("submit", async e => {
                e.preventDefault();

                const fd = new FormData(form);
                fd.append("action", form.id === "teacherForm" ? "add_teacher" : "add_student");

                const res = await API.send(fd);

                if (res?.status === "success") {
                    UI.alert("Success", res.message, "success");
                    setTimeout(() => location.reload(), 800);
                } else {
                    UI.alert("Error", res?.message, "error");
                }
            });
        });
    }

    static initDeletes() {
        DOM.on(".deleteBtn", "click", async e => {
            const btn = e.target;
            const { id, type } = btn.dataset;

            const confirm = await UI.confirm({
                title: "Are you sure?",
                text: `Delete this ${type}?`
            });

            if (!confirm.isConfirmed) return;

            const fd = new FormData();
            fd.append("action", "delete_" + type);
            fd.append("id", id);

            const res = await API.send(fd);
            if (res?.status === "success") location.reload();
        });
    }

    static initUpdates() {
        DOM.on(".updateBtn", "click", async e => {
            const tr = e.target.closest("tr");
            const type = e.target.dataset.type;

            const fields = [...tr.children].map(td => Sanitizer.text(td.innerText));

            const [id, username, email, phone, year, subject = ""] = fields;

            const { value: formValues } = await Swal.fire({
                title: "Update " + type,
                html: `
                    <input id="swal-username" class="swal2-input" value="${username}">
                    <input id="swal-email" class="swal2-input" value="${email}">
                    <input id="swal-phone" class="swal2-input" value="${phone}">
                    <input id="swal-year" class="swal2-input" value="${year}">
                    ${type === "teachers" ? `<input id="swal-subject" class="swal2-input" value="${subject}">` : ""}
                `,
                preConfirm: () => ({
                    username: DOM.$("#swal-username").value,
                    email: DOM.$("#swal-email").value,
                    phone: DOM.$("#swal-phone").value,
                    year: DOM.$("#swal-year").value,
                    subject: type === "teachers" ? DOM.$("#swal-subject").value : ""
                })
            });

            if (!formValues) return;

            const fd = new FormData();
            Object.entries(formValues).forEach(([k, v]) => fd.append(k, v));
            fd.append("id", id);
            fd.append("action", "update_" + type);

            const res = await API.send(fd);
            if (res?.status === "success") location.reload();
        });
    }
}

// =========================
// Component: Searching
// =========================
class TableSearch {
    constructor(type) {
        DOM.on(`#search${type}`, "input", e => {
            const filter = e.target.value.toLowerCase();
            const rows = DOM.$all(`#${type.toLowerCase()}Table tbody tr`);

            rows.forEach(row => {
                const visible = [...row.children].some(td =>
                    td.innerText.toLowerCase().includes(filter)
                );
                row.style.display = visible ? "" : "none";
            });
        });
    }
}

// =========================
// Init Everything
// =========================
document.addEventListener("DOMContentLoaded", () => {
    new FormToggle("#showStudent", "#showTeacher");
    CRUD.initForms();
    CRUD.initDeletes();
    CRUD.initUpdates();

    ["Teachers", "Students"].forEach(t => new TableSearch(t));

    if (Internet.isSlow()) {
        UI.alert("Slow Internet", "You're on a slow connection. Loading may take longer.", "warning");
    }
});
