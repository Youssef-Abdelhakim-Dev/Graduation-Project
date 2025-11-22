<?php
require __DIR__ . "/vendor/autoload.php";
use Dotenv\Dotenv;

// ================= LOAD ENV =================
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// ================= SESSION =================
session_set_cookie_params([
    "lifetime" => 0,
    "path"     => "/",
    "secure"   => isset($_SERVER['HTTPS']),
    "httponly" => true,
    "samesite" => "Strict"
]);
session_start();

// ================= ERROR =================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// ================= DB CLASS =================
class DB {
    private mysqli $conn;
    public function __construct() {
        $this->conn = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_NAME']);
        if($this->conn->connect_error) throw new Exception("Database connection failed");
    }
    public function prepare(string $sql): mysqli_stmt {
        $stmt = $this->conn->prepare($sql);
        if(!$stmt) throw new Exception("Prepare failed: ".$this->conn->error);
        return $stmt;
    }
    public function emailExists(string $email): bool {
        $stmt = $this->prepare("SELECT id FROM admins WHERE email=? LIMIT 1");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $stmt->store_result();
        return $stmt->num_rows > 0;
    }
}

// ================= CSRF =================
class CSRF {
    public static function token(): string {
        if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }
    public static function check(string $token): bool {
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'],$token);
    }
}

// ================= RATE LIMIT =================
class RateLimit {
    private int $maxAttempts, $window, $lockout;
    public function __construct() {
        $this->maxAttempts = intval($_ENV['MAX_ATTEMPTS']??5);
        $this->window = intval($_ENV['ATTEMPT_WINDOW']??900);
        $this->lockout = intval($_ENV['LOCKOUT_SECONDS']??900);
        if(!isset($_SESSION['rate_limit'])) $_SESSION['rate_limit']=[];
    }
    private function now(): int { return time(); }
    public function attempt(string $ip): void {
        $rec = $_SESSION['rate_limit'][$ip]??['attempts'=>0,'last'=>0,'locked_until'=>0];
        if($this->now()-$rec['last']>$this->window) $rec['attempts']=0;
        $rec['attempts']++; $rec['last']=$this->now();
        if($rec['attempts'] >= $this->maxAttempts) $rec['locked_until']=$this->now()+$this->lockout;
        $_SESSION['rate_limit'][$ip]=$rec;
    }
    public function blocked(string $ip): bool {
        $rec = $_SESSION['rate_limit'][$ip]??null;
        return $rec && $rec['locked_until']>$this->now();
    }
}

// ================= REMEMBER ME =================
class RememberMe {
    public function create(string $token): void {
        setcookie("remember",$token,time()+30*24*3600,"/","",isset($_SERVER['HTTPS']),true);
        $_SESSION['remember']=$token;
    }
    public function exists(): bool {
        return !empty($_COOKIE['remember']) || !empty($_SESSION['remember']);
    }
}

// ================= AUTH =================
class Auth {
    private DB $db; private RememberMe $remember;
    public function __construct(DB $db, RememberMe $remember){
        $this->db=$db; $this->remember=$remember;
    }

    private function validatePassword(string $password): bool {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/',$password);
    }
    private function validatePhone(string $phone): bool {
        return preg_match('/^\+?\d{7,15}$/',$phone);
    }

    public function register(string $name,string $email,string $password,string $phone): bool {
        if($this->db->emailExists($email)) throw new Exception("Email already exists");
        if(!$this->validatePassword($password)) throw new Exception("Password too weak");
        if(!$this->validatePhone($phone)) throw new Exception("Invalid phone");
        if(empty($name) || strlen($name)<2) throw new Exception("Name too short");

        $stmt = $this->db->prepare("INSERT INTO admins(name,email,password,phone) VALUES (?,?,?,?)");
        $hash = password_hash($password,PASSWORD_DEFAULT);
        $stmt->bind_param("ssss",$name,$email,$hash,$phone);
        if(!$stmt->execute()) throw new Exception("Failed to register: ".$stmt->error);
        return true;
    }

    public function login(string $email,string $password): bool {
        $stmt = $this->db->prepare("SELECT password,name FROM admins WHERE email=? LIMIT 1");
        $stmt->bind_param("s",$email); $stmt->execute();
        $stmt->bind_result($hash,$name);
        if(!$stmt->fetch() || !password_verify($password,$hash)) throw new Exception("Invalid credentials");

        $_SESSION['admin_logged']=true;
        $_SESSION['email']=$email;
        $_SESSION['name']=$name;

        if(!empty($_POST['remember'])){
            $token = bin2hex(random_bytes(32));
            $this->remember->create($token);
        }
        return true;
    }
}

// ================= INIT =================
$db = new DB();
$rate = new RateLimit();
$remember = new RememberMe();
$auth = new Auth($db,$remember);

if($remember->exists()) $_SESSION['admin_logged']=true;

// ================= HANDLE POST =================
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login'){
    header('Content-Type: application/json');
    $ip = $_SERVER['REMOTE_ADDR']??'0.0.0.0';

    if($rate->blocked($ip)){ echo json_encode(['status'=>'error','message'=>"Too many attempts"]); exit; }
    if(!CSRF::check($_POST['csrf']??'')){ echo json_encode(['status'=>'error','message'=>"Invalid CSRF"]); exit; }

    $rate->attempt($ip);

    $name = trim($_POST['name']??'');
    $email = trim($_POST['email']??'');
    $phone = trim($_POST['phone']??'');
    $password = $_POST['password']??'';

    try{
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email");
        $auth->register($name,$email,$password,$phone);
        $auth->login($email,$password);
        echo json_encode(['status'=>'ok','redirect'=>'/projectGraduation/admin.php','username'=>$name]);
    }catch(Exception $e){
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

$csrf = CSRF::token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-slate-900 text-white">

<!-- Online/Offline Banner -->
<div id="networkStatus" class="fixed top-0 left-0 w-full text-center p-2 font-bold text-white hidden"></div>

<div class="w-full max-w-md bg-slate-800 p-8 rounded-2xl shadow-xl">
<h2 class="text-2xl mb-6 font-bold">Admin Login</h2>

<form id="form" action="javascript:void(0);">
<input type="hidden" name="csrf" value="<?= $csrf ?>">
<input type="hidden" name="action" value="login">

<div class="mb-4 relative">
<label class="block mb-1">Name</label>
<i class="fa fa-user absolute left-3 top-9 text-gray-500"></i>
<input name="name" class="w-full p-3 pl-10 rounded bg-slate-100 text-black">
</div>

<div class="mb-4 relative">
<label class="block mb-1">Email</label>
<i class="fa fa-envelope absolute left-3 top-9 text-gray-500"></i>
<input name="email" class="w-full p-3 pl-10 rounded bg-slate-100 text-black">
</div>

<div class="mb-4 relative">
<label class="block mb-1">Phone</label>
<i class="fa fa-phone absolute left-3 top-9 text-gray-500"></i>
<input name="phone" class="w-full p-3 pl-10 rounded bg-slate-100 text-black">
</div>

<div class="mb-4 relative">
<label class="block mb-1">Password</label>
<i class="fa fa-lock absolute left-3 top-9 text-gray-500"></i>
<input name="password" type="password" class="w-full p-3 pl-10 rounded bg-slate-100 text-black">
</div>

<div class="mb-4">
<label><input type="checkbox" name="remember" value="1"> Remember me</label>
</div>

<button class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 rounded font-bold">Login</button>
</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // ================= NETWORK STATUS =================
  class NetworkStatus {
    constructor(bannerId) {
      this.banner = document.getElementById(bannerId);
      this.update();
      window.addEventListener('online', () => this.update());
      window.addEventListener('offline', () => this.update());
    }
    update() {
      if (!this.banner) return;
      if (navigator.onLine) {
        this.banner.innerHTML = `<i class="fa fa-check-circle mr-2"></i> You are online`;
        this.banner.className = `fixed top-0 left-0 w-full text-center p-2 font-bold bg-green-600 text-white shadow-md transition-all duration-500`;
        setTimeout(() => this.banner.classList.add('hidden'), 3000);
      } else {
        this.banner.innerHTML = `<i class="fa fa-exclamation-triangle mr-2"></i> You are offline`;
        this.banner.className = `fixed top-0 left-0 w-full text-center p-2 font-bold bg-red-600 text-white shadow-md transition-all duration-500`;
        this.banner.classList.remove('hidden');
      }
    }
  }
  new NetworkStatus("networkStatus");

  // ================= FORM HANDLER =================
  class FormHandler {
    constructor(formId, url) {
      this.form = document.getElementById(formId);
      this.url = url || window.location.href;
      this.fields = ["name", "email", "phone"];
      this.validations = {
        name: /^.{2,100}$/,
        email: /^\S+@\S+\.\S+$/,
        // Example password regex: min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 symbol
        password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/,
        phone: /^\+?\d{7,15}$/
      };

      if (!this.form) return;

      this.loadSaved();
      this.form.addEventListener("submit", e => this.submit(e));
      this.form.addEventListener("input", e => this.saveToLocal(e));
    }

    // ================= VALIDATION =================
    validate(fd) {
      for (let [key, value] of fd.entries()) {
        if (this.validations[key] && !this.validations[key].test(value)) {
          let guide = '';
          switch(key) {
            case 'name': guide = 'Name must be 2-100 characters long.'; break;
            case 'email': guide = 'Enter a valid email address like example@mail.com.'; break;
            case 'password':
              guide = 'Password must be at least 8 characters and include:<ul class="list-disc pl-5 text-left mt-1">' +
                      '<li>1 uppercase letter</li>' +
                      '<li>1 lowercase letter</li>' +
                      '<li>1 number</li>' +
                      '<li>1 special character</li></ul>';
              break;
            case 'phone': guide = 'Phone must be 7-15 digits, can start with +.'; break;
          }

          Swal.fire({
            icon: "error",
            title: `<span class="text-red-600 font-bold">Invalid ${key}</span>`,
            html: `<p class="text-gray-700 text-sm mt-1">${guide}</p>`,
            showCloseButton: true
          });
          if (this.form.elements[key]) this.form.elements[key].focus();
          return false;
        }
      }
      return true;
    }

    // ================= LOCAL STORAGE =================
    saveToLocal(e) {
      const { name, value } = e.target;
      if (name && name !== "password" && this.fields.includes(name)) {
        const data = JSON.parse(localStorage.getItem("login_data") || "{}");
        localStorage.setItem("login_data", JSON.stringify({ ...data, [name]: value }));
      }
    }

    loadSaved() {
      const data = JSON.parse(localStorage.getItem("login_data") || "{}");
      this.fields.forEach(f => {
        if (data[f] && this.form.elements[f]) this.form.elements[f].value = data[f];
      });
    }

    // ================= LOADING SVG =================
    showLoading() {
      return Swal.fire({
        html: `
          <div class="flex flex-col items-center">
            <svg class="animate-spin h-16 w-16 text-indigo-600 mb-4" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
            <p class="font-bold text-gray-800 text-lg">Processing...</p>
          </div>`,
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
      });
    }

    // ================= AJAX SUBMIT =================
    async submit(e) {
      e.preventDefault();
      const fd = new FormData(this.form);

      if (!this.validate(fd)) return;
      if (!navigator.onLine) {
        Swal.fire({
          icon: "warning",
          title: `<span class="text-yellow-600 font-bold">Offline</span>`,
          html: `<p class="text-gray-700 text-sm mt-1">Please check your internet connection</p>`,
        });
        return;
      }

      try {
        this.showLoading();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);

        const response = await fetch(this.url, {
          method: "POST",
          body: fd,
          signal: controller.signal,
          headers: { "X-Requested-With": "XMLHttpRequest" }
        });

        clearTimeout(timeout);
        Swal.close();

        if (!response.ok) {
          let msg = `Server returned status ${response.status}`;
          switch (response.status) {
            case 400: msg = "Bad Request – Invalid data"; break;
            case 401: msg = "Unauthorized – Invalid credentials"; break;
            case 403: msg = "Forbidden – Access denied"; break;
            case 404: msg = "Not Found – Endpoint missing"; break;
            case 500: msg = "Internal Server Error"; break;
          }
          Swal.fire({
            icon: "error",
            title: `<span class="text-red-600 font-bold">Error ${response.status}</span>`,
            html: `<p class="text-gray-700 text-sm mt-2">${msg}</p>`,
          });
          return;
        }

        const data = await response.json();

        if (data?.status === "ok") {
          const saved = JSON.parse(localStorage.getItem("login_data") || "{}");
          this.fields.forEach(f => saved[f] = fd.get(f));
          localStorage.setItem("login_data", JSON.stringify(saved));

          Swal.fire({
            icon: "success",
            title: `<span class="text-green-600 font-bold">Welcome!</span>`,
            html: `<p class="text-gray-700 text-sm mt-1">Hello <strong class="text-indigo-600">${data.username}</strong></p>`,
            timer: 1500,
            showConfirmButton: false
          });
          setTimeout(() => window.location = data.redirect, 1600);
        } else {
          Swal.fire({
            icon: "error",
            title: `<span class="text-red-600 font-bold">Login Failed</span>`,
            html: `<p class="text-gray-700 text-sm mt-1">${data.message || "Unknown error"}</p>`
          });
        }

      } catch (err) {
        Swal.close();
        const msg = err?.name === "AbortError" ? "Request timed out" : err.message;
        Swal.fire({
          icon: "error",
          title: `<span class="text-red-600 font-bold">Unexpected Error</span>`,
          html: `<p class="text-gray-700 text-sm mt-1">${msg}</p>`
        });
      }
    }
  }

  new FormHandler("form");
});
</script>

</body>
</html>
