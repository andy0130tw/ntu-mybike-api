<?php

switch ($_SERVER['HTTP_ORIGIN']) {
    case 'http://learnmode.host22.com':
	case 'http://lm.twbbs.org':
    case 'http://andy0130tw.qov.tw':
    case 'http://chat2008.site40.net':
	header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    break;
    default:
    //die("access denied!");
}

header('Content-type: application/json');

define("URL_QUERY",'http://mybike.ntu.edu.tw/query2.php');
define("MATCH_PATTERN_RECORD",'/<tr>\s+<td.+?>(.+?)<\/td>\s+<td.+?>(.+?)<\/td>\s+<td.+?>(.+?)<\/td>\s+<td.+?><img.+IllegalImages\/(.+?)\" .+?><\/td>\s+<\/tr>/');
define("MATCH_PATTERN_PAGNIATION",'/onclick=\'gopage\((\d+)\);\'/');
define("CURLTIMEOUT",20000);
define("DEBUG",true);

function timestamp(){
	// return intval(microtime(true)/1000);
	return microtime(true);
}

function jsonpWrap($msg){
	$jsonp=isset($_GET['callback']) ? $_GET['callback'] : null;
	return $jsonp ? $jsonp.'('.$msg.')' : $msg;
}

function output(){
	global $rtn,$info,$list;
	$info['et']=timestamp()-$info['et'];
	$rtn['info']=$info;
	$rtn['list']=$list;
	$json=json_encode($rtn);
	print jsonpWrap($json);
	die();
}

function throwError($msg){
	global $rtn;
	$rtn['status']='error';
	$rtn['msg']=$msg;
	print jsonpWrap($rtn);
	die();
}

function traceArg($k,$v){
	if(!DEBUG)return false;
	global $info;
	$info['args'][]=$v;
	return true;
}

function execCurl(){
	global $info;
	$ch        =curl_init(URL_QUERY);
	$fields    =array('area_id','page','qdate_from','qdate_end');
	$fields_in =array('area'   ,'page','after'     ,'before'   );
	$postdata  =array();
	//$header    ='';
	//$contents  ='';
	
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch,CURLOPT_POST,true);
	curl_setopt($ch,CURLOPT_TIMEOUT_MS,CURLTIMEOUT);
	//is this correct?
	curl_setopt($ch,CURLOPT_USERAGENT,$_GET['user_agent']||$_SERVER['HTTP_USER_AGENT']);

	foreach($fields_in as $k => $v ){
		$gv=$_GET[$v];
		if(!isset($gv))continue;
		traceArg($k,$v);
		$postdata[$fields[$k]]=$gv;
	}
	//$info['post']=$postdata;
	curl_setopt($ch,CURLOPT_POSTFIELDS,$postdata);

	$ec=curl_exec($ch);
	if(curl_errno($ch)==28){
		throwError('Connection timeout!');		
	}else if(curl_errno($ch)){
		throwError('Curl Error occured. '.curl_errno($ch).': '.curl_error($ch).").");
	}
	//else{
	//	list($header,$contents)=preg_split('/([\r\n][\r\n])\\1/',$ec,2);
	//}
	curl_close($ch);
	return $ec;
}

function processRecords($result){
	global $list;
	$len=preg_match_all(MATCH_PATTERN_RECORD,$result,$match);
	for($i=0;$i<$len;$i++){
		$data=array();
		$data['date']=$match[1][$i];
		$data['code']=$match[2][$i];
		$data['area']=$match[3][$i];
		$data['image']=$match[4][$i];
		$list[]=$data;
	}
	return $list;
}

function processPagniations($result){
	global $info;
	$len=preg_match_all(MATCH_PATTERN_PAGNIATION,$result,$match);
	//print_r($match);
	$data=array();
	for($i=0;$i<$len;$i++){
		$data[$i]=$match[1][$i]+1;
	}
	$info["pagniations"]=$data;
	return $info;
}

function main(){
	global $list,$info;
	$isEmpty=false;
	$result=execCurl();
	//if try to redirect
	if(preg_match("/location.href=/",$result)===1){
		$isEmpty=true;
	}else{
		processRecords($result);
		processPagniations($result);
		//$info["raw"]=$result;
	}
	$info["is_empty"]=$isEmpty;
}

$info=array('et'=>timestamp(),'args'=>array());
$list=array();
$rtn=array('status'=>'ok');

main();
output();

?>
