<?php
// login.php – password prompt for the whole site.
declare(strict_types=1);
require __DIR__ . '/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = (string)($_POST['password'] ?? '');
    if (verifyPassword($submitted)) {
        login();
        header('Location: index.php');
        exit;
    }
    $error = 'Wrong Password.';
} elseif (isset($_GET['logout'])) {
    logout();
    header('Location: login.php');
    exit;
}

if (authPassword() === '' && (string)(authConfig()['password_hash'] ?? '') === '') {
    $error = 'No password configured. Please create config.php (see config.php.example).';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Video Calendar</title>
    <link rel="icon" href="<?= htmlspecialchars(faviconHref(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="docs/styles.css">
</head>
<body>
    <div class="login-form">
        <h1>Video Calendar</h1>
        <p>This page is password protected.</p>
        <?php if ($error !== ''): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" action="login.php">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" autofocus required>
            <button type="submit">View</button>
        </form>
    </div>
</body>
</html>
