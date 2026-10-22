<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$login_site_name = getSetting($conn, 'site_name', '') ?: SITE_NAME;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($conn, $email, $password)) {
        header("Location: dashboard.php");
        exit();
    } else {
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($login_site_name); ?></title>
    <link rel="icon" href="../assets/img/favicon.png">
    <link rel="stylesheet" href="../<?php echo htmlspecialchars(versionedAsset('assets/css/admin.css')); ?>">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img class="login-logo" src="<?php echo htmlspecialchars(siteLogoUrl($conn, '../')); ?>"
                     alt="<?php echo htmlspecialchars($login_site_name); ?>">
                <p>Login into Your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="login-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="login-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($login_site_name); ?>. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>