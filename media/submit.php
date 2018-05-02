<?php

include_once('settings.php');
include_once('common.php');

$res = explode($_SERVER["SCRIPT_NAME"]."/",$_SERVER["PHP_SELF"]);

if(preg_match("/\\.\\./",$res[1])) {
  error(400);
}

if($_SERVER["REQUEST_METHOD"]=="PUT") {
  $path = explode("/",$res[1]);
  unset($path[count($path)-1]);
  $path = implode("/",$path)."/";
  exec("mkdir -p 'files/".$path."'");
  $contents = fopen("php://input","r");
  if(!$contents) error(403);
  $fp = fopen("files/".$res[1],"w");
  if(!$fp) error(403);
  while($data = fread($contents,CHUNK))
    fwrite($fp,$data);
  fclose($fp);
  fclose($contents);
  header("HTTP/1.1 201 Created");
}
else if($_SERVER["REQUEST_METHOD"]=="POST") {
  $uid = $_SERVER["PHP_AUTH_USER"];
  $q = "PREFIX foaf: <http://xmlns.com/foaf/0.1/>\r\nSELECT ?uri WHERE { ?uri foaf:account [ foaf:accountName ?name ] FILTER(str(?name) = \"$uid\") }";
  //SELECT ?uri WHERE { ?uri foaf:account ?x . ?x foaf:accountName \"$uid\" . }";
  $result = file_get_contents(ENDPOINT."?query=".urlencode($q));
  $dom = new DOMDocument();
  $dom->loadXML($result);
  $nodes = $dom->getElementsByTagName("uri");
  if($nodes->length>1) {
?>
    <html>
      <body>
        <p>Your user account appears more than once in the triple store. You should verify that no one is attempting to hijack your account.</p>
      </body>
    </html>
<?
  }
  else if($nodes->length<1) {
?>
    <html>
      <body>
      <p>Your user account is not specified in the triple store. You are not allowed to upload images.</p>
      </body>
    </html>
<?
  }
  $uri = $nodes->item(0)->textContent;
  
  if($_FILES["content"]["error"]==UPLOAD_ERR_OK) {
    $tmp_name = $_FILES["content"]["tmp_name"];
    $file = $_POST["file"];
    handleFileUpload($tmp_name,$file,$uri);
  }
  else {
    ?><html>
      <body>
      <p>No document uploaded.</p>
      </body>
    </html><?
  }
}
else {
?>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>TWC Document Manager</title>
    <script src="media.js"></script>
    <script src="sha1.js"></script>
    <script src="json2.js"></script>
  </head>
  <body onload="prepare();">
    <span>Hello <?=$_SERVER["PHP_AUTH_USER"]?></span>
    <form name="media" method="POST" action="submit.php" enctype="multipart/form-data">
      <p><b>Source File: </b><input type="file" name="content"/></p>
      <p><b>Destination: </b><input type="text" name="file" onkeydown="stopVerify();" onkeyup="startVerify();"/><br/><span id="errorcode" style="display:none; color:red; background-color: pink;"></span></p>
      <p><input type="submit" value="Upload"/></p>
    </form>
  </body>
</html>
<?
}
?>
