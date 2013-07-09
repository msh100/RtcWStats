<?php
require_once("helper.php");

// Notes: index as number; array with name => index (rename -> 2 names with same index)
// kills/deaths as matrix = twodimensional array
// count weapons extra? (in player or total?)
//	function for weaponkills => REGEX_WEAPONS = array(...)

// stats[index]
//		osp
//			round		// replace stats_current round with current index, keep stats_player_round but rename (for map_restart purposes)
//				1-x
//					team
//					killefficiency
//					kills
//					deaths
//					suicides
//					damageefficiency
//					dmg
//					dmr
//					teamkills
//					teamdmg
//			total
//				team
//				killefficiency
//				kills
//				deaths
//				suicides
//				damageefficiency
//				dmg
//				dmr
//				teamkills
//				teamdmg
//		shortname = string (to get playername)
//		teamkills = count
//		teamkilled = count
//		chatlines = count
//		votes = count
//		selfkills = count
//		kills = count
//		deaths = count
//		killefficiency = number
//		spreecount = count
//		maxspree = count
//		freefragcount = count
//		teamdamage = count
//		dmg = count
//		dmr = count
//		damageefficiency = number
//		damageperfrag = number
//		team = string(ask?)
// optional:
//		gp = count
//		scores = count
//
// weapons[weaponname]
//		kills[index] = count
//		deaths[index] = count
//		count = count
//
// selfkills[weaponname]
// 		player = count
//
// players[name]		ask for double index entries and delete others
//		playername = string
//		index = index
//      activitycount = count;
//
//
// teams[index]
//		index = index

class StatsAnalyzer
{
	private $playerStats;
	private $chatlog;
	private $weapons;
	private $playerIndex;
	private $matrix;
	private $indexNames;
	private $teams;
	private $team_count;
	
	private $selfkills;
	
	private $frag_count;
	private $round_count;
	
	function __construct($filepath) {
		$this::ProcessFile($filepath);
	}
	
	function __destruct() {
		unset($this->chatlog);
		unset($this->weapons);
		unset($this->playerStats);
		unset($this->playerIndex);
		unset($this->matrix);
	}
	
	// function to return team index or NULL if new team
	function getOrCreateTeam($index)
	{
		foreach($this->team as $teamindex => $team)
		{
			if (array_search($index, $team) !== false)
			{
				return $team;
			}
		}
		return NULL;
	}
	
	// function to create a new round
	function createRound()
	{
		$round = array	(
							"team" => "",
							"frageff" => 0,
							"frags" => 0,
							"deaths" => 0,
							"suicides" => 0,
							"damageeff" => 0,
							"dmg" => 0,
							"dmr" => 0,
							"teamkills" => 0,
							"gibs" => 0,
							"teamdmg" => 0,
							"count" => 0,
							"damageperfrag" => 0
						);
		return $round;
	}
	
	// function to create a new player
	function createPlayer($shortname)
	{
		$player = array	(
							"osp" => array("total" => $this::createRound()),
							"shortname" => $shortname,
							"teamkills" => 0,
							"gibs" => 0,
							"teamdeaths" => 0,
							"chatlines" => 0,
							"votes" => 0,
							"kicks" => 0,
							"selfkills" => 0,
							"suicides" => 0,
							"frageff" => 0,
							"frags" => 0,
							"deaths" => 0,
							"spreecount" => 0,
							"maxspree" => 0,
							"freefragcount" => 0,
							"damageperfrag" => 0,
							"team" => ""
						);
		return $player;
	}
	
	// function to create a new playerindex
	function createPlayerIndex($shortname, $index, $playername)
	{
		$player = array	(
							"playername" => $playername,
							"shortname" => $shortname,
							"activitycount" => 0,
							"index" => $index
						);
		return $player;
	}
	
	// function to set a value in a matrix and create the row/column if not existing
	function setOrCreateMatrix(&$matrix, $killerindex, $victimindex, $count)
	{
		// handle killerindex
		$columns = array_keys($matrix);
		if (!isset($matrix[$killerindex]))
		{
			$matrix[$killerindex] = array();
			$columns[count($columns)] = $killerindex;
			$matrix[$killerindex] = array_fill_keys($columns, 0);
			foreach($matrix as $i => $j)
			{
				if (!isset($matrix[$i][$killerindex]))
					$matrix[$i][$killerindex] = 0;
			}
		}
		
		// handle victimindex
		if (!isset($matrix[$victimindex]))
		{
			$matrix[$victimindex] = array();
			$columns[count($columns)] = $victimindex;
			$matrix[$victimindex] = array_fill_keys($columns, 0);
			foreach($matrix as $i => $j)
			{
				if (!isset($matrix[$i][$victimindex]))
					$matrix[$i][$victimindex] = 0;
			}
		}
		$matrix[$killerindex][$victimindex] += $count;
	}
	
	// function to get player index by shortname or create and return new index if not existing
	function getOrCreatePlayer($shortname, $playername)
	{
		if (!isset($this->playerIndex[$shortname]))		// create player & index if not existing
		{
			$index = count($this->playerStats);			// array starting with index 0, so count gives new index
			$this->playerStats[$index] = $this::createPlayer($shortname);
			$this->playerIndex[$shortname] = $this::createPlayerIndex($shortname, $index, $playername);
			return $index;
		}
		else
		{
			if ($playername !== NULL)
				$this->playerIndex[$shortname]["playername"] = $playername;
			return $this->playerIndex[$shortname]["index"];
		}
	}
	
	// function to create round player stats
	function createRoundPlayer($playername)
	{
		$player = array	(
							"playername" => $playername,
							"frags" => 0,
							"deaths" => 0,
							"selfkills" => 0,
							"suicides" => 0,
							"spreecount" => 0,
							"teamkills" => 0,
							"teamdeaths" => 0,
							"currentspree" => 0,
							"maxspree" => 0,
							"freefragcount" => 0,
							"currentfreefrags" => 0
						);
		return $player;
	}
	
	// function to create a single weapon
	function createSingleWeapon()
	{
		$weapon = array	(
							"kills" => array(),
							"deaths" => array(),
							"selfkills" => array(),
							"count" => 0
						);
		return $weapon;
	}
	
	// function to create all weapons
	function createWeapons()
	{
		$weapons = array	(
								"grenade" => $this::createSingleWeapon(),
								"thompson" => $this::createSingleWeapon(),
								"pf" => $this::createSingleWeapon(),
								"mp40" => $this::createSingleWeapon(),
								"sten" => $this::createSingleWeapon(),
								"luger" => $this::createSingleWeapon(),
								"colt" => $this::createSingleWeapon(),
								"sniper" => $this::createSingleWeapon(),
								"mauser" => $this::createSingleWeapon(),
								"venom" => $this::createSingleWeapon(),
								"flamethrower" => $this::createSingleWeapon(),
								"knife" => $this::createSingleWeapon(),
								"support" => $this::createSingleWeapon(),
								"artillery" => $this::createSingleWeapon(),
								"dynamite" => $this::createSingleWeapon(),
								"mg" => $this::createSingleWeapon(),
								"drown" => $this::createSingleWeapon(),
								"other" => $this::createSingleWeapon(),
								"jump" => $this::createSingleWeapon(),
								"crush" => $this::createSingleWeapon(),
							);
		return $weapons;
	}
	
	// function to write a killevent into the weapon stats
	function ProcessKill(&$weapons, $weapon, $killerindex, $victimindex)
	{
		$weapons[$weapon]["count"] += 1;
		if (!isset($weapons[$weapon]["kills"][$killerindex]))		// process killer
			$weapons[$weapon]["kills"][$killerindex] = 0;
		$weapons[$weapon]["kills"][$killerindex] += 1;
		
		if (!isset($weapons[$weapon]["deaths"][$victimindex]))		// process victim
			$weapons[$weapon]["deaths"][$victimindex] = 0;
		$weapons[$weapon]["deaths"][$victimindex] += 1;
	}
	
	// function to process a single stats line
	function ProcessStatsLine($line, &$newstats)
	{
		$statsline = Helper::stripColors($line);		// remove colors codes
		$split = preg_split("/[\s]+/", $statsline);	// split by blanks
		$len = count($split);
		
		// create new stats and write values into it
		$newstats = $this::createRound();
		$newstats["team"] = $split[0];
		$newstats["frags"] = $split[$len-11];
		$newstats["deaths"] = $split[$len-10];
		$newstats["suicides"] = $split[$len-9];
		$newstats["teamkills"] = $split[$len-8];
		$newstats["gibs"] = $split[$len-6];
		$newstats["dmg"] = $split[$len-5];
		$newstats["dmr"] = $split[$len-4];
		$newstats["teamdmg"] = $split[$len-3];
		$newstats["frageff"] = $newstats["deaths"] == 0 ? $newstats["frags"] : $newstats["frags"] / $newstats["deaths"];
		$newstats["damageeff"] = $newstats["dmr"] == 0 ? $newstats["dmg"] : $newstats["dmg"] / $newstats["dmr"];
		$newstats["damageperfrag"] = $newstats["frags"] == 0 ? 0 : $newstats["dmg"] / $newstats["frags"];
		$newstats["score"] = $split[$len-2];
		
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
		$this->frag_count = 0;
		$stats_thisround = array();
		$weapons_thisround = $this::createWeapons();
		$matrix_thisround = array();
		$this->stats = array();		// reset stats
		$this->chatlog = array();
		$this->weapons = $this::createWeapons();
		$this->matrix = array();
		$this->teams = array();
		$team1 = array();
		$team2 = array();
		
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
				{
					$stats_thisround = array();
					$weapons_thisround = $this::createWeapons();
					$matrix_thisround = array();
				}
				else					// normal round start
				{
					$isStarted = true;
					$stats_thisround = array();
					$weapons_thisround = $this::createWeapons();
					$matrix_thisround = array();
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
				
				// sum up osp total stats
				for($i=0; $i<count($this->playerStats); $i++)
				{
					if (isset($this->playerStats[$i]["osp"][$this->round_count]))
					{
						$this->playerStats[$i]["osp"]["total"]["team"] = $this->playerStats[$i]["osp"][$this->round_count]["team"];
						$this->playerStats[$i]["osp"]["total"]["frags"] += $this->playerStats[$i]["osp"][$this->round_count]["frags"];
						$this->playerStats[$i]["osp"]["total"]["deaths"] += $this->playerStats[$i]["osp"][$this->round_count]["deaths"];
						$this->playerStats[$i]["osp"]["total"]["suicides"] += $this->playerStats[$i]["osp"][$this->round_count]["suicides"];
						$this->playerStats[$i]["osp"]["total"]["dmg"] += $this->playerStats[$i]["osp"][$this->round_count]["dmg"];
						$this->playerStats[$i]["osp"]["total"]["dmr"] += $this->playerStats[$i]["osp"][$this->round_count]["dmr"];
						$this->playerStats[$i]["osp"]["total"]["teamkills"] += $this->playerStats[$i]["osp"][$this->round_count]["teamkills"];
						$this->playerStats[$i]["osp"]["total"]["gibs"] += $this->playerStats[$i]["osp"][$this->round_count]["gibs"];
						$this->playerStats[$i]["osp"]["total"]["teamdmg"] += $this->playerStats[$i]["osp"][$this->round_count]["teamdmg"];
						$this->playerStats[$i]["osp"]["total"]["frageff"] = $this->playerStats[$i]["osp"]["total"]["deaths"] == 0 ? 0 : $this->playerStats[$i]["osp"]["total"]["frags"] / $this->playerStats[$i]["osp"]["total"]["deaths"];
						$this->playerStats[$i]["osp"]["total"]["damageeff"] = $this->playerStats[$i]["osp"]["total"]["dmr"] == 0 ? 0 : $this->playerStats[$i]["osp"]["total"]["dmg"] / $this->playerStats[$i]["osp"]["total"]["dmr"];
						$this->playerStats[$i]["osp"]["total"]["damageperfrag"] = $this->playerStats[$i]["osp"]["total"]["frags"] == 0 ? 0 : $this->playerStats[$i]["osp"]["total"]["dmg"] / $this->playerStats[$i]["osp"]["total"]["frags"];
						$this->playerStats[$i]["osp"]["total"]["count"] += 1;
					}
				}
				
				// write player round stats into overall stats
				foreach($stats_thisround as $index => $value)
				{
					$this->frag_count += $value["frags"];
					
					$this->playerStats[$index]["frags"] += $value["frags"];
					$this->playerStats[$index]["deaths"] += $value["deaths"];
					$this->playerStats[$index]["selfkills"] += $value["selfkills"];
					$this->playerStats[$index]["suicides"] += $value["suicides"];
					$this->playerStats[$index]["teamkills"] += $value["teamkills"];
					$this->playerStats[$index]["teamdeaths"] += $value["teamdeaths"];
					
					if ($value["currentspree"] > $value["maxspree"])			// handle sprees
						$value["maxspree"] = $value["currentspree"];
						
					if ($value["maxspree"] > $this->playerStats[$index]["maxspree"])
						$this->playerStats[$index]["maxspree"] = $value["maxspree"];
						
					if ($value["currentspree"] >= 5)
						$value["spreecount"] += 1;
					$this->playerStats[$index]["spreecount"] += $value["spreecount"];
					
					
					if ($value["currentfreefrags"] > $value["freefragcount"])	// handle freefrags
						$value["freefragcount"] = $value["currentfreefrags"];
					
					if ($value["freefragcount"] > $this->playerStats[$index]["freefragcount"])
						$this->playerStats[$index]["freefragcount"] = $value["freefragcount"];
				}
				
				// write weapon round stats into overall weapon stats
				foreach($weapons_thisround as $weapon => $value)
				{
					foreach($value["kills"] as $index => $count)
					{
						if(!isset($this->weapons[$weapon]["kills"][$index]))
							$this->weapons[$weapon]["kills"][$index] = 0;
						$this->weapons[$weapon]["kills"][$index] += $count;
					}
					foreach($value["deaths"] as $index => $count)
					{
						if(!isset($this->weapons[$weapon]["deaths"][$index]))
							$this->weapons[$weapon]["deaths"][$index] = 0;
						$this->weapons[$weapon]["deaths"][$index] += $count;
					}
					$this->weapons[$weapon]["count"] += $value["count"];
				}
				
				foreach ($matrix_thisround as $i => $row)
				{
					foreach($row as $j => $count)
					{
						$this::setOrCreateMatrix($this->matrix, $i, $j, $count);
					}
				}				
				$this->round_count++;				// reset values for next round
			}
			// osp stats
			else if (Helper::startsWith($buffer, "^7TEAM"))
			{
				// reset current round stats
				for($i=0; $i<count($this->playerStats); $i++)
				{
					unset($this->playerStats["osp"][$this->round_count]);
				}
				
				$buffer = fgets($handle, 4096);		// advance two lines
				$buffer = fgets($handle, 4096);
				
				$this->team[$this->round_count] = array(1 => array(), 2 => array());
				$this->team[$this->round_count][1] = array();
				$this->team[$this->round_count][2] = array();
				do		// read team 1 stats
				{
					$shortname = substr($this::ProcessStatsLine($buffer, $newstats), 0, 15);		// process stats line
					$index = $this::getOrCreatePlayer($shortname, NULL);										// create player & index if not existing and/or get index
					$this->playerStats[$index]["osp"][$this->round_count] = $newstats;
					array_push($this->team[$this->round_count][1], $index);
					
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
					$playername = substr($this::ProcessStatsLine($buffer, $newstats), 0, 15);		// process stats line
					$index = $this::getOrCreatePlayer($playername, NULL);										// create player & index if not existing and/or get index
					$this->playerStats[$index]["osp"][$this->round_count] = $newstats;
					
					array_push($this->team[$this->round_count][2], $index);
					
					if (($buffer = fgets($handle, 4096)) == false)		// read next stats line
						return -2;
				}
				while (!Helper::startsWith($buffer, "^7---------------------------------------------------------------------"));
			}
			// check all the regexps
			else
			{
				$lineFound = false;
				// check for kill line
				foreach(Helper::$regex_kills as $weapon => $regex)		// iterate through every kill possibility
				{
					if (preg_match($regex, $buffer, $matches))
					{
						$lineFound = true;
						
						$killer = $matches[2];
						$victim = $matches[1];
						$killershort = Helper::getShortname($killer);
						$victimshort = Helper::getShortname($victim);
						
						if ($isStarted === true)
						{
							// handle killer
							$killerindex = $this::getOrCreatePlayer($killershort, $killer);		// get overall index
							if(!isset($stats_thisround[$killerindex]))							// create player for this round if not existing
								$stats_thisround[$killerindex] = $this::createRoundPlayer($killer);
								
							$stats_thisround[$killerindex]["frags"] += 1;
							$stats_thisround[$killerindex]["currentspree"] += 1;
							if ($stats_thisround[$killerindex]["currentfreefrags"] > $stats_thisround[$killerindex]["freefragcount"])	// handle freefrags
								$stats_thisround[$killerindex]["freefragcount"] = $stats_thisround[$killerindex]["currentfreefrags"];
							$stats_thisround[$killerindex]["currentfreefrags"] = 0;
							
							// handle victim
							$victimindex = $this::getOrCreatePlayer($victimshort, $victim);		// get overall index
							if(!isset($stats_thisround[$victimindex]))							// create player for this round if not existing
								$stats_thisround[$victimindex] = $this::createRoundPlayer($victim);
								
							$stats_thisround[$victimindex]["deaths"] += 1;
							$stats_thisround[$victimindex]["currentfreefrags"] += 1;
							if 	($stats_thisround[$victimindex]["currentspree"] > $stats_thisround[$victimindex]["maxspree"])			// handle sprees
								$stats_thisround[$victimindex]["maxspree"] = $stats_thisround[$victimindex]["currentspree"];
							if ($stats_thisround[$victimindex]["currentspree"] >= 5)
								$stats_thisround[$victimindex]["spreecount"] += 1;
							$stats_thisround[$victimindex]["currentspree"] = 0;
							
							// handle weaponstats
							$this::ProcessKill($weapons_thisround, $weapon, $killerindex, $victimindex);							
							
							// update round matrix
							$this::setOrCreateMatrix($matrix_thisround, $killerindex, $victimindex, 1);
							
							$this->playerIndex[$killershort]["activitycount"] += 1;
							$this->playerIndex[$victimshort]["activitycount"] += 1;
						}
						break;
					}
				}
				if (!$lineFound)
				{
					// check for selfkill-lines
					foreach(Helper::$regex_selfkills as $weapon => $regex)		// iterate through every selfkill possibility
					{
						if (preg_match($regex, $buffer, $matches))
						{
							$lineFound = true;
							
							$victim = $matches[1];
							$victimshort = Helper::getShortname($victim);
							
							if ($isStarted === true)
							{
								// handle selfkill
								$index = $this::getOrCreatePlayer($victimshort, $victim);		// get overall index
								if(!isset($stats_thisround[$index]))							// create player for this round if not existing
									$stats_thisround[$index] = $this::createRoundPlayer($victim);
									
								$stats_thisround[$index]["selfkills"] += 1;
								
								// handle weaponstats
								if (!isset($this->weapons[$weapon]["selfkills"][$index]))		// process killer
									$this->weapons[$weapon]["selfkills"][$index] = 0;
								$this->weapons[$weapon]["selfkills"][$index] += 1;
								
								$this->playerIndex[$victimshort]["activitycount"] += 1;
							}
							break;
						}
					}
				}
				if (!$lineFound)
				{
					// check for suicide line
					if (preg_match(Helper::REGEX_SUICIDE, $buffer, $matches))
					{
						$victim = $matches[1];
						$victimshort = Helper::getShortname($victim);
						
						if ($isStarted === true)
						{
							// handle suicide
							$index = $this::getOrCreatePlayer($victimshort, $victim);		// get overall index
							if(!isset($stats_thisround[$index]))							// create player for this round if not existing
								$stats_thisround[$index] = $this::createRoundPlayer($victim);
								
							$stats_thisround[$index]["suicides"] += 1;
							
							// update round matrix
							$this::setOrCreateMatrix($matrix_thisround, $index, $index, 1);
							
							$this->playerIndex[$victimshort]["activitycount"] += 1;
						}
					}
					// check for teamkill line
					else if (preg_match(Helper::REGEX_TEAMKILL, $buffer, $matches))
					{						
						$killer = $matches[2];
						$victim = $matches[1];
						$killershort = Helper::getShortname($killer);
						$victimshort = Helper::getShortname($victim);
						
						if ($isStarted === true)
						{
							// handle killer
							$killerindex = $this::getOrCreatePlayer($killershort, $killer);		// get overall index
							if(!isset($stats_thisround[$killerindex]))							// create player for this round if not existing
									$stats_thisround[$killerindex] = $this::createRoundPlayer($killer);
							
							$stats_thisround[$killerindex]["teamkills"] += 1;
							
							// handle victim
							$victimindex = $this::getOrCreatePlayer($victimshort, $victim);		// get overall index
							if(!isset($stats_thisround[$victimindex]))							// create player for this round if not existing
									$stats_thisround[$victimindex] = $this::createRoundPlayer($victim);
							
							$stats_thisround[$victimindex]["teamdeaths"] += 1;
							
							// update round matrix
							$this::setOrCreateMatrix($matrix_thisround, $killerindex, $victimindex, 1);
							
							$this->playerIndex[$victimshort]["activitycount"] += 1;
							$this->playerIndex[$killershort]["activitycount"] += 1;
						}
					}
					// check for chat line
					else if (preg_match(Helper::REGEX_CHATLINE, $buffer, $matches))
					{
						$chatter = $matches[1];
						$chattershort = Helper::getShortname($chatter);
						
						array_push($this->chatlog, $buffer);

						// handle killer
						$index = $this::getOrCreatePlayer($chattershort, $chatter);		// get overall index
						$this->playerStats[$index]["chatlines"] += 1;
						
						$this->playerIndex[$chattershort]["activitycount"] += 1;
					}
					// check for vote line
					else if (preg_match(Helper::REGEX_VOTE, $buffer, $matches))
					{
						$voter = $matches[1];
						$votershort = Helper::getShortname($voter);
						
						// handle vote
						$index = $this::getOrCreatePlayer($votershort, $voter);		// get overall index
						$this->playerStats[$index]["votes"] += 1;
						
						$this->playerIndex[$votershort]["activitycount"] += 1;
					}
					// check for kick line
					else if (preg_match(Helper::REGEX_KICK, $buffer, $matches))
					{
						$victim = $matches[1];
						$victimshort = Helper::getShortname($victim);
						
						// handle kick
						$index = $this::getOrCreatePlayer($victimshort, $victim);		// get overall index
						$this->playerStats[$index]["kicks"] += 1;
						
						$this->playerIndex[$victimshort]["activitycount"] += 1;
					}
					// check for rename line
					else if (preg_match(Helper::REGEX_RENAME, $buffer, $matches))
					{
						$oldname = $matches[1];
						$oldnameshort = Helper::getShortname($oldname);
						$newname = $matches[2];
						$newnameshort = Helper::getShortname($newname);
						
						// handle rename in global index
						if (isset($this->playerIndex[$oldnameshort]))	// if player is already existing
						{
							$index = $this->playerIndex[$oldnameshort]["index"];
							$this->playerIndex[$newnameshort] = $this::createPlayerIndex($newnameshort, $index, $newname);
						}
						else										// create new player
						{
							$index = $this::getOrCreatePlayer($newname, $newnameshort);
						}
					}
				}
			}
		}
		
		// delete redundant playerIndexes
		$index_done = array();
		$to_delete = array();
		$this->indexNames = array();
		foreach($this->playerIndex as $key => $player)
		{
			$index = $player["index"];
			if(!isset($index_done[$index]))
			{
				$index_done[$index] = $key;
				$this->indexNames[$index] = $player["playername"];
			}
			else
			{
				if ($player["activitycount"] > $this->playerIndex[$index_done[$index]]["activitycount"])
				{
					array_push($to_delete, $index_done[$index]);
					$index_done[$index] = $key;
					$this->indexNames[$index] = $player["playername"];
				}
				else
					array_push($to_delete, $key);
			}
		}
		foreach($to_delete as $key)
		{
			unset($this->playerIndex[$key]);
		}
		
		// set teams correct
		reset($this->team);
		$firstround = key($this->team);
		$team = array();
		$team[1] = $this->team[$firstround][1];
		$team[2] = $this->team[$firstround][2];
		foreach($team[1] as $index)
		{
			$this->playerStats[$index]["team"] = "1";
		}
		foreach($team[2] as $index)
		{
			$this->playerStats[$index]["team"] = "2";
		}
		
		// check for any late-joiners (without team set)
		foreach($this->playerStats as $index => $player)
		{
			if ($player["team"] == "")
			{
				$currteam = $this::getTeam($index, $team);
				if ($currteam != false)
				{
					$this->playerStats[$index]["team"] = $currteam;
					array_push($team[$currteam], $index);
				}
			}
		}

		/*Helper::dump($this->playerStats);
		Helper::dump($this->playerIndex);
		Helper::dump($this->weapons);
		Helper::dump($this->matrix);
		Helper::dump($this->team);
		exit;*/
		
		// close logfile
		fclose($handle);
		
	}
	
	// function to get team index
	public function getTeam($index, &$teams)
	{
		foreach($this->team as $roundindex => $round)
		{
			foreach($round as $team)
			{
				if (in_array($index, $team))
				{
					$count1 = 0;
					$count2 = 0;
					// sum up count of teammates in each team
					foreach($team as $value)
					{
						if (in_array($value, $teams[1])) $count1++;
						if (in_array($value, $teams[2])) $count2++;
					}
					if ($count1 > $count2)
						return "1";
					else
						return "2";
				}				
			}
		}
		return false;
	}
	
	// function to print stats to a table
	public function StatsToTable($playername, &$stats, $csstype)
	{
		echo "<tr class='" . $csstype . "'>\n";
		echo "<td class='tablename'>" . Helper::colorsToHtml($playername) . "</td>\n";
		echo "<td class='" . ($stats["frageff"]>=1?"positive":"negative") . "'>" . number_format($stats["frageff"], 2) . "</td>\n";

		echo "<td>" .  number_format($stats["frags"]) . "</td>\n";
		echo "<td>" .  number_format($stats["deaths"]) . "</td>\n";
		echo "<td>" .  number_format($stats["gibs"]) . "</td>\n";
		echo "<td>" .  number_format($stats["suicides"]) . "</td>\n";
		echo "<td>" .  number_format($stats["selfkills"]) . "</td>\n";
		echo "<td>" .  number_format($stats["teamkills"]) . "</td>\n";
		echo "<td>" .  number_format($stats["teamdmg"]) . "</td>\n";
		echo "<td class='" . ($stats["damageeff"]>=1?"positive":"negative") . "'>" . number_format($stats["damageeff"], 2) . "</td>\n";
		echo "<td>" .  number_format($stats["dmg"]) . "</td>\n";
		echo "<td>" .  number_format($stats["dmr"]) . "</td>\n";
		echo "<td>" . number_format($stats["damageperfrag"], 0) . "</td>\n";
		echo "<td>" . $stats["count"] . "</td>\n";
		echo "</tr>\n";
	}
	
	public function ProcessPrintStats($team, &$sort_stats)
	{
		echo "<h2>Team " . $team . "</h2>\n";
		echo "<table id='statstable' class='sortable'>\n<thead>\n<tr>\n<th class='tablename'>Player</th>\n<th>Kill-Efficiency</th>\n<th>Frags</th>\n<th>Deaths</th>\n<th>Gibs</th>\n<th>Suicides</th>\n";
		echo "<th>Selfkills</th>\n<th>Teamkills</th>\n<th>Team-DMG</th>\n<th>Damage-Efficiency</th>\n<th>DMG</th>\n<th>DMR</th>\n<th>DMG/Frag</th>\n<th>Rounds</th>\n</tr>\n</thead>\n<tbody>\n";
		
		// set up total stats
		$totals = array("frags" => 0,
				"deaths" => 0,
				"suicides" => 0,
				"selfkills" => 0,
				"teamkills" => 0,
				"dmg" => 0,
				"gibs" => 0,
				"dmr" => 0,
				"teamdmg" => 0,
				"count" => ""
		);
		
		// loop over every team player
		foreach ($sort_stats[$team] as $player)
		{
			$index = $player["index"];
			// sort out already done playerstats and empty playerstats
			if(!isset($index_done[$index]))
			{
				$index_done[$index] = true;
				
				// build player stats
				$this->playerStats[$index]["frageff"] = $this->playerStats[$index]["deaths"] == 0 ? $this->playerStats[$index]["frags"] : $this->playerStats[$index]["frags"] / $this->playerStats[$index]["deaths"];
				$this->playerStats[$index]["damageperfrag"] = $this->playerStats[$index]["frags"] == 0 ? $this->playerStats[$index]["osp"]["total"]["dmg"] : $this->playerStats[$index]["osp"]["total"]["dmg"] / $this->playerStats[$index]["frags"];
				$this->playerStats[$index]["teamdmg"] = $this->playerStats[$index]["osp"]["total"]["teamdmg"];
				$this->playerStats[$index]["gibs"] = $this->playerStats[$index]["osp"]["total"]["gibs"];
				$this->playerStats[$index]["dmg"] = $this->playerStats[$index]["osp"]["total"]["dmg"];
				$this->playerStats[$index]["dmr"] = $this->playerStats[$index]["osp"]["total"]["dmr"];
				$this->playerStats[$index]["damageeff"] = $this->playerStats[$index]["osp"]["total"]["damageeff"];
				$this->playerStats[$index]["count"] = $this->playerStats[$index]["osp"]["total"]["count"];
				
				// sum up total stats for team
				$totals["frags"] += $this->playerStats[$index]["frags"];
				$totals["deaths"] += $this->playerStats[$index]["deaths"];
				$totals["suicides"] += $this->playerStats[$index]["suicides"];
				$totals["selfkills"] += $this->playerStats[$index]["selfkills"];
				$totals["teamkills"] += $this->playerStats[$index]["teamkills"];
				$totals["dmg"] += $this->playerStats[$index]["osp"]["total"]["dmg"];
				$totals["dmr"] += $this->playerStats[$index]["osp"]["total"]["dmr"];
				$totals["teamdmg"] += $this->playerStats[$index]["osp"]["total"]["teamdmg"];
				
				// create table
				$this::StatsToTable($player["playername"], $this->playerStats[$index], "tablerow");
			}
		}
		
		// print axis total stats
		$totals["frageff"] = $totals["deaths"] == 0 ? $totals["frags"] : $totals["frags"] / $totals["deaths"];
		$totals["damageeff"] = $totals["dmr"] == 0 ? $totals["dmg"] : $totals["dmg"] / $totals["dmr"];
		$totals["damageperfrag"] = $totals["frags"] == 0 ? $totals["frags"] : $totals["dmg"] / $totals["frags"];
		echo "</tbody>\n<tfoot>\n";
		$this::StatsToTable("TOTAL", $totals, "tablerowtotal");
		echo "</tfoot>\n";
		echo "</table>\n";
	}
	
	// function to print stats
	public function PrintStats()
	{	
		$sort_stats = array("1" => array(), "2" => array());
	
		// sort by team and kill efficiency		
		foreach ($this->playerIndex as $key => $player)
		{
			$index = $player["index"];
			$team = $this->playerStats[$index]["team"];
			$sort_stats[$team][$key] = array();
			$sort_stats[$team][$key]["frageff"] = $this->playerStats[$index]["deaths"] == 0 ? $this->playerStats[$index]["frags"] : $this->playerStats[$index]["frags"] / $this->playerStats[$index]["deaths"];
			$sort_stats[$team][$key]["index"] = $index;
			$sort_stats[$team][$key]["playername"] = $player["playername"];
			$frageff[$team][$key] = $sort_stats[$team][$key]["frageff"];
			$this->playerStats[$index]["team"] = $team;
		}
		array_multisort($frageff["1"], SORT_DESC, $sort_stats["1"]);
		array_multisort($frageff["2"], SORT_DESC, $sort_stats["2"]);
		
		unset($frageff);
		
		$this::ProcessPrintStats("1", $sort_stats);
		$this::ProcessPrintStats("2", $sort_stats);
		
		unset($sort_stats);
	}
	
	// function to print all round stats
	function PrintAllRoundStats()
	{
		for($round=0; $round<$this->round_count; $round++)		// iterate over rounds
		{
			$this::PrintRoundStats($round);
		}
	}
	
	// print detailed round stats from osp
	function PrintRoundStats($round)
	{
		if($round == "total")
		{
			echo "<h2>Total</h2>\n";
			echo "<table id='statstable' class='sortable'>\n<thead>\n<tr>\n<th class='tablename'>Player</th>\n<th>Kill-Efficiency</th>\n<th>Frags</th>\n<th>Deaths</th>\n<th>Gibs</th>\n<th>Suicides</th>\n<th>Teamkills</th>\n<th>Damage-Efficiency</th>\n<th>DMG</th>\n<th>DMR</th>\n<th>Team-DMG</th>\n<th>DMG/Frag</th>\n</tr>\n</thead>\n<tbody>\n";
		}
		else
		{
			echo "<h2>Round " . ($round+1) . "</h2>\n";
			echo "<table id='statstable' class='sortable'>\n<thead>\n<tr>\n<th>Team</th>\n<th class='tablename'>Player</th>\n<th>Kill-Efficiency</th>\n<th>Frags</th>\n<th>Deaths</th>\n<th>Gibs</th>\n<th>Suicides</th>\n<th>Teamkills</th>\n<th>Damage-Efficiency</th>\n<th>DMG</th>\n<th>DMR</th>\n<th>Team-DMG</th>\n<th>DMG/Frag</th>\n</tr>\n</thead>\n<tbody>\n";
		}
	
		$temp_stats = array();
		foreach($this->playerStats as $index => $player)
		{
			// check if player participated in this round
			if(isset($player["osp"][$round]))
			{
				$team = $player["osp"][$round]["team"];
				$temp_stats[$team][$index] = $player["osp"][$round];
			}
		}
			
		// iterate over every team in this round
		foreach($temp_stats as $team => $useless)
		{
			unset($frageff);
			foreach ($temp_stats[$team] as $key => $row) {		// sort round stats by kill efficiency
				$frageff[$key] = $row["frageff"];
			}
			array_multisort($frageff, SORT_DESC, SORT_NUMERIC, $temp_stats[$team]);
			
			$totals = array("frags" => 0,		// reset total team stats
					"deaths" => 0,
					"suicides" => 0,
					"teamkills" => 0,
					"dmg" => 0,
					"gibs" => 0,
					"dmr" => 0,
					"teamdmg" => 0,
					"score" => 0,
					);
		
			$count = 0;
			
			foreach($temp_stats[$team] as $index => $value)
			{
				// sum up total stats for team
				$totals["frags"] += $value["frags"];
				$totals["deaths"] += $value["deaths"];
				$totals["suicides"] += $value["suicides"];
				$totals["teamkills"] += $value["teamkills"];
				$totals["dmg"] += $value["dmg"];
				$totals["dmr"] += $value["dmr"];
				$totals["gibs"] += $value["gibs"];
				$totals["teamdmg"] += $value["teamdmg"];
				if ($round != "total")
					$totals["score"] += $value["score"];
			
				$playername = $this->indexNames[$index];	// find playername
				echo "<tr class='tablerow'>\n";
				if ($round != "total")
					echo "<td>" . $team . "</td>\n";
				echo "<td class='tablename'>" . Helper::colorsToHtml($playername) . "</td>\n";
				echo "<td class='" . ($value["frageff"]>=1?"positive":"negative") . "'>" . number_format($value["frageff"], 2) . "</td>\n";
				echo "<td>" . $value["frags"] . "</td>\n";
				echo "<td>" . $value["deaths"] . "</td>\n";
				echo "<td>" . $value["gibs"] . "</td>\n";
				echo "<td>" . $value["suicides"] . "</td>\n";
				echo "<td>" . $value["teamkills"] . "</td>\n";
				echo "<td class='" . ($value["damageeff"]>=1?"positive":"negative") . "'>" . number_format($value["damageeff"], 2) . "</td>\n";
				echo "<td>" . $value["dmg"] . "</td>\n";
				echo "<td>" . $value["dmr"] . "</td>\n";
				echo "<td>" . $value["teamdmg"] . "</td>\n";
				echo "<td>" . number_format($value["damageperfrag"], 0) . "</td>\n";
				echo "</tr>\n";
				$count++;
			}
			
			// print total team stats
			$frageff = $totals["deaths"] == 0 ? $totals["frags"] : $totals["frags"] / $totals["deaths"];
			$damageeff = $totals["dmr"] == 0 ? $totals["dmg"] : $totals["dmg"] / $totals["dmr"];
			$damageperfrag = $totals["frags"] == 0 ? 0 : $totals["dmg"] / $totals["frags"];
			echo "</tbody>\n<tfoot>\n<tr class='tablerowtotal'>\n";
			if ($round != "total")
				echo "<td>" . $team . "</td>\n";
			echo "<td>TOTAL</td>\n";
			echo "<td class='" . ($frageff>=1?"positive":"negative") . "'>" . number_format($frageff, 2) . "</td>\n";
			echo "<td>" . $totals["frags"] . "</td>\n";
			echo "<td>" . $totals["deaths"] . "</td>\n";
			echo "<td>" . $totals["gibs"] . "</td>\n";
			echo "<td>" . $totals["suicides"] . "</td>\n";
			echo "<td>" . $totals["teamkills"] . "</td>\n";
			echo "<td class='" . ($damageeff>=1?"positive":"negative") . "'>" . number_format($damageeff, 2) . "</td>\n";
			echo "<td>" . $totals["dmg"] . "</td>\n";
			echo "<td>" . $totals["dmr"] . "</td>\n";
			echo "<td>" . $totals["teamdmg"] . "</td>\n";
			echo "<td>" . number_format($damageperfrag, 0) . "</td>\n";
		}
		echo "</tfoot>\n</table>\n";

		unset($totals);
		unset($temp_stats);
	
	}
	
	// function to sort the stats by specific value (e.g. killefficiency for terminator award)
	function AwardSort($column, $sortdir)
	{	
		// sort by team and kill efficiency		
		$sort_stats = array();
		foreach ($this->playerIndex as $key => $player)
		{
			$index = $player["index"];
			$value = $this->playerStats[$index][$column];
			$team = $this->playerStats[$index]["team"];
			if ($team != "")	// check for specs
			{
				$current = array(
							"index" => $index,
							"playername" => $player["playername"],
							"value" => $value,
							);
				array_push($sort_stats, $current);
				$sortcrit[$key] = $value;
			}
		}
		array_multisort($sortcrit, $sortdir, SORT_NUMERIC, $sort_stats);
		unset($sortcrit);

		return $sort_stats;
	}
	
	// function to sort the stats by specific value (e.g. killefficiency for terminator award)
	function AwardWeaponSort($weapon, $type)
	{
		// sort by team and kill efficiency		
		$sort_stats = array();
		foreach ($this->playerIndex as $key => $player)
		{
			$index = $player["index"];
			$team = $this->playerStats[$index]["team"];
			if (isset($this->weapons[$weapon][$type][$index]) && $team != "")
			{
				$value = $this->weapons[$weapon][$type][$index];

				$current = array(
							"index" => $index,
							"playername" => $player["playername"],
							"value" => $value,
							);
				array_push($sort_stats, $current);
				$sortcrit[$key] = $value;
			}
		}
		if (count($sort_stats) > 0)
			array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $sort_stats);
		else
		{
			$sort_stats[0]["value"] = 0;
		}
		unset($sortcrit);

		return $sort_stats;
	}
	
	// function to print every award name whoever is first
	function PrintAwardName($award)
	{
		echo Helper::colorsToHtml($award[0]["playername"]);
		$index_done[$award[0]["index"]] = true;
		for($i=1; $i<count($award); $i++)
		{
			$index = $award[$i]["index"];
			if ($award[0]["value"] != $award[$i]["value"])
				break;
			
			if(!isset($index_done[$index]))
			{
				$index_done[$index] = true;
				echo ", " . Helper::colorsToHtml($award[$i]["playername"]);
			}
		}
	}
	
	// function to print detailed award statistics
	function PrintAwardTable($award, $decimals)
	{
		echo "<div class='awarddetail'>";
		foreach($award as $player)
		{
			$index = $player["index"];
			if(!isset($index_done[$index]))
			{
				$index_done[$index] = true;
				if($player["value"] > 0)
					echo "<div class='awardname'>" . Helper::colorsToHtml($player["playername"]) . "</div><div class='awardvalue'>" . number_format($player["value"], $decimals) . "</div><br/>";
			}
		}
		echo "</div>\n<p/>\n";
	}
	
	// function to print the main awards
	function PrintMainAwards()
	{	
		echo "<h2>Main Awards</h2>\n";

		foreach(Helper::$awards_main as $award)
		{
			$sort = $this->AwardSort($award["column"], $award["sortdir"]);
			if ($sort[0]["value"] > 0)
			{
				echo "<div class='awardtitle' onclick='togglechild(this)'>" . $award["name"] . " Award: ";
				$this::PrintAwardName($sort);
				echo $award["before"] . number_format($sort[0]["value"], $award["decimals"]) . $award["after"] . "</div>\n";
				$this::PrintAwardTable($sort, $award["decimals"]);
			}
		}
		
		foreach(Helper::$awards_main_weapons as $award)
		{
			$sort = $this->AwardWeaponSort($award["column"], $award["type"], $award["sortdir"]);
			if ($sort[0]["value"] > 0)
			{
				echo "<div class='awardtitle' onclick='togglechild(this)'>" . $award["name"] . " Award: ";
				$this::PrintAwardName($sort);
				echo $award["before"] . number_format($sort[0]["value"], $award["decimals"]) . $award["after"] . "</div>\n";
				$this::PrintAwardTable($sort, $award["decimals"]);
			}
		}
		
		unset($sort);
	}
	
	// function to print the weapon awards
	function PrintWeaponAwards()
	{		
		echo "<h2>Weapon Awards</h2>\n";
		
		foreach(Helper::$awards_weapons as $award)
		{
			$sort = $this->AwardWeaponSort($award["column"], $award["type"], $award["sortdir"]);
			if ($sort[0]["value"] > 0)
			{
				echo "<div class='awardtitle' onclick='togglechild(this)'>" . $award["name"] . " Award: ";
				$this::PrintAwardName($sort);
				echo $award["before"] . number_format($sort[0]["value"], $award["decimals"]) . $award["after"] . "</div>\n";
				$this::PrintAwardTable($sort, $award["decimals"]);
			}
		}
		
		// pistol
		$award = $this->AwardWeaponSort("luger", "kills");
		$award2 = $this->AwardWeaponSort("colt", "kills");
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
	}
	
	// function to print weapon statistics
	function PrintWeapons()
	{
		echo "<br/>\n<table id='statstable' class='tablesorter'>\n<thead>\n<tr>\n<th class='tablename'>Weapon</th>\n<th>Frags</th>\n<th>%</th>\n</thead>\n<tbody>\n";
		
		foreach($this->weapons as $name => $value)	// sort array
		{
			$sortcrit[$name] = $value["count"];
		}
		array_multisort($sortcrit, SORT_DESC, SORT_NUMERIC, $this->weapons);
		
		$count = 0;
		foreach($this->weapons as $name => $stats)
		{
			if($stats > 0)
			{
				$value = $stats["count"];
				if ($value > 0)
				{
					echo "<tr class='tablerow'>\n<td>" . Helper::$weaponnames[$name] . "</td>\n";
					echo "<td>" . $value . "</td>\n";
					echo "<td>" . number_format((($this->frag_count != 0) ? $value/$this->frag_count*100 : 0), 2) . "</td>\n</tr>\n";
				}
				$count++;
			}
		}
		echo "<tr class='tablerowtotal'>\n<td>TOTAL</td>\n<td>" . $this->frag_count . "</td>\n<td>100.00</td>\n</tr>\n";
		echo "</tbody>\n</table>\n";
	}
	
	// function to print every player enemy name whoever is first
	function PrintPlayerName($index, &$stats)
	{
		$keys = array_keys($stats);
		$team = $this->playerStats[$index]["team"];
		for($i=0; $i<count($keys); $i++)
		{
			if($keys != $index && $team != $this->playerStats[$keys[$i]]["team"])
			{
				$first = $i;
				break;
			}
		}
		
		if (count($keys) > 0 && isset($first))
		{
			$count = $stats[$keys[$first]];

			if ($count > 0)
				{
				echo Helper::colorsToHtml($this->indexNames[$keys[$first]]);
				for($i=$first+1; $i<count($keys); $i++)
				{
					if ($count != $stats[$keys[$i]])
						break;
					echo ", " . Helper::colorsToHtml($this->indexNames[$keys[$i]]);
				}
				return $count;
			}
		}
		echo "none";
		return 0;
	}
	
	// function to print detailed player enemy statistics
	function PrintPlayerTable($index, &$stats)
	{
		echo "<div class='playerdetail'>";
		$team = $this->playerStats[$index]["team"];
		foreach($stats as $key => $value)
		{
			if($key != $index && $value > 0 && $team != $this->playerStats[$key]["team"])
				echo "<div class='playerdetailname'>" . Helper::colorsToHtml($this->indexNames[$key]) . "</div><div class='playerdetailvalue'>" . $value . "</div>\n<br/>\n";
		}
		echo "</div>\n";
	}
	
	function ProcessPrintPlayers($team, &$sort_stats)
	{
		// loop over every team player
		foreach ($sort_stats[$team] as $player)
		{
			$index = $player["index"];
			
			// sort out already done playerstats and empty playerstats
			if(!isset($index_done[$index]))
			{
				$index_done[$index] = true;
				
				echo "<div class='playerstat'>" . Helper::colorsToHtml($player["playername"]) . "</div>\n";
				echo "<div class='playerstatdetail'>\n";

				// Victims
				arsort($this->matrix[$index]);
				echo "<div class='playerenemy' onclick='togglechild(this)'>Favorite Enemy: ";
				$count = $this::PrintPlayerName($index, $this->matrix[$index]);
				echo " with " . $count . " kills.</div>\n";
				$this::PrintPlayerTable($index, $this->matrix[$index]);
				echo "<br/>";
				
				// Killers
				$killers = array();
				foreach($this->matrix as $key => $value)
				{
					$killers[$key] = $value[$index];
				}
				arsort($killers);
				echo "<div class='playerenemy' onclick='togglechild(this)'>Worst Enemy:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
				$count = $this::PrintPlayerName($index, $killers);
				echo " with " . $count . " deaths.</div>\n";
				$this::PrintPlayerTable($index, $killers);
				echo "</div><br/>\n";
			}
		}
	}
	
	// function to print player statistics
	function PrintPlayers()
	{
		$sort_stats = array("1" => array(), "2" => array());
	
		// sort by team and kill efficiency		
		foreach ($this->playerIndex as $key => $player)
		{
			$index = $player["index"];
			$team = $this->playerStats[$index]["team"];
			$sort_stats[$team][$key] = array();
			$sort_stats[$team][$key]["frageff"] = $this->playerStats[$index]["deaths"] == 0 ? $this->playerStats[$index]["frags"] : $this->playerStats[$index]["frags"] / $this->playerStats[$index]["deaths"];
			$sort_stats[$team][$key]["index"] = $index;
			$sort_stats[$team][$key]["playername"] = $player["playername"];
			$frageff[$team][$key] = $sort_stats[$team][$key]["frageff"];
		}
		array_multisort($frageff["1"], SORT_DESC, $sort_stats["1"]);
		array_multisort($frageff["2"], SORT_DESC, $sort_stats["2"]);
		unset($frageff);
		
		echo "<div class='playerstats'>\n";
		$this::ProcessPrintPlayers("1", $sort_stats);
		$this::ProcessPrintPlayers("2", $sort_stats);
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
	
	// function to print the kill matrix
	public function PrintMatrix()
	{
		$count = 1;
		$indexCount = array();
		$countIndex = array();
		
		// sort matrix
		$sort_matrix = array();
	
		// sort by team and kill efficiency		
		foreach ($this->matrix as $index => $entry)
		{
			$frageff[$index] = $this->playerStats[$index]["frageff"];
			$countIndex[$index] = $index;
		}
		array_multisort($frageff, SORT_DESC, $countIndex);
		
		// create table header
		echo "<table class='matrixtable'>\n";
		echo "<thead>\n<th class='tablename'>Player</th>\n";
		for($i=1; $i<=count($this->matrix); $i++)
		{
			echo "<th><div class='rotated_text'>" . Helper::colorsToHtml($this->indexNames[$countIndex[$i-1]]) . "</div></th>";
		}
		echo "</thead>\n<tbody>";
		
		// create entry for each player
		for($i=0; $i<count($countIndex); $i++)
		{
			echo "<tr class='tablerow'>\n";
			echo "<td class='tablename'>" . Helper::colorsToHtml($this->indexNames[$countIndex[$i]]) . "</td>\n";
			
			for($j=0; $j<count($this->matrix[$countIndex[$i]]); $j++)
			{
				$value = $this->matrix[$countIndex[$i]][$countIndex[$j]];
				if($i == $j)
					echo "<td class='matrixself'>" . $value . "</td>\n";
				else if($this->playerStats[$countIndex[$i]]["team"] == $this->playerStats[$countIndex[$j]]["team"])
					echo "<td class='matrixteam'>" . $value . "</td>\n";
				else
					echo "<td>" . $value . "</td>\n";
			}
			echo "</tr>\n";
		}
		echo "</tbody></table>";
	}
	
}
?>