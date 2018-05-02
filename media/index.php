<?php

include_once('settings.php');

function getBestSupportedMimeType($mimeTypes = null) {
  // Values will be stored in this array
  $AcceptTypes = Array ();

  // Accept header is case insensitive, and whitespace isn’t important
  $accept = strtolower(str_replace(' ', '', $_SERVER['HTTP_ACCEPT']));
  // divide it into parts in the place of a ","
  $accept = explode(',', $accept);
  foreach ($accept as $a) {
    // the default quality is 1.
    $q = 1;
    // check if there is a different quality
    if (strpos($a, ';q=')) {
      // divide "mime/type;q=X" into two parts: "mime/type" i "X"
      list($a, $q) = explode(';q=', $a);
    }
    // mime-type $a is accepted with the quality $q
    // WARNING: $q == 0 means, that mime-type isn’t supported!
    $AcceptTypes[$a] = $q;
  }
  arsort($AcceptTypes);

  // if no parameter was passed, just return parsed data
  if (!$mimeTypes) return $AcceptTypes;

  $mimeTypes = array_map('strtolower', (array)$mimeTypes);

  // let’s check our supported types:
  foreach ($AcceptTypes as $mime => $q) {
    if ($q && in_array($mime, $mimeTypes)) return $mime;
  }
  // no mime-type found
  return null;
}


function getRequestedFile() {
  $res = explode($_SERVER["SCRIPT_NAME"]."/",$_SERVER["PHP_SELF"]);
  if($res[1]=="") return FALSE;
  return preg_replace('/ /','_',$res[1]);
}


function processEntry(&$entries, $uri) {
  // If the URI doesn't exist we are done
  if(!isset($entries[$uri])) return;

  // If the created != modified then we have already processed this entry
  if($entries[$uri]["created"]!=$entries[$uri]["modified"]) return;

  // If there is no previous node, we are done
  if(!isset($entries[$uri]["prev"])) return;

  // Process the previous document
  processEntry($entries, $entries[$uri]["prev"]);

  // Update the created field
  $entries[$uri]["created"] = $entries[$entries[$uri]["prev"]]["created"];
  $entries[$uri]["creator"] = array_merge($entries[$uri]["creator"],
					  $entries[$entries[$uri]["prev"]]["creator"]);

  // Unset the previous document
  unset($entries[$entries[$uri]["prev"]]);
}


function processEntries(&$entries) {
  foreach($entries as $k => $v) {
    processEntry($entries, $k);
  }
}


$requestedFile = getRequestedFile();

// Check if this is a request for a file or is a request for the media manager interface
if($requestedFile===FALSE) {
  // No file specified, display manager interface

  // Request file metadata stored in the triple store
  $base = getBase();
  $query = 
    "PREFIX foaf: <http://xmlns.com/foaf/0.1/>\r\n".
    "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>\r\n".
    "PREFIX dc: <http://purl.org/dc/terms/>\r\n".
    "\r\n".
    "SELECT ?uri ?creator ?first ?last ?created ?modified ?prev WHERE {\r\n".
    "  ?uri rdf:type foaf:Document .\r\n".
    "  FILTER(regex(str(?uri),\"".BASE."\")) .\r\n".
    "  ?uri dc:creator ?creator .\r\n".
    "  { ?creator foaf:firstName ?first }\r\n".
    "  UNION { ?creator foaf:givenname ?first }\r\n".
    "  UNION { ?creator foaf:givenName ?first }\r\n".
    "  { ?creator foaf:lastName ?last }\r\n".
    "  UNION { ?creator foaf:surname ?last }\r\n".
    "  UNION { ?creator foaf:family_name ?last }\r\n".
    "  UNION { ?creator foaf:familyName ?last }\r\n".
    "  ?uri dc:modified ?modified .\r\n".
    "  ?uri dc:created ?created .\r\n".
    "  OPTIONAL { ?uri dc:replaces ?prev }\r\n".
    "} ORDER BY DESC(?modified)";
  $result = file_get_contents(ENDPOINT."?query=".urlencode($query));
  $doc = new DOMDocument();
  $doc->loadXML($result);

  // Process results
  $entries = array();
  $results = $doc->getElementsByTagName("result");
  $len = $results->length;
  for($i=0;$i<$len;$i++) {
    $entry = array();
    $result = $results->item($i);
    $bindings = $result->getElementsByTagName("binding");
    $blen = $bindings->length;
    for($j=0;$j<$blen;$j++) {
      $binding = $bindings->item($j);
      $label = $binding->getAttribute("name");
      $value = trim($binding->textContent);
      if($label=="creator")
	$entry[$label] = array($value => 1);
      else
	$entry[$label] = $value;
    }
    $entries[$entry["uri"]] = $entry;
  }

  processEntries($entries);

  // Build the interface
?><a href="submit.php">Upload a new document</a>
<table style="width: 100%;">
<tr><th>Document</th><th>Creator</th><th>Last Modified</th><th>Created</th></tr>
<?
  foreach($entries as $uri => $doc) {
    echo "<tr";
    if($i % 2 == 0) echo " class=\"even\"";
    else echo " class=\"odd\"";
    echo ">";
    $name = explode("/",$uri);
    $name = $name[count($name)-1];
    echo "<td><a href=\"".$uri."\">".$name."</a></td>";
    echo "<td><a href=\"".$doc["creator"]."\">".$doc["first"]." ".$doc["last"]."</a></td>";
    $time = strtotime($doc["modified"]);
    echo "<td>".date("h:i a \\o\\n M j, Y",$time)."</td>";
    $time = strtotime($doc["created"]);
    echo "<td>".date("h:i a \\o\\n M j, Y",$time)."</td>";
    echo "</tr>";
  }
?>
</table>
<?
}
else {

  // Handle GET method. For now we don't support any other method
  if($_SERVER["REQUEST_METHOD"]=="GET") {

    // Obtain best possible Accept type
    $type = getBestSupportedMimeType(array("application/rdf+xml","*/*"));
    $fileType = exec("file -biL 'files/".$requestedFile."'");
    if($type=="application/rdf+xml" || 
       (isset($_GET["mode"]) && $_GET["mode"]=="rdf")) {
      // Handle application/rdf+xml as a separate condition
      $query = "";

      // Test if this is the 'current' symlink, look up the original file
      // and use it as the URI in the SPARQL request
      if(is_link('files/'.$requestedFile)) {
	$requestedFile = readlink('files/'.$requestedFile);
	$perl = "/".preg_replace("/\\//","\\/",getRoot())."files\\//";
	$requestedFile = preg_replace($perl,"",$requestedFile);
      }

      // Build the SPARQL query
      $base = getBase();
      $query = "PREFIX dc: <http://purl.org/dc/terms/> DESCRIBE <$base".$requestedFile."> ?x WHERE { <$base".$requestedFile."> dc:creator ?x }";

      // Return SPARQL result
      header("Content-type: application/rdf+xml");
      echo file_get_contents(ENDPOINT."?query=".urlencode($query));
    }
    else if(!preg_match("/ERROR/",$fileType)) {
      // Handle when the file command returns an error
      // (e.g. the file doesn't exist or is not readable)

      // Get the mime component
      $fileType = explode(';',$fileType);
      $fileType = $fileType[0];

      // Send headers and content
      header("Content-type: $fileType");
      readfile("files/".$requestedFile);
    }
    else {
      // Error occurred when stating file, so assume it doesn't exist
      header("HTTP/1.1 404 Not Found");
    }
  }
  else {
    // Not a GET request, so send a 405
    header("HTTP/1.1 405 Method Not Allowed");
    echo "<html><body>Method not allowed</body></html>";
  }
}
?>