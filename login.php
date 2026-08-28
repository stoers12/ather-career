<?php
require_once __DIR__ . '/includes/admin_session.php';

startAdminSession();

if (isAdminAuthenticated()) {
    header('Location: admin.php');
    exit;
}

$adminUsername = getenv('ADMIN_USERNAME');
$adminPasswordHash = getenv('ADMIN_PASSWORD_HASH');
$authConfigured = is_string($adminUsername) && $adminUsername !== ''
    && is_string($adminPasswordHash) && $adminPasswordHash !== '';
$loginError = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) && is_string($_POST['username'])
        ? trim($_POST['username'])
        : '';
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    if ($authConfigured && hash_equals($adminUsername, $username) && password_verify($password, $adminPasswordHash)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;

        header('Location: admin.php');
        exit;
    }

    $loginError = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - My Portfolio</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="admin.css">
    <script src="admin.js" defer></script>
</head>
<body>
    <main>
        <section class="login-section" aria-labelledby="login-title">
            <div class="login-card">
            <p class="eyebrow">PORTFOLIO ADMIN</p><h1 id="login-title">Welcome back</h1>
            <p class="login-subtitle">Sign in to manage your portfolio content.</p>

            <?php if ($loginError !== ''): ?>
                <p class="status-message error" role="alert"><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="username">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">

                <button type="submit">Log in securely</button>
            </form>

            <p class="login-back"><a href="index.php">← Back to portfolio</a></p>
            </div>
        </section>
    </main>
</body>
</html>
