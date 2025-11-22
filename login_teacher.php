<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Registration - Educational Platform</title>
<meta name="description" content="Register as a teacher on our educational platform to manage courses, subjects, and students effectively.">
<meta name="keywords" content="Teacher Registration, Education, School, Subjects, Teaching, Teacher Portal">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js" defer></script>
<script src="https://unpkg.com/feather-icons"></script>

<style>
body { background: linear-gradient(to right, #f0f4f8, #d9e2ec); font-family: 'Inter', sans-serif; }
.form-container { max-width: 550px; margin: 3rem auto; background: #fff; padding: 2.5rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
.input-wrapper { position: relative; margin-bottom:1rem; }
.input-wrapper i { position: absolute; right: 12px; top: 12px; color: #9ca3af; }
.tooltip { position: absolute; top: -1.5rem; right: 0; background: #111; color: #fff; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; display: none; }
input:focus + .tooltip { display: block; }
#loader { display: none; vertical-align: middle; }
#preview { max-width: 80px; max-height: 80px; border-radius: 0.5rem; margin-top: 0.5rem; display:none;}
.progress-bar { height: 5px; background: #e0e0e0; border-radius: 5px; margin-bottom: 1rem; }
.progress { height: 5px; background: #4f46e5; border-radius: 5px; width: 0%; transition: width 0.3s ease; }
</style>
</head>
<body>
<main class="form-container" role="main">
  <h1 class="text-2xl font-bold mb-6 text-center text-gray-800">Teacher login</h1>

  <!-- Progress Bar -->
  <div class="progress-bar h-2 bg-gray-200 rounded-full mb-6">
    <div class="progress h-full bg-blue-500 rounded-full" id="progress"></div>
  </div>

  <form id="teacherForm" enctype="multipart/form-data" class="space-y-4" aria-label="Teacher Registration Form">

    <!-- Step 1: Personal Info -->
    <section class="form-step" data-step="1">
      <fieldset class="space-y-4">
        <legend class="text-lg font-semibold">Personal Info</legend>

        <div class="input-wrapper relative">
          <label for="name" class="block mb-1 font-medium">Full Name</label>
          <input type="text" id="name" name="name" placeholder="John Doe" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-400">
          <i data-feather="user" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>

        <div class="input-wrapper relative">
          <label for="subject" class="block mb-1 font-medium">Subject</label>
          <input type="text" id="subject" name="subject" placeholder="Mathematics" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-400">
          <i data-feather="book" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>

        <button type="button" class="next-btn w-full bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Next</button>
      </fieldset>
    </section>

    <!-- Step 2: Account Info -->
    <section class="form-step hidden" data-step="2">
      <fieldset class="space-y-4">
        <legend class="text-lg font-semibold">Account Info</legend>

        <div class="input-wrapper relative">
          <label for="email" class="block mb-1 font-medium">Email</label>
          <input type="email" id="email" name="email" placeholder="example@mail.com" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-400">
          <i data-feather="mail" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>

        <div class="input-wrapper relative">
          <label for="password" class="block mb-1 font-medium">Password</label>
          <input type="password" id="password" name="password" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-400">
          <i data-feather="lock" class="absolute right-10 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
          <button type="button" id="togglePassword" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
            <i data-feather="eye"></i>
          </button>
        </div>

        <div class="flex justify-between gap-2">
          <button type="button" class="prev-btn w-1/2 bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded">Previous</button>
          <button type="button" class="next-btn w-1/2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">Next</button>
        </div>
      </fieldset>
    </section>

    <!-- Step 3: Contact & Profile -->
    <section class="form-step hidden" data-step="3">
      <fieldset class="space-y-4">
        <legend class="text-lg font-semibold">Contact & Profile</legend>

        <div class="input-wrapper relative">
          <label for="phone" class="block mb-1 font-medium">Phone Number</label>
          <input type="tel" id="phone" name="phone" placeholder="0123456789" pattern="\d{10,}" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-400">
          <i data-feather="phone" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>

        <div class="input-wrapper relative">
          <label for="year" class="block mb-1 font-medium">Year</label>
          <input type="number" id="year" name="year" min="1" placeholder="5" required class="w-full border border-gray-300 rounded px-3 py-2 focus:ring-2 focus:ring-blue-400">
          <i data-feather="calendar" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
        </div>

        <!-- Profile Image Upload -->
        <div class="flex flex-col mt-4">
          <label for="teacher_image" class="mb-2 font-semibold text-gray-700">Profile Image</label>
          <input type="file" id="teacher_image" name="teacher_image" accept="image/*" hidden>
          <button type="button" onclick="document.getElementById('teacher_image').click()" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded shadow flex items-center space-x-2">
            <i data-feather="upload" class="w-5 h-5"></i>
            <span>Upload Profile Image</span>
          </button>
          <span id="fileName" class="mt-2 text-gray-500 text-sm">No file selected</span>
          <img id="preview" src="#" alt="Profile Preview" class="mt-2 rounded shadow-sm" style="display:none;">
        </div>

        <div class="flex justify-between gap-2">
          <button type="button" class="prev-btn w-1/2 bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded">Previous</button>
          <button type="submit" class="w-1/2 bg-gradient-to-r from-blue-400 to-indigo-500 hover:from-blue-500 hover:to-indigo-600 text-white font-bold py-2 px-4 rounded shadow flex justify-center items-center space-x-2">
            <span>Submit</span>
            <svg id="loader" viewBox="0 0 100 100" width="25" height="25" class="animate-spin hidden">
              <circle cx="50" cy="50" r="35" stroke="white" stroke-width="10" fill="none" stroke-linecap="round" stroke-dasharray="180 60"/>
            </svg>
          </button>
        </div>
      </fieldset>
    </section>

  </form>
</main>


<script>
class TeacherRegistrationForm {
    constructor(formId) {
        this.form = document.getElementById(formId);
        this.steps = this.form.querySelectorAll(".form-step");
        this.progress = document.getElementById("progress");
        this.currentStep = 1;
        this.loader = this.form.querySelector("#loader");
        this.passwordInput = this.form.querySelector("#password");
        this.togglePasswordBtn = this.form.querySelector("#togglePassword");
        this.fileInput = this.form.querySelector("#teacher_image");
        this.fileName = this.form.querySelector("#fileName");
        this.preview = this.form.querySelector("#preview");

        this.init();
    }

    init() {
        feather.replace();
        this.showStep(this.currentStep);
        this.bindEvents();
    }

    bindEvents() {
        // Next buttons
        this.form.querySelectorAll('.next-btn').forEach(btn => {
            btn.addEventListener('click', () => this.nextStep());
        });

        // Previous buttons
        this.form.querySelectorAll('.prev-btn').forEach(btn => {
            btn.addEventListener('click', () => this.prevStep());
        });

        // Password toggle
        if (this.togglePasswordBtn && this.passwordInput) {
            this.togglePasswordBtn.addEventListener('click', () => this.togglePassword());
        }

        // Form submission
        this.form.addEventListener('submit', (e) => this.handleSubmit(e));

        // Profile image preview
        if (this.fileInput) {
            this.fileInput.addEventListener('change', () => this.previewImage());
        }
    }

    showStep(step) {
        this.steps.forEach((s, i) => {
            s.classList.add('hidden');
            if (i === step - 1) s.classList.remove('hidden');
        });
        this.progress.style.width = ((step - 1) / (this.steps.length - 1)) * 100 + '%';
    }

    nextStep() {
        if (this.currentStep < this.steps.length) {
            this.currentStep++;
            this.showStep(this.currentStep);
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }

    togglePassword() {
        const type = this.passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        this.passwordInput.setAttribute('type', type);
        this.togglePasswordBtn.innerHTML = `<i data-feather="${type === 'password' ? 'eye' : 'eye-off'}"></i>`;
        feather.replace();
    }

    previewImage() {
        if (this.fileInput.files.length) {
            this.fileName.textContent = this.fileInput.files[0].name;
            this.preview.src = URL.createObjectURL(this.fileInput.files[0]);
            this.preview.style.display = 'block';
        } else {
            this.fileName.textContent = 'No file selected';
            this.preview.style.display = 'none';
        }
    }

    async handleSubmit(e) {
        e.preventDefault();
        const fd = new FormData(this.form);
        fd.append("register", 1);
        this.loader.style.display = 'inline-block';

        try {
            const res = await axios.post("action_login_teacher.php", fd);
            this.loader.style.display = 'none';

            if (!res.data || !res.data.status) {
                Swal.fire({ icon: 'error', title: 'Unexpected Response', text: 'The server returned an unexpected response.' });
                return;
            }

            if (res.data.status === "success") {
                Swal.fire({ icon: "success", title: "Success!", text: res.data.message || "Operation completed successfully.", timer: 1500, showConfirmButton: false });
                setTimeout(() => window.location.href = res.data.redirect || "dashboard.php", 1500);
            } else if (res.data.status === "validation_error") {
                const errors = res.data.errors || {};
                const errorMessages = Object.values(errors).flat().join("\n");
                Swal.fire({ icon: 'warning', title: 'Validation Error', html: `<pre style="text-align:left;">${errorMessages}</pre>` });
            } else {
                Swal.fire({ icon: "error", title: "Error", text: res.data.message || "An unknown error occurred." });
            }

        } catch (err) {
            this.loader.style.display = 'none';
            if (err.response) Swal.fire({ icon: 'error', title: `Server Error: ${err.response.status}`, text: err.response.data.message || JSON.stringify(err.response.data) });
            else if (err.request) Swal.fire({ icon: 'error', title: 'Network Error', text: 'No response from server. Check your internet connection.' });
            else Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Something went wrong while processing the request.' });
        }
    }
}

// Initialize when DOM loaded
document.addEventListener('DOMContentLoaded', () => {
    new TeacherRegistrationForm('teacherForm');
});

</script>

</body>
</html>
