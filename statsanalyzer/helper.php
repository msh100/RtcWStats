<?php

// static class to provide helper function
class Helper
{
	public static $weaponnames = array ("grenade" => "Grenade",
										"thompson" => "Thompson",
										"pf" => "Panzerfaust",
										"mp40" => "MP40",
										"sten" => "Sten",
										"luger" => "Luger 9mm",
										"colt" => ".45ACP 1911",
										"sniper" => "Sniper Rifle",
										"mauser" => "Mauser",
										"venom" => "Venom",
										"flamethrower" => "Flamethrower",
										"knife" => "Knife",
										"support" => "Support Fire",
										"artillery" => "Artillery Support",
										"dynamite" => "Dynamite",
										"mg" => "Crew-Served MG42",
										"drown" => "Drown",
										"crush" => "Crush",
										"jump" => "Deathjump",
										"other" => "Other"
									);

	public static $colors = array(	array('^0', '^p', '^P'),
									array('^1', '^Q', '^q'),
									array('^2', '^R', '^r'),
									array('^3', '^S', '^s'),
									array('^4', '^T', '^t'),
									array('^5', '^U', '^u'),
									array('^6', '^V', '^v'),
									array('^7', '^W', '^w'),
									array('^8', '^X', '^x'),
									array('^9', '^Y', '^y'),
									array('^:', '^Z', '^z'),
									array('^;', '^[', '^{'),
									array('^<', '^}', '^|'),
									array('^=', '^]', '^}'),
									array('^>', '^^', '^~'),
									array('^?', '^_', '^¿'),
									array('^@', '^`', '^À'),
									array('^!', '^A', '^a'),
									array('^"', '^B', '^b'),
									array('^#', '^C', '^c'),
									array('^$', '^D', '^d'),
									array('^%', '^E', '^e'),
									array('^&', '^F', '^f'),
									array('^\'', '^G', '^g'),
									array('^(', '^H', '^h'),
									array('^)', '^I', '^i'),
									array('^*', '^J', '^j'),
									array('^+', '^K', '^k'),
									array('^,', '^L', '^l'),
									array('^-', '^M', '^m'),
									array('^.', '^N', '^n'),
									array('^/', '^O', '^o')
								);
	
	// regular expressions to filter the logtext
	public static $regex_kills = array	(
										 "grenade" => "/\\[skipnotify\\](.*)\\^7 was exploded by (.*)\\^7's grenade/",
										 "pf" => "/\\[skipnotify\\](.*)\\^7 was blasted by (.*)\\^7\\'s Panzerfaust/",
										 "thompson" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7\\'s Thompson/",
										 "mp40" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7\\'s MP40/",
										 "sten" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7\\'s Sten/",
										 "luger" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7\\'s Luger 9mm/",
										 "colt" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7 \\'s \\.45ACP 1911/",
										 "sniper" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7\\'s sniper rifle/",
										 "mauser" => "/\\[skipnotify\\](.*)\\^7 was killed by (.*)\\^7\\'s Mauser/",
										 "venom" => "/\\[skipnotify\\](.*)\\^7 was ventilated by (.*)\\^7\\'s Venom/",
										 "flamethrower" => "/\\[skipnotify\\](.*)\\^7 was cooked by (.*)\\^7\\'s flamethrower/",
										 "knife" => "/\\[skipnotify\\](.*)\\^7 was stabbed by (.*)\\^7\\'s knife/",
										 "support" => "/\\[skipnotify\\](.*)\\^7 was blasted by (.*)\\^7\\'s support fire/",
										 "artillery" => "/\\[skipnotify\\](.*)\\^7 was shelled by (.*)\\^7\\'s artillery support/",
										 "dynamite" => "/\\[skipnotify\\](.*)\\^7 was blasted by (.*)\\^7\\'s dynamite/",
										 "mg" => "/\\[skipnotify\\](.*)\\^7 was perforated by (.*)\\^7\\'s crew-served MG42/"
									);
									
	public static $regex_selfkills = array	(
												"crush" => "/\\[skipnotify\\](.*)\\^7 was crushed\\./",
												"other" => "/\\[skipnotify\\](.*)\\^7 died\\./",
												"drown" => "/\\[skipnotify\\](.*)\\^7 drowned\\./",
												"jump" => "/\\[skipnotify\\](.*)\\^7 fell to his death\\./",
												"pf" => "/\\[skipnotify\\](.*)\\^7 vaporized himself\\./",
												"artillery" => "/\\[skipnotify\\](.*)\\^7 fired-for-effect on himself\\./",
												"support" => "/\\[skipnotify\\](.*)\\^7 obliterated himself\\./",
												"dynamite" => "/\\[skipnotify\\](.*)\\^7 dynamited himself to pieces\\./",
												"grenade" => "/\\[skipnotify\\](.*)\\^7 dove on his own grenade\\./"
											);
	
	const REGEX_RENAME = "/\\[skipnotify\\](.*)\\^7 renamed to (.*)/";
	const REGEX_TEAMKILL = "/\\[skipnotify\\](.*)\\^7 \\^1WAS KILLED BY TEAMMATE\\^7 (.*)\\^7/";
	const REGEX_SUICIDE = "/\\[skipnotify\\](.*)\\^7 killed himself\\./";
	const REGEX_CHATLINE = "/\\[skipnotify\\](.*)\\^7: (.*)/";
	const REGEX_VOTE = "/\\[skipnotify\\](.*)\\^7 called a vote\\./";
	const REGEX_KICK = "/\\[skipnotify\\](.*)\\^7 player kicked/";
	
	public static $awards_main = array(
		array("name" => "Terminator", "column" => "frageff", "sortdir" => SORT_DESC, "before" => " for killing efficiency of ", "after" => ".", "decimals" => 2),
		array("name" => "Slaughterhouse", "column" => "frags", "sortdir" => SORT_DESC, "before" => " with total ", "after" => " kills.", "decimals" => 0),
		array("name" => "Slaughterhouse Lama ", "column" => "deaths", "sortdir" => SORT_DESC, "before" => " for getting slaughtered a total of ", "after" => " times.", "decimals" => 0),
		array("name" => "Sly Fox", "column" => "deaths", "sortdir" => SORT_ASC, "before" => " for getting killed only ", "after" => " times.", "decimals" => 0),
		array("name" => "Harakiri", "column" => "suicides", "sortdir" => SORT_DESC, "before" => " for committing suicide ", "after" => " times", "decimals" => 0),
		array("name" => "Internal Enemy", "column" => "teamkills", "sortdir" => SORT_DESC, "before" => " for killing ", "after" => " teammates.", "decimals" => 0),
		array("name" => "Needless Player", "column" => "teamdeaths", "sortdir" => SORT_DESC, "before" => " for getting slaughtered a total of ", "after" => " times by teamkill.", "decimals" => 0),
		array("name" => "Blabbermouth", "column" => "chatlines", "sortdir" => SORT_DESC, "before" => " for ", "after" => " lines of messagelog.", "decimals" => 0),
		array("name" => "King Of Votes", "column" => "votes", "sortdir" => SORT_DESC, "before" => " for calling a total of ", "after" => " votes.", "decimals" => 0),
		array("name" => "Most Hated", "column" => "kicks", "sortdir" => SORT_DESC, "before" => " for getting kicked a total of ", "after" => " times.", "decimals" => 0),
		array("name" => "Rampage", "column" => "maxspree", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags without dying.", "decimals" => 0),
		array("name" => "Brutal Rambo", "column" => "spreecount", "sortdir" => SORT_DESC, "before" => " for ", "after" => " series frags (5 frags without dying).", "decimals" => 0),
		array("name" => "Freefrag", "column" => "freefragcount", "sortdir" => SORT_DESC, "before" => " for ", "after" => " deaths without fragging.", "decimals" => 0),
		array("name" => "Desecrator Of Corpses", "column" => "gibs", "sortdir" => SORT_DESC, "before" => " for ", "after" => " gibs.", "decimals" => 0),
	);
	
	public static $awards_main_weapons = array(
		array("name" => "Small Dick", "column" => "pf", "type" => "selfkills", "sortdir" => SORT_DESC, "before" => " for can't handling his big toy and blowing himself up a total of ", "after" => " times.", "decimals" => 0),
		array("name" => "Tiberinus Silvius", "column" => "drown", "type" => "selfkills", "sortdir" => SORT_DESC, "before" => " for drowning ", "after" => " times.", "decimals" => 0),
		array("name" => "Careless Sheep", "column" => "knife", "type" => "deaths", "sortdir" => SORT_DESC, "before" => " for getting stabbed a total of ", "after" => " times.", "decimals" => 0),
	);
	
	public static $awards_weapons = array(
		array("name" => "The Master Of MP40", "column" => "mp40", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags.", "decimals" => 0),
		array("name" => "The King Of The Thompson", "column" => "thompson", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags.", "decimals" => 0),
		array("name" => "The Master Of Sten", "column" => "sten", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags.", "decimals" => 0),
		array("name" => "The Panzer-Lama", "column" => "pf", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " fuckin' frags.", "decimals" => 0),
		array("name" => "The Sharp-Shooter", "column" => "sniper", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " sniper frags.", "decimals" => 0),
		array("name" => "The Unscoped Killer", "column" => "mauser", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " mauser frags.", "decimals" => 0),
		array("name" => "The Master Of Grenade", "column" => "grenade", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags.", "decimals" => 0),
		array("name" => "The Best Indian Smoke-Messenger", "column" => "support", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " support-fire frags.", "decimals" => 0),
		array("name" => "The God Of War", "column" => "artillery", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " artillery frags.", "decimals" => 0),
		array("name" => "The Burner", "column" => "flamethrower", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " flamethrower frags.", "decimals" => 0),
		array("name" => "The Silent Killer", "column" => "knife", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " knife frags.", "decimals" => 0),
		array("name" => "The Master Of Venom", "column" => "venom", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags.", "decimals" => 0),
		array("name" => "The Best Chain-Gun User", "column" => "mg", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " frags.", "decimals" => 0),
		array("name" => "The Arabian Engineer", "column" => "dynamite", "type" => "kills", "sortdir" => SORT_DESC, "before" => " for ", "after" => " dynamite frags.", "decimals" => 0),
	);
	
	// function to remove colortags
	static function stripColors($string)
	{
		$ret = $string;
		for($i=0; $i<count(Helper::$colors); $i++)
		{
			$ret = str_replace(Helper::$colors[$i], '', $ret);
		}
		return $ret;
	}
	
	// function to create shortname (nocolors, maxlength 15) from playername
	static function getShortname($playername)
	{
		return substr(Helper::stripColors($playername), 0, 15);
	}
	
	// function to replace colortags with html colors
	static function colorsToHtml($string)
	{
		$ret = $string;
		
		// replace double ^'s first
		$ret = str_replace('^^', '&#94^', $ret);
		for($i=0; $i<count(Helper::$colors); $i++)
		{
			$ret = str_replace(Helper::$colors[$i], '</span><span class=color' . $i . '>', $ret);
		}
		$ret = str_replace('~', '&#34;', $ret);
		return "<b>" . $ret . "</span></b>";
	}
	
	// function to dump a cvar into html
	static function dump($var)
	{
		echo '<pre>';
		echo print_r($var);
		echo '</pre>';
	}

	// function to check if a string starts with a sequence
	static function startsWith($string, $start)
	{
		return !strncmp($string, $start, strlen($start));
	}
}

?>