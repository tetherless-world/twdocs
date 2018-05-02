<?php

function wordPreview($path) {

  // Tmp directory to store intermediate output
  $name = sha1("".time().$path);
  $return = getRoot();
  $parts = explode("/",$path);
  $return .= "files/preview/".$parts[count($parts)-1];
  exec("mkdir /tmp/$name");

  // Conversion process
  exec("wvPDF $path /tmp/$name/preview.pdf");
  exec("pdftk A=$name/preview.pdf cat A1 output /tmp/$name/preview2.pdf");
  exec("convert -resize 150 /tmp/$name/preview.pdf2 $return");

  // Cleanup
  exec("rm -rf /tmp/$name");

  return $return;
}
