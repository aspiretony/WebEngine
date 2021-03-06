<?php
/**
 * WebEngine
 * http://muengine.net/
 * 
 * @version 1.0.9
 * @author Lautaro Angelico <http://lautaroangelico.com/>
 * @copyright (c) 2013-2017 Lautaro Angelico, All Rights Reserved
 * 
 * Licensed under the MIT license
 * http://opensource.org/licenses/MIT
 */

class weProfiles {
	
	private $_request;
	private $_type;
	
	private $_reqMaxLen;
	private $_guildsCachePath;
	private $_playersCachePath;
	private $_cacheUpdateTime;
	
	private $_fileData;
	
	function __construct(dB $dB, common $common) {
		
		# database
		$this->common = $common;
		$this->dB = $dB;
		
		# settings
		$this->_guildsCachePath = __PATH_CACHE__ . 'profiles/guilds/';
		$this->_playersCachePath = __PATH_CACHE__ . 'profiles/players/';
		$this->_cacheUpdateTime = 3600;
		
		# check cache directories
		$this->checkCacheDir($this->_guildsCachePath);
		$this->checkCacheDir($this->_playersCachePath);
		
	}
	
	public function setType($input) {
		switch($input) {
			case "guild":
				$this->_type = "guild";
				$this->_reqMaxLen = 8;
				break;
			default:
				$this->_type = "player";
				$this->_reqMaxLen = 10;
		}
	}
	
	public function setRequest($input) {
		if(!Validator::AlphaNumeric($input)) throw new Exception(lang('error_25',true));
		if(strlen($input) > $this->_reqMaxLen) throw new Exception(lang('error_25',true));
		if(strlen($input) < 4) throw new Exception(lang('error_25',true));
		$this->_request = $input;
	}
	
	private function checkCacheDir($path) {
		if(check_value($path)) {
			if(!file_exists($path) || !is_dir($path)) {
				if(config('error_reporting',true)) {
					throw new Exception("Invalid cache directory ($path)");
				} else {
					throw new Exception(lang('error_21',true));
				}
			} else {
				if(!is_writable($path)) {
					if(config('error_reporting',true)) {
						throw new Exception("The cache directory is not writable ($path)");
					} else {
						throw new Exception(lang('error_21',true));
					}
				}
			}
		}
	}
	
	private function checkCache() {
		switch($this->_type) {
			case "guild":
				$reqFile = $this->_guildsCachePath . strtolower($this->_request) . '.cache';
				if(!file_exists($reqFile)) {
					$this->cacheGuildData();
				}
				$fileData = file_get_contents($reqFile);
				$fileData = explode("|", $fileData);
				if(is_array($fileData)) {
					if(time() > ($fileData[0]+$this->_cacheUpdateTime)) {
						$this->cacheGuildData();
					}
				} else {
					throw new Exception(lang('error_21',true));
				}
				$this->_fileData = file_get_contents($reqFile);
				break;
			default:
				$reqFile = $this->_playersCachePath . strtolower($this->_request) . '.cache';
				if(!file_exists($reqFile)) {
					$this->cachePlayerData();
				}
				$fileData = file_get_contents($reqFile);
				$fileData = explode("|", $fileData);
				if(is_array($fileData)) {
					if(time() > ($fileData[0]+$this->_cacheUpdateTime)) {
						$this->cachePlayerData();
					}
				} else {
					throw new Exception(lang('error_21',true));
				}
				$this->_fileData = file_get_contents($reqFile);
		}
	}
	
	private function cacheGuildData() {
		// General Data
		$guildData = $this->dB->query_fetch_single("SELECT * FROM "._TBL_GUILD_." WHERE "._CLMN_GUILD_NAME_." = ?", array($this->_request));
		if(!$guildData) throw new Exception(lang('error_25',true));
		
		// Members
		$guildMembers = $this->dB->query_fetch("SELECT * FROM "._TBL_GUILDMEMB_." WHERE "._CLMN_GUILDMEMB_NAME_." = ?", array($this->_request));
		if(!$guildMembers) throw new Exception(lang('error_25',true));
		$members = array();
		foreach($guildMembers as $gmember) {
			$members[] = $gmember[_CLMN_GUILDMEMB_CHAR_];
		}
		$gmembers_str = implode(",", $members);
		
		// Cache
		$data = array(
			time(),
			$guildData[_CLMN_GUILD_NAME_],
			$guildData[_CLMN_GUILD_LOGO_],
			$guildData[_CLMN_GUILD_SCORE_],
			$guildData[_CLMN_GUILD_MASTER_],
			$gmembers_str
		);
		
		// Cache Ready Data
		$cacheData = implode("|", $data);
		
		// Update Cache File
		$reqFile = $this->_guildsCachePath . strtolower($this->_request) . '.cache';
		$fp = fopen($reqFile, 'w+');
		fwrite($fp, $cacheData);
		fclose($fp);
	}
	
	private function cachePlayerData() {
		// general player data
		$playerData = $this->dB->query_fetch_single("SELECT * FROM "._TBL_CHR_." WHERE "._CLMN_CHR_NAME_." = ?", array($this->_request));
		if(!$playerData) throw new Exception(lang('error_25',true));
		
		// guild data
		$guild = "";
		$guildData = $this->dB->query_fetch_single("SELECT * FROM "._TBL_GUILDMEMB_." WHERE "._CLMN_GUILDMEMB_CHAR_." = ?", array($this->_request));
		if($guildData) $guild = $guildData[_CLMN_GUILDMEMB_NAME_];
		
		// online status
		$status = 0;
		if($this->common->accountOnline($playerData[_CLMN_CHR_ACCID_])) {
			$status = 1;
		}
		
		// Cache
		$data = array(
			time(),
			$playerData[_CLMN_CHR_NAME_],
			$playerData[_CLMN_CHR_CLASS_],
			$playerData[_CLMN_CHR_LVL_],
			$playerData[_CLMN_CHR_RSTS_],
			$playerData[_CLMN_CHR_STAT_STR_],
			$playerData[_CLMN_CHR_STAT_AGI_],
			$playerData[_CLMN_CHR_STAT_VIT_],
			$playerData[_CLMN_CHR_STAT_ENE_],
			$playerData[_CLMN_CHR_STAT_CMD_],
			$playerData[_CLMN_CHR_PK_KILLS_],
			$playerData[_CLMN_CHR_GRSTS_],
			$guild,
			$status
		);
		
		// Cache Ready Data
		$cacheData = implode("|", $data);
		
		// Update Cache File
		$reqFile = $this->_playersCachePath . strtolower($this->_request) . '.cache';
		$fp = fopen($reqFile, 'w+');
		fwrite($fp, $cacheData);
		fclose($fp);
	}
	
	public function data() {
		if(!check_value($this->_type)) throw new Exception(lang('error_21',true));
		if(!check_value($this->_request)) throw new Exception(lang('error_21',true));
		$this->checkCache();
		return(explode("|", $this->_fileData));
	}
	
}