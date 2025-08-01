<?php
try {
    $cmp = new PDO('mysql:host=mysql;dbname=workz_companies;charset=utf8mb4', 'root', 'root_password');
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>