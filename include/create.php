&nbsp;
<?php
include_once("statsanalyzer/statsanalyzer.php");

$filename = "stats/" . date('Y.M.d. H\hi\ms\s') . ".html";
if ($_FILES['logfile']['error'] != UPLOAD_ERR_OK)
{
	echo "Error while uploading file, error code " . $_FILES['logfile']['error'];
	echo "<script type='text/javascript'>alert('" . $_FILES['logfile']['error'] . "');</script>";
}
else
{
	ob_start();
	echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\"/>";
	echo "<html>\n<head>\n<title>RTCW Logfile</title>\n";
	echo "<script type=\"text/javascript\">
		function togglechild(obj) {
			if(obj.nextElementSibling.style.display == \"block\")
			{
				obj.nextElementSibling.style.display = \"none\";
			}
			else
			{
				obj.nextElementSibling.style.display = \"block\";
			}
		}
		</script>
		</head>
		<body>
	";
	echo "<style type=\"text/css\">\n";
	echo file_get_contents('./browser.css');
	echo "</style>\n";
	echo "<div class='container'>\n";
	echo "<div class='shortcuts'><a href='#overallstats'>Overall Stats</a> | <a href='#awards'>Awards</a> | <a href='#weapons'>Weapon Stats</a> | <a href='#matrix'>Kill Matrix</a> | <a href='#players'>Player Stats</a> | <a href='#chatlog'>Chat Log</a></div>\n";
	$analyzer = new StatsAnalyzer($_FILES["logfile"]["tmp_name"]);
	//$analyzer = new StatsAnalyzer("rtcwconsole.log");
	echo "<h1><a name='overallstats'>&nbsp;</a>Overall Stats</h1>\n";
	$analyzer->PrintStats();
	echo "<h1><a name='awards'>&nbsp;</a>Awards</h1>\n";
	echo "<div class='wrapper'>\n";
	$analyzer->PrintMainAwards();
	$analyzer->PrintWeaponAwards();
	echo "</div>\n";
	echo "<h1><a name='weapons'>&nbsp;</a>Weapon Stats</h1>\n";
	$analyzer->PrintWeapons();
	echo "<h1><a name='matrix'>&nbsp;</a>Kill Matrix</h1>\n";
	$analyzer->PrintMatrix();
	echo "<h1><a name='players'>&nbsp;</a>Player Stats</h1>\n";
	echo "<div class='wrapper'>\n";
	$analyzer->PrintPlayers();
	echo "</div>\n";
	echo "<h1><a name='chatlog'>&nbsp;</a>Chat Log</h1>\n";
	$analyzer->PrintChatlog();
	echo "<div class='impressum'>Tool made by Raul, 2013</div>\n";
	echo "</div>";
	echo "</body>\n</html>";
	file_put_contents($filename, ob_get_contents());
	
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on")
		$pageURL .= "s";
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80")
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].dirname($_SERVER["REQUEST_URI"]);
	else
		$pageURL .= $_SERVER["SERVER_NAME"] . dirname($_SERVER["REQUEST_URI"]);
	header("Location: " . $pageURL . "/" . $filename);
	exit;
}
?>