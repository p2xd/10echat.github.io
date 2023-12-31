<?php
/**
 * ----------------------------------------------
 * Simple Animated Counter PHP 1.1
 * Copyright (c)2001 Chi Kien Uong
 * URL: http://www.proxy2.de
 * ----------------------------------------------
 */

class acounter {

	var $config = array();

	function acounter () {

		/* URL to the digitset */
		$this->config['img'] = "./acount/digits/";

		/* URL to the animated digitset */
		$this->config['animated_img'] = "./acount/digits_ani/";

		/* How many digits to show */
		$this->config['pad'] = 4;

		/* digit width and height */
		$this->config['width'] = 9;
		$this->config['height'] = 13;

		/* ip blocking (true/false) */
		$this->config['block_ip'] = true;

		/* path to ip logfiles */
		$this->config['logfile'] = "./acount/pages/ip.txt";

		/* timeout (minutes) */
		$this->config['block_time'] = 120;
	}

	function is_new_visitor() {
		$is_new = true;
		$rows = @file($this->config['logfile']);
		$this_time = time();
		$ip = getenv("REMOTE_ADDR");
		$reload_dat = fopen($this->config['logfile'],"wb");
		flock($reload_dat, 2);
		for ($i=0; $i<sizeof($rows); $i++) {
			list($time_stamp,$ip_addr) = split("\|",$rows[$i]);
			if ($this_time < ($time_stamp+$this->config['block_time'])) {
				if (chop($ip_addr) == $ip) {
					$is_new = false;
				} else {
					fwrite($reload_dat,"$time_stamp|$ip_addr");
				}
			}
		}
		fwrite($reload_dat,"$this_time|$ip\n");
		flock($reload_dat, 3);
		fclose($reload_dat);
		return $is_new;
	}

	function read_counter_file($page) {
		$update = false;
		$counter_ignore_agents = array('aspseek', 'abachobot', 'accoona', 'acoirobot', 'adsbot', 'alexa', 'alta vista', 'altavista', 'ask jeeves', 'baidu', 'croccrawler', 'dumbot', 'estyle', 'exabot', 'fast-enterprise', 'fast-webcrawler', 'francis', 'geonabot', 'gigabot', 'google', 'heise', 'heritrix', 'ibm', 'iccrawler', 'idbot', 'ichiro', 'lycos', 'msn', 'msrbot', 'majestic-12', 'metager', 'ng-search', 'nutch', 'omniexplorer', 'psbot', 'rambler', 'seosearch', 'scooter', 'scrubby', 'seekport', 'sensis', 'seoma', 'snappy', 'steeler', 'synoo', 'telekom', 'turnitinbot', 'voyager', 'wisenut', 'yacy', 'yahoo', 'bot', 'b0t', 'search', 'engine', 'seek', 'spider', 'crawl', 'worm', 'ABCdatos', 'Acme', 'Ahoy!', 'Alkaline', 'Anthill', 'Walhello', 'Arachnophilia', 'Arale', 'Araneo', 'AraybOt', 'Architext', 'Aretha', 'ARIADNE', 'arks', 'AskJeeves', 'ATN Worldwide', 'Search', 'AURESYS', 'BackRub', 'Bay', 'Big Brother', 'Bjaaland', 'BlackWidow', 'Die Blinde Kuh', 'Bloodhound', 'Borg-Bot', 'BoxSeaBot', 'bright.net caching', 'BSpider', 'CACTVS Chemistry', 'Calif', 'Cassandra', 'Digimarc Marcspider', 'Checkbot', 'ChristCrawler.com', 'churl', 'cIeNcIaFiCcIoN.nEt', 'CMC', 'Collective', 'Combine System', 'Conceptbot', 'ConfuzzledBot', 'CoolBot', 'Web Core', 'XYLEME', 'Cruiser', 'Cusco', 'CyberSpyder', 'CydralSpider', 'Desert Realm', 'DeWeb', 'DienstSpider', 'Digger', 'Digital Integrity', 'Direct Hit Grabber', 'DNAbot', 'DownLoad Express', 'DragonBot', 'DWCP', 'e-collector', 'EbiNess', 'EIT Link Verifier', 'ELFINBOT', 'Emacs-w3', 'ananzi', 'esculapio', 'Esther', 'Evliya Celebi', 'FastCrawler', 'Fluid Dynamics', 'Felix IDE', 'Wild Ferret Web Hopper', 'FetchRover', 'fido', 'KIT-Fireball', 'Fish', 'Fouineur', 'Francoroute', 'Freecrawl', 'FunnelWeb', 'gammaSpider', 'FocusedCrawler', 'gazz', 'GCreep', 'GetBot', 'GetURL', 'Golem', 'Google', 'Grapnel', 'Experiment', 'Griffon', 'Gromit', 'Northern Light Gulliver', 'Gulper Bot', 'HamBot', 'Harvest', 'havIndex', 'HI (HTML Index)', 'Hometown Pro', 'ht://Dig', 'HTMLgobble', 'Hyper-Decontextualizer', 'iajaBot', 'IBM_Planetwide', 'Popular Iconoclast', 'Ingrid', 'Imagelock', 'IncyWincy', 'Informant', 'InfoSeek', 'InfoSpiders', 'Inspector Web', 'IntelliAgent', 'Iron33', 'Israeli', 'JavaBee', 'JBot Java Web', 'JCrawler', 'Jeeves', 'JoBo Java Web', 'Jobot', 'JoeBot', 'Jubii Indexing', 'JumpStation', 'image.kapsi.net', 'Katipo', 'KDD-Explorer', 'Kilroy', 'KO_Yappo', 'LabelGrabber', 'larbin', 'legs', 'Link Validator', 'LinkScan', 'LinkWalker', 'Lockon', 'logo.gif', 'Lycos', 'Mac WWWWorm', 'Magpie', 'marvin/infoseek', 'Mattie', 'MediaFox', 'MerzScope', 'NEC-MeshExplorer', 'MindCrawler', 'mnoGo software', 'moget', 'MOMspider', 'Monster', 'Motor', 'MSNBot', 'Muncher', 'Muninn', 'Muscat Ferret', 'Mwd', 'Internet Shinchakubin', 'NDSpider', 'Nederland.zoek', 'NetCarta WebMap', 'NetMechanic', 'NetScoop', 'newscan-online', 'NHSE Web Forager', 'Nomad', 'NorthStar', 'nzexplorer', 'Objects', 'Occam', 'HKU WWW Octopus', 'OntoSpider', 'Openfind', 'data gatherer', 'Orb', 'Pack Rat', 'PageBoy', 'ParaSite', 'Patric', 'pegasus', 'Peregrinator', 'PerlCrawler', 'Phantom', 'PhpDig', 'PiltdownMan', 'Pimptrain.com', 'Pioneer', 'html_analyzer', 'Portal Juice', 'PGP Key Agent', 'PlumtreeWebAccessor', 'Poppi', 'PortalB', 'psbot', 'GetterroboPlus', 'Puu', 'Python', 'Raven', 'RBSE', 'Resume', 'RoadHouse', 'Crawling System', 'RixBot', 'Road Runner', 'ImageScape', 'Robbie', 'ComputingSite Robi', 'RoboCrawl', 'RoboFox', 'Robozilla', 'Roverbot', 'RuLeS', 'SafetyNet', 'Scooter', 'Sleek', 'Aus-AU.COM', 'SearchProcess', 'Senrigan', 'SG-Scout', 'ShagSeeker', 'Shai', 'Hulud', 'Sift', 'Simmany', 'Site Valet', 'Open Text Index', 'SiteTech-Rover', 'Skymob.com', 'SLCrawler', 'Inktomi Slurp', 'Smart', 'Snooper', 'Solbot', 'Spanner', 'Speedy', 'spider_monkey', 'SpiderBot', 'Spiderline Crawler', 'SpiderMan', 'SpiderView', 'Spry Wizard', 'Siteer', 'Suke', 'suntek', 'Sven', 'Sygol', 'TACH Black Widow', 'Tarantula', 'tarspider', 'Tcl W3', 'TechBOT', 'Templeton', 'Teoma', 'Technologies', 'TITAN', 'TitIn', 'TkWWW', 'TLSpider', 'UCSD Crawl', 'UdmSearch', 'UptimeBot', 'URL Check', 'URL Pro', 'Valkyrie', 'Verticrawl', 'Victoria', 'vision-search', 'void-bot', 'Voyager', 'VWbot', 'NWI', 'W3M2', 'WallPaper', 'World Wide Web Wanderer', 'w@pSpider', 'wap4.com', 'WebBandit Web', 'WebCatcher', 'WebCopy', 'webfetcher', 'Webfoot', 'Webinator', 'weblayers', 'WebLinker', 'WebMirror', 'Web Moose', 'WebQuest', 'Digimarc', 'MarcSpider', 'WebReaper', 'webs', 'Websnarf', 'WebSpider', 'WebVac', 'webwalk', 'WebWalker', 'WebWatch', 'Wget', 'whatUseek Winona', 'WhoWhere', 'Wired Digital', 'Weblog Monitor', 'w3mir', 'WebStolperer', 'Web Wombat', 'World Wide Web', 'WWWC', 'WebZinger', 'XGET');
		if (!file_exists("./acount/pages/$page.txt")) {
			$count_dat = fopen("./acount/pages/$page.txt","w+");
			$this->counter = 1;
			fwrite($count_dat,$this->counter);
			fclose($count_dat);
		} else {
			$fp = fopen("./acount/pages/$page.txt", "r+");
			flock($fp, 2);
			$this->counter = fgets($fp, 4096);
			flock($fp, 3);
			fclose($fp);
			if ($this->config['block_ip']) {
				if ($this->is_new_visitor()) {
					$this->counter++;
					$update = true;
				}
			}
			else{
				$this->counter++;
				$update = true;
			}
			// get basic information
			$counter_agent = $_SERVER['HTTP_USER_AGENT'];
			$length = sizeof($counter_ignore_agents);
			for ($i = 0; $i < $length; $i++){
				if (substr_count(strtolower($counter_agent), strtolower($counter_ignore_agents[$i]))){
				$update = false;
				break;
				}
			}
			if ($update) {
				$fp = fopen("./acount/pages/$page.txt", "r+");
				flock($fp, 2);
				rewind($fp);
				fwrite($fp, $this->counter);
				flock($fp, 3);
				fclose($fp);
			}
		}
		return $this->counter;
	}

	function utf_conv($iso,$Charset,$what)
	{
		if(function_exists('iconv')) $what = iconv($iso, $Charset, $what);
		return $what;
	}

	function create_output($page='') {
		if (empty($page)) {
			$page = "counter";
		}
		$Charset = "utf-8";
		$visits = $this->read_counter_file($page) + 1;
/*		$this->counter = sprintf("%0"."".$this->config['pad'].""."d",$this->counter);
		$ani_digits = sprintf("%0"."".$this->config['pad'].""."d",$this->counter+1);
*/
		$ani_digits = $this->counter+1;
		$html_output = "<BDO dir=ltr><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr align=\"center\">\n";
		$INSTALL_DATE = strftime(L_SHORT_DATE,strtotime(C_INSTALL_DATE));
		if(stristr(PHP_OS,'win'))
		{
			$INSTALL_DATE = utf_conv(WIN_DEFAULT,$Charset,$INSTALL_DATE);
			if(strstr($L,"chinese") || strstr($L,"korean") || strstr($L,"japanese")) $INSTALL_DATE = str_replace(" ","",$INSTALL_DATE);
		}
		$visitors = sprintf(L_VISITOR_REPORT,$INSTALL_DATE);
		for ($i=0; $i<strlen($this->counter); $i++) {
			if (substr("$this->counter",$i,1) == substr("$ani_digits",$i,1)) {
				$digit_pos = substr("$this->counter",$i,1);
				$html_output .= "<td><img src=\"".$this->config['img']."$digit_pos.gif\" alt=\"".$visits." ".$visitors."\" title=\"".$visits." ".$visitors."\"";
			} else {
				$digit_pos = substr("$ani_digits",$i,1);
				$html_output .= "<td><img src=\"".$this->config['animated_img']."$digit_pos.gif\" alt=\"".$visits." ".$visitors."\" title=\"".$visits." ".$visitors."\"";
			}
			$html_output .= " width=\"".$this->config['width']."\" height=\"".$this->config['height']."\"></td>\n";
		}
		$html_output .= "</tr></table></BDO>\n";
		return $html_output;
	}

}


// This script exports all the IPs that hit this chat server into a file called /logs/chat_ip_logs.txt
function getip() {
 if(isset($_SERVER)) {
	if(isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
	$realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}elseif(isset($_SERVER["HTTP_CLIENT_IP"])) {
	 $realip = $_SERVER["HTTP_CLIENT_IP"];
	}else{
	 $realip = $_SERVER["REMOTE_ADDR"];
	}
 }else{
 if(getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
	$realip = getenv( 'HTTP_X_FORWARDED_FOR' );
 }elseif (getenv( 'HTTP_CLIENT_IP' ) ) {
	$realip = getenv( 'HTTP_CLIENT_IP' );
 }else {
	$realip = getenv( 'REMOTE_ADDR' );
 }
}
	if (strstr($realip, ',')) {
		$ips = explode(',', $realip);
		$realip = $ips[0];
	}
return $realip;
}

$logIP = "&nbsp";
$logIP = getip();
#if (!eregi("0.0.0.0", $logIP))
if (strpos($logIP, "0.0.0.0") === false) //Replace this IP with yours (entire - if it's a static IP, or partial - if it's a dinamic IP)
{
	$logURI = "&nbsp";
	$logHOST = "&nbsp";
	$logPROXY = ($_SERVER['REMOTE_ADDR'] != $logIP) ? $_SERVER['REMOTE_ADDR'] : "&nbsp";
	$logURI = $_SERVER['REQUEST_URI'];
	$logREF = ($_SERVER['HTTP_REFERER'] != '') ? $_SERVER['HTTP_REFERER'] : "&nbsp";
	$logDATE = date("D, d-m-y, H:i:s");
	$logHOST = gethostbyaddr($logIP);
	$invoegen = "<tr valign=top><td nowrap=\"nowrap\">".$logDATE."</td><td>".$logIP."</td><td>".$logHOST."</td><td>".$logURI."</td><td>".$logPROXY."</td><td>".$logREF."</td></tr>\n";
if (!file_exists("./acount/pages/chat_ip_logs.htm"))
{
	copy("./config/index/chat_ip_logs.htm","./acount/pages/chat_ip_logs.htm");
	chmod("./acount/pages/chat_ip_logs.htm", 0666);
}
	$fopen = fopen("./acount/pages/chat_ip_logs.htm", "a");
	fwrite($fopen, $invoegen);
	fclose($fopen);
}
?>