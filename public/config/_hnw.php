<?php
try {
    $hnw = new PDO('mysql:host=mysql;dbname=workz_data;charset=utf8mb4', 'root', 'root_password');
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>