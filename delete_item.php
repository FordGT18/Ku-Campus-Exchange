<?php
/******************************************************
 * Author: Samar Gill 
 * Project: KU Campus Exchange
 * File: delete_item.php
 * Description: Backend for deleting a listing from Marketplace
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

$userId = (int)$_SESSION['user_id'];
$itemId = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($itemId<=0) { header("Location: profile.php"); exit(); }

// Fetch & verify ownership
$own = $pdo->prepare("SELECT id,item_name,image_path,file_path FROM uploads WHERE id=:id AND user_id=:uid");
$own->execute([':id'=>$itemId, ':uid'=>$userId]);
$item = $own->fetch(PDO::FETCH_ASSOC);
if (!$item) { header("Location: profile.php"); exit(); }

// On POST actually delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // gather paths
    $imgq=$pdo->prepare("SELECT image_path FROM upload_images WHERE upload_id=:id");
    $imgq->execute([':id'=>$itemId]);
    $paths=$imgq->fetchAll(PDO::FETCH_COLUMN,0) ?: [];
    if (!empty($item['image_path'])) $paths[]=$item['image_path'];
    if (!empty($item['file_path']))  $paths[]=$item['file_path'];
    $paths=array_values(array_unique($paths));

    $pdo->beginTransaction();
    $del=$pdo->prepare("DELETE FROM uploads WHERE id=:id AND user_id=:uid");
    $del->execute([':id'=>$itemId, ':uid'=>$userId]);
    $pdo->commit();

    foreach ($paths as $rel) {
        $fs = __DIR__ . '/' . ltrim($rel,'/');
        if (is_file($fs)) @unlink($fs);
    }
    header("Location: profile.php");
    exit();
}

include __DIR__ . '/delete_item.html';
