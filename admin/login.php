<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

// -----------------------------------------------------------------
// IP-based brute-force protection (file-backed, survives sessions)
// -----------------------------------------------------------------
function _login_attempts_file(string $ip): string
{
    $dir = dirname(__DIR__) . '/logs/login_attempts';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }
    return $dir . '/' . md5($ip) . '.json';
}

function is_login_locked(string $ip): bool
{
    $file = _login_attempts_file($ip);
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true);
    return isset($data['locked_until']) && $data['locked_until'] > time();
}

function record_login_failure(string $ip): void
{
    $file = _login_attempts_file($ip);
    $data = file_exists($file)
        ? json_decode(file_get_contents($file), true)
        : ['count' => 0, 'window_start' => time(), 'locked_until' => 0];

    // Clear stale lockout
    if (!empty($data['locked_until']) && $data['locked_until'] < time()) {
        $data = ['count' => 0, 'window_start' => time(), 'locked_until' => 0];
    }

    $data['count']++;
    if ($data['count'] >= 5) {
        $data['locked_until'] = time() + 900; // 15-minute lockout
    }
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function clear_login_attempts(string $ip): void
{
    $file = _login_attempts_file($ip);
    if (file_exists($file)) {
        @unlink($file);
    }
}

// Already logged in — go straight to dashboard
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (is_login_locked($client_ip)) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            try {
                $db   = Database::connect();
                $stmt = $db->prepare(
                    "SELECT id, username, password_hash, role
                     FROM admin_users
                     WHERE username = :u
                     LIMIT 1"
                );
                $stmt->execute([':u' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    clear_login_attempts($client_ip);

                    // Regenerate session ID on login (session fixation protection)
                    session_regenerate_id(true);

                    $_SESSION['admin_id']       = (int) $user['id'];
                    $_SESSION['admin_username'] = $user['username'];
                    $_SESSION['admin_role']     = $user['role'];
                    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

                    // Record last login
                    $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = :id")
                       ->execute([':id' => $user['id']]);

                    header('Location: index.php');
                    exit;
                } else {
                    record_login_failure($client_ip);
                    $error = is_login_locked($client_ip)
                        ? 'Too many failed attempts. Please try again in 15 minutes.'
                        : 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                $error = 'Database connection failed. Check your config.php credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Puresol Admin</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        maroon: { DEFAULT: '#5C1A2A', light: '#7a2438', dark: '#3d1019' },
                        gold:   { DEFAULT: '#D4A853', light: '#debb75' }
                    },
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui'] }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-br from-stone-50 via-white to-rose-50/30 flex items-center justify-center font-sans antialiased">

    <div class="w-full max-w-[400px] px-5">

        <!-- Brand mark -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-14 h-14 bg-maroon rounded-2xl shadow-lg shadow-maroon/20 mb-4">
                <span class="text-[#D4A853] font-bold text-xl">P</span>
            </div>
            <h1 class="text-gray-900 text-2xl font-semibold tracking-tight">Welcome back</h1>
            <p class="text-gray-400 text-sm mt-1">Sign in to the Puresol admin panel</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm shadow-gray-200/80 border border-gray-100 p-8">

            <?php if ($error !== ''): ?>
            <div class="mb-5 flex items-start gap-2.5 px-4 py-3 bg-red-50 border border-red-100 rounded-xl text-red-700 text-sm">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="on" class="space-y-5">

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">Username</label>
                    <input type="text" id="username" name="username" required autofocus
                           autocomplete="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           placeholder="admin"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-gray-900 text-sm
                                  placeholder-gray-300 outline-none transition-all
                                  focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <input type="password" id="password" name="password" required
                           autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full px-4 py-2.5 rounded-xl border border-gray-200 text-gray-900 text-sm
                                  placeholder-gray-300 outline-none transition-all
                                  focus:border-[#5C1A2A] focus:ring-4 focus:ring-[#5C1A2A]/8">
                </div>

                <button type="submit"
                        class="w-full py-2.5 bg-[#5C1A2A] text-white text-sm font-semibold rounded-xl
                               hover:bg-[#7a2438] active:bg-[#3d1019] transition-colors
                               focus:outline-none focus:ring-4 focus:ring-[#5C1A2A]/20 mt-1 shadow-sm">
                    Sign In
                </button>

            </form>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            &copy; <?= date('Y') ?> Puresol &middot; Agrigore Ventures Pvt. Ltd.
        </p>
    </div>

</body>
</html>
