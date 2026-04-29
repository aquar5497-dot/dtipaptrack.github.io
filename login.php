<?php
session_start();
require_once 'config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // Auto-detect role — no "Login As" needed
        $stmt = $conn->prepare("SELECT id, username, password, role, permissions, is_suspended, suspended_reason FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Block suspended accounts
                if (!empty($user['is_suspended'])) {
                    $suspend_reason = !empty($user['suspended_reason']) ? $user['suspended_reason'] : 'No reason provided.';
                    $error = "Your account has been <strong>suspended</strong> by an Administrator.<br><small>" . htmlspecialchars($suspend_reason) . "</small><br><small>Please contact your system administrator.</small>";
                } else {
                    $_SESSION['user_id']     = $user['id'];
                    $_SESSION['username']    = $user['username'];
                    $_SESSION['role']        = $user['role'];
                    $_SESSION['permissions'] = json_decode($user['permissions'] ?? '[]', true) ?: [];

                    $is_admin = (strtolower(trim($user['role'])) === 'administrator');
                    require_once __DIR__ . '/inc/audit.php';
                    logAudit('AUTH','LOGIN',(int)$user['id'],$user['username'],[],[]);
                    header("Location: " . ($is_admin ? "admin.php" : "index.php"));
                    exit;
                }
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    } else {
        $error = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTI PAPtrack Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #0a2e63;
            background-image: linear-gradient(135deg, #002f6c 0%, #0059b3 100%);
        }
        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 1.2s ease-out forwards;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .glow-pulse {
            animation: glow 3s ease-in-out infinite;
            border-radius: 1rem;
        }
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 10px #ffffff40, 0 0 20px #007bff40, 0 0 40px #007bff40; }
            50%       { box-shadow: 0 0 20px #ffffff80, 0 0 30px #007bff80, 0 0 50px #007bff80; }
        }
        .input-transition { transition: border-color 0.3s, box-shadow 0.3s; }

        /* Hide browser's own password-eye icon */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear { display: none; }
        input[type="password"]::-webkit-contacts-auto-fill-button,
        input[type="password"]::-webkit-textfield-decoration-container {
            visibility: hidden; display: none !important; pointer-events: none;
        }
        .password-container { position: relative; }
        #togglePassword {
            position: absolute; top: 50%; right: 0.5rem;
            transform: translateY(-50%); z-index: 10;
            height: 100%; cursor: pointer;
        }
    </style>
</head>
<body class="flex min-h-screen">

    <!-- Left panel — branding -->
    <div class="hidden lg:flex w-1/2 text-white flex-col justify-center items-center p-8">
        <div class="fade-in delay-100 flex flex-col items-center">
            <div class="glow-pulse p-2 bg-white/10 rounded-2xl">
                <img src="dti.jpg" alt="DTI Logo" class="w-32 h-32 rounded-xl shadow-lg border-2 border-white/70">
            </div>
            <h1 class="text-4xl font-bold tracking-wide text-center fade-in mt-6">Department of Trade and Industry</h1>
            <p class="text-lg text-blue-100 text-center max-w-sm mt-4 fade-in">
                Promoting inclusive innovation and industrialization through DTI PAPtrack.
            </p>
            <div class="mt-8 w-full max-w-lg h-80 flex justify-center items-center fade-in delay-300">
                <lottie-player
                    src="tracking-animation.json"
                    background="transparent"
                    speed="1"
                    style="width:100%;height:100%;"
                    loop autoplay class="p-4">
                </lottie-player>
            </div>
        </div>
    </div>

    <!-- Right panel — form -->
    <div class="flex w-full lg:w-1/2 justify-center items-center bg-gray-50 fade-in delay-700">
        <div class="bg-white shadow-2xl rounded-2xl p-10 w-11/12 max-w-md">
            <h2 class="text-3xl font-extrabold text-center text-blue-900 mb-6">Welcome to DTI PAPtrack</h2>

            <?php if ($error): ?>
                <div role="alert" aria-live="assertive"
                     class="bg-red-100 text-red-700 p-3 mb-4 rounded text-center border border-red-300">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-gray-700 font-medium mb-1">Username</label>
                    <input type="text" name="username" id="username" required autofocus
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-600 outline-none input-transition"
                        placeholder="Enter username">
                </div>

                <div class="password-container relative">
                    <label for="password" class="block text-gray-700 font-medium mb-1">Password</label>
                    <input type="password" name="password" id="password" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-600 outline-none pr-10 input-transition"
                        placeholder="Enter password">
                    <button type="button" id="togglePassword" aria-label="Toggle Password Visibility"
                        class="flex items-center justify-center text-gray-500 hover:text-blue-600 focus:outline-none transition duration-150">
                        <i id="eye-icon" class="fas fa-eye" style="margin-top:25px;"></i>
                    </button>
                </div>

                <button type="submit" id="signInButton"
                    class="w-full bg-blue-800 hover:bg-blue-900 text-white py-2 rounded-lg font-semibold shadow-md transition duration-300 transform hover:scale-[1.01] active:scale-[0.99] focus:outline-none focus:ring-4 focus:ring-blue-300">
                    Sign In
                </button>
            </form>

            <p class="text-center text-gray-500 text-sm mt-6">© <?= date('Y') ?> Department of Trade and Industry</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const passwordInput     = document.getElementById('password');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const eyeIcon           = document.getElementById('eye-icon');

        togglePasswordBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeIcon.classList.toggle('fa-eye',      !isPassword);
            eyeIcon.classList.toggle('fa-eye-slash', isPassword);
        });
    });
    </script>
</body>
</html>
