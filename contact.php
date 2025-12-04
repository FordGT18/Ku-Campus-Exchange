<?php
/******************************************************
 * Author: Samar Gill and Juan Vargas
 * Project: KU Campus Exchange
 * File: contact.php
 * Description: Frontend/Backend for contact Page
 ******************************************************/
session_start();

// Only allow logged in users
if (!isset($_SESSION['user_id'])) {
    header("Location: Kuweblogin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contact Us</title>
  <link rel="stylesheet" href="contact.css"> 
</head>
<body>
  <main>
    <h1>Contact Us</h1>

    <div class="box">
      <p>If you have any questions about KU Campus Exchange, click below to email us.</p>
      <p>We’ll get back to you as soon as we can.</p>

      <!-- mailto link to email -->
      <a id="emailLink"
         class="btn"
         href="mailto:kuexchng@gmail.com?subject=KU Campus Exchange Contact">
        Email Us
      </a>
    </div>
  </main>

  <script>
    // After email window opens, return user to Marketplace
    document.getElementById("emailLink").addEventListener("click", function () {
      setTimeout(function () {
        window.location.href = "Marketplace.php"; 
      }, 500);
    });
  </script>
</body>
</html>
