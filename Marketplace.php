<?php
/******************************************************
 * Author: Samar Gill and Juan Vargas
 * Project: KU Campus Exchange
 * File: Marketplace.php
 * Description: Frontend/Backend for Marketplace
 ******************************************************/
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: Kuweblogin.php");
    exit();
}

// DB connection
$host = "db";
$port = 5432;
$dbname = "postgres";
$username = "postgres";
$password = "kucampus";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all items
    $stmt = $conn->prepare("
        SELECT u.*, up.display_name AS seller_display
        FROM uploads u
        LEFT JOIN user_profiles up ON up.user_id = u.user_id
        ORDER BY u.id DESC
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch images for each item
    $imgStmt = $conn->prepare("
        SELECT image_path
        FROM upload_images
        WHERE upload_id = :id
    ");

} catch (PDOException $e) {
    die("DB error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Marketplace</title>
<link rel="stylesheet" href="style2.css">
</head>
<body>

<div class="sidebar">
    <h2>Ku Campus Marketplace</h2>

    <ul>
        <li onclick="filterCategory('All')">All Items</li>
        <li onclick="filterCategory('Electronics')">Electronics</li>
        <li onclick="filterCategory('Clothing')">Clothing</li>
        <li onclick="filterCategory('Furniture')">Furniture</li>
        <li onclick="filterCategory('Vehicles')">Vehicles</li>
    </ul>

    <button onclick="window.location.href='upload.html'" class="upload-btn">+ Upload Item</button>
    <button onclick="toggleDarkMode()" class="dark-mode-toggle">Dark Mode</button>
    <button onclick="window.location.href='contact.php'" class="contact-btn">Contact Us</button>
    <button onclick="window.location.href='profile.php'" class="profile-btn">My Profile</button>
    <button onclick="window.location.href='logout.php'" class="logout-button">Logout</button>
</div>

<div class="content">

    <input type="text" id="search-bar" class="search-bar" placeholder="Search…" oninput="searchItems()">

    <div class="grid">
        <?php foreach ($items as $item): ?>

            <?php
                // Fetch images
                $imgStmt->execute([':id' => $item['id']]);
                $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN, 0);

                if (!$images && !empty($item['file_path'])) {
                    $images = [$item['file_path']];
                }

                $thumb = $images[0] ?? "placeholder.png";

                $price = is_numeric($item['item_price'])
                    ? "$" . number_format((float)$item['item_price'], 2)
                    : "$0.00";

                $itemName = $item['item_name'] ?? "";
                $seller   = $item['seller_display'] ?: "Unknown Seller";
                $category = $item['item_category'] ?? "Misc";
                $desc     = $item['item_desc'] ?? "";
                $isSold   = !empty($item['is_sold']);
            ?>

            <div class="item<?= $isSold ? ' sold' : '' ?>" data-category="<?= htmlspecialchars($category) ?>">
                <img
                    src="<?= htmlspecialchars($thumb) ?>"
                    onclick='openModal(
                        <?= json_encode($itemName) ?>,
                        <?= json_encode($images) ?>,
                        <?= json_encode($price) ?>,
                        <?= (int)$item["id"] ?>,
                        <?= json_encode($seller) ?>,
                        <?= json_encode($desc) ?>
                    )'
                >
                <p>
                    <strong><?= htmlspecialchars($itemName) ?></strong>
                    <?php if ($isSold): ?>
                        <span class="sold-badge">SOLD</span>
                    <?php endif; ?>
                    - <?= htmlspecialchars($price) ?>
                </p>
                <p><em>Category: <?= htmlspecialchars($category) ?></em></p>
            </div>

        <?php endforeach; ?>
    </div>
</div>

<div id="item-modal" class="modal" style="display:none;">
    <div class="modal-content">

        <span class="close" onclick="closeModal()">&times;</span>

        <div class="gallery">
            <button id="gallery-prev" class="gallery-arrow">&lt;</button>
            <div id="gallery-main" class="gallery-main"></div>
            <button id="gallery-next" class="gallery-arrow">&gt;</button>
        </div>

        <div id="gallery-thumbs" class="gallery-thumbs"></div>

        <h2 id="modal-title"></h2>
        <p id="modal-price"></p>
        <p id="modal-seller" style="font-weight:bold;"></p>
        <p id="modal-desc" style="margin-top:8px; white-space:pre-wrap;"></p>

        <button id="message-seller-btn" class="message-btn">Message Seller</button>

    </div>
</div>

<script src="script.js?v=2025"></script>

</body>
</html>
