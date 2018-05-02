<?php
// $Id$

/**
 * Defines a logger object for TW Sparql module to provide feedback to users.
 * @author pattoe 
 */
interface TWDocsLogger {
  
  /**
   * Return whether debugging is turned on or not
   */
  public function shouldDebug();

  /**
   * Set whether or not we should debug
   */
  public function enableDebug($val);

  /**
   * Logs a string as an error.
   * @param string $str String to log to the error console. 
   */
  public function logError($str);
  
  /**
   * Logs a string as a warning
   * @param string $str String to log to the warning console. 
   */
  public function logWarning($str);
  
  /**
   * Logs a string as a status
   * @param string $str String to log to the status console. 
   */
  public function logStatus($str);
}

abstract class TWDocs {
  
  public static $engine = NULL;
  public static $logger = NULL;
  protected $params = NULL ;
  protected $curTag = NULL ;
  protected $curQueryUri = NULL;
  protected $curQueryText = NULL;
  protected $curEndpoint = NULL;
  protected $curXsltUri = NULL;
  protected $curXsltDoc = NULL;
  protected $curRDFDoc = NULL;
  protected $curReferer = NULL;
  protected $pageid = NULL;
  protected $sendAccept = TRUE;

  /**
   * Sets whether to send the Accept: header to the remote endpoint. This
   * is a hack added to prevent an NPE in the VIVO endpoint.
   * @param boolean $accept If TRUE, the engine will send an Accept header
   * for application/rdf+xml or text/turtle to the endpoint. If FALSE, no
   * Accept header will be sent.
   */
  public function setSendAccept( $accept )
  {
    $this->sendAccept = $accept == TRUE; // force to TRUE/FALSE from bool-like
  }

  /**
   * Gets whether the engine will send the HTTP Accept header when querying
   * a remote endpoint.
   * @returns TRUE if an Accept header will be sent, otherwise FALSE.
   */
  public function getSendAccept()
  {
    return $this->sendAccept;
  }

  /**
   * Gets a static instance of the logger used for communicating information
   * to the containing CMS.
   * @return TWDocsLogger
   */
  public static function getLogger() {
    return TWDocs::$logger;
  }
  
  /**
   * Gets a static instance of the TW Docs Engine for the CMS.
   * @return TWDocs
   */
  public static function getEngine() {
    return TWDocs::$engine;
  }
  
  /** Main entry point for the TWDocs interface
   *
   * This is the main function, main entry point for the common TWDocs
   * interface. This is the function that controls the flow of operation
   * for rendering the content.
   *
   * The content of the page to be rendered is passed to this function.
   * In that content could be document tags, one or more. For each tag:
   *
   * - parse the tag, pulling out any and all attributes specified
   * - execute the query (query could be cached, or use query from
   * previous tag)
   * - transform the results (xslt could be cached, results could be
   * cached)
   * - Replace the document tag in the content with the rendered content
   *
   * @param int $pageid The identifier for this page
   * @param string $content The body of the page in the CMS to be
   * rendered
   */
  public function render( $pageid, $content )
  {
	file_put_contents("/tmp/docsRender", "$content") ;
    TWDocs::getLogger()->logStatus( "*****STARTING A NEW QUERY*****." ) ;

    $this->pageid = $pageid ;

    $newContent = "" ;

    // Match script tag
    $matches = array() ;

    // search for document tags
    if(0<preg_match_all("/<document(([^\/>]*(\/[^>])?)*)(\/>|>(([^<]*(<[^\/])?)*)<\/document>)/",$content,$matches,PREG_OFFSET_CAPTURE)) {
      // parse the attributes of the document tag. Keep track of how long
      // this takes
      $start = microtime( TRUE ) ;
      $this->params = TWDocs::parseParams( $matches ) ;
      $end = microtime( TRUE ) ;
      TWDocs::getLogger()->logStatus( "Processing document parameters took ".(($end-$start)*1000)." milliseconds." ) ;

      $start = 0;

      // The query file, query results, xslt file, and rendered content
      // could be cached. But we call a generic function to execute a
      // query, transform the results.

      // can be multiple document tags, for each one ...
      foreach( $this->params as $desc )
      {
        $this->curTag = $desc ;

        // save off the current uri
        if( $desc["uri"] && $desc["uri"] != "" )
        {
          $this->curReferer = $desc["uri"] ;
        }

        // add everything from the end of the last document tag up to this document tag
        $newContent .= substr( $content, $start, $desc["startChar"]-$start ) ;

        // go get the html that replaces the document tag. Could be an embedded image, link to a document, link to the document page, link to upload a new
        // version, link to download
        $newContent .= $this->placeReference($desc, $node);

        // new start is the end of this tag
        $start = $desc["endChar"];
      }

      $content = $newContent . substr($content,$start);
    }

    // - replace the tag with the rendered result
    return preg_replace("/<\/document>(<\/p>)?/","",$content);
  }

  /**
   * Parses each &lt;document&gt; tag and their attributes
   * @param array $matches Output from preg_match_all
   * @return 
   */
  public static function parseParams($matches) {
    $params = array();

    $tagText = $matches[0];
    $paramText = $matches[1];
    $queryText = $matches[6];

    for($i=0;$i<count($tagText);$i++) {
      $text = $tagText[$i];
      $desc = array();
      $desc["startChar"] = $text[1];
      $desc["length"] = strlen($text[0]);
      $desc["endChar"] = $desc["startChar"] + $desc["length"];
      $desc["href"] = null;
      $desc["version"] = "latest";
      $desc["alt"] = null;
      $desc["mode"] = "link";
      $desc["id"] = null;
      $pairs = array();
      $count = preg_match_all("/([[:alpha:]]*)=\"([^\"]*)\"/",$paramText[$i][0],$pairs);
      for($j=0;$j<$count;$j++) {
        switch($pairs[1][$j]) {
          case "usemap":
            $desc["usemap"] = $pairs[2][$j];
            break;
          case "style":
            $desc["style"] = $pairs[2][$j];
            break;
          case "href":
            $desc["href"] = $pairs[2][$j];
            break;
          case "version":
            $desc["version"] = $pairs[2][$j];
            break;
          case "alt":
            $desc["alt"] = $pairs[2][$j];
            break;
          case "mode":
            $desc["mode"] = $pairs[2][$j];
            break;
          case "id":
            $desc["id"] = $pairs[2][$j];
            break;
          case "revision":
            $desc["revision"] = $pairs[2][$j];
            break;
        }
      }
      if($desc["alt"]==null) {
        // Short term: untitled
        // Long term: SPARQL for dc:title and display
        $desc["alt"] = $desc["href"];
      }
      $params[] = $desc;
    }

    return $params;
  }
  
  public function placeReference($params, &$node) {
    global $user;
    if(!$this->doesFileExist($params["href"])) {
      $class = "class=\"missing\"";
      $params["mode"] = "link";
    } else {
      $class = "";
      $base = $this->getBaseURI() ;
      $uri = $base.$params["version"]."/".$params["href"];
      $page = $this->getProperty($uri,"http://xmlns.com/foaf/0.1/page");
      if($page != null && count($page)>0) {
        $page = $page[0];
      } else {
        unset($page);
        $arr = explode( "/", $uri ) ;
        if( count( $arr ) > 0 ) { 
          $oldName = $arr[count( $arr ) - 1] ;
          $fulldir = "/var/www/html/tw.rpi.edu/media/files" ;
          $fullpath = $fulldir."/latest/".$oldName ;
          $actualuri = $base."latest/".$oldName ;
          $page = "http://tw.rpi.edu/web/doc/Document?uri=$actualuri" ;
        }
      }
    }
    if(isset($params["id"])) {
      $id = 'id="'.$params["id"].'" ';
    } else {
      $id = "";
    }
    switch($params["mode"]) {
      case "link":
        $value = "<a $id$class ".(isset($page)?"href=\"":"");
        break;
      case "img":
        $value = "<a href=\"$uri\"><img $id$class src=\"$uri\"";
        if(isset($params["style"])) {
          $value .= ' style="'.$params["style"].'" ';
        }
        if(isset($params["usemap"])) {
          $value .= ' usemap="'.$params["usemap"].'" ';
        }
        unset($page);
        break;
      case "embed":
        $value = "<object $id$class ".(isset($page)?"data=\"":"");
        break;
    }
    $value .= (isset($page) ? $page."\" " : "");
    if($params["mode"]=="embed") {
      $value .= "/>";
    } else {
      if( !isset($params["usemap"]) ) {
        $value .= "alt=\"".$params["alt"]."\"";
      }
      if($params["mode"]=="img") {
        $value .= " title=\"".$params["alt"]."\" /></a>";
      } else {
        $value .= ">".$params["alt"]."</a>";
        if($class=="") {
          $uri = preg_replace('/\s/','_',$uri);
          $value .= " [<a href=\"$uri\">Download</a>]";
        }
        /*
        } else if(node_access("update",$node)) {
          $value .= " [<a onclick=\"uploadForm('".$params["href"]."');\">Upload</a>]";
        }
        */
      }
    }
    return $value;
  }

  private function doesFileExist($href) {
    $result = $this->makeApiCall("checkInUse",array("file"=>$href));
    file_put_contents("/tmp/fileexists", $result);
    if($result===null || $result->response==FALSE) return FALSE;
    return TRUE;
  }

  private function getProperty($uri, $prop) {
    $result = $this->makeApiCall("getProperty",array("subject"=>$uri,"property"=>$prop));
    if($result===null || count($result->response)==0) {
      return null;
    }
    return $result->response;
  }

  private function makeApiCall($request, $args) {
    $nonce = time();
    $service = $this->getServiceID();
    $key = $this->getApiKey();
    $unhashed = "$service:$nonce:$request:$key";
    $hashed = sha1($unhashed);
    $msg = "service=$service&nonce=$nonce&request=$request&hash=$hashed";
    foreach($args as $param => $value) {
      $msg .= "&$param=".urlencode($value);
    }
	file_put_contents("/tmp/apiCall", "$msg - " . $this->getApiURL()) ;
    $params = array('http' => array('method' => 'POST',
            'content' => $msg));
    $ctx = stream_context_create($params);
    $ans = @file_get_contents($this->getApiURL(),false,$ctx);
    $answer = @json_decode($ans);
    return $answer;
  }

  /** Simple function that simply dumps the string variables for this
   * class
   */
  private function dump()
  {
    TWDocs::getLogger()->logStatus( "TWDocs dump" ) ;
    #TWDocs::getLogger()->logStatus( "&nbsp;&nbsp;&nbsp;&nbsp;curQueryUri = $this->curQueryUri" ) ;
  }
  
  // Settings
  public abstract function shouldDebug();
  public abstract function enableDebug($val);
  public abstract function getBaseURI();
  public abstract function setBaseURI($val);
  public abstract function getServiceID();
  public abstract function setServiceID($val);
  public abstract function getApiKey();
  public abstract function setApiKey($val);

  function getApiURL() {
    return $this->getBaseURI()."api.php";
  }
}

