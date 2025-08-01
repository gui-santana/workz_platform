<?
$rs = "";
$nm = "";
$cn = "";
$ci = "";

$wurl = explode('/', $_SERVER['DOCUMENT_ROOT']);
$eurl = end($wurl);

if($eurl == 'sistemas'){
	require_once($_SERVER['DOCUMENT_ROOT']."/config/bd.php");
}else{
	require_once($_SERVER['DOCUMENT_ROOT']."/sistemas/config/bd.php");	
}

//include('../../classes/crypt.php');

$bndes_username = 'u394640603_bndes';
$bndes_password = 'bndesrt2018';
$bndes_pdo = new PDO('mysql:host=mysql.hostinger.com.br;dbname=u394640603_bndes', $bndes_username, $bndes_password);

function allSoc($pdo){
	$ckun = $pdo->prepare("SELECT * FROM WR01 WHERE of LIKE :of AND tp = :tp;");
	$ckun->bindParam(':of', $_SESSION['office_id'], PDO::PARAM_STR);
	$ckun->bindValue(':tp', 1);
	$ckun->execute();		
	$SocList = array();
	while($unds = $ckun->fetch(PDO::FETCH_ASSOC)){
		$SocList[] = $unds;
	}
	return $SocList;
}
$SLib = allSoc($pdo);

function allBNDES($bndes_pdo, $soc, $SLib){
	
	if($soc == ''){
		$sc = $SLib[0]['id'];
	}else{
		$sc = $soc;
	}
	
	$lobn = $bndes_pdo->prepare("SELECT * FROM data WHERE sc = '".$sc."';");	
	$lobn->execute();		
	$BNDESlist = array();
	while($libn = $lobn->fetch(PDO::FETCH_ASSOC)){
		$BNDESlist[] = $libn;
	}
	
	/*
	$count = 0;
	$search_size = count($SLib); 
	$s_sc = "SELECT * FROM data WHERE ";
	foreach($SLib as $unds){
		$count++;
		if($count < $search_size){
			$s_sc .= "(sc = ".$unds['id'].") OR ";
		}elseif($count == $search_size){
			$s_sc .= "(sc = ".$unds['id'].") ORDER BY sc ASC, sb ASC";
		}
	}
	
	$lobn = $bndes_pdo->query($s_sc);
	$BNDESlist = array();
	foreach($lobn as $libn){
		$BNDESlist[] = $libn;
	}
	*/
	return $BNDESlist;
	
}
if(isset($soc)){
	$BLib = allBNDES($bndes_pdo, $soc, $SLib);
}

function SocName($scid, $pdo){
	$sname = $pdo->prepare("SELECT * FROM sociedade WHERE id = '".$scid."' ;");
	$sname->execute();
	$vname = $sname->fetch(PDO::FETCH_ASSOC);		
	$socname = Crypt::Decrypt($vname['nm']);	
	return $socname;
}

if(isset($soc)){
	$WL1_count = count($BLib);
}
?>