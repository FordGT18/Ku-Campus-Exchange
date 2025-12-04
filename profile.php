<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: profile.php
 * Description: Backend for handling seller/buyer conversations and 
 * avatar upload
 ******************************************************/
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: Kuweblogin.html");
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
    die("DB error: " . htmlspecialchars($e->getMessage()));
}

// Local config
define('AVATAR_DIR_FS', __DIR__ . "/upload/avatars/");
define('AVATAR_DIR_URL', "upload/avatars/");

if (!is_dir(AVATAR_DIR_FS)) {
    @mkdir(AVATAR_DIR_FS, 0755, true);
}

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS user_profiles (
  user_id integer PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  display_name text,
  avatar_path text,
  updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
)");

$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = null;

// Handle POST update 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $displayName = trim($_POST['display_name'] ?? '');

    $avatarRel = null;

    // Handle avatar upload
    if (!empty($_FILES['avatar']['name'])) {
        $f = $_FILES['avatar'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($f['tmp_name']) ?: '';
            if (strpos($mime, 'image/') !== 0) {
                $errors[] = "Avatar must be an image.";
            } else {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: 'jpg');
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
                    $errors[] = "Unsupported avatar type.";
                } else {
                    $safe = 'avatar-'.$userId.'-'.bin2hex(random_bytes(5)).'.'.$ext;
                    $dest = AVATAR_DIR_FS.$safe;
                    $avatarRel = AVATAR_DIR_URL.$safe;

                    if (!move_uploaded_file($f['tmp_name'], $dest)) {
                        $errors[] = "Failed to save avatar.";
                        $avatarRel = null;
                    } else {
                        @chmod($dest,0644);
                    }
                }
            }
        } else {
            $errors[] = "Avatar upload error ({$f['error']}).";
        }
    }

    // Update DB 
    if (!$errors) {
        if ($avatarRel !== null) {
            $q = $pdo->prepare("
                INSERT INTO user_profiles (user_id, display_name, avatar_path, updated_at)
                VALUES (:u, :d, :img, CURRENT_TIMESTAMP)
                ON CONFLICT (user_id) DO UPDATE SET
                    display_name = EXCLUDED.display_name,
                    avatar_path = EXCLUDED.avatar_path,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $q->execute([
                ':u'   => $userId,
                ':d'   => $displayName,
                ':img' => $avatarRel
            ]);

        } else {
            $q = $pdo->prepare("
                INSERT INTO user_profiles (user_id, display_name, updated_at)
                VALUES (:u, :d, CURRENT_TIMESTAMP)
                ON CONFLICT (user_id) DO UPDATE SET
                    display_name = EXCLUDED.display_name,
                    updated_at = CURRENT_TIMESTAMP
            ");
            $q->execute([
                ':u' => $userId,
                ':d' => $displayName
            ]);
        }

        $success = "Profile updated.";
    }
}

// Fetch user and profile
$user = $pdo->query("SELECT id, username, email FROM users WHERE id = {$userId}")
            ->fetch(PDO::FETCH_ASSOC) ?: [];

$prof = $pdo->query("
    SELECT display_name, avatar_path
    FROM user_profiles
    WHERE user_id = {$userId}
")->fetch(PDO::FETCH_ASSOC) ?: [];

$displayName = $prof['display_name'] ?? '';
$avatarPath  = $prof['avatar_path'] ?? 'upload_icon.png';

// Fetch user items 
$itemStmt = $pdo->prepare("
    SELECT id, item_name, item_price, item_desc, item_category, image_path, is_sold
    FROM uploads
    WHERE user_id = :uid
    ORDER BY id DESC
");
$itemStmt->execute([':uid'=>$userId]);
$myItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$imgStmt = $pdo->prepare("SELECT image_path FROM upload_images WHERE upload_id=:id ORDER BY id ASC");


// Conversations where *I am the seller* (people message my items)
$sellerConvStmt = $pdo->prepare("
    SELECT 
        m.item_id,
        u.item_name,
        m.sender_id AS other_user_id,
        COALESCE(up_b.display_name, ub.username) AS other_display_name,
        COUNT(*) AS message_count,
        MAX(m.created_at) AS last_message_at
    FROM messages m
    JOIN uploads u ON m.item_id = u.id
    JOIN users us ON u.user_id = us.id          -- me (seller)
    JOIN users ub ON m.sender_id = ub.id        -- buyer
    LEFT JOIN user_profiles up_b ON up_b.user_id = ub.id
    WHERE u.user_id = :uid                      -- my items
    GROUP BY 
        m.item_id, 
        u.item_name,
        m.sender_id,
        other_display_name
    ORDER BY last_message_at DESC
");
$sellerConvStmt->execute([':uid' => $userId]);
$sellerConversations = $sellerConvStmt->fetchAll(PDO::FETCH_ASSOC);

// Conversations where *I am the buyer* 
$buyerConvStmt = $pdo->prepare("
    SELECT 
        m.item_id,
        u.item_name,
        u.user_id AS other_user_id,
        COALESCE(up_s.display_name, us.username) AS other_display_name,
        COUNT(*) AS message_count,
        MAX(m.created_at) AS last_message_at
    FROM messages m
    JOIN uploads u ON m.item_id = u.id
    JOIN users us ON u.user_id = us.id          -- seller
    LEFT JOIN user_profiles up_s ON up_s.user_id = us.id
    WHERE m.sender_id = :uid                    -- I sent the message
      AND u.user_id <> :uid                     -- to someone else
    GROUP BY 
        m.item_id,
        u.item_name,
        u.user_id,
        other_display_name
    ORDER BY last_message_at DESC
");
$buyerConvStmt->execute([':uid' => $userId]);
$buyerConversations = $buyerConvStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/profile.html';
