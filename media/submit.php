<?php

include_once('settings.php');
include_once('common.php');

if( $_SERVER["REQUEST_METHOD"] != "POST" ) {
  http_response_code(405);
  echo "{ \"error\": \"Upload of file was unsuccessful. Failed to post the file.\" }";
  return;
}

if( !isset($_POST['alt']) || !isset($_POST['username']) || !isset($_POST['source']) ) {
  http_response_code(400);
  echo "{ \"error\": \"Upload of file was unsuccessful. Failed to save the information.\" }";
  return;
}

$uid = $_POST['username'];
$q = "PREFIX foaf: <http://xmlns.com/foaf/0.1/>\r\nSELECT ?uri WHERE { ?uri foaf:account [ foaf:accountName ?name ] FILTER(str(?name) = \"$uid\") }";
try {
  $result = file_get_contents(ENDPOINT."?query=".urlencode($q));
  $dom = new DOMDocument();
  if($result == "") {
    http_response_code(404);
    echo "{ \"error\": \"Upload of file was unsuccessful. Unable to authorize.\" }";
    return;
  }
  $dom->loadXML($result);
  $nodes = $dom->getElementsByTagName("uri");
  if($nodes->length != 1) {
    http_response_code(404);
    echo "{ \"error\": \"Upload of file was unsuccessful. You are not authorized.\" }";
    return;
  }
} catch(Exception $err) {
  http_response_code(500);
  echo "{ \"error\": \"" . $err->getMessage() . "\" }";
  return;
}

$uri = $nodes->item(0)->textContent;
$fileName = preg_replace("/ /","_",$_POST['alt']);
$tmpPath = "/tmp/" . $fileName;

$f = fopen('/tmp/' . $_POST['alt'], 'w');
fwrite($f, base64_decode($_POST['source']));
fclose($f);

handleFileUpload($tmpPath, $fileName, $uri);
?>

