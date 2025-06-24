<?php
// config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); 
define('DB_PASSWORD', 'abcd1234');     
define('DB_NAME', 'diyabet_analiz'); 

// Bağlantı oluştur
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Bağlantıyı kontrol et
if ($conn->connect_error) {
    die("Bağlantı hatası: " . $conn->connect_error);
}

// Karakter setini ayarla (Türkçe karakterler için önemli)
$conn->set_charset("utf8mb4");
?>