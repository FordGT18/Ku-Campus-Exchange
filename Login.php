<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: Login.php
 * Description: Backend for Login Page
 ******************************************************/
session_start();

//db connection
$host = "db";
$port = 5432;
$dbname = "postgres";
$username = "postgres";
$password = "kucampus";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $email = trim($_POST["email"]);
        $pass = $_POST["password"];

        if (empty($email) || empty($pass)) {
            $_SESSION['login_error'] = "All fields are required.";
            header("Location: Kuweblogin.php");
            exit();
        }

        // Fetch user by email
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($pass, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: Marketplace.php");
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid email or password.";
            header("Location: Kuweblogin.php");
            exit();
        }
    }
} catch (PDOException $e) {
    $_SESSION['login_error'] = "Database connection failed: " . $e->getMessage();
    header("Location: Kuweblogin.php");
    exit();
}
?>
