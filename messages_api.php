<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: messages_api.php
 * Description: Backend for Message portal, 
 * shows the chat messages between two users about an item
 ******************************************************/
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Not authorized";
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
    http_response_code(500);
    echo "DB error";
    exit();
}

// Get item_id
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($itemId <= 0) {
    http_response_code(400);
    echo "Invalid item";
    exit();
}

// Find seller_id for this item
$sellerStmt = $pdo->prepare("SELECT user_id FROM uploads WHERE id = :item_id");
$sellerStmt->execute([':item_id' => $itemId]);
$sellerId = (int)$sellerStmt->fetchColumn();

// Determine the other person in the conversation
if ($currentUserId === $sellerId) {
    // Seller to find the buyer messaging them
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
    $otherUserId = (int)$otherStmt->fetchColumn();
} else {
    // Viewer is buyer
    $otherUserId = $sellerId;
}

// If no messages yet, fallback to seller to display empty chat
if (!$otherUserId) $otherUserId = $sellerId;

// Fetch ONLY messages between these two users
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
         OR (m.sender_id = :other AND m.receiver_id = :me)
      )
    ORDER BY m.created_at ASC, m.id ASC
");
$msgStmt->execute([
    ':item_id' => $itemId,
    ':me'     => $currentUserId,
    ':other'  => $otherUserId
]);

$messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

// Output message bubbles
if (!$messages) {
    echo '<p class="empty-thread">No messages yet. Start the conversation!</p>';
    exit();
}

foreach ($messages as $m) {
    $senderName = $m['sender_display_name'] ?? $m['username'] ?? 'User';
    $isMe = ((int)$m['sender_id'] === $currentUserId);
    ?>
    <div class="message-row <?= $isMe ? 'me' : 'them' ?>">
      <div class="message-bubble">
        <div class="sender-name"><?= htmlspecialchars($senderName) ?></div>
        <div class="body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
        <div class="timestamp"><?= htmlspecialchars($m['created_at']) ?></div>
      </div>
    </div>
    <?php
}
