<?php

define('MEDIA_DEBUG',FALSE);

function error($num) {
  switch($num) {
  case 400:
    header("HTTP/1.1 400 Bad Request");
    break;
  case 403:
    header("HTTP/1.1 403 Forbidden");
    break;
  default:
    header("HTTP/1.1 500 Internal Server Error");
    break;
  }
  die;
}

function handleFileUpload($tmpPath, $file, $creator, $oldName = NULL, $title = NULL) {
  // For debugging purposes, if an error occurs we'll see the dump
  header("Content-type: text/html");

  $file = preg_replace("/ /","_",$file);
  if($oldName === NULL) $oldName = $file;

  // Compute date and path information for the new file
  $date = date("Y-m-d\\TH:i:sP");
  $sec = dechex((3600*date("H")) + (60*date("i")) + (1*date("s")));
  $base = getBase();
  $path = date("Y/m/d") . "/" . $sec ;

  // Create the directory to store the file if it doesn't exist
  if(MEDIA_DEBUG) {
    echo "mkdir -p 'files/".$path."'<br>";
  }
  else {
    exec("mkdir -p 'files/".$path."'",$text,$result);
    if($result!=0) {
      echo '{"success":false,"error":"unable to create target directory"}';
      die;
    }
  }

  // Move the file
  if(MEDIA_DEBUG) {
    echo "move_upload_file($tmpPath, 'files/$path/$file')<br>";
  }
  else {
    if(!move_uploaded_file($tmpPath, "files/$path/$file"))
      error(500);
    /*
    exec("openssl enc -d -base64 -in '$tmpPath' -out 'files/$path/$file'");
    */
  }

  // Create or update the symlink in the "latest" directory
  if(!is_link("files/latest/$oldName")) {
    // There's no link yet, so create one
    if(MEDIA_DEBUG) {
      echo "Create symlink 'files/latest/$file' to 'files/$path/$file'<br>";
    }
    else {
      symlink(getRoot()."files/$path/$file","files/latest/$file");
      $query = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>\r\n".
        "PREFIX dc: <http://purl.org/dc/terms/>\r\n".
        "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\r\n".
        "PREFIX foaf: <http://xmlns.com/foaf/0.1/>\r\n\r\n".
        "INSERT DATA {\r\n".
        "  <$base"."latest/$file> rdf:type foaf:Document .\r\n".
        ($title?"  <$base"."latest/$file> dc:title \"$title\" .\r\n":"").
        "}";
      if(MEDIA_DEBUG) {
        echo "Execute query:<br><pre>".htmlspecialchars($query)."</pre><br>";
      }
      else {
        $params = array('http' => array('method' => 'POST',
  				        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
				        'content' => 'request='.urlencode($query)));
        $ctx = stream_context_create($params);
        file_get_contents(UPDATE,false,$ctx);
      }
    }
  }
  else {
    // Make SPARQL update to reference old file
    $oldPath = readlink("files/latest/$oldName");
    $oldPath = explode("files/",$oldPath);
    $oldPath = $oldPath[count($oldPath)-1];
    $query = "PREFIX dc: <http://purl.org/dc/terms/>\r\n".
      "INSERT DATA {\r\n".
      "<$base$path/$file> dc:replaces <$base$oldPath> .\r\n".
      "}";
    if(MEDIA_DEBUG) {
      echo "Execute query:<br><pre>".htmlspecialchars($query)."</pre><br>";
    }
    else {
      $params = array('http' => array('method' => 'POST',
				      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
				      'content' => 'request='.urlencode($query)));
      $ctx = stream_context_create($params);
      file_get_contents(UPDATE,false,$ctx);
    }

    // Replace the symlink with one to the new file
    if(MEDIA_DEBUG) {
      echo "Remove symlink 'files/latest/$file'<br>";
      echo "Create symlink 'files/latest/$file' to 'files/$path/$file'<br>";
    }
    else {
      exec("rm 'files/latest/$file'");
      symlink(getRoot()."files/$path/$file","files/latest/$file");
    }
  }

  // Add metadata about the file to the SPARQL endpoint
  $query = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>\r\n".
    "PREFIX dc: <http://purl.org/dc/terms/>\r\n".
    "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\r\n".
    "PREFIX foaf: <http://xmlns.com/foaf/0.1/>\r\n\r\n".
    "INSERT DATA {\r\n".
    "  <$base$path/$file> rdf:type foaf:Document .\r\n".
    "  <$base$path/$file> dc:creator <$creator> .\r\n".
    ($title?"  <$base$path/$file> dc:title \"$title\" .\r\n":"").
    "  <$base$path/$file> dc:created \"$date\"^^xsd:dateTime .\r\n".
    "  <$base$path/$file> dc:modified \"$date\"^^xsd:dateTime .\r\n".
    "}";
  if(MEDIA_DEBUG) {
    echo "Execute query:<br><pre>".htmlspecialchars($query)."</pre>";
  }
  else {
    $params = array('http' => array('method' => 'POST',
				    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
				    'content' => 'request='.urlencode($query))
		    );
    $ctx = stream_context_create($params);
    $response = file_get_contents(UPDATE,false,$ctx);
  }

  return array("success"=>true,"persist"=>"$base$path/$file","latest"=>$base."latest/$file");
}
