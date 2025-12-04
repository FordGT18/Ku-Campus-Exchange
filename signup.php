<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: signup.php
 * Description: Backend for signup page
 ******************************************************/
session_start();

//DB connection
$host = "db";
$port = 5432;
$dbname = "postgres";
$username = "postgres";
$password = "kucampus";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Display name from form
        $display_name = trim($_POST["username"] ?? "");
        // KU email (used for login)
        $email = trim($_POST["email"] ?? "");
        $pass = $_POST["password"] ?? "";
        $repeat_pass = $_POST["repeat_password"] ?? "";

        if ($display_name === "" || $email === "" || $pass === "" || $repeat_pass === "") {
            die("All fields are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Invalid email format.");
        }

        // Enforce KU domain
        if (!preg_match('/@live\.kutztown\.edu$/', $email)) {
            die("Only @live.kutztown.edu email addresses are allowed.");
        }

        if ($pass !== $repeat_pass) {
            die("Passwords do not match.");
        }

        // check if email already exists
        $checkStmt = $conn->prepare("SELECT 1 FROM users WHERE email = :email");
        $checkStmt->execute([':email' => $email]);
        if ($checkStmt->fetchColumn()) {
            die("An account with this email already exists.");
        }

        // Hash the password before saving
        $hashed_password = password_hash($pass, PASSWORD_BCRYPT);

        // store the email as the login username
        $userStmt = $conn->prepare("
            INSERT INTO users (username, email, password)
            VALUES (:login_user, :email, :password)
            RETURNING id
        ");

        $userStmt->execute([
            ":login_user" => $email,          
            ":email"      => $email,
            ":password"   => $hashed_password
        ]);

        $user_id = (int)$userStmt->fetchColumn();
        if ($user_id <= 0) {
            die("Error creating user account.");
        }

        // display name
        $profileStmt = $conn->prepare("
            INSERT INTO user_profiles (user_id, display_name)
            VALUES (:user_id, :display_name)
            ON CONFLICT (user_id) DO UPDATE
            SET display_name = EXCLUDED.display_name
        ");

        $profileStmt->execute([
            ":user_id"      => $user_id,
            ":display_name" => $display_name
        ]);

        // Redirect to login
        header("Location: Kuweblogin.php");
        exit();
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
