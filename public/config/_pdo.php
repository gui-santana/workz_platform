<?php
try{
	$pdo = new PDO('mysql:host=127.0.0.1;dbname=u796300692_work', 'u796300692_work', '7=F^A6iqVK;h');
}catch(PDOException $e){
	throw new PDOException($e);
}
?>