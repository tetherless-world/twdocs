<?php
echo "H = ".date("H")."\r\n";
echo "i = ".date("i")."\r\n";
echo "s = ".date("s")."\r\n";
echo dechex((3600*date("H")) + (60*date("i")) + (1*date("s")))."\r\n";
