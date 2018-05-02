<?php

/** DEBUG **/
/*
echo "File size according to PHP = ".$_FILES["content"]["size"];
echo "Error: ".$_FILES["content"]["error"];
echo "Path: ".$_FILES["content"]["tmp_name"];
$fd = fopen($_FILES["content"]["tmp_name"],"r");
$stats = fstat($fd);
fclose($fd);
echo "File size according to OS = ".$stats["size"];
die;
*/
/** /DEBUG **/

include_once('settings.php');
include_once('db.php');
include_once('common.php');

function checkInUse($file) {
  return file_exists("files/latest/$file");
}

function renameFile($oldName, $newName) {
  
}

function addTripleObj($subj, $pred, $obj) {
  
}

function addTripleVal($subj, $pred, $val) {
  
}

$service = $_POST["service"];
$nonce = $_POST["nonce"];
$request = $_POST["request"];
$hash = $_POST["hash"];

$unhashed = $service.":".$nonce.":".$request;
if(($key=getSecretForApp($service))==false) { echo "{\"success\":false,\"error\":\"No service '$service' registered.\"}"; die; }
$unhashed .= ":".$key;
if($hash != sha1($unhashed)) { echo "{\"success\":false,\"error\":\"Hash did not match. Got $hash, expected ".sha1($unhashed)."\"}"; die; }
if($request=="checkInUse") {
  $file = $_POST["file"];
  if(preg_match("/\\.\\./",$file)) {
    error(403);
  }
  $file = preg_replace("/ /","_",$file);
  $answer = checkInUse($file) ? "true" : "false";
  echo '{"response":'.$answer."}";
}
else if($request=="getProperty") {
  $subj = $_POST["subject"];
  $subj = preg_replace("/ /","_",$subj);
  $prop = $_POST["property"];
  $prop = preg_replace("/ /","_",$prop);
  $q = "SELECT ?val WHERE { <$subj> <$prop> ?val }";
  $result = @file_get_contents(ENDPOINT."?query=".urlencode($q));
  if($result==""||$result==null) {
    echo '{"response":false}';
    die;
  }
  $dom = new DOMDocument();
  if(!@$dom->loadXML($result)) {
    echo '{"response":false}';
    die;
  }
  $nodes = $dom->getElementsByTagName("binding");
  $len = $nodes->length;
  $response = "[";
  for($i=0;$i<$len;$i++) {
    if($i>0) $response .= ",";
    $response .= '"'.trim($nodes->item($i)->textContent).'"';
  }
  $response .= "]";
  echo '{"response":'.$response.'}';
  die;
}
else if($request=="putFile") {
  if($service=="media") {
?><html><head></head><body><p>Upload requests are not available for the media application.</p></body></html><?
    error(403);
  }
  $nonce = $_POST["nonce"];
  $oldNonce = getNonceForApp($service);
  if(intval($oldNonce) >= intval($nonce)) {
    ?><html><head></head><body><p>Nonce is incorrect</p></body></html><?
    error(403);
  }
  $file = $_POST["file"];
  $creator = $_POST["creator"];
  if(0!==strpos($creator,"http://")) {
    $q = "PREFIX foaf: <http://xmlns.com/foaf/0.1/>\r\nSELECT ?uri WHERE { ?uri foaf:account [ foaf:accountName ?name ] FILTER(str(?name) = \"$creator\") }";
    $result = file_get_contents(ENDPOINT."?query=".urlencode($q));
    $dom = new DOMDocument();
    $dom->loadXML($result);
    $nodes = $dom->getElementsByTagName("uri");
    if($nodes->length>1) {
      echo "{\"success\":false,\"error\":\"Your user account appears more than once in the triple store. You should verify that no one is attempting to hijack your account.\"}";
      die;
    }
    else if($nodes->length<1) {
      echo "{\"success\":false,\"error\":\"Your user account '".$creator."' is not specified in the triple store. You are not allowed to upload documents.\"}";
      die;
    }
    $creator = $nodes->item(0)->textContent;
  }
  $content = $_FILES["content"];
  if($_FILES["content"]["error"]!==UPLOAD_ERR_OK) {
    echo '{"success":false,"error":'.$_FILES["content"]["error"].'}';
    die;
  }
  if(isset($_POST['title']))
    $title = $_POST["title"];
  else
    $title = NULL;
  $result = handleFileUpload($content["tmp_name"],$file,$creator,NULL,$title);
  setNonceForApp($service, $nonce);
  /*
  if(isset($_SERVER['HTTP_REFERER']))
    header("Location: ".$_SERVER["HTTP_REFERER"]);
  else
  */
    echo json_encode($result);
}
else {
  echo '{"success":false,"error":"Unsupported method"}';
  die;
}
