class Ajax {
    static controller = null;

    static async get(url) {
        if (Ajax.controller) Ajax.controller.abort();
        Ajax.controller = new AbortController();

        const res = await fetch(url, { signal: Ajax.controller.signal });
        return await res.json();
    }
}

class UI {
    static skeleton(count = 6) {
        const html = [...Array(count)].map(() => `
            <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow skeleton h-40"></div>
        `).join("");
        document.getElementById("lecture-list").innerHTML = html;
    }

    static card(lecture) {
        return `
            <article class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow border">
                <h3 class="text-xl font-semibold">${lecture.name}</h3>
                <p class="mt-2 text-gray-600">${lecture.description}</p>
                <p class="text-sm text-gray-500 mt-1"><i class="fa fa-user"></i> ${lecture.doctor}</p>

                <div class="flex gap-2 mt-4 flex-wrap">
                    <button onclick="App.preview('${lecture.path}','${lecture.type}')"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                        <i class="fa fa-eye"></i> Preview
                    </button>

                    <button onclick="App.download('${lecture.path}')"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg">
                        <i class="fa fa-download"></i> Download
                    </button>

                    <button onclick="App.copy('${lecture.path}')"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg">
                        <i class="fa fa-link"></i> Copy Link
                    </button>
                </div>
            </article>
        `;
    }
}

class App {
    static data = [];
    static page = 1;
    static perPage = 6;
    static search = "";

    static async init() {
        UI.skeleton();
        App.data = await Ajax.get("<?= $_SERVER['PHP_SELF'] ?>?json=1");

        document.getElementById("search-input").addEventListener("input", e => {
            App.search = e.target.value.toLowerCase();
            App.page = 1;
            App.render();
        });

        App.render();
    }

    static filtered() {
        return App.data.filter(c =>
            c.name.toLowerCase().includes(App.search) ||
            c.description.toLowerCase().includes(App.search)
        );
    }

    static render() {
        const list = document.getElementById("lecture-list");
        const results = App.filtered();

        const start = (App.page - 1) * App.perPage;
        const sliced = results.slice(start, start + App.perPage);

        list.innerHTML = sliced.map(UI.card).join("");

        App.renderPagination(results.length);
    }

    static renderPagination(total) {
        const pages = Math.ceil(total / App.perPage);
        const el = document.getElementById("pagination");
        let html = "";

        for (let i = 1; i <= pages; i++) {
            html += `
                <button onclick="App.goto(${i})"
                    class="px-3 py-2 rounded ${i === App.page ? 'bg-blue-600 text-white' : 'bg-gray-200'}">
                    ${i}
                </button>`;
        }

        el.innerHTML = html;
    }

    static goto(n) {
        App.page = n;
        App.render();
    }

    // ================= ACTIONS =================

    static preview(path, type) {
        // LOAD PDF WITHOUT FREEZING — uses Web Worker
        if (type === "pdf") {
            worker.postMessage({ path });
            worker.onmessage = e => {
                Swal.fire({
                    title: "PDF Preview",
                    html: `<embed src="${e.data}" type="application/pdf" class="w-full h-[80vh] rounded-xl">`,
                    width: "90%",
                    showConfirmButton: false
                });
            };
        } else {
            Swal.fire({
                html: `<img src="${path}" class="max-h-[80vh] rounded-xl"/>`,
                showConfirmButton: false
            });
        }
    }

    static download(path) {
        const a = document.createElement("a");
        a.href = path;
        a.download = "";
        a.click();
    }

    static copy(path) {
        navigator.clipboard.writeText(path);
        Swal.fire("Copied!", "Link copied to clipboard!", "success");
    }
}

document.addEventListener("DOMContentLoaded", App.init);