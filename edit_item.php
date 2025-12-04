<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: edit_item.php
 * Description: Backend for editing listings on Marketplace
 ******************************************************/
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: Kuweblogin.html"); exit(); }

// DB connection
$host="db"; $port=5432; $dbname="postgres"; $username="postgres"; $password="kucampus";
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
    ]);
} catch (Throwable $e) { die("DB error"); }

// Local config
define('UPLOAD_DIR_FS', __DIR__ . "/upload/");
define('UPLOAD_DIR_URL', "upload/");
define('MAX_FILES', 5);
define('MAX_PER_FILE_B', 20*1024*1024);
define('MAX_W', 800);
define('MAX_H', 800);

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
    case 'jpeg': $src=@imagecreatefromjpeg($srcTmp); break;
    case 'png':  $src=@imagecreatefrompng($srcTmp);  break;
    case 'gif':  $src=@imagecreatefromgif($srcTmp);  break;
    case 'webp': $src=function_exists('imagecreatefromwebp')?@imagecreatefromwebp($srcTmp):null; break;
    default: $src=null;
  }
  if (!$src) return false;
  $dst=imagecreatetruecolor($newW,$newH);
  if ($extLower==='png'||$extLower==='webp'){imagealphablending($dst,false);imagesavealpha($dst,true);}
  imagecopyresampled($dst,$src,0,0,0,0,$newW,$newH,$origW,$origH);
  $ok=false;
  switch($extLower){
    case 'jpg': case 'jpeg': $ok=imagejpeg($dst,$destFs,90); break;
    case 'png': $ok=imagepng($dst,$destFs,6); break;
    case 'gif': $ok=imagegif($dst,$destFs); break;
    case 'webp': $ok=function_exists('imagewebp')?imagewebp($dst,$destFs,85):false; break;
  }
  imagedestroy($src); imagedestroy($dst);
  return (bool)$ok;
}

$userId = (int)$_SESSION['user_id'];
$itemId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($itemId<=0) { die("Invalid id"); }

// Ownership and item
$own = $pdo->prepare("SELECT id,item_name,item_price,item_desc,item_category,image_path FROM uploads WHERE id=:id AND user_id=:uid");
$own->execute([':id'=>$itemId, ':uid'=>$userId]);
$item = $own->fetch(PDO::FETCH_ASSOC);
if (!$item) { die("Not allowed"); }

// Existing images
$imgsStmt = $pdo->prepare("SELECT id, image_path FROM upload_images WHERE upload_id=:id ORDER BY id ASC");
$imgsStmt->execute([':id'=>$itemId]);
$images = $imgsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['item_name'] ?? '');
    $desc  = trim($_POST['item_desc'] ?? '');
    $cat   = trim($_POST['item_category'] ?? '');
    $price = is_numeric($_POST['item_price'] ?? '') ? (float)$_POST['item_price'] : 0.0;

    if (!is_dir(UPLOAD_DIR_FS)) @mkdir(UPLOAD_DIR_FS,0755,true);

    // delete selected images
    $toDelete = array_map('intval', $_POST['remove_image'] ?? []);
    if ($toDelete) {
      $in = implode(',', array_fill(0,count($toDelete),'?'));
      $q = $pdo->prepare("SELECT id,image_path FROM upload_images WHERE upload_id=? AND id IN ($in)");
      $q->execute(array_merge([$itemId], $toDelete));
      $rows=$q->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $del=$pdo->prepare("DELETE FROM upload_images WHERE upload_id=:uid AND id=:id");
      foreach ($rows as $r) {
        $del->execute([':uid'=>$itemId, ':id'=>$r['id']]);
        $fs = __DIR__.'/'.ltrim($r['image_path'],'/');
        if (is_file($fs)) @unlink($fs);
      }
    }

    // count the remaining
    $cq=$pdo->prepare("SELECT COUNT(*) FROM upload_images WHERE upload_id=:id");
    $cq->execute([':id'=>$itemId]);
    $currentCount=(int)$cq->fetchColumn();

    // add new images
    $added=[];
    if (isset($_FILES['new_images']) && is_array($_FILES['new_images']['name'])) {
      $files=$_FILES['new_images'];
      $fi=new finfo(FILEINFO_MIME_TYPE);
      for ($i=0;$i<count($files['name']);$i++) {
        if ($currentCount + count($added) >= MAX_FILES) break;
        $orig=$files['name'][$i];
        if ($orig==='' || $files['error'][$i]!==UPLOAD_ERR_OK) continue;
        $tmp=$files['tmp_name'][$i]; $sz=(int)$files['size'][$i];
        if ($sz<=0 || $sz>MAX_PER_FILE_B) continue;
        $mime=$fi->file($tmp) ?: ''; if (strpos($mime,'image/')!==0) continue;
        $extLower=strtolower(pathinfo($orig,PATHINFO_EXTENSION) ?? '');
        if (!in_array($extLower,['jpg','jpeg','png','gif','webp'],true)) continue;

        $destName=uniq_name($orig); $destFs=UPLOAD_DIR_FS.$destName; $relPath=UPLOAD_DIR_URL.$destName;
        $si=getimagesize($tmp);
        if ($si){
            [$w,$h]=$si;
            if($w>MAX_W||$h>MAX_H){
                $ratio=($h===0)?1:($w/$h);
                if($ratio>1){
                    $newW=MAX_W;
                    $newH=(int)round(MAX_W/$ratio);
                } else{
                    $newH=MAX_H;
                    $newW=(int)round(MAX_H*$ratio);
                }
                $ok=resize_and_save($tmp,$destFs,$extLower,$newW,$newH,$w,$h);
                if(!$ok && !move_uploaded_file($tmp,$destFs)) continue;
            } else {
                if(!move_uploaded_file($tmp,$destFs)) continue;
            }
        } else {
            if(!move_uploaded_file($tmp,$destFs)) continue;
        }
        @chmod($destFs,0644);
        $added[]=$relPath;
      }
      if ($added) {
        $ins=$pdo->prepare("INSERT INTO upload_images (upload_id,image_path) VALUES (:uid,:p)");
        foreach($added as $p)$ins->execute([':uid'=>$itemId, ':p'=>$p]);
      }
    }

    // ensure primary image
    $first=$pdo->prepare("SELECT image_path FROM upload_images WHERE upload_id=:id ORDER BY id ASC LIMIT 1");
    $first->execute([':id'=>$itemId]);
    $primary=$first->fetchColumn();

    // update item
    $upd=$pdo->prepare("UPDATE uploads SET item_name=:n,item_price=:p,item_desc=:d,item_category=:c,
                        image_path=COALESCE(:img,image_path), file_path=COALESCE(:img,file_path)
                        WHERE id=:id AND user_id=:uid");

    $upd->execute([
        ':n'=>$name,
        ':p'=>$price,
        ':d'=>$desc,
        ':c'=>$cat,
        ':img'=>$primary ?: null,
        ':id'=>$itemId,
        ':uid'=>$userId
    ]);

    header("Location: profile.php");
    exit();
} 

include __DIR__ . '/edit_item.html';
