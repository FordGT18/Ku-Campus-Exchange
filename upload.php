<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: upload.php
 * Description: Backend for uploading multiple images
 ******************************************************/
declare(strict_types=1);
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: Kuweblogin.html");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: upload.html");
    exit();
}

const MAX_FILES       = 5;
const MAX_PER_FILE_B  = 20 * 1024 * 1024; // 20MB per file 
const UPLOAD_DIR_FS   = __DIR__ . "/upload/"; 
const UPLOAD_DIR_URL  = "upload/";
const MAX_W           = 800;
const MAX_H           = 800;

// ensure folder 
if (!is_dir(UPLOAD_DIR_FS) && !mkdir(UPLOAD_DIR_FS, 0755, true)) {
    http_response_code(500);
    exit("Server cannot create upload directory.");
}

// form fields
$itemName     = trim($_POST['item_name'] ?? '');
$itemPriceIn  = $_POST['item_price'] ?? '';
$itemDesc     = trim($_POST['item_desc'] ?? '');
$itemCategory = trim($_POST['item_category'] ?? '');

if ($itemName === '' || $itemCategory === '') exit("Missing required fields.");
$itemPrice = (float)$itemPriceIn;
if (!is_finite($itemPrice) || $itemPrice < 0) exit("Invalid price.");

// Files present
if (!isset($_FILES['item_images'])) exit("No files uploaded.");
$files = $_FILES['item_images'];
$names = is_array($files['name']) ? $files['name'] : [];
$fileCount = 0;
foreach ($names as $n) if ($n !== null && $n !== '') $fileCount++;
if ($fileCount < 1) exit("Please select at least one image.");
if ($fileCount > MAX_FILES) exit("Too many images (max ".MAX_FILES.").");

// DB connection
$host="db"; $port=5432; $dbname="postgres"; $username="postgres"; $password="kucampus";
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    exit("DB connection failed: " . htmlspecialchars($e->getMessage()));
}

// Helper functions
function uniq_name(string $orig): string {
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?? '');
    $base = pathinfo($orig, PATHINFO_FILENAME) ?? 'file';
    $slug = trim(preg_replace('/[^a-z0-9\-]+/i', '-', $base), '-');
    if ($slug === '') $slug = 'file';
    return $slug . '-' . bin2hex(random_bytes(6)) . ($ext ? ".".$ext : "");
}
function resize_and_save(string $srcTmp, string $destFs, string $extLower, int $newW, int $newH, int $origW, int $origH): bool {
    switch ($extLower) {
        case 'jpg':
        case 'jpeg': $src = @imagecreatefromjpeg($srcTmp); break;
        case 'png':  $src = @imagecreatefrompng($srcTmp);  break;
        case 'gif':  $src = @imagecreatefromgif($srcTmp);  break;
        case 'webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcTmp) : null; break;
        default:     $src = null;
    }
    if (!$src) return false;
    $dst = imagecreatetruecolor($newW, $newH);
    if ($extLower === 'png' || $extLower === 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0,0,0,0, $newW,$newH, $origW,$origH);
    $ok = false;
    switch ($extLower) {
        case 'jpg':
        case 'jpeg': $ok = imagejpeg($dst, $destFs, 90); break;
        case 'png':  $ok = imagepng($dst, $destFs, 6);   break;
        case 'gif':  $ok = imagegif($dst, $destFs);      break;
        case 'webp': $ok = function_exists('imagewebp') ? imagewebp($dst, $destFs, 85) : false; break;
    }
    imagedestroy($src); imagedestroy($dst);
    return (bool)$ok;
}

// Save files first 
$finfo = new finfo(FILEINFO_MIME_TYPE);
$savedPaths = []; 
$createdFs  = []; 

for ($i = 0; $i < count($files['name']); $i++) {
    $origName = $files['name'][$i];
    if ($origName === null || $origName === '') continue;

    $err  = (int)$files['error'][$i];
    $tmp  = $files['tmp_name'][$i];
    $size = (int)$files['size'][$i];
    if ($err !== UPLOAD_ERR_OK) { foreach ($createdFs as $fs) @unlink($fs); exit("Upload error ($err)."); }
    if ($size <= 0) { foreach ($createdFs as $fs) @unlink($fs); exit("$origName: empty."); }
    if ($size > MAX_PER_FILE_B) { foreach ($createdFs as $fs) @unlink($fs); exit("$origName: too large."); }

    $mime = $finfo->file($tmp) ?: '';
    if (strpos($mime, 'image/') !== 0) { foreach ($createdFs as $fs) @unlink($fs); exit("$origName: not an image ($mime)."); }

    $extLower = strtolower(pathinfo($origName, PATHINFO_EXTENSION) ?? '');
    if (!in_array($extLower, ['jpg','jpeg','png','gif','webp'], true)) {
        foreach ($createdFs as $fs) @unlink($fs); exit("$origName: unsupported extension .$extLower");
    }

    $destName = uniq_name($origName);
    $destFs   = UPLOAD_DIR_FS . $destName;
    $relPath  = UPLOAD_DIR_URL . $destName;

    $sizeInfo = getimagesize($tmp);
    if ($sizeInfo !== false) {
        [$w, $h] = $sizeInfo;
        if ($w > MAX_W || $h > MAX_H) {
            $ratio = ($h === 0) ? 1 : ($w / $h);
            if ($ratio > 1) { $newW = MAX_W; $newH = (int)round(MAX_W / $ratio); }
            else            { $newH = MAX_H; $newW = (int)round(MAX_H * $ratio); }
            $ok = resize_and_save($tmp, $destFs, $extLower, $newW, $newH, $w, $h);
            if (!$ok && !move_uploaded_file($tmp, $destFs)) {
                foreach ($createdFs as $fs) @unlink($fs);
                exit("Failed to save $origName.");
            }
        } else {
            if (!move_uploaded_file($tmp, $destFs)) {
                foreach ($createdFs as $fs) @unlink($fs);
                exit("Failed to move $origName.");
            }
        }
    } else {
        if (!move_uploaded_file($tmp, $destFs)) {
            foreach ($createdFs as $fs) @unlink($fs);
            exit("Failed to move $origName.");
        }
    }

    @chmod($destFs, 0644);
    $createdFs[]  = $destFs;
    $savedPaths[] = $relPath;
}
if (!$savedPaths) exit("No files saved.");

// Primary path
$primaryPath = $savedPaths[0];
$firstOriginalName = $files['name'][ array_key_first(array_filter($files['name'])) ] ?? basename($primaryPath);

// Insert DB rows
try {
    $pdo->beginTransaction();

    $userId = (int)$_SESSION['user_id'];

    // parent row (uploads) 
    $stmt = $pdo->prepare("
        INSERT INTO uploads (user_id, item_name, item_price, item_desc, item_category, image_path, file_path, original_name)
        VALUES (:uid, :name, :price, :descr, :cat, :img, :file, :orig)
        RETURNING id
    ");
    $stmt->execute([
        ':uid'  => $userId,
        ':name' => $itemName,
        ':price'=> $itemPrice,
        ':descr'=> $itemDesc,
        ':cat'  => $itemCategory,
        ':img'  => $primaryPath,
        ':file' => $primaryPath,
        ':orig' => $firstOriginalName,
    ]);
    $uploadId = (int)$stmt->fetchColumn();
    if ($uploadId <= 0) throw new RuntimeException("Could not create upload record.");

    // child images
    $imgStmt = $pdo->prepare("INSERT INTO upload_images (upload_id, image_path) VALUES (:uid, :path)");
    foreach ($savedPaths as $p) $imgStmt->execute([':uid'=>$uploadId, ':path'=>$p]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($createdFs as $fs) @unlink($fs);
    http_response_code(500);
    exit("DB error: " . htmlspecialchars($e->getMessage()));
}

echo "Item uploaded successfully!<br>";
echo "<a href='Marketplace.php'>Back to Marketplace</a>";
