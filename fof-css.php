<?php
$width = intval($_REQUEST['width']);
header("Content-Type: text/css");
?>
#sidebar { width: <?=$width?>px; }
#handle { left: <?=$width?>px; }
#items { marginLeft: <?=($width+20)?>px; }
#item-display-controls { left: <?=($width+10)?>px; }
