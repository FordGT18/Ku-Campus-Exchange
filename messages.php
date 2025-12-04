<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: messages.php
 * Description: Shows and sends chat messages between two users about an item
 ******************************************************/
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: Kuweblogin.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];

// DB connection
$host = "db";
$port = 5432;
$dbname = "postgres";
$username = "postgres";
$password = "kucampus";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

$errors = [];

// Get item_id from query
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($itemId <= 0) {
    die("Invalid item.");
}

// Fetch item and seller info
$itemStmt = $pdo->prepare("
    SELECT u.*,
           us.id AS seller_id,
           us.username,
           COALESCE(up.display_name, us.username) AS seller_display_name
    FROM uploads u
    JOIN users us ON u.user_id = us.id
    LEFT JOIN user_profiles up ON up.user_id = us.id
    WHERE u.id = :id
");
$itemStmt->execute([':id' => $itemId]);
$item = $itemStmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    die("Item not found.");
}

$itemName      = $item['item_name'] ?? 'Item';
$sellerId      = (int)($item['seller_id'] ?? 0);
$sellerName    = $item['seller_display_name'] ?? ($item['username'] ?? 'Seller');

// Determine the other participant in private thread
if ($currentUserId == $sellerId) {
    $otherStmt = $pdo->prepare("
        SELECT DISTINCT sender_id
        FROM messages
        WHERE item_id = :item_id
          AND sender_id != :seller_id
        LIMIT 1
    ");
    $otherStmt->execute([
        ':item_id' => $itemId,
        ':seller_id' => $sellerId
    ]);
    $otherUserId = $otherStmt->fetchColumn();
} else {
    $otherUserId = $sellerId;
}
if (!$otherUserId) {
    $otherUserId = $sellerId;
}

// Fetch current user's display name for header
$userStmt = $pdo->prepare("
    SELECT u.username,
           COALESCE(up.display_name, u.username) AS display_name
    FROM users u
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE u.id = :id
");
$userStmt->execute([':id' => $currentUserId]);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$currentDisplayName = $userRow['display_name'] ?? ($userRow['username'] ?? 'You');

// Handle new message submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');

    if ($body === '') {
        $errors[] = "Message cannot be empty.";
    }

    if (empty($errors)) {
        $receiverId = ($currentUserId == $sellerId) ? $otherUserId : $sellerId;

        $ins = $pdo->prepare("
            INSERT INTO messages (item_id, sender_id, receiver_id, body)
            VALUES (:item_id, :sender_id, :receiver_id, :body)
        ");
        $ins->execute([
            ':item_id'     => $itemId,
            ':sender_id'   => $currentUserId,
            ':receiver_id' => $receiverId,
            ':body'        => $body
        ]);

        header("Location: messages.php?item_id=" . $itemId);
        exit();
    }
}

// Fetch messages for specific conversation only
$msgStmt = $pdo->prepare("
    SELECT m.id,
           m.item_id,
           m.sender_id,
           m.receiver_id,
           m.body,
           m.created_at,
           u.username,
           up.display_name AS sender_display_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE m.item_id = :item_id
      AND (
            (m.sender_id = :me AND m.receiver_id = :other)
            OR
            (m.sender_id = :other AND m.receiver_id = :me)
          )
    ORDER BY m.created_at ASC, m.id ASC
");
$msgStmt->execute([
    ':item_id' => $itemId,
    ':me'     => $currentUserId,
    ':other'  => $otherUserId
]);
$messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/messages.html';
