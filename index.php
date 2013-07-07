<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"/>
<html>
<head>
	<title>RTCW Logfile Analyzer</title>
	<link rel="stylesheet" media="screen" href="browser.css"/>
	<script type="text/javascript">
		function togglechild(obj) {
			if(obj.nextElementSibling.style.display == "block")
			{
				obj.nextElementSibling.style.display = "none";
			}
			else
			{
				obj.nextElementSibling.style.display = "block";
			}
		}
	</script>
	<script src="sorttable.js"></script>
</head>
<body>
<?php

	ini_set('display_errors', E_ALL);
	
	if (isset($_GET['s']))
		$site = $_GET['s'];
	else
		$site = "upload";
		
	$site_path = "include/" . $site . ".php";
	
	if (file_exists($site_path))
		include($site_path);
	else
		echo "file not found.";
		
	
?>
</body>
</html>