<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: mark_sold.php
 * Description: Backend for if a seller sells his item they
 * can have an option to click sold for that item
 ******************************************************/
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Kuweblogin.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];
$itemId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($itemId <= 0) {
    header("Location: profile.php");
    exit();
}

// DB connection
$host = "db";
$port = 5432;
$dbname = "postgres";
$username = "postgres";
$password = "kucampus";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Throwable $e) {
    die("DB error");
}

// Make sure the item belongs to this user
$stmt = $pdo->prepare("SELECT id FROM uploads WHERE id = :id AND user_id = :uid");
$stmt->execute([':id' => $itemId, ':uid' => $userId]);
if (!$stmt->fetchColumn()) {
    header("Location: profile.php");
    exit();
}

// Mark as sold
$upd = $pdo->prepare("UPDATE uploads SET is_sold = TRUE WHERE id = :id AND user_id = :uid");
$upd->execute([':id' => $itemId, ':uid' => $userId]);

header("Location: profile.php");
exit();
