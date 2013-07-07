<?php
require_once("helper.php");


// Notes: index as number; array with name => index (rename -> 2 names with same index)
// kills/deaths as matrix
// count weapons extra? (in player or total?)

define("dump", "Helper::dump");
define("SPREE_COUNT", 5);

class StatsAnalyzer
{
	private $stats;
	private $chatlog;
	private $weapons;
	private $frag_count;
	private $round_count;
	
	function __construct($filepath) {
		$this::ProcessFile($filepath);
	}
	
	function __destruct() {
		unset($this->stats);
		unset($this->chatlog);
		unset($this->weapons);
	}

	// function to create a new player
	function createPlayer($name)
	{
		$player = array("teamkills" => 0,
						"teamkilled" => 0,
						"chatlines" => 0,
						"suicides" => 0,
						"votes" => 0,
						"kicks" => 0,
						"currentspree" => 0,
						"freefrags" => 0,
						"currentfreefrags" => 0,
						"sprees" => array(),
						"selfkills" => array("panzerfaust" => 0, "artillery" => 0, "airstrike" => 0, "dynamite" => 0, "grenade" => 0, "drown" => 0, "other" => 0, "jump" => 0, "crush" => 0),
						"kills" => array("grenade" => array(), "thompson" => array(), "panzerfaust" => array(), "mp40" => array(), "sten" => array(), "luger" => array(), "colt" => array(), "sniper" => array(), "mauser" => array(), "venom" => array(), "flamethrower" => array(), "knife" => array(), "support" => array(), "artillery" => array(), "dynamite" => array(), "mg" => array()),
						"deaths" => array("grenade" => array(), "thompson" => array(), "panzerfaust" => array(), "mp40" => array(), "sten" => array(), "luger" => array(), "colt" => array(), "sniper" => array(), "mauser" => array(), "venom" => array(), "flamethrower" => array(), "knife" => array(), "support" => array(), "artillery" => array(), "dynamite" => array(), "mg" => array()),
						"playername" => $name
						);
		return $player;
	}
	
	// function to process a single stats line
	function ProcessStatsLine($line, &$newstats)
	{
		$statsline = Helper::stripColors($line);		// remove colors codes
		$split = preg_split("/[\s]+/", $statsline);	// split by blanks
		$len = count($split);
		
		// create new stats and write values into it
		$newstats = array();
		$newstats["team"] = $split[0];
		$newstats["kills"] = $split[$len-11];
		$newstats["deaths"] = $split[$len-10];
		$newstats["suicides"] = $split[$len-9];
		$newstats["teamkills"] = $split[$len-8];
		$newstats["gp"] = $split[$len-6];
		$newstats["damagegiven"] = $split[$len-5];
		$newstats["damagereceived"] = $split[$len-4];
		$newstats["teamdamage"] = $split[$len-3];
		$newstats["score"] = $split[$len-2];
		$newstats["killefficiency"] = $newstats["deaths"] == 0 ? $newstats["kills"] : $newstats["kills"] / $newstats["deaths"];
		$newstats["damageefficiency"] = $newstats["damagereceived"] == 0 ? $newstats["damagegiven"] : $newstats["damagegiven"] / $newstats["damagereceived"];
		$newstats["damageperfrag"] = $newstats["kills"] == 0 ? 0 : $newstats["damagegiven"] / $newstats["kills"];
		
		// reassemble the name
		$name = $split[1];
		for($l=2; $l<=$len-12; $l++)
			$name .= " " . $split[$l];
		
		return $name;
	}
	
	// function to process a logfile
	function ProcessFile($filepath)
	{
		$isPaused = false;
		$isStarted = false;
		$this->round_count = 0;
		$stats_player_round = array();
		$this->stats = array();		// reset stats
		$this->chatlog = array();
		
		// open logfile
		$handle = @fopen($filepath, 'r');
		if (!$handle)
			return 0;

		// loop over lines
		while(($buffer = fgets($handle, 4096)) != false)
		{
			// game start
			if (Helper::startsWith($buffer, "[skipnotify]^1FIGHT!"))
			{
				if ($isPaused)			// game resuming
					$isPaused = false;
				else if ($isStarted)	// map_restart, delete stats
					$stats_player_round = array();
				else					// normal round start
				{
					$isStarted = true;
					$stats_player_round = array();
				}
			}
			// game pause
			else if (Helper::startsWith($buffer, "[skipnotify]^3Referee ^1PAUSED^3 the match") || Helper::startsWith($buffer, "[skipnotify]^3Match is ^1PAUSED^3!"))
			{
				$isPaused = true; 	// game is pausing
			}
			// round end
			else if (Helper::startsWith($buffer, "[skipnotify]>>> ^3Clock set to:") || Helper::startsWith($buffer, "[skipnotify]>>> ^3Objective reached at") || Helper::startsWith($buffer, "[skipnotify]>>> ^3Objective NOT reached in time"))
			{
				$isPaused = false;
				$isStarted = false;
				
				if (isset($stats_current_round))	// copy roundstats into overall stats, if existing
				{
					foreach($stats_current_round as $name => $value)
					{
						if (!isset($this->stats[$name]))
							$this->stats[$name] = array("osp", "total", "custom");
						$this->stats[$name]["osp"][$this->round_count] = $value;
					}
				}
				$this->round_count++;				// reset values for next round
				
				foreach($stats_player_round as $name => $value)		// write player round stats into overall stats
				{
					$shortname = substr($name, 0, 15);
					if (isset($this->stats[$shortname]))			// sum up if existing
					{
						if(!isset($this->stats[$shortname]["custom"]))
							$this->stats[$shortname]["custom"] = $this::createPlayer($value["playername"]);
						
						foreach($value["kills"] as $weapon => $killed)
						{
							foreach($killed as $victim)
							{
								array_push($this->stats[$shortname]["custom"]["kills"][$weapon], $victim);
							}
						}
						foreach($value["deaths"] as $weapon => $killed)
						{
							foreach($killed as $killer)
							{
								array_push($this->stats[$shortname]["custom"]["deaths"][$weapon], $killer);
							}
						}
						foreach($value["selfkills"] as $weapon => $count)
						{
							$this->stats[$shortname]["custom"]["selfkills"][$weapon] += $count;
						}
						$this->stats[$shortname]["custom"]["teamkills"] += $value["teamkills"];
						$this->stats[$shortname]["custom"]["teamkilled"] += $value["teamkilled"];
						$this->stats[$shortname]["custom"]["suicides"] += $value["suicides"];
						$this->stats[$shortname]["custom"]["chatlines"] += $value["chatlines"];
						$this->stats[$shortname]["custom"]["kicks"] += $value["kicks"];
						$this->stats[$shortname]["custom"]["votes"] += $value["votes"];
						
						if ($value["currentspree"] >= SPREE_COUNT)
						{
							array_push($this->stats[$shortname]["custom"]["sprees"], $value["currentspree"]);
						}
						$this->stats[$shortname]["custom"]["sprees"] = array_merge($this->stats[$shortname]["custom"]["sprees"], $value["sprees"]);
						
						
						if ($value["currentfreefrags"] > $value["freefrags"])
							$value["freefrags"] = $value["currentfreefrags"];
						if ($value["freefrags"] > $this->stats[$shortname]["custom"]["freefrags"])
							$this->stats[$shortname]["custom"]["freefrags"] = $value["freefrags"];
					}
					else													// otherwise just copy over
					{
						$this->stats[$shortname]["custom"] = $stats_player_round[$shortname];
					}
				}
			}
			// chatline
			else if (preg_match(Helper::REGEX_CHATLINE, $buffer, $matches))
			{
				$chatter = substr(Helper::stripColors($matches[1]), 0, 15);
				array_push($this->chatlog, $buffer);

				if(!isset($this->stats[$chatter]))
					$this->stats[$chatter]["custom"] = $this::createPlayer($matches[1]);
						
				$this->stats[$chatter]["custom"]["chatlines"]++;
			}
			// rename
			else if (preg_match(Helper::REGEX_RENAME, $buffer, $matches))
			{
				$playername = $matches[2];
				$oldname = substr(Helper::stripColors($matches[1]), 0, 15);
				$newname = substr(Helper::stripColors($matches[2]), 0, 15);

				if (isset($stats_player_round[$oldname]))	// if player already exists in round stats, rename
				{
					$stats_player_round[$newname] = $stats_player_round[$oldname];
					$stats_player_round[$newname]["playername"] = $playername;
				}
				else											// otherwise create him
				{
					$stats_player_round[$newname] = $this::createPlayer($playername);
				}

				if(isset($this->stats[$oldname]))	// also rename the overall stats accordingly if existing
				{
					$this->stats[$newname] = $this->stats[$oldname];
					$this->stats[$newname]["custom"]["playername"] = $playername;
				}
				
				// rename every kill
				foreach($this->stats as $player => $value)
					foreach($value["custom"]["kills"] as $weapon => $kills)
						foreach ($kills as $key => $name)
							if($name == $oldname)
								$this->stats[$player]["custom"]["kills"][$weapon][$key] = $newname;

				foreach($stats_player_round as $player => $value)
					foreach($value["kills"] as $weapon => $deaths)
						foreach ($deaths as $key => $name)
							if($name == $oldname)
								$stats_player_round[$player]["kills"][$weapon][$key] = $newname;
								
				// rename every death
				foreach($this->stats as $player => $value)
					foreach($value["custom"]["deaths"] as $weapon => $kills)
						foreach ($kills as $key => $name)
							if($name == $oldname)
								$this->stats[$player]["custom"]["deaths"][$weapon][$key] = $newname;

				foreach($stats_player_round as $player => $value)
					foreach($value["deaths"] as $weapon => $deaths)
						foreach ($deaths as $key => $name)
							if($name == $oldname)
								$stats_player_round[$player]["deaths"][$weapon][$key] = $newname;
				
				if (isset($stats_current_round[$oldname])) {		// if round stats are already existing, also rename them
					$stats_current_round[$newname] = $stats_current_round[$oldname];
					unset($stats_current_round[$oldname]);}

				unset ($this->stats[$oldname]);
				unset ($stats_player_round[$oldname]);
			}
			// teamkill
			else if (preg_match(Helper::REGEX_TEAMKILL, $buffer, $matches))
			{
				$teammate = substr(Helper::stripColors($matches[1]), 0, 15);
				$teamkiller = substr(Helper::stripColors($matches[2]), 0, 15);
				
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$teamkiller]))	// create player if not existing and save the teamkill
						$stats_player_round[$teamkiller] = $this::createPlayer($matches[2]);
					if (!isset($stats_player_round[$teammate]))	// create player if not existing and save the teamkill
						$stats_player_round[$teammate] = $this::createPlayer($matches[1]);
					$stats_player_round[$teamkiller]["teamkills"]++;
					$stats_player_round[$teammate]["teamkilled"]++;
					
					if ($stats_player_round[$teammate]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$teammate]["sprees"], $stats_player_round[$teammate]["currentspree"]);
					$stats_player_round[$teammate]["currentspree"] = 0;
					
				}
			}
			// suicide
			else if (preg_match(Helper::REGEX_SUICIDE, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["suicides"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// grenadekill
			else if (preg_match(Helper::REGEX_GRENADE, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["grenade"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["grenade"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// pfkill
			else if (preg_match(Helper::REGEX_PANZERFAUST, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["panzerfaust"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["panzerfaust"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
				
			}
			// thompsonkill
			else if (preg_match(Helper::REGEX_THOMPSON, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["thompson"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["thompson"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// mp40kill
			else if (preg_match(Helper::REGEX_MP40, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["mp40"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["mp40"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// stenkill
			else if (preg_match(Helper::REGEX_STEN, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["sten"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["sten"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// lugerkill
			else if (preg_match(Helper::REGEX_LUGER, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["luger"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["luger"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// coltkill
			else if (preg_match(Helper::REGEX_COLT, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["colt"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["colt"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// sniperkill
			else if (preg_match(Helper::REGEX_SNIPER, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["sniper"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["sniper"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// mauserkill
			else if (preg_match(Helper::REGEX_MAUSER, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["mauser"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["mauser"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// venomkill
			else if (preg_match(Helper::REGEX_VENOM, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["venom"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["venom"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// flamekill
			else if (preg_match(Helper::REGEX_FLAMETHROWER, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["flamethrower"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["flamethrower"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// knifekill
			else if (preg_match(Helper::REGEX_FLAMETHROWER, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["knife"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["knife"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// supportkill
			else if (preg_match(Helper::REGEX_SUPPORT, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["support"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["support"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// artykill
			else if (preg_match(Helper::REGEX_ARTILLERY, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["artillery"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["artillery"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// dynamitekill
			else if (preg_match(Helper::REGEX_FLAMETHROWER, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["dynamite"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["dynamite"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// mgkill
			else if (preg_match(Helper::REGEX_MG, $buffer, $matches))
			{
				$killed = substr(Helper::stripColors($matches[1]), 0, 15);
				$killer = substr(Helper::stripColors($matches[2]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if(!isset($stats_player_round[$killer]))
						$stats_player_round[$killer] = $this::createPlayer($matches[2]);
					array_push($stats_player_round[$killer]["kills"]["mg"], $killed);
					if (!isset($stats_player_round[$killed]))
						$stats_player_round[$killed] = $this::createPlayer($matches[1]);
					array_push($stats_player_round[$killed]["deaths"]["mg"], $killer);
					
					$stats_player_round[$killer]["currentspree"]++;
					if ($stats_player_round[$killed]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$killed]["sprees"], $stats_player_round[$killed]["currentspree"]);
					$stats_player_round[$killed]["currentspree"] = 0;
					
					if ($stats_player_round[$killer]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$killer]["freefrags"] = $stats_player_round[$killer]["currentfreefrags"];
					$stats_player_round[$killer]["currentfreefrags"] = 0;
					$stats_player_round[$killed]["currentfreefrags"]++;
				}
			}
			// pf-selfkill
			else if (preg_match(Helper::REGEX_PANZERFAUST_SELFKILL, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["panzerfaust"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// artillery-selfkill
			else if (preg_match(Helper::REGEX_ARTILLERY_SELFKILL, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["artillery"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// support-selfkill
			else if (preg_match(Helper::REGEX_ARTILLERY_SELFKILL, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["support"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// dynamite-selfkill
			else if (preg_match(Helper::REGEX_DYNAMITE_SELFKILL, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["dynamite"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// grenade-selfkill
			else if (preg_match(Helper::REGEX_GRENADE_SELFKILL, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["grenade"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// drown
			else if (preg_match(Helper::REGEX_DROWN, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["drown"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// other death
			else if (preg_match(Helper::REGEX_OTHER, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["other"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// deathjump
			else if (preg_match(Helper::REGEX_DEATHJUMP, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["jump"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// crush
			else if (preg_match(Helper::REGEX_CRUSH, $buffer, $matches))
			{
				$sknub = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$sknub]))	// create player if not existing and add suicide
						$stats_player_round[$sknub] = $this::createPlayer($matches[1]);
					$stats_player_round[$sknub]["selfkills"]["crush"] += 1;
					
					if ($stats_player_round[$sknub]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$sknub]["sprees"], $stats_player_round[$sknub]["currentspree"]);
					$stats_player_round[$sknub]["currentspree"] = 0;
				}
			}
			// vote
			else if (preg_match(Helper::REGEX_VOTE, $buffer, $matches))
			{
				$voter = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$voter]))	// create player if not existing and add suicide
						$stats_player_round[$voter] = $this::createPlayer($matches[1]);
					$stats_player_round[$voter]["votes"] += 1;
				}
			}
			// kick
			else if (preg_match(Helper::REGEX_KICK, $buffer, $matches))
			{
				$kicked = substr(Helper::stripColors($matches[1]), 0, 15);
				if ($isStarted && !$isPaused) {		// only valid if game is started
					if (!isset($stats_player_round[$kicked]))	// create player if not existing and add suicide
						$stats_player_round[$kicked] = $this::createPlayer($matches[1]);
					$stats_player_round[$kicked]["kicks"] += 1;
					
					if ($stats_player_round[$kicked]["currentspree"] >= SPREE_COUNT)
						array_push($stats_player_round[$kicked]["sprees"], $stats_player_round[$kicked]["currentspree"]);
					$stats_player_round[$kicked]["currentspree"] = 0;
					
					if ($stats_player_round[$kicked]["currentfreefrags"] > $stats_player_round[$killer]["freefrags"])
						$stats_player_round[$kicked]["freefrags"] = $stats_player_round[$kicked]["currentfreefrags"];
					$stats_player_round[$kicked]["currentfreefrags"] = 0;
					$stats_player_round[$kicked]["currentfreefrags"]++;
				}
			}
			// osp stats
			else if (Helper::startsWith($buffer, "^7TEAM"))
			{
				$stats_current_round = array();		// reset current round stats
				
				$buffer = fgets($handle, 4096);		// advance two lines
				$buffer = fgets($handle, 4096);
				
				do		// read team 1 stats
				{
					$name = substr($this::ProcessStatsLine($buffer, $newstats), 0, 15);		// process stats line
					$stats_current_round[$name] = $newstats;
					
					unset($newstats);

					if (($buffer = fgets($handle, 4096)) == false)		// read next stats line
						return -2;
				}
				while (!Helper::startsWith($buffer, "^7---------------------------------------------------------------------"));
				
				$buffer = fgets($handle, 4096);	// advance 5 lines to read second team
				$buffer = fgets($handle, 4096);
				$buffer = fgets($handle, 4096);
				$buffer = fgets($handle, 4096);
				$buffer = fgets($handle, 4096);
				
				do		// read team 2 stats
				{
					$name = substr($this::ProcessStatsLine($buffer, $newstats), 0, 15);		// process stats line
					$stats_current_round[$name] = $newstats;
					unset($newstats);
					
					if (($buffer = fgets($handle, 4096)) == false)		// read next stats line
						return -2;
				}
				while (!Helper::startsWith($buffer, "^7---------------------------------------------------------------------"));
			}
		}
		
		// close logfile
		if (!feof($handle))
			return -1;
		fclose($handle);
		
		// create total osp stats
		foreach($this->stats as $name => $value)
		{
			$this->stats[$name]["osp"]["total"] = array("team" => "",
												"kills" => 0,
												"deaths" => 0,
												"suicides" => 0,
												"teamkills" => 0,
												"gp" => 0,
												"damagegiven" => 0,
												"damagereceived" => 0,
												"teamdamage" => 0,
												"score" => 0,
												"count" => 0,
												"damageefficiency" => 0,
												"killefficiency" => 0,
												"damageperfrag" => 0
												);
			
			$this->stats[$name]["osp"]["total"]["count"] = 0;
			for($i=0; $i<$this->round_count; $i++)		// sum up every round, if existing
			{
				if (isset($this->stats[$name]["osp"][$i]))
				{
					$this->stats[$name]["osp"]["total"]["team"] = $this->stats[$name]["osp"][$i]["team"];
					$this->stats[$name]["osp"]["total"]["kills"] += $this->stats[$name]["osp"][$i]["kills"];
					$this->stats[$name]["osp"]["total"]["deaths"] += $this->stats[$name]["osp"][$i]["deaths"];
					$this->stats[$name]["osp"]["total"]["suicides"] += $this->stats[$name]["osp"][$i]["suicides"];
					$this->stats[$name]["osp"]["total"]["teamkills"] += $this->stats[$name]["osp"][$i]["teamkills"];
					$this->stats[$name]["osp"]["total"]["gp"] += $this->stats[$name]["osp"][$i]["gp"];
					$this->stats[$name]["osp"]["total"]["damagegiven"] += $this->stats[$name]["osp"][$i]["damagegiven"];
					$this->stats[$name]["osp"]["total"]["damagereceived"] += $this->stats[$name]["osp"][$i]["damagereceived"];
					$this->stats[$name]["osp"]["total"]["teamdamage"] += $this->stats[$name]["osp"][$i]["teamdamage"];
					$this->stats[$name]["osp"]["total"]["score"] += $this->stats[$name]["osp"][$i]["score"];
					$this->stats[$name]["osp"]["total"]["count"] += 1;
				}
			}
			
			// calculate total metrics
			$this->stats[$name]["osp"]["total"]["damageefficiency"] = $this->stats[$name]["osp"]["total"]["damagereceived"] == 0 ? $this->stats[$name]["osp"]["total"]["damagegiven"] : $this->stats[$name]["osp"]["total"]["damagegiven"]/$this->stats[$name]["osp"]["total"]["damagereceived"];
			$this->stats[$name]["osp"]["total"]["killefficiency"] = $this->stats[$name]["osp"]["total"]["deaths"] == 0 ? $this->stats[$name]["osp"]["total"]["kills"] : $this->stats[$name]["osp"]["total"]["kills"]/$this->stats[$name]["osp"]["total"]["deaths"];
			$this->stats[$name]["osp"]["total"]["damageperfrag"] = $this->stats[$name]["osp"]["total"]["kills"] == 0 ? 0 : $this->stats[$name]["osp"]["total"]["damagegiven"]/$this->stats[$name]["osp"]["total"]["kills"];
		}
		
		// create stats from logkills
		$this->weapons = array();
		$this->frag_count = 0;
		
		foreach($this->stats as $name => $player)
		{
			$this->stats[$name]["custom"]["total"] = array("kills" => 0,
															"deaths" => 0,
															"selfkills" => 0
															);
			// count kills
			foreach($player["custom"]["kills"] as $weapon => $kills)
			{
				$this->stats[$name]["custom"]["total"]["kills"] += count($kills);
			}
			// count deaths
			$deathcounter = 0;
			foreach($player["custom"]["deaths"] as $weapon => $death)
			{
				$this->stats[$name]["custom"]["total"]["deaths"] += count($death);
			}
			
			
			$this->stats[$name]["custom"]["total"]["votes"] = $this->stats[$name]["custom"]["votes"];
			$this->stats[$name]["custom"]["total"]["kicks"] = $this->stats[$name]["custom"]["kicks"];
			$this->stats[$name]["custom"]["total"]["teamkills"] = $this->stats[$name]["custom"]["teamkills"];
			$this->stats[$name]["custom"]["total"]["teamkilled"] = $this->stats[$name]["custom"]["teamkilled"];
			$this->stats[$name]["custom"]["total"]["suicides"] = $this->stats[$name]["custom"]["suicides"];
			$this->stats[$name]["custom"]["total"]["chatlines"] = $this->stats[$name]["custom"]["chatlines"];
			
			$this->stats[$name]["custom"]["total"]["damagegiven"] = $this->stats[$name]["osp"]["total"]["damagegiven"];
			$this->stats[$name]["custom"]["total"]["damagereceived"] = $this->stats[$name]["osp"]["total"]["damagereceived"];
			
			$this->stats[$name]["custom"]["total"]["damageefficiency"] = $this->stats[$name]["custom"]["total"]["damagereceived"] == 0 ? $this->stats[$name]["custom"]["total"]["damagegiven"] : $this->stats[$name]["custom"]["total"]["damagegiven"]/$this->stats[$name]["custom"]["total"]["damagereceived"];
			$this->stats[$name]["custom"]["total"]["killefficiency"] = $this->stats[$name]["custom"]["total"]["deaths"] == 0 ? $this->stats[$name]["custom"]["total"]["kills"] : $this->stats[$name]["custom"]["total"]["kills"]/$this->stats[$name]["custom"]["total"]["deaths"];
			$this->stats[$name]["custom"]["total"]["damageperfrag"] = $this->stats[$name]["custom"]["total"]["kills"] == 0 ? 0 : $this->stats[$name]["custom"]["total"]["damagegiven"]/$this->stats[$name]["custom"]["total"]["kills"];
			$this->stats[$name]["custom"]["total"]["team"] = $this->stats[$name]["osp"]["total"]["team"];
			$this->stats[$name]["custom"]["total"]["teamdamage"] = $this->stats[$name]["osp"]["total"]["teamdamage"];
			$this->stats[$name]["custom"]["total"]["count"] = $this->stats[$name]["osp"]["total"]["count"];
			
			
			$this->stats[$name]["custom"]["total"]["killers"] = array();
			$this->stats[$name]["custom"]["total"]["victims"] = array();
			// create weapon stats
			foreach($player["custom"]["kills"] as $weapon => $kills)
			{
				if(!isset($this->weapons[$weapon]))
					$this->weapons[$weapon] = count($kills);
				else
					$this->weapons[$weapon] += count($kills);
					
				$this->frag_count += count($kills);
				
				// create victims stats
				foreach($kills as $victim) {
					if (!isset($this->stats[$name]["custom"]["total"]["victims"][$victim]))
						$this->stats[$name]["custom"]["total"]["victims"][$victim] = 0;
					$this->stats[$name]["custom"]["total"]["victims"][$victim] += 1;
				}
				arsort($this->stats[$name]["custom"]["total"]["victims"]);		// sort the victims
			}
			// create killers stats
			foreach($player["custom"]["deaths"] as $weapon => $deaths)
			{
				foreach($deaths as $killer) {
					if (!isset($this->stats[$name]["custom"]["total"]["killers"][$killer]))
						$this->stats[$name]["custom"]["total"]["killers"][$killer] = 0;
					$this->stats[$name]["custom"]["total"]["killers"][$killer] += 1;
				}
				arsort($this->stats[$name]["custom"]["total"]["killers"]);		// sort the killers
			}
			
			// remove empty entries (specs)
			if ($this->stats[$name]["osp"]["total"]["count"] == 0)
				unset($this->stats[$name]);
		}		
		
	}
	
	// function to print stats
	public function PrintStats($type)
	{
		$curr_team = "";
		$count_team = 0;
		$count = 0;
	
		// sort by team and kill efficiency
		foreach ($this->stats as $name => $player)
		{
			$team[$name] = $player[$type]["total"]["team"];
			$killefficiency[$name] = $player[$type]["total"]["killefficiency"];
		}
		array_multisort($team, SORT_DESC, $killefficiency, SORT_DESC, $this->stats);
		
		foreach($this->stats as $name => $player)
		{
			if($curr_team != $player[$type]["total"]["team"])	// print total team stats before printing the other team
			{
				if ($count_team > 0) {
					$killefficiency = $totals["deaths"] == 0 ? $totals["kills"] : $totals["kills"] / $totals["deaths"];
					$damageefficiency = $totals["damagereceived"] == 0 ? $totals["damagegiven"] : $totals["damagegiven"] / $totals["damagereceived"];
					$damageperfrag = $totals["kills"] == 0 ? 0 : $totals["damagegiven"] / $totals["kills"];
					echo "<tr class='tablerowtotal'>\n<td>TOTAL</td>\n";
					echo "<td class='" . ($killefficiency>=1?"positive":"negative") . "'>" . number_format($killefficiency, 2) . "</td>\n";
					echo "<td>" . number_format($totals["kills"]) . "</td>\n";
					echo "<td>" . number_format($totals["deaths"]) . "</td>\n";
					echo "<td>" . number_format($totals["suicides"]) . "</td>\n";
					echo "<td>" . number_format($totals["teamkills"]) . "</td>\n";
					echo "<td class='" . ($damageefficiency>=1?"positive":"negative") . "'>" . number_format($damageefficiency, 2) . "</td>\n";
					echo "<td>" . number_format($totals["damagegiven"]) . "</td>\n";
					echo "<td>" . number_format($totals["damagereceived"]) . "</td>\n";
					echo "<td>" . number_format($totals["teamdamage"]) . "</td>\n";
					echo "<td>" . number_format($damageperfrag, 0) . "</td>\n";
					echo "<td>&nbsp</td>";
					echo "</tr>\n</tbody>\n</table>\n";
				}
				
				// set up total stats
				$totals = array("kills" => 0,
						"deaths" => 0,
						"suicides" => 0,
						"teamkills" => 0,
						"gp" => 0,
						"damagegiven" => 0,
						"damagereceived" => 0,
						"teamdamage" => 0,
						"score" => 0,
						);
					
				$count_team++;
				$count = 0;
				$curr_team = $player[$type]["total"]["team"];
				echo "<h2>Team " . $count_team . "</h2>\n";
				echo "<table id='statstable' class='tablesorter'>\n<thead>\n<tr>\n<th class='tablename'>Player</th>\n<th>Kill-Efficiency</th>\n<th>Frags</th>\n<th>Deaths</th>\n";
				echo "<th>Suicides</th>\n<th>Teamkills</th>\n<th>Damage-Efficiency</th>\n<th>DMG</th>\n<th>DMR</th>\n<th>Team-DMG</th>\n<th>DMG/Frag</th>\n<th>Rounds</th>\n</tr>\n</thead>\n<tbody>\n";
			}

			echo "<tr class='tablerow" . ($count%2) . "'>\n";
			echo "<td class='tablename'>" . Helper::colorsToHtml($this->stats[$name]["custom"]["playername"]) . "</td>\n";
			echo "<td class='" . ($player[$type]["total"]["killefficiency"]>=1?"positive":"negative") . "'>" . number_format($player[$type]["total"]["killefficiency"], 2) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["kills"]) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["deaths"]) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["suicides"]) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["teamkills"]) . "</td>\n";
			echo "<td class='" . ($player[$type]["total"]["damageefficiency"]>=1?"positive":"negative") . "'>" . number_format($player[$type]["total"]["damageefficiency"], 2) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["damagegiven"]) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["damagereceived"]) . "</td>\n";
			echo "<td>" .  number_format($player[$type]["total"]["teamdamage"]) . "</td>\n";
			echo "<td>" . number_format($player[$type]["total"]["damageperfrag"], 0) . "</td>\n";
			echo "<td>" . $player[$type]["total"]["count"] . "</td>\n";
			echo "</tr>\n";
			$count++;
		
			// sum up total stats for team
			$totals["kills"] += $player[$type]["total"]["kills"];
			$totals["deaths"] += $player[$type]["total"]["deaths"];
			$totals["suicides"] += $player[$type]["total"]["suicides"];
			$totals["teamkills"] += $player[$type]["total"]["teamkills"];
			$totals["damagegiven"] += $player[$type]["total"]["damagegiven"];
			$totals["damagereceived"] += $player[$type]["total"]["damagereceived"];
			$totals["teamdamage"] += $player[$type]["total"]["teamdamage"];
		}
		
		// print stats for the last team too
		$killefficiency = $totals["deaths"] == 0 ? $totals["kills"] : $totals["kills"] / $totals["deaths"];
		$damageefficiency = $totals["damagereceived"] == 0 ? $totals["damagegiven"] : $totals["damagegiven"] / $totals["damagereceived"];
		$damageperfrag = $totals["kills"] == 0 ? 0 : $totals["damagegiven"] / $totals["kills"];
		echo "<tr class='tablerowtotal'>\n<td>TOTAL</td>\n";
		echo "<td class='" . ($killefficiency>=1?"positive":"negative") . "'>" . round($killefficiency, 2) . "</td>\n";
		echo "<td>" . number_format($totals["kills"]) . "</td>\n";
		echo "<td>" . number_format($totals["deaths"]) . "</td>\n";
		echo "<td>" . number_format($totals["suicides"]) . "</td>\n";
		echo "<td>" . number_format($totals["teamkills"]) . "</td>\n";
		echo "<td class='" . ($damageefficiency>=1?"positive":"negative") . "'>" . number_format($damageefficiency, 2) . "</td>\n";
		echo "<td>" . number_format($totals["damagegiven"]) . "</td>\n";
		echo "<td>" . number_format($totals["damagereceived"]) . "</td>\n";
		echo "<td>" . number_format($totals["teamdamage"]) . "</td>\n";
		echo "<td>" . number_format($damageperfrag, 0) . "</td>\n";
		echo "<td>&nbsp</td>";
		echo "</tr>\n</tbody>\n</table>\n";
		
		unset($totals);
	}
	
	// print detailed round stats from osp
	function PrintRoundStats()
	{
		$temp_stats = array();
		for($round=0; $round<$this->round_count; $round++)		// iterate over rounds
		{
			$temp_stats[$round] = array();
			echo "<h2>Round " . ($round+1) . "</h2>\n";
			echo "<table id='statstable' class='tablesorter'>\n<thead>\n<tr>\n<th>Team</th>\n<th class='tablename'>Player</th>\n<th>Kill-Efficiency</th>\n<th>Frags</th>\n<th>Deaths</th>\n<th>Suicides</th>\n<th>Teamkills</th>\n<th>Damage-Efficiency</th>\n<th>DMG</th>\n<th>DMR</th>\n<th>Team-DMG</th>\n<th>DMG/Frag</th>\n</tr>\n</thead>\n<tbody>\n";
			
			foreach($this->stats as $name => $player)			// check if player has participated in this round and add him to the temp_stats array
			{
				if (isset($player["osp"][$round]))
				{
					$team = $player["osp"][$round]["team"];
					if(!isset($temp_stats[$round][$team]))
						$temp_stats[$round][$team] = array();
					$temp_stats[$round][$team][$name] = $player["osp"][$round];
				}
			}
			
			// iterate over every team in this round
			foreach($temp_stats[$round] as $team => $useless)
			{
				unset($killefficiency);
				foreach ($temp_stats[$round][$team] as $key => $row) {		// sort round stats by kill efficiency
					$killefficiency[$key] = $row["killefficiency"];
				}
				array_multisort($killefficiency, SORT_DESC, SORT_NUMERIC, $temp_stats[$round][$team]);
				
				$totals = array("kills" => 0,		// reset total team stats
						"deaths" => 0,
						"suicides" => 0,
						"teamkills" => 0,
						"gp" => 0,
						"damagegiven" => 0,
						"damagereceived" => 0,
						"teamdamage" => 0,
						"score" => 0,
						);
			
				$count = 0;
				foreach($temp_stats[$round][$team] as $name => $value)
				{
					// sum up total stats for team
					$totals["kills"] += $value["kills"];
					$totals["deaths"] += $value["deaths"];
					$totals["suicides"] += $value["suicides"];
					$totals["teamkills"] += $value["teamkills"];
					$totals["damagegiven"] += $value["damagegiven"];
					$totals["damagereceived"] += $value["damagereceived"];
					$totals["teamdamage"] += $value["teamdamage"];
				
					$playername = $name;		// find playername
					$playername = $this->stats[$name]["custom"]["playername"];
					echo "<tr class='tablerow" . ($count%2) . "'>\n";
					echo "<td>" . $team . "</td>\n";	
					echo "<td class='tablename'>" . Helper::colorsToHtml($playername) . "</td>\n";
					echo "<td class='" . ($value["killefficiency"]>=1?"positive":"negative") . "'>" . number_format($value["killefficiency"], 2) . "</td>\n";
					echo "<td>" . $value["kills"] . "</td>\n";
					echo "<td>" . $value["deaths"] . "</td>\n";
					echo "<td>" . $value["suicides"] . "</td>\n";
					echo "<td>" . $value["teamkills"] . "</td>\n";
					echo "<td class='" . ($value["damageefficiency"]>=1?"positive":"negative") . "'>" . number_format($value["damageefficiency"], 2) . "</td>\n";
					echo "<td>" . $value["damagegiven"] . "</td>\n";
					echo "<td>" . $value["damagereceived"] . "</td>\n";
					echo "<td>" . $value["teamdamage"] . "</td>\n";
					echo "<td>" . number_format($value["damageperfrag"], 0) . "</td>\n";
					echo "</tr>\n";
					$count++;
				}
				
				// print total team stats
				$killefficiency = $totals["deaths"] == 0 ? $totals["kills"] : $totals["kills"] / $totals["deaths"];
				$damageefficiency = $totals["damagereceived"] == 0 ? $totals["damagegiven"] : $totals["damagegiven"] / $totals["damagereceived"];
				$damageperfrag = $totals["kills"] == 0 ? 0 : $totals["damagegiven"] / $totals["kills"];
				echo "<tr class='tablerowtotal'>\n<td>" . $team . "</td>\n";
				echo "<td>TOTAL</td>\n";
				echo "<td class='" . ($killefficiency>=1?"positive":"negative") . "'>" . number_format($killefficiency, 2) . "</td>\n";
				echo "<td>" . $totals["kills"] . "</td>\n";
				echo "<td>" . $totals["deaths"] . "</td>\n";
				echo "<td>" . $totals["suicides"] . "</td>\n";
				echo "<td>" . $totals["teamkills"] . "</td>\n";
				echo "<td class='" . ($damageefficiency>=1?"positive":"negative") . "'>" . number_format($damageefficiency, 2) . "</td>\n";
				echo "<td>" . $totals["damagegiven"] . "</td>\n";
				echo "<td>" . $totals["damagereceived"] . "</td>\n";
				echo "<td>" . $totals["teamdamage"] . "</td>\n";
				echo "<td>" . number_format($damageperfrag, 0) . "</td>\n";
			}
			echo "</table>\n";
		}

		unset($totals);
		unset($temp_stats);
	
	}
	
	// function to sort the stats by specific value (e.g. killefficiency for terminator award)
	function AwardSort($column, $sortdir)
	{
		$award = array();
		$i = 0;
		
		foreach($this->stats as $name => $player)
		{
			$sortcrit[$name] = $player["custom"]["total"][$column];
		}
		array_multisort($sortcrit, $sortdir, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => $player["custom"]["total"][$column]);
			
			$i++;
		}
		return $award;
	}
	
	// function to sort the stats by specific value (e.g. killefficiency for terminator award)
	function AwardWeaponKillSort($weapon)
	{
		$award = array();
		$i = 0;
		
		foreach($this->stats as $name => $player)
		{
			$sortcrit[$name] = count($player["custom"]["kills"][$weapon]);
		}

		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => count($player["custom"]["kills"][$weapon]));
			
			$i++;
		}
		return $award;
	}
	
	// function to sort the stats by specific value (e.g. killefficiency for terminator award)
	function AwardSelfkillSort($weapon)
	{
		$award = array();
		$i = 0;
		
		foreach($this->stats as $name => $player)
		{
			$sortcrit[$name] = $player["custom"]["selfkills"][$weapon];
		}

		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => $player["custom"]["selfkills"][$weapon]);
			
			$i++;
		}
		return $award;
	}
	
	// function to sort the stats by specific value (e.g. killefficiency for terminator award)
	function AwardDeathSort($weapon)
	{
		$award = array();
		$i = 0;
		
		foreach($this->stats as $name => $player)
		{
			$sortcrit[$name] = count($player["custom"]["deaths"][$weapon]);
		}

		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => count($player["custom"]["deaths"][$weapon]));
			
			$i++;
		}
		return $award;
	}
	
	// function to print every award name whoever is first
	function PrintAwardName($award)
	{
		echo Helper::colorsToHtml($award[0]["playername"]);
		for($i=1; $i<count($award); $i++)
		{
			if ($award[0]["value"] != $award[$i]["value"])
				break;
			echo ", " . Helper::colorsToHtml($award[$i]["playername"]);
		}
	}
	
	// function to print detailed award statistics
	function PrintAwardTable($award, $decimals)
	{
		echo "<div class='awarddetail'>";
		foreach($award as $player)
		{
			echo "<div class='awardname'>" . Helper::colorsToHtml($player["playername"]) . "</div><div class='awardvalue'>" . number_format($player["value"], $decimals) . "</div><br/>";
		}
		echo "</div>\n<p/>\n";
	}
	
	// function to print the main awards
	function PrintMainAwards()
	{	
		echo "<h2>Main Awards</h2>\n";
		
		// terminator
		$award = $this->AwardSort("killefficiency", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Terminator Award: ";
			$this::PrintAwardName($award);
			echo " for killing efficiency of " . number_format($award[0]["value"], 2) . ".</div>\n";
			$this::PrintAwardTable($award, 2);
		}
		
		// Slaughterhouse
		$award = $this->AwardSort("kills", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Slaughterhouse Award: ";
			$this::PrintAwardName($award);
			echo " with total " . $award[0]["value"] . " kills.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Slaughterhouse Lama
		$award = $this->AwardSort("deaths", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Slaughterhouse Lama Award: ";
			$this::PrintAwardName($award);
			echo " for getting slaughtered a total of " . $award[0]["value"] . " times.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Slyfox
		$award = $this->AwardSort("deaths", SORT_ASC);
		echo "<div class='awardtitle' onclick='togglechild(this)'>Sly Fox Award: ";
		$this::PrintAwardName($award);
		echo " for getting killed only " . $award[0]["value"] . " times.</div>\n";
		$this::PrintAwardTable($award, 0);
		
		// Harakiri
		$award = $this->AwardSort("suicides", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Harakiri Award: ";
			$this::PrintAwardName($award);
			echo " for committing suicide " . $award[0]["value"] . " times.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Internal Enemy
		$award = $this->AwardSort("teamkills", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Internal Enemy Award: ";
			$this::PrintAwardName($award);
			echo " for killing " . $award[0]["value"] . " teammates.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Needless Player
		$award = $this->AwardSort("teamkilled", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Needless Player Award: ";
			$this::PrintAwardName($award);
			echo " for getting slaughtered a total of " . $award[0]["value"] . " times by teamkill.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Blabbermouth
		$award = $this->AwardSort("chatlines", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Blabbermouth Award: ";
			$this::PrintAwardName($award);
			echo " for " . $award[0]["value"] . " lines of messagelog.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Smalldick
		$award = $this->AwardSelfkillSort("panzerfaust");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Small Dick Award: ";
			$this::PrintAwardName($award);
			echo " for can't handling his big toy and blowing himself up a total of " . $award[0]["value"] . " times with panzer.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Learn to swim
		$award = $this->AwardSelfkillSort("drown");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Tiberinus Silvius Award: ";
			$this::PrintAwardName($award);
			echo " for drowning " . $award[0]["value"] . " times.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Careless Sheep
		$award = $this->AwardDeathSort("knife");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Careless Sheep Award: ";
			$this::PrintAwardName($award);
			echo " for getting stabbed a total of " . $award[0]["value"] . " times.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// King of Vote
		$award = $this->AwardSort("votes", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>King Of Vote Award: ";
			$this::PrintAwardName($award);
			echo " for calling a total of " . $award[0]["value"] . " votes.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Most Hated
		$award = $this->AwardSort("kicks", SORT_DESC);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Most Hated Player Award: ";
			$this::PrintAwardName($award);
			echo " for getting kicked a total of " . $award[0]["value"] . " times.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Rampage
		$award = array();
		$i = 0;
		
		foreach($this->stats as $name => $player)	// sort by longest spree
		{
			arsort($player["custom"]["sprees"]);	// sort sprees
			$sortcrit[$name] = isset($player["custom"]["sprees"][0]) ? $player["custom"]["sprees"][0] : 0;
		}
		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => isset($player["custom"]["sprees"][0]) ? $player["custom"]["sprees"][0] : 0);
			
			$i++;
		}
	
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Rampage Award: ";
			$this::PrintAwardName($award);
			echo " for " . $award[0]["value"] . " frags without dying.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Brutal Rambo
		$award = array();
		$i = 0;
		foreach($this->stats as $name => $player)	// sort by spree count
		{
			$sortcrit[$name] = count($player["custom"]["sprees"]);
		}
		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => count($player["custom"]["sprees"]));
			
			$i++;
		}
	
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Brutal Rambo Award: ";
			$this::PrintAwardName($award);
			echo " for " . $award[0]["value"] . " series frags (" . SPREE_COUNT . " frags without dying)</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// Freefrag
		$award = array();
		$i = 0;
		foreach($this->stats as $name => $player)	// sort by freefrags
		{
			$sortcrit[$name] = $player["custom"]["freefrags"];
		}
		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->stats);
		
		foreach($this->stats as $player)
		{
			$award[$i] = array("playername" => $player["custom"]["playername"],
								"value" => $player["custom"]["freefrags"]);
			
			$i++;
		}
	
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>Freefrag Award: ";
			$this::PrintAwardName($award);
			echo " for " . $award[0]["value"] . " deaths without fragging.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		unset($award);
	}
	
	// function to print the weapon awards
	function PrintWeaponAwards()
	{		
		echo "<h2>Weapon Awards</h2>\n";
		// mp40
		$award = $this->AwardWeaponKillSort("mp40");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Master Of MP40 Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// thompson
		$award = $this->AwardWeaponKillSort("thompson");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The King Of The Thompson Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// sten
		$award = $this->AwardWeaponKillSort("sten");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Master Of Sten Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// pf
		$award = $this->AwardWeaponKillSort("panzerfaust");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Panzer-Lama Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " fuckin' frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// sniper
		$award = $this->AwardWeaponKillSort("sniper");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Sharp-Shooter Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " sniper frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// mauser
		$award = $this->AwardWeaponKillSort("mauser");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Unscoped Killer Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " mauser frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// grenade
		$award = $this->AwardWeaponKillSort("grenade");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Master Of Grenade Award: ";
			$this::PrintAwardName($award);
			echo " with " . number_format($award[0]["value"], 0) . " frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// support
		$award = $this->AwardWeaponKillSort("support");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Best Indian Smoke-Messenger Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " support-fire frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// artillery
		$award = $this->AwardWeaponKillSort("artillery");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The God Of War Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " artillery frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// flamethrower
		$award = $this->AwardWeaponKillSort("flamethrower");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Burner Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " flamethrower frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// knife
		$award = $this->AwardWeaponKillSort("knife");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Silent Killer Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " knife frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// venom
		$award = $this->AwardWeaponKillSort("venom");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Master Of Venom Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// pistol
		$award = $this->AwardWeaponKillSort("luger");
		$award2 = $this->AwardWeaponKillSort("colt");
		for ($i=0; $i<count($award2); $i++)		// combine both arrays
		{
			for($j=0; $j<count($award); $j++)
			{
				if($award[$j]["playername"] == $award2[$i]["playername"])
				{
					$award[$j]["value"] += $award2[$i]["value"];
					break;
				}
			}
		}
		unset($award2);
		foreach($award as $name => $value)	// sort array
		{
			$sortcrit[$name] = $value["value"];
		}
		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $award);
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The \"John Wayne Is Lama\" Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " pistol frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// mg
		$award = $this->AwardWeaponKillSort("mg");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Best Chain-Gun User Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		
		// dynamite
		$award = $this->AwardWeaponKillSort("dynamite");
		if ($award[0]["value"] > 0)
		{
			echo "<div class='awardtitle' onclick='togglechild(this)'>The Arabian Engineer Award: ";
			$this::PrintAwardName($award);
			echo " for " . number_format($award[0]["value"], 0) . " dynamite frags.</div>\n";
			$this::PrintAwardTable($award, 0);
		}
		unset($award);
	}
	
	// function to print weapon statistics
	function PrintWeapons()
	{
		echo "<br/>\n<table id='statstable' class='tablesorter'>\n<thead>\n<tr>\n<th class='tablename'>Weapon</th>\n<th>Frags</th>\n<th>%</th>\n</thead>\n<tbody>\n";
		
		arsort($this->weapons);
		
		$count = 0;
		foreach($this->weapons as $name => $kills)
		{
			if($kills > 0)
			{
				echo "<tr class='tablerow" . ($count%2) . "'>\n<td>" . Helper::$weaponnames[$name] . "</td>\n<td>" . $kills . "</td>\n<td>" . number_format((($this->frag_count != 0) ? $kills/$this->frag_count*100 : 0), 2) . "</td>\n</tr>\n";
				$count++;
			}
		}
		echo "<tr class='tablerowtotal'>\n<td>TOTAL</td>\n<td>" . $this->frag_count . "</td>\n<td>100.00</td>\n</tr>\n";
		echo "</tbody>\n</table>\n";
	}
	
	// function to print every player enemy name whoever is first
	function PrintPlayerEnemyName($playername, $which)
	{
		$keys = array_keys($this->stats[$playername]["custom"]["total"][$which]);
		if (count($keys) > 0)
		{
			$count = $this->stats[$playername]["custom"]["total"][$which][$keys[0]];		

			echo Helper::colorsToHtml($this->stats[$keys[0]]["custom"]["playername"]);
			
			for($i=1; $i<count($keys); $i++)
			{
				if ($count != $this->stats[$playername]["custom"]["total"][$which][$keys[$i]])
					break;
				echo ", " . Helper::colorsToHtml($this->stats[$keys[$i]]["custom"]["playername"]);
			}
			return $count;
		}
		echo "none";
		return 0;
	}
	
	// function to print detailed player enemy statistics
	function PrintPlayerTable($playername, $which)
	{
		echo "<div class='playerdetail'>";
		foreach($this->stats[$playername]["custom"]["total"][$which] as $playername => $value)
		{
			echo "<div class='playerdetailname'>" . Helper::colorsToHtml($this->stats[$playername]["custom"]["playername"]) . "</div><div class='playerdetailvalue'>" . $value . "</div>\n<br/>\n";
		}
		echo "</div>\n";
	}
	
	// function to print player statistics
	function PrintPlayers()
	{
		// sort by team and killefficiency
		foreach ($this->stats as $name => $player)
		{
			$team[$name] = $player["custom"]["total"]["team"];
			$killefficiency[$name] = $player["custom"]["total"]["killefficiency"];
		}
		array_multisort($team, SORT_DESC, $killefficiency, SORT_DESC, $this->stats);
		
		echo "<div class='playerstats'>\n";
		foreach($this->stats as $name => $player)
		{
			echo "<div class='playerstat'>" . Helper::colorsToHtml($player["custom"]["playername"]) . "</div>\n";
			echo "<div class='playerstatdetail'>\n";
			
			// Victims
			$keys = array_keys($player["custom"]["total"]["victims"]);
			echo "<div class='playerenemy' onclick='togglechild(this)'>Favorite Enemy: ";
			$count = $this::PrintPlayerEnemyName($name, "victims");
			echo " with " . $count . " kills.</div>\n";
			$this::PrintPlayerTable($name, "victims");
			echo "<br/>";
			// Killers
			$keys = array_keys($player["custom"]["total"]["killers"]);
			echo "<div class='playerenemy' onclick='togglechild(this)'>Worst Enemy:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			$count = $this::PrintPlayerEnemyName($name, "killers");
			echo " with " . $count . " deaths.</div>\n";
			$this::PrintPlayerTable($name, "killers");
			echo "</div><br/>\n";
		}
		echo "</div>\n";
	}
	
	// function to print chat log
	function PrintChatlog()
	{
		echo "<div class='chatlog'>\n";
		foreach($this->chatlog as $entry)
		{
			echo Helper::colorsToHtml(substr($entry, 12)) . "<br/>\n";		// remove [skipnotify]
		}
		echo "</div>\n";
	}
	
}
?>