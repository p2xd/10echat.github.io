<?php
// Get the names and values for vars sent by index.lib.php
if (isset($_GET))
{
	while(list($name,$value) = each($_GET))
	{
		$$name = $value;
	};
};

// Get the names and values for post vars
if (isset($_POST))
{
	while(list($name,$value) = each($_POST))
	{
		$$name = $value;
	};
};

// Fix a security hole
if (isset($L) && !is_dir("./localization/".$L)) exit();
#if (ereg("SELECT|UNION|INSERT|UPDATE",$_SERVER["QUERY_STRING"])) exit();  //added by Bob Dickow for extra security NB Kludge
if (preg_match("/SELECT|UNION|INSERT|UPDATE/i",$_SERVER["QUERY_STRING"])) exit();  //added by Bob Dickow for extra security NB Kludge

// Fix some security issues
if ((empty($From) || trim($From) == '')
	|| (empty($U) || trim($U) == '')
	|| (empty($R) || trim($R) == '')
	|| (empty($Ver) || empty($L) || empty($N))
	|| (!isset($T) || !isset($D) || !isset($O) || !isset($ST) || !isset($NT))
	|| !is_dir("./localization/".$L))
{
	exit();
}

// Added for Skin mod
if (isset($_COOKIE["CookieRoom"])) $R = urldecode($_COOKIE["CookieRoom"]);

require("./config/config.lib.php");
require("./lib/release.lib.php");
require("./localization/".$L."/localized.chat.php");
require("./lib/database/".C_DB_TYPE.".lib.php");
require("./lib/clean.lib.php");

header("Content-Type: text/html; charset=${Charset}");

// avoid server configuration for magic quotes
if (function_exists('set_magic_quotes_runtime') && version_compare(PHP_VERSION, '5.3.0') < 0) set_magic_quotes_runtime(0);
else ini_set("magic_quotes_runtime", 0);
// Can't turn off magic quotes gpc so just redo what it did if it is on.
if (get_magic_quotes_gpc()) {
	foreach($_GET as $k=>$v)
		$_GET[$k] = stripslashes($v);
	foreach($_POST as $k=>$v)
		$_POST[$k] = stripslashes($v);
	foreach($_COOKIE as $k=>$v)
		$_COOKIE[$k] = stripslashes($v);
}

$U = urldecode($U);
$R = urldecode($R);

// Translate to html special characters, and entities if message was sent with a latin 1 charset
$Latin1 = ($Charset != "utf-8" ? 1 : 0);
function special_char($str,$lang)
{
	return addslashes($lang ? htmlentities(stripslashes($str)) : htmlspecialchars(stripslashes($str)));
};

// Added for php4 support of mb functions
if (!function_exists('mb_convert_case'))
{
	function mb_convert_case($str,$type,$Charset)
	{
/*
		if (eregi("TITLE",$type)) $str = ucwords($str);
		elseif (eregi("LOWER",$type)) $str = strtolower($str);
		elseif (eregi("UPPER",$type)) $str = strtoupper($str);
*/
		if (stripos($type,"TITLE") !== false) $str = ucwords($str);
		elseif (stripos($type,"LOWER") !== false) $str = strtolower($str);
		elseif (stripos($type,"UPPER") !== false) $str = strtoupper($str);
		return $str;
	}
};

// Ghost Control mod by Ciprian
function ghosts_in($what, $in, $Charset)
{
	$ghosts = explode(",",$in);
	for (reset($ghosts); $ghost_name=current($ghosts); next($ghosts))
	{
		if (strcasecmp(mb_convert_case($what,MB_CASE_LOWER,$Charset), mb_convert_case($ghost_name,MB_CASE_LOWER,$Charset)) == 0) return true;
	}
	return false;
}

/**
 * Check Internet Connection.
 * 
 * @param string $sCheckHost Default: www.google.com
 * @return boolean
 */
function check_internet_connection($sCheckHost = 'www.google.com') 
{
    return (bool) @fsockopen($sCheckHost, 80, $iErrno, $sErrStr, 2);
}

$DbLink = new DB;

// ** Updates user info in connected users tables and fix some security issues **
// Fixed a security issue thanks to SeazoN
if (C_REQUIRE_REGISTER && (!isset($PWD_Hash) || $PWD_Hash == ''))
{
	exit(); // hack attack
}
else if (isset($PWD_Hash) && $PWD_Hash != '')
{
	$DbLink->query(	'SELECT ' . C_USR_TBL . '.room, ' . C_USR_TBL . '.status, ' . C_USR_TBL. '.ip'
					. ' FROM ' . C_USR_TBL . ', ' . C_REG_TBL
					. ' WHERE ' . C_USR_TBL . '.username = \'' . $U . '\''
					.   ' AND ' . C_REG_TBL . '.username = \'' . $U . '\''
					.   ' AND ' . C_REG_TBL . '.password = \'' . $PWD_Hash . '\''
					. ' LIMIT 1');
}
else // C_REQUIRE_REGISTER == 0 && $PWD_Hash is empty
{
	$DbLink->query('SELECT username FROM ' . C_REG_TBL . ' WHERE username = \'' . $U . '\' LIMIT 1');
	if ($DbLink->num_rows() == 0)
	{
		$DbLink->query('SELECT room, status, ip FROM ' . C_USR_TBL . ' WHERE username = \'' . $U . '\' LIMIT 1');
	}
	else
	{
		$DbLink->clean_results();
		$DbLink->close();
		exit(); // hack attack
	}
}
// End of SeazoN Fix
if ($DbLink->num_rows() != 0)
{
	list($room, $status, $knownIp) = $DbLink->next_record();
	$DbLink->clean_results();
	$kicked = 0;
	// Security issue
	require("./lib/get_IP.lib.php");
	if ($knownIp != $IP)
	{
		$kicked = 5;
	}
	// Update users info
	if ($room != stripslashes($R))	// Same nick in another room
	{
		$DbLink->query("INSERT INTO ".C_MSG_TBL." VALUES ($T, '$R', 'SYS exit', '$Latin1', ".time().", '', 'sprintf(L_EXIT_ROM, \"".special_char($U,$Latin1)."\")', '', '')");
		$kicked = 3;
	}
	elseif ($status == "k")			// Kicked by a moderator or the admin.
	{
		$kicked = 1;
	}
	elseif ($status == "d")			// The admin just deleted the room
	{
		$kicked = 2;
	}
	elseif ($status == "b")			// Banished by a moderator or the admin.
	{
		$DbLink->query("INSERT INTO ".C_MSG_TBL." VALUES ($T, '$R', 'SYS exit', '$Latin1', ".time().", '', 'sprintf(L_BANISHED, \"".special_char($U,$Latin1)."\")', '', '')");
		$kicked = 4;
	};
	if ($kicked > 0)
	{
		// Kick the user from the current room
		$kickedUrl	= ($kicked < 5)
					? "$From?L=$L&U=".urlencode(stripslashes($U))."&E=".urlencode(stripslashes($R))."&KK=$kicked"
					: "$From?L=$L";
		?>
		<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
		<!--
		window.parent.window.location = '<?php echo($kickedUrl); ?>';
		// -->
		</SCRIPT>
		<?php
		$DbLink->close();
		exit;
	}
}
else
{
	$DbLink->clean_results();
	// Fix a security issue
	?>
	<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
	<!--
	window.parent.window.location = '<?php echo("$From?L=$L"); ?>';
	// -->
	</SCRIPT>
	<?php
	$DbLink->close();
	exit;
};

if (C_BOT_CONTROL) include("./bot/respond.php");

// Extended two fields for Private Message Popup and room_from by Ciprian
// ** Send formated messages to the message table **
function AddMessage($M, $T, $R, $U, $C, $Private, $Read, $RF, $Charset)
{
	global $DbLink, $Latin1, $status, $Read, $M1, $COLOR_TB;
#	if (C_BOT_CONTROL && C_BOT_PUBLIC && $Private == "")
	if (C_BOT_CONTROL && C_BOT_PUBLIC && $Private == "" && !(preg_match("#^\/#", $M) || preg_match("#^:#", $M)))
	{
		//--Bot Control Popeye
#		include("./bot/respond.php";
		$botpath = "botfb/" . $U . ".txt" ;
		$botcontrol ="botfb/" . $R . ".txt";
		if(file_exists($botcontrol))
		{
	# 		if (file_exists ($botpath) || eregi(mb_convert_case(C_BOT_NAME,MB_CASE_LOWER,$Charset), mb_convert_case($M,MB_CASE_LOWER,$Charset)))
			if (file_exists ($botpath) || stripos(mb_convert_case($M,MB_CASE_LOWER,$Charset), mb_convert_case(C_BOT_NAME,MB_CASE_LOWER,$Charset)) !== false)
			{
				include("./lib/bot.lib.php");
			}
		}
	}
	//---End Bot Control

	if (!isset($M1)) $M1 = $M;
	$M = str_replace("\"", "&quot;", $M);
	$M = str_replace("'", "&#39;", $M);
	$M = str_ireplace("<applet", "", $M);
	$M = str_ireplace("<javascript", "", $M);
	// Text formating tags
	if(C_HTML_TAGS_KEEP == "none")
	{
		if(!C_HTML_TAGS_SHOW)
		{
			// eliminates every HTML like tags
			$M = str_replace("<[^>]+>", "", $M);
			$M = str_replace("x3c", "", $M);
			$M = str_replace("x3e", "", $M);
		}
		else
		{
			// or keep it without effect
			$M = str_replace("<", "&lt;", $M);
			$M = str_replace(">", "&gt;", $M);
			$M = str_replace("x3c", "&lt;", $M);
			$M = str_replace("x3e", "&gt;", $M);
		}
	}
	else
	{
		// then C_HTML_TAGS_KEEP == "simple", we keep U, B and I tags
		$M = str_replace("<", "&lt;", $M);
		$M = str_replace(">", "&gt;", $M);
		$M = str_replace("x3c", "&lt;", $M);
		$M = str_replace("x3e", "&gt;", $M);

		if(function_exists("preg_match"))
		{
			while(preg_match("/&lt;([ubi]?)&gt;(.*?)&lt;(\/\\1)&gt;/i",$M))
			{
				$M = preg_replace("/&lt;([ubi]?)&gt;(.*?)&lt;(\/\\1)&gt;/i","<\\1>\\2<\\3>",$M);
			}
			if(!C_HTML_TAGS_SHOW)
			{
				$M = preg_replace("/&lt;\/?[ubi]?&gt;/i","",$M);
			}
		}
	}

	// Text Direction for dir commands by Ciprian
/*
	if(ereg('^bdo_rtl',$M)) $M = str_replace('bdo_rtl', '<BDO dir="rtl">', $M)."</BDO>";
	elseif(ereg('^bdo_ltr',$M)) $M = str_replace('bdo_ltr', '<BDO dir="ltr">', $M)."</BDO>";
*/
	if(preg_match('/^bdo_rtl/',$M)) $M = str_replace('bdo_rtl', '<BDO dir="rtl">', $M)."</BDO>";
	elseif(preg_match('/^bdo_ltr/',$M)) $M = str_replace('bdo_ltr', '<BDO dir="ltr">', $M)."</BDO>";

	// URL
/*
	$M = eregi_replace('([[:space:]]|^)(www[.])', '\\1http://\\2', $M); // no prefix (www.myurl.ext)
	$M = eregi_replace('([[:space:]]|^)(ftp[.])', '\\1ftp://\\2', $M); // no prefix (ftp.myurl.ext)
*/
	$M = preg_replace('/([[:space:]]|^)(www[.])/i', '\\1http://\\2', $M); // no prefix (www.myurl.ext)
	$M = preg_replace('/([[:space:]]|^)(ftp[.])/i', '\\1ftp://\\2', $M); // no prefix (ftp.myurl.ext)
	// Word wrap fix by Alexander Eisele <xaex@xaex.de> - deprecated by Ciprian due to japanese (2-bytes undesired trimming)

	if (!preg_match_all("((http://|https://|ftp://|mailto:)[^ ]+)", $M, $pmatch))
	{
		$M = wordwrap($M, 40, " ", 1);
	}
/*	$prefix = '(http|https|ftp|telnet|news|gopher|file|wais)://';
	$pureUrl = '([[:alnum:]/\n+-=%&:_.~?]+[#[:alnum:]+-_~]*)';
*/
	$prefix = '(http|https|ftp|telnet|news|gopher|file|wais):\/\/';
	$pureUrl = '([[:alnum:]\/\n+-=%&:_.~?]+[#[:alnum:]+-_~]*)';
	if (C_POPUP_LINKS)
	{
	    $purl="";
	    for ($x=0; $x<count($pmatch[0]); $x++)
	    {
			$purl .= "||".$pmatch[0][$x];
	    }
#		$M = eregi_replace($prefix.$pureUrl, '<a href="links.php?link='.urlencode($purl).'" target="_blank"></a>', $M);
		$M = preg_replace("/".$prefix.$pureUrl."/i", '<a href="links.php?link='.urlencode($purl).'" target="_blank"></a>', $M);
	}
#	else $M = eregi_replace($prefix.$pureUrl, '<a href="\\1://\\2" target="_blank">\\1://\\2</a>', $M);
	else $M = preg_replace("/".$prefix.$pureUrl."/i", '<a href="\\1://\\2" target="_blank">\\1://\\2</a>', $M);

	// e-mail addresses
//	$M = eregi_replace('([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-wyz][a-z](fo|g|l|m|mes|o|op|pa|ro|seum|t|u|v|z)?)', '<a href="mailto:\\1" alt="Send email">\\1</a>', $M);
	// Added the new top-level domains (mail, asia, travel, aso)
#	$M = eregi_replace('([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-wyz][a-z](avel|bi|bs|fo|g|ia|l|m|me|mes|o|op|pa|ro|seum|t|to|u|v|z)?)', '<a href="mailto:\\1" alt="Send email">\\1</a>', $M);
	$M = preg_replace('/([0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-.]?[0-9a-z])*\\.[a-wyz][a-z](avel|bi|bs|fo|g|ia|l|m|me|mes|o|op|pa|ro|seum|t|to|u|v|z)?)/i', '<a href="mailto:\\1" alt="Send email">\\1</a>', $M);
	if(C_EN_STATS)
	{
#		if(eregi('<a href="mailto',$M)) $DbLink->query("UPDATE ".C_STS_TBL." SET emails_posted=emails_posted+1 WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
		if(stripos($M,'<a href="mailto') !== false) $DbLink->query("UPDATE ".C_STS_TBL." SET emails_posted=emails_posted+1 WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
#		if(eregi('<a href="http',$M)) $DbLink->query("UPDATE ".C_STS_TBL." SET urls_posted=urls_posted+1 WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
		if(stripos($M,'<a href="http') !== false) $DbLink->query("UPDATE ".C_STS_TBL." SET urls_posted=urls_posted+1 WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
	}

	// Smilies
	if (C_USE_SMILIES)
	{
		include("./lib/smilies.lib.php");
		$ss = Check4Smilies($M,$SmiliesTbl);
		if(C_EN_STATS && $ss > 0)
		{
			$DbLink->query("UPDATE ".C_STS_TBL." SET smilies_posted=smilies_posted+$ss WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
		}
		unset($SmiliesTbl, $ss);
	};

	// transform ISO-8859-1 special characters
	if ($Latin1)
	{
		global $MsgTo;
#		ereg("(.*)(".$MsgTo."(&gt;)?)(.*)",$M,$Regs);
		preg_match("/(.*)(".$MsgTo."(&gt;)?)(.*)/",$M,$Regs);
		if ($MsgTo != "" && ($Regs[1] == "" && $Regs[4] == "")) $Regs[4] = $M;
#		if (!ereg("&[[:alnum:]]{1,10};",$Regs[1]) && !ereg("&[[:alnum:]]{1,10};",$Regs[4]))
		if (!preg_match("/&[[:alnum:]]{1,10};/",$Regs[1]) && !preg_match("/&[[:alnum:]]{1,10};/",$Regs[4]))
		{
			for ($i = 1; $i <= 4; $i++)
			{
				if (($i != 1 && $i != 4) || $Regs[$i] == "") continue;
				$part = $Regs[$i];
				$part = htmlentities($part);
				$part = str_replace("&amp;lt;", "&lt;", $part);
				$part = str_replace("&amp;gt;", "&gt;", $part);
				$part = str_replace("&lt;", "<", $part);
				$part = str_replace("&gt;", ">", $part);
				$part = str_replace("&quot;","\"", $part);
#				$part = ereg_replace("&amp;(#[[:digit:]]{2,5};)", "&\\1", $part);
				$part = preg_replace("/&amp;(#[[:digit:]]{2,5};)/", "&\\1", $part);
				$Regs[$i] = $part;
			}
			$M = $Regs[1].$Regs[2].$Regs[4];
		}
	}

	// Color Sniffer scripting safe mode filter by Alexander Eisele <xaex@xeax.de> & Ciprian
	$C = str_replace("<", "&lt;", $C);
	$C = str_replace(">", "&gt;", $C);
	$C = str_replace("\"", "&quot;", $C);
	$C = str_replace("x3c", "&lt;", $C);
	$C = str_replace("x3e", "&gt;", $C);

	$CC = array("","black","dimgray","gray","darkgray","silver","lightgrey","gainsboro","whitesmoke","ghostwhite","white","slategray","lightslategray","midnightblue","navy","darkblue","darkslateblue","mediumblue","blue","steelblue","royalblue","cornflowerblue","dodgerblue","deepskyblue","lightskyblue","skyblue","lightsteelblue","lightblue","powderblue","paleturquoise","lightcyan","aliceblue","azure","mintcream","darkslategray","cadetblue","teal","darkcyan","lightseagreen","darkturquoise","mediumturquoise","turquoise","aqua","cyan","mediumaquamarine","aquamarine","darkolivegreen","olive","olivedrab","darkkhaki","darkgreen","green","forestgreen","seagreen","mediumseagreen","darkseagreen","mediumspringgreen","springgreen","palegreen","honeydew","limegreen","lime","lightgreen","lawngreen","chartreuse","greenyellow","yellowgreen","indigo","purple","darkmagenta","darkviolet","darkorchid","mediumorchid","orchid","violet","plum","thistle","blueviolet","mediumpurple","slateblue","mediumslateblue","lavender","mediumvioletred","magenta","fuchsia","deeppink","palevioletred","hotpink","lightpink","pink","mistyrose","lavenderblush","maroon","darkred","firebrick","crimson","red","orangered","tomato","indianred","lightcoral","salmon","darksalmon","lightsalmon","coral","darkorange","orange","sandybrown","darkgoldenrod","goldenrod","gold","yellow","khaki","palegoldenrod","lemonchiffon","cornsilk","lightgoldenrodyellow","beige","lightyellow","ivory","rosybrown","saddlebrown","brown","sienna","chocolate","peru","tan","burlywood","wheat","navajowhite","peachpuff","moccasin","bisque","blanchedalmond","papayawhip","antiquewhite","linen","oldlace","seashell","floralwhite","snow");

	if (trim($C)!="")
	{
		if (!in_array($C, $CC))
		{
			$C="lime";
		}
	}

	//Color's Power Filter Mod by Ciprian
	if (isset($_COOKIE["CookieColor"]) && (!isset($C))) $C = strcasecmp($_COOKIE["CookieColor"], $COLOR_TB) != 0 ? $_COOKIE["CookieColor"] : '';
	//Registered colorname to use for text color by Ciprian
	else
	{
		$DbLink->query("SELECT colorname FROM ".C_REG_TBL." WHERE username = '$U' LIMIT 1");
		if ($DbLink->num_rows() != 0 && (!isset($C)))
		{
	    list($C) = $DbLink->next_record();
		}
	}
	if (COLOR_FILTERS)
	{
		if (!isset($C))
		{
			if ($status == "a" || $status == "t") $C = COLOR_CA;
			elseif ($status == "m") $C = COLOR_CM;
		}
		elseif ($C != '')
		{
			// Red colors are reserved to the admin
			if ((strcasecmp($C, COLOR_CA) == 0 || strcasecmp($C, COLOR_CA1) == 0 || strcasecmp($C, COLOR_CA2) == 0) && $C != "" && $status != "a" && $status != "t")
			{
				if ($status == "m")
				{
					$C = COLOR_CM; //default moderator's color
				}
				else
				{
					$C = '';	//default color
				}
			}
			// Blue colors are reserved to a moderator for the current room
			elseif ((strcasecmp($C, COLOR_CM) == 0 || strcasecmp($C, COLOR_CM1) == 0 || strcasecmp($C, COLOR_CM2) == 0) && $C != "" && $status != "a" && $status != "t" && $status != "m")
			{
				$C = '';	//default color
			}
			elseif (strcasecmp($C, $COLOR_TB) == 0)
			{
				$C = '';
			}
		}
	};
	if (!COLOR_ALLOW_GUESTS && $status == "u") $C = '';
	include_once("./lib/swearing.lib.php");
	if (checkwords($C, true, $Charset)) $C = '';		//if user is using a swear word (defined in swearing.lib.php), the font color will resets to default. this is to keep your database as well as our computer clean of swearing (no swear into your cookies on your local computer).
	if (isset($C) && $C != '' && strcasecmp($C, COLOR_CD) != 0)
	{
		$M = "<FONT COLOR=\"".$C."\">".$M."</FONT>";
		setcookie("CookieColor", $C, time() + 60*60*24*365);        // cookie expires in one year
	}
	elseif(isset($_COOKIE["CookieColor"]))
	{
		setcookie("CookieColor", '', time());        // cookie expires in one year
	}

	// Text tags for power users
	if(C_ITALICIZE_POWERS)
	{
		$text_tag = "";
		$text_endtag = "";
		if ($status == "a" || $status == "t" || $status == "m")
		{
			if(stristr(C_TAGS_POWERS, "B"))
			{
				$text_tag .= "<B>";
				$text_endtag .= "</B>";
			}
			if(stristr(C_TAGS_POWERS, "I"))
			{
				$text_tag .= "<I>";
				$text_endtag .= "</I>";
			}
			if(stristr(C_TAGS_POWERS, "U"))
			{
				$text_tag .= "<U>";
				$text_endtag .= "</U>";
			}
		}
		if ($text_tag != "") $M = $text_tag.$M.$text_endtag;
	}

	$DbLink->query("INSERT INTO ".C_MSG_TBL." VALUES ($T, '$R', '".addslashes($U)."', '$Latin1', ".time().", '$Private', '".addslashes($M)."', '$Read', '$RF')");
};

	// ** Define the default color that will be used for messages **
	//Color's Power Filter Mod by Ciprian
	$DbLink->query("SELECT colorname FROM ".C_REG_TBL." WHERE username = '$U' LIMIT 1");
	if ($DbLink->num_rows() != 0 && (!isset($C)))
	{
		list($colorname) = $DbLink->next_record();
	}
	if (isset($_COOKIE["CookieColor"]) && (!isset($C))) $C = strcasecmp($_COOKIE["CookieColor"], $COLOR_TB) != 0 ? $_COOKIE["CookieColor"] : '';
	//Registered colorname to use for text color by Ciprian
	elseif (isset($colorname) && (!isset($C))) $C = strcasecmp($colorname, $COLOR_TB) != 0 ? $colorname : '';
	if (COLOR_FILTERS)
	{
		if (!isset($C))
		{
			if ($status == "a" || $status == "t") $C = COLOR_CA;
			elseif ($status == "m") $C = COLOR_CM;
		}
		elseif ($C != '')
		{
			// Red colors are reserved to the admin
			if ((strcasecmp($C, COLOR_CA) == 0 || strcasecmp($C, COLOR_CA1) == 0 || strcasecmp($C, COLOR_CA2) == 0) && $C != "" && $status != "a" && $status != "t")
			{
				if ($status == "m")
				{
					$ErrorC = COL_ERROR_BOX_MODA;
					setcookie("CookieColor", "", time());        // delete power color cookie
					$C = COLOR_CM; //default moderator's color
				}
				else
				{
					$ErrorC = COL_ERROR_BOX_USRA;
					setcookie("CookieColor", "", time());        // delete power color cookie
					$C = '';	//default color
				}
			}
			// Blue colors are reserved to a moderator for the current room
			elseif ((strcasecmp($C, COLOR_CM) == 0 || strcasecmp($C, COLOR_CM1) == 0 || strcasecmp($C, COLOR_CM2) == 0) && $C != "" && $status != "a" && $status != "t" && $status != "m")
			{
				$ErrorC = COL_ERROR_BOX_USRM;
				setcookie("CookieColor", "", time());        // delete power color cookie
				$C = '';	//default color
			}
			elseif (strcasecmp($C, $COLOR_TB) == 0)
			{
				setcookie("CookieColor", "", time());        // delete power color cookie
				$C = '';
				?>
				<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript1.2">
				<!--
					window.parent.frames['input'].window.location.reload();
				// -->
				</SCRIPT>
				<?php
			}
		}
	};
	if (!COLOR_ALLOW_GUESTS && $status == "u") $C = '';

// ** Test for online commands and swear words **
$IsCommand = false;
$RefreshMessages = false;
$IsPopup = false;
$IsM = false;

#if (isset($M) && trim($M) != "" && (ereg("^\/", $M) || ereg("^: ", $M))) include("./lib/commands.lib.php");
#if (isset($M) && trim($M) != "" && (ereg("^\/", $M) || ereg("^: ", $M)))
if (isset($M) && trim($M) != "" && (preg_match("#^\/#", $M) || preg_match("#^: #", $M)))
{
	if (file_exists("./${ChatPath}localization/${L}/localized.cmds.php")) require("./${ChatPath}localization/${L}/localized.cmds.php");
	include("./lib/commands.lib.php");
}

#if (isset($M) && (ereg("^\/", $M) || ereg("^: ", $M)) && !($IsCommand) && !isset($Error)) $Error = L_BAD_CMD;
if (isset($M) && (preg_match("#^\/#", $M) || preg_match("#^: #", $M)) && !($IsCommand) && !isset($Error)) $Error = L_BAD_CMD;

if (isset($M) && trim($M) != "" && (!isset($M0) || ($M != $M0)) && !($IsCommand || isset($Error)))
{
	if (C_NO_SWEAR && $R != C_NO_SWEAR_ROOM1 && $R != C_NO_SWEAR_ROOM2 && $R != C_NO_SWEAR_ROOM3 && $R != C_NO_SWEAR_ROOM4)
	{
		include("./lib/swearing.lib.php");
		if (checkwords($C, true, $Charset)) $C = '';		//if user is using a swear word (defined in swearing.lib.php), the font color will resets to default. this is to keep your database as well as our computer clean of swearing (no swear into your cookies on your local computer).
		$M = checkwords($M, false, $Charset);
 		if(C_EN_STATS && isset($Found) && $b>0)
		{
			$DbLink->query("UPDATE ".C_STS_TBL." SET swears_posted=swears_posted+$b WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
		}
		unset($Found, $b);
	}
// Bob Dickow Custom code for /away command modification - modified by Ciprian for Plus behaviour.:

   $DbLink->query("SELECT awaystat FROM ".C_USR_TBL." WHERE username='$U'");

   if ($DbLink->num_rows() != 0)
   {
     list($awaystat) = $DbLink->next_record();
   }
   $DbLink->clean_results();

   if ($awaystat == 1) {
     $Msg = "sprintf(L_BACK, \"".special_char($U,$Latin1)."\")";
     $time_back = time() - 1;
     $awaystat = 0;
     $DbLink->query("INSERT INTO ".C_MSG_TBL." VALUES ($T, '$R', 'SYS away', '$Latin1', '$time_back', '', '".addslashes($Msg)."', '', '$RF')");
     $DbLink->query("UPDATE ".C_USR_TBL." SET awaystat='0' WHERE username='$U'");
	if(C_EN_STATS)
	{
		$DbLink->query("UPDATE ".C_STS_TBL." SET seconds_away=seconds_away+(".time()."-last_away), longest_away=IF(".time()."-last_away < longest_away, longest_away, ".time()."-last_away), last_away='' WHERE (stat_date=FROM_UNIXTIME(last_away,'%Y-%m-%d') OR stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d')) AND room='$R' AND username='$U'");
	}
   }
   AddMessage(stripslashes($M), $T, $R, $U, $C, "", "", $RF, $Charset);
// END Bob Dickow custom code for /away command modification - modified by Ciprian for Plus behaviour..
	$RefreshMessages = true;
	if(C_EN_STATS)
	{
		$DbLink->query("UPDATE ".C_STS_TBL." SET posts_sent=posts_sent+1 WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
	}
	if(C_EN_STATS && $IsCommand)
	{
		$DbLink->query("UPDATE ".C_STS_TBL." SET cmds_used=cmds_used+1 WHERE stat_date=FROM_UNIXTIME(last_in,'%Y-%m-%d') AND room='$R' AND username='$U'");
	}
}
// For translations with an explicit charset (not the 'x-user-defined' one)
if (!isset($FontName)) $FontName = "";
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML dir="<?php echo(($Align == "right") ? "RTL" : "LTR"); ?>">

<HEAD>
<TITLE>Input frame</TITLE>
<LINK REL="stylesheet" HREF="<?php echo($skin.".css.php?Charset=${Charset}&medium=${FontSize}&FontName=".urlencode($FontName)); ?>" TYPE="text/css">
<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript1.2">
<!--
	// Get the position for the help popup
	if (window.parent.NS4) document.captureEvents(Event.MOUSEDOWN);
	document.onmousedown = window.parent.displayLocation;
// -->
</SCRIPT>
</HEAD>

<BODY CLASS="frame"<?php if (!$IsPopup) echo(" onLoad=\"if (window.focus) window.parent.get_focus(); window.document.forms['MsgForm'].elements['M'].focus();\""); ?>>
<TABLE BORDER=0 CELLPADDING=0 CELLSPACING=0>
<TR>
	<!-- Input form  -->
	<TD valign=top align=left nowrap="nowrap">
	<?php
	// Define the way posted values will be handled according to the javascript abilities
	// of the browser
	if ($Ver == "H")
	{
		$action = "handle_inputH.php";
		$target = "input_sent";
	}
	else
	{
		$action = "input.php";
		$target = "_self";
	};
	?>
	<FORM NAME="MsgForm" ACTION="<?php echo($action); ?>" METHOD="POST" AUTOCOMPLETE="OFF" TARGET="<?php echo($target); ?>" onSubmit="return window.parent.validateSubmission();">
		<INPUT TYPE="hidden" NAME="From" VALUE="<?php echo($From); ?>">
		<INPUT TYPE="hidden" NAME="Ver" VALUE="<?php echo($Ver); ?>">
		<INPUT TYPE="hidden" NAME="L" VALUE="<?php echo($L); ?>">
		<INPUT TYPE="hidden" NAME="U" VALUE="<?php echo(htmlspecialchars(stripslashes(urlencode($U)))); ?>">
		<INPUT TYPE="hidden" NAME="R" VALUE="<?php echo(htmlspecialchars(stripslashes(urlencode($R)))); ?>">
		<INPUT TYPE="hidden" NAME="T" VALUE="<?php echo($T); ?>">
		<INPUT TYPE="hidden" NAME="D" VALUE="<?php echo($D); ?>">
		<INPUT TYPE="hidden" NAME="N" VALUE="<?php echo($N); ?>">
		<INPUT TYPE="hidden" NAME="O" VALUE="<?php echo($O); ?>">
		<INPUT TYPE="hidden" NAME="ST" VALUE="<?php echo($ST); ?>">
		<INPUT TYPE="hidden" NAME="NT" VALUE="<?php echo($NT); ?>">
		<INPUT TYPE="hidden" NAME="PWD_Hash" VALUE="<?php echo(isset($PWD_Hash) ? $PWD_Hash : ''); ?>">

		<!-- Ignored users list -->
		<INPUT TYPE="hidden" NAME="Ign" VALUE="<?php echo(isset($Ign) ? stripslashes($Ign) : ""); ?>">

		<!-- Last sent message or command (will be used for the '/!' command) -->
		<INPUT TYPE="hidden" NAME="M0" VALUE="<?php echo(isset($M1) ? $M1 : (isset($M) ? stripslashes($M) : "")); ?>">

		<A HREF="help_popup.php?<?php echo("L=$L&Ver=$Ver"); ?>" onClick="window.parent.help_popup(); return false" TARGET="_blank" onMouseOver="document.images['helpImg'].src = window.parent.imgHelpOn.src" onMouseOut="document.images['helpImg'].src = window.parent.imgHelpOff.src" title="<?php echo(L_HLP); ?>"><IMG NAME="helpImg" SRC="localization/<?php echo($L); ?>/images/helpOff.gif" WIDTH=30 HEIGHT=20 BORDER=0 ALT="<?php echo(L_HLP); ?>" onMouseOver="window.status='<?php echo(L_HLP); ?>.'; return true" onClick="document.forms['MsgForm'].elements['M'].focus();"></A>&nbsp;

		<?php
		// Get the value to put in the message box : preceding M0 field value for /! command,
		// preceding entry if it was an erroneous command, else nothing;
		preg_match("/^[\w?(\Q".REG_CHARS_ALLOWED."\E)?\w]*\>/",$M,$add);
		$M0 = stripslashes($M0);
		$M0 = str_replace("&#39;", "'", $M0);
		$ValM = $IsM ? $M0 : (strstr($add[0],">") && ($text_tag == "" || !$text_tag) ? $add[0]." " : "");
		if (isset($Error) && !($IsCommand)) $ValM = $M1;
#		unset($M0);
		?>
		<INPUT TYPE="text" NAME="M" SIZE="50" MAXLENGTH="2000" VALUE="<?php echo(stripslashes($ValM)); ?>"<?php echo((isset($C) && $C != "") ? " style=\"color: $C;\"" : ""); ?>>

		<!-- Addressee that will be filled when the user click on a nick at the users frame -->
		<INPUT TYPE="hidden" NAME="MsgTo" VALUE="">

<?php
// Color Input Select mod by Alexander Eisele <xaex@xeax.de> & Ciprian
// Drop down list of colors
$ColorList = COLORLIST;
if (COLOR_FILTERS)
{
	if (!COLOR_ALLOW_GUESTS && $status == "u")
	{
		$ColorList = '"",'.COLOR_CD.'';
	}
	elseif (COLOR_ALLOW_GUESTS && $status != "a" && $status != "t" && $status != "m")
	{
		if (COLOR_CA != "") $ColorList = str_replace('"'.COLOR_CA.'",', "", $ColorList);
		if (COLOR_CA1 != "") $ColorList = str_replace('"'.COLOR_CA1.'",', "", $ColorList);
		if (COLOR_CA2 != "") $ColorList = str_replace('"'.COLOR_CA2.'",', "", $ColorList);
		if (COLOR_CM != "") $ColorList = str_replace('"'.COLOR_CM.'",', "", $ColorList);
		if (COLOR_CM1 != "") $ColorList = str_replace('"'.COLOR_CM1.'",', "", $ColorList);
		if (COLOR_CM2 != "") $ColorList = str_replace('"'.COLOR_CM2.'",', "", $ColorList);
	}
	elseif ($status == "m")
	{
		if (COLOR_CA != "") $ColorList = str_replace('"'.COLOR_CA.'",', "", $ColorList);
		if (COLOR_CA1 != "") $ColorList = str_replace('"'.COLOR_CA1.'",', "", $ColorList);
		if (COLOR_CA2 != "") $ColorList = str_replace('"'.COLOR_CA2.'",', "", $ColorList);
	}
}
else
{
	if (!COLOR_ALLOW_GUESTS && $status == "u")
	{
		$ColorList = '"",'.COLOR_CD.'';
	}
}
$ColorList = str_replace('"', "", $COLOR_TB != "" ? str_ireplace($COLOR_TB.'","',"",COLORLIST) : COLORLIST);
$CC = explode(",", $ColorList);
#if ($Ver != "H" || (eregi("firefox|chrome|opera|safari", $_SERVER['HTTP_USER_AGENT']) && !eregi("MSIE", $_SERVER['HTTP_USER_AGENT']))) echo("<SELECT NAME=\"C\" style=\"background-color:".$C.";\">\n");
if ($Ver != "H" || (preg_match("/[firefox|chrome|opera|safari]/i", $_SERVER['HTTP_USER_AGENT']) && stripos($_SERVER['HTTP_USER_AGENT'],"MSIE") === false)) echo("<SELECT NAME=\"C\" style=\"background-color:".$C.";\">\n");
else echo("<SELECT NAME=\"C\">\n");
$not_selected = ((L_NOT_SELECTED_F != "") ? L_NOT_SELECTED_F : L_NOT_SELECTED);
$null = ((L_NULL_F != "") ? L_NULL_F : L_NULL);
$selected = " (".$selected.")";
$not_selected = " ".$null." (".$not_selected.")";
			while(list($ColorNumber1, $ColorCode) = each($CC))
			{
				// Red color is reserved to the admin or a moderator for the current room
				if ($ColorCode != "" && $ColorCode != COLOR_CD) echo("<OPTION style=\"background-color:".$ColorCode."; color:".COLOR_CD."\" VALUE=\"".$ColorCode."\"");
				else echo("<OPTION style=\"background-color:".COLOR_CD."; color:".COLOR_CD."\" VALUE=\"".$ColorCode."\"");
				if ($C == $ColorCode) echo(" SELECTED");
				if ($ColorCode != "" && $ColorCode != COLOR_CD && $ColorCode != $colorname && $ColorCode != COLOR_CA && $ColorCode != COLOR_CA1 && $ColorCode != COLOR_CA2 && $ColorCode != COLOR_CM && $ColorCode != COLOR_CM1 && $ColorCode != COLOR_CM2) echo(">".$ColorCode."</OPTION>");
				elseif ($ColorCode == $colorname && $ColorCode != "") echo(">".$ColorCode." (".L_PRO_COLOR.")</OPTION>");
				elseif ($ColorCode != "" && ($ColorCode == COLOR_CA || $ColorCode == COLOR_CA1 || $ColorCode == COLOR_CA2)) echo(COLOR_FILTERS ? ">".$ColorCode." (".L_WHOIS_ADMIN.")</OPTION>" : ">".$ColorCode."</OPTION>");
				elseif ($ColorCode != "" && ($ColorCode == COLOR_CM || $ColorCode == COLOR_CM1 || $ColorCode == COLOR_CM2)) echo(COLOR_FILTERS ? ">".$ColorCode." (".L_WHOIS_MODER.")</OPTION>" : ">".$ColorCode."</OPTION>");
				elseif ($ColorCode == "") echo(">".$not_selected."</OPTION>");
				else echo(">".COLOR_CD." (".L_ROOM_COLOR.")</OPTION>");
			}
			echo("\n</SELECT>\n");
	// Ghost Control mod by Ciprian
	$ghost = 0;
	$superghost = 0;
	if ($SPECIAL_GHOSTS != "")
	{
			$sghosts = "";
			$sghosts = str_replace("'","",C_SPECIAL_GHOSTS);
			$sghosts = str_replace(" AND username != ",",",$sghosts);
	}
	if (($sghosts != "" && ghosts_in(stripslashes($U), $sghosts, $Charset)) || (C_HIDE_MODERS && $status == "m") || (C_HIDE_ADMINS && ($status == "a" || $status == "t")))
	{
		if ($status == "a" || $status == "t") $superghost = 1;
		else $ghost = 1;
	}
?>
		<INPUT TYPE="hidden" NAME="sent" VALUE="0">
		<INPUT TYPE="submit" NAME="sendForm" VALUE=<?php echo(L_OK); ?> onClick="document.forms['MsgForm'].elements['M'].focus();"><br />
<?php
// Avatar System Start.
	if (C_USE_AVATARS && C_AVA_PROFBUTTON)
	{
    $DbLink->query("SELECT * FROM ".C_REG_TBL." WHERE username='$U' LIMIT 1");
    if ($DbLink->num_rows() != 0)
    {
      $Cmd2Send = "'profile',''";
?>
<A HREF="#" onClick="window.parent.runCmd(<?php echo($Cmd2Send); ?>); return false;" TARGET="_blank" onMouseOver="document.images['launchProfimage'].src = window.parent.ProfimageOn.src" onMouseOut="document.images['launchProfimage'].src = window.parent.ProfimageOff.src" title="<?php echo(L_SEL_NEW_AV); ?>"><IMG name="launchProfimage" SRC="images/avatarbuttonroll.gif" WIDTH=25 HEIGHT=25 BORDER=0 ALT="<?php echo(L_SEL_NEW_AV); ?>" onMouseOver="window.status='<?php echo(L_SEL_NEW_AV); ?>'; return true;"></A>&nbsp;
<?php
    }
           $DbLink->clean_results();
	}
$DbLink->close();
// Avatar: moved down from earlier in file.
// Avatar System End.
?>
<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
<!--
function inputDropMsg()
{
	window.focus();
        selInd = document.forms['MsgForm'].quicknote.selectedIndex;
        window.document.forms['MsgForm'].elements['M'].value = window.document.forms['MsgForm'].quicknote.options[selInd].value;
        window.document.forms['MsgForm'].quicknote.selectedIndex = 0;
};
// -->
</SCRIPT>
<?php
$qnotelabel = L_QUICK;
// Change the numbers below (50 or 40) to fit the length of your Quick Menu title:
 if (isset($dropdownmsga) && ($status == "a" || $status == "t")) {
  print "<select name=\"quicknote\" onChange='inputDropMsg()'>";
       print "<option value=\"\">".str_pad(" ".$qnotelabel." ", 45, "*", STR_PAD_BOTH)."</option>\n";
	foreach ($dropdownmsga as $msg) {
	$msg = stripslashes(sprintf($msg,$U));
        print "<option value=\"".$msg."\">".$msg."</option>\n";
        }
}
 elseif (isset($dropdownmsgm) && $status == "m") {
  print "<select name=\"quicknote\" onChange='inputDropMsg()'>";
       print "<option value=\"\">".str_pad(" ".$qnotelabel." ", 45, "*", STR_PAD_BOTH)."</option>\n";
	foreach ($dropdownmsgm as $msg) {
	$msg = stripslashes(sprintf($msg,$U));
        print "<option value=\"".$msg."\">".$msg."</option>\n";
        }
}
else {
 if (isset($dropdownmsg)) {
  print "<select name=\"quicknote\" onChange='inputDropMsg()'>";
       print "<option value=\"\">".str_pad(" ".$qnotelabel." ", 40, "*", STR_PAD_BOTH)."</option>\n";
	foreach ($dropdownmsg as $msg) {
	$msg = stripslashes(sprintf($msg,$U));
        print "<option value=\"".$msg."\">".$msg."</option>\n";
       }
		}
	}
        print "</select>";
?>
&nbsp;&nbsp;
<?php
if (C_USE_SMILIES)
{
?>
		<A HREF="<?php echo("${ChatPath}smilie_popup.php?L=$L"); ?>" onClick="window.parent.smilie_popup(); return false" TARGET="_blank" onClick="document.forms['MsgForm'].elements['M'].focus();" onMouseOver="window.status='<?php echo(L_LINKS_16); ?>.'; return true" title="<?php echo(L_LINKS_16); ?>"><img src="images/smilies/smile42.gif" border=0 alt="<?php echo(L_LINKS_16); ?>"></A>&nbsp;
<?php
}
// Settings below should be the same as in lib/commands/buzz.php.
 //if ($status == "a" || $status == "t") // use this  to show buzz list only to administrators
if (($status == "m") || ($status == "t") || ($status == "a")) // use this to show buzz list to both admins and moderators.
//if (($status == "m") || ($status == "a") || ($status == "t") || ($status == "r")) // use this to show buzz list to admins, moderators and registered users. Guests can't use it.
{
?>
		<A HREF="<?php echo("${ChatPath}buzz_popup.php?L=$L"); ?>" onClick="window.parent.buzz_popup(); return false" CLASS="ChatReg" onClick="document.forms['MsgForm'].elements['M'].focus();" onMouseOver="window.status='<?php echo(L_BUZZ); ?>.'; return true" TARGET="_blank" title="<?php echo(L_BUZZ); ?>"><img src="images/buzz.gif" border=0 alt="<?php echo(L_BUZZ); ?>"></A>
<?php
}
?>
	&nbsp;<INPUT TYPE="text" NAME="server_clock" SIZE="<?php echo(($L == "georgian" || $L == "greek" || $L == "urdu" || $L == "vietnamese") ? "25" : "19"); ?>" VALUE="Chat time" READONLY style="font-size: 11; font-weight: 800;">
<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
<!--
<?php
// Display the server time in the clock text box
	include_once("./${ChatPath}lib/worldtime.lib.php");
	$CorrectedTime = mktime(date("G") + C_TMZ_OFFSET,date("i"),date("s"),date("m"),date("d"),date("Y"));
?>
	gap = calc_gap("<?php echo(date("F d, Y H:i:s", $CorrectedTime)); ?>");
	clock_input(gap);
// -->
</SCRIPT>
	&nbsp;&nbsp;<span style="background-color:yellow; color:blue; font-weight:800" title="<?php echo($U); if($superghost) echo(" * ".L_SUPER_GHOST." *"); elseif($ghost) echo(" * ".L_GHOST." *"); ?>">&nbsp;<?php echo($U); if($superghost) echo("&nbsp;<span style=\"color:red\">*&nbsp;".L_SUPER_GHOST."&nbsp;*</span>"); elseif($ghost) echo("&nbsp;<span style=\"color:red\">*&nbsp;".L_GHOST."&nbsp;*</span>");?>&nbsp;</span>
</TD>
</TR>
</FORM>
</TABLE>

<?php

// ** Refresh the messages frame if necessary **
if($RefreshMessages)
{
	$Tmp = isset($Ign) ? "&Ign=".urlencode(stripslashes($Ign)) : "";
	$First = isset($First) ? $First : 0;
	?>
	<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
	<!--
	<?php
	if ($Ver == "H")
	{
		if ($First) echo("window.parent.frames['messages'].window.document.close();\n\twindow.parent.connect = 0;\n");
		?>
		if (window.parent.connect == 0)
		{
			window.parent.refresh_query = "<?php echo("From=".urlencode($From)."&L=$L&U=".urlencode(stripslashes($U)).(isset($PWD_Hash) ? '&PWD_Hash=' . $PWD_Hash : '')."&R=".urlencode(stripslashes($R))."&T=$T&D=$D&N=$N&ST=$ST&NT=$NT".$Tmp."&First=$First"); ?>";
			window.parent.force_refresh();
		};
		<?php
	}
	else
	{
		?>
		window.parent.frames['messages'].window.location = 'messagesL.php?<?php echo("From=".urlencode($From)."&L=$L&U=".urlencode(stripslashes($U)).(isset($PWD_Hash) ? '&PWD_Hash=' . $PWD_Hash : '')."&R=".urlencode(stripslashes($R))."&T=$T&D=$D&N=$N&O=$O&ST=$ST&NT=$NT".$Tmp); ?>';
		<?php
	};
	?>
	// -->
	</SCRIPT>
	<?php
};

// ** Display a JavaScript alert box with the error message if necessary **
if(isset($Error))
{
	?>
	<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
	<!--
	document.forms['MsgForm'].elements['M'].select();
	alert("<?php echo(str_replace("\\\\n","\\n",addslashes($Error))); ?>");
	// -->
	</SCRIPT>
	<?php
}

// ** Display a JavaScript alert box with the error message if necessary, but send the message anyway (resets the color for Color mod by Ciprian) **
if(isset($ErrorC))
{
	?>
	<SCRIPT TYPE="text/javascript" LANGUAGE="JavaScript">
	<!--
	alert("<?php echo(str_replace("\\\\n","\\n",addslashes($ErrorC))); ?>");
	document.forms['MsgForm'].elements['M'].focus();
	// -->
	</SCRIPT>
	<?php
}

// ** Put JavaScript instructions that commands may have set
if (isset($jsTbl))
{
	for (reset($jsTbl); $jsInst=current($jsTbl); next($jsTbl))
	{
		echo("$jsInst\n");
	};
	unset($jsTbl);
}
?>
</BODY>
</HTML>
<?php
?>