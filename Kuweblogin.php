<?php
/******************************************************
 * Author: Samar Gill and Juan Vargas
 * Project: KU Campus Exchange
 * File: Kuweblogin.php
 * Description: Frontend/Backend for Login page
 ******************************************************/
session_start();

// Initialize error message
$error = "";

// Check if redirected from a failed login attempt
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Ku Campus Exchange</title>
    <link rel="stylesheet" href="signup_style.css">
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        <div class="signup-box">
            <?php if (!empty($error)) : ?>
                <p style="color:red; font-weight:bold; text-align:center;"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>

            <form action="Login.php" method="POST">
                <input type="text" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>

            <p class="redirect">Don't have an account? <a href="signup.html">Sign Up</a></p>
        </div>
    </div>
</body>
</html>
