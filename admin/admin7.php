<?php
// Search panel by Digioz Multimedia.
// This sheet is diplayed when the admin wants to search the database for registered users

// Credit for this goes to Pete Soheil <webmaster@digioz.com>
if ($_SESSION["adminlogged"] != "1") exit(); // added by Bob Dickow for security.

while(list($name,$value) = each($_GET))
{
	$$name = $value;
};
while(list($name,$value) = each($_POST))
{
	$$name = $value;
};
?>
<P CLASS=title><?php echo (A_SEARCH_1) ; ?></P>
<center>
<?php
// Remove profiles of users that have not been chatting for a time > C_REG_DEL
if (!isset($FORM_SEND) && C_REG_DEL != 0) $DbLink->query("DELETE FROM ".C_REG_TBL." WHERE last_login < ".(time() - C_REG_DEL * 60 * 60 * 24)." AND perms != 'admin' AND perms != 'topmod' AND perms != 'moderator'");

// The admin has required an action to be done
if (isset($FORM_SEND) && ($FORM_SEND == 1 || $FORM_SEND == 7))
{
?>
<P>
<TABLE WIDTH=98% ALIGN=CENTER BORDER=0 CELLPADDING=3 CLASS="table">
<TR>
	<TD ALIGN=CENTER>
		<FORM ACTION="<?php echo("$From?$URLQueryBody"); ?>" METHOD="POST" AUTOCOMPLETE="" NAME="Form1">
		<INPUT TYPE=hidden NAME="From" value="<?php echo($From); ?>">
		<INPUT TYPE=hidden NAME="pmc_username" value="<?php echo(htmlspecialchars(stripslashes($pmc_username))); ?>">
		<INPUT TYPE=hidden NAME="pmc_password" value="<?php echo($pmc_password); ?>">
		<INPUT TYPE=hidden NAME="FORM_SEND" value="1">
<?php
	// A registred user have to be deleted or banished?
	$DELETE_MODE = (stripslashes($submit_type) == A_SHEET1_6)? 1:0;
	$BANISH_MODE = (stripslashes($submit_type) == A_SHEET1_9)? 1:0;

	// Get the list of the users
	$DbLink->query("SELECT username FROM ".C_REG_TBL." WHERE email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%' AND username != '$pmc_username'");
	$users = Array();
	while (list($username) = $DbLink->next_record())
	{
		$users[] = $username;
	}
	$DbLink->clean_results();

	for (reset($users); $username=current($users); next($users))
	{
		$usrHash = md5($username);
		$VarName = "user_".$usrHash;
		if (!isset($$VarName)) continue;
		// Delete a profile after having sent a message to the user if he is connected
		if ($DELETE_MODE)
		{
			$VarName = "selected_".$usrHash;
			if (isset($$VarName))
			{
				$uuu = addslashes($username);
				$DbLink->query("DELETE FROM ".C_REG_TBL." WHERE username='$uuu'");
				$DbLink->query("SELECT room FROM ".C_USR_TBL." WHERE username='$uuu' LIMIT 1");
				$in_room = ($DbLink->num_rows() != 0);
				if ($in_room)
				{
					list($room) = $DbLink->next_record();
					$DbLink->clean_results();
					$DbLink->query("SELECT type FROM ".C_MSG_TBL." WHERE room='".addslashes($room)."' LIMIT 1");
					list($type) = $DbLink->next_record();
					$DbLink->clean_results();
					$DbLink->query("UPDATE ".C_USR_TBL." SET status='u' WHERE username='$uuu'");
					$DbLink->query("INSERT INTO ".C_MSG_TBL." VALUES ('$type', '".addslashes($room)."', 'SYS delreg', '$Latin1', ".time().", '$uuu', 'L_ADM_2', '', '')");
				};
				// Optimize the registered users table when a MySQL DB is used
				$DbLink->optimize(C_REG_TBL);
			};
		}
		// Banish an user
		elseif ($BANISH_MODE)
		{
			$VarName = "reason";
			$reason = $$VarName;
			$VarName = "selected_".$usrHash;
			if (isset($$VarName))
			{
				$uuu = addslashes($username);
				$DbLink->query("SELECT latin1,ip,country_code,country_name FROM ".C_REG_TBL." WHERE username='$uuu' LIMIT 1");
				list($Latin1, $IP, $COUNTRY_CODE, $COUNTRY_NAME) = $DbLink->next_record();
				$DbLink->clean_results();
				if(C_USE_FLAGS)
				{
					if(!isset($COUNTRY_CODE) || $COUNTRY_CODE == "")
					{
						// GeoIP mode for country flags
						if (!class_exists("GeoIP"))
						{
							include("plugins/countryflags/geoip.inc");
						}
						$gi = geoip_open("plugins/countryflags/GeoIP.dat",GEOIP_STANDARD);
					}
				}
				$DbLink->query("SELECT count(*) FROM ".C_BAN_TBL." WHERE username='$uuu' LIMIT 1");
				list($Nb) = $DbLink->next_record();
				$DbLink->clean_results();
				if ($Nb == "0")
				{
					$Until = time() + round(C_BANISH * 60 * 60 * 24);
					if ($Until > 2222222222) $Until = "2222222222";
					$DbLink->query("INSERT INTO ".C_BAN_TBL." VALUES ('$uuu','$Latin1','$IP','*','$Until','$reason','$COUNTRY_CODE','$COUNTRY_NAME')");
				}
				elseif ($reason != "")
				{
					$DbLink->query("UPDATE ".C_BAN_TBL." SET reason = '$reason' where username='$uuu'");
				}
				$Success = A_SHEET1_10;
			};
		};
	};
};
if (isset($Success) && $Success != "") echo("<P CLASS=\"success\">".$Success."</SPAN></P>\n");

// If form is submitted update values in the database
if (isset($FORM_SEND) && $FORM_SEND == 7)
{
	if($searchCategory == 1 && $searchTerm != "")
	{
		// create query for 1
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE (username LIKE '%".$searchTerm."%' OR firstname LIKE '%".$searchTerm."%' OR lastname LIKE '%".$searchTerm."%') AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
		//echo $query;
	}
	elseif($searchCategory == 2 && $searchTerm != "")
	{
		// create query for 2
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE ip LIKE '%".$searchTerm."%' AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 3 && $searchTerm != "")
	{
		// create query for 3
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE perms LIKE '%".$searchTerm."%' AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 4 && $searchTerm != "")
	{
		// create query for 4
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE email LIKE '%".$searchTerm."%' AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 5 && $searchTerm != "")
	{
		// create query for 5
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bdayshow_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE gender='".$searchTerm."' AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 6 && $searchTerm != "")
	{
		// create query for 6
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE description LIKE '%".$searchTerm."%' AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 7 && $searchTerm != "")
	{
		// create query for 7
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE (favlink LIKE '%".$searchTerm."%' OR favlink1 LIKE '%".$searchTerm."%' OR website LIKE '%".$searchTerm."%') AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 8 && $searchTerm != "")
	{
		// create query for 8
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE birthday LIKE '%".$searchTerm."%' AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 10 && $searchTerm != "")
	{
		// create query for 10
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE (country LIKE '%".$searchTerm."%' OR country_code LIKE '%".$searchTerm."%' OR country_name LIKE '%".$searchTerm."%') AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	elseif($searchCategory == 9 && $searchTerm != "")
	{
		// create query for all categories 9
		$sql = "SELECT username,firstname,lastname,country,email,perms,ip,gender,birthday,show_bday,show_age,showemail,country_code,country_name FROM ".C_REG_TBL." WHERE (username LIKE '%".$searchTerm."%' OR firstname LIKE '%".$searchTerm."%' OR lastname LIKE '%".$searchTerm."%' OR country LIKE '%".$searchTerm."%' OR website LIKE '%".$searchTerm."%' OR ip LIKE '%".$searchTerm."%' OR perms LIKE '%".$searchTerm."%' OR email LIKE '%".$searchTerm."%' OR slang LIKE '%".$searchTerm."%' OR description LIKE '%".$searchTerm."%' OR favlink LIKE '%".$searchTerm."%' OR favlink1 LIKE '%".$searchTerm."%' OR birthday LIKE '%".$searchTerm."%' OR country_code LIKE '%".$searchTerm."%' OR country_name LIKE '%".$searchTerm."%') AND email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%';";
	}
	else
	{
		// Means forgot to specify a search term
		$Warning = A_SEARCH_23;
		if (isset($Warning) && $Warning != "") echo("<P CLASS=\"error\">$Warning</SPAN></P>\n");
		exit;
	}

	$DbLink->query("SELECT username FROM ".C_REG_TBL." WHERE email NOT LIKE '%@bot.com%' AND email NOT LIKE '%@quote.com%' AND username != '".$pmc_username."' ORDER BY username ASC");
	$users = Array();
	while (list($usernames) = $DbLink->next_record())
	{
		$users[] = $usernames;
	}
	$DbLink->clean_results();

	$DbLink->query($sql);
	if ($DbLink->num_rows() == 0) $Warning = A_SEARCH_24;

	// Display search result on screen
	if (isset($Warning) && $Warning != "") echo("<P CLASS=\"error\">".$Warning."</SPAN></P>\n");
	else
	{
		echo "<table align=center border=\"1\" cellpadding=\"1\" cellspacing=\"0\" width=\"98%\" CLASS=table>";
		echo "<tr align=\"center\" class=\"tabtitle\">\n
		<td>&nbsp;</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_13."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".(!(strstr($L,"chinese") || strstr($L,"korean") || strstr($L,"japanese")) ? A_SEARCH_14."</td><td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_15 : A_SEARCH_15."</td><td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_14)."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_16."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_6."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_18."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_19." / ".L_REG_52."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".A_SEARCH_20."</td>\n
		<td style=\"vertical-align:middle; text-align:center;\" class=tabtitle>".L_PRO_7."</td>\n
		</tr>";

		$s_username = "&nbsp;";
		$s_firstname = "&nbsp;";
		$s_lastname = "&nbsp;";
		$s_country = "&nbsp;";
		$s_email = "&nbsp;";
		$s_perms = "&nbsp;";
		$s_ip = "&nbsp;";
		$s_gender = "&nbsp;";
		$s_birthday = "&nbsp;";
		$bannished_user = "";
		$bannished_ip = "";
		$note = 0;
		$DbLinkNew = new DB;
		// GeoIP mode for country flags
		if(!isset($s_country_code) || $s_country_code == "")
		{
			if (!class_exists("GeoIP")) include("plugins/countryflags/geoip.inc");
			if(!isset($gi)) $gi = geoip_open("plugins/countryflags/GeoIP.dat",GEOIP_STANDARD);
		}
	while(list($s_username,$s_firstname,$s_lastname,$s_country,$s_email,$s_perms,$s_ip,$s_gender,$s_birthday,$s_show_bday,$s_show_age,$s_showemail,$s_country_code,$s_country_name) = $DbLink->next_record())
	{
		$s_username = stripslashes($s_username);
		$DbLinkNew->query("SELECT username,reason FROM ".C_BAN_TBL." WHERE username='$s_username' LIMIT 1");
		list($Nb,$reason) = $DbLinkNew->next_record();
		if ($reason != "") $reason = " (".L_HELP_REASON.": ".$reason.")";
		if ($Nb) $bannished_user = "&nbsp;<img src=images/bannished.gif alt='".A_MENU_21.$reason."' title='".A_MENU_21.$reason."'>";
		$usrHash = md5($s_username);
		$a_username = pos_array($s_username,$users);
		if ($s_username != $pmc_username) $s_username = "<a onClick=\"browse_user($a_username);\" target=\"_self\" title=\"".A_SEARCH_25."\">$s_username</a>";
		$s_firstname = stripslashes($s_firstname);
		$s_lastname = stripslashes($s_lastname);
		$s_country = stripslashes($s_country);
		$s_email = stripslashes($s_email);
		$s_perms = stripslashes($s_perms);
		$s_ip = stripslashes($s_ip);
		$s_birthday = stripslashes($s_birthday);
		if (empty($s_firstname)) $s_firstname = "&nbsp;";
		if (empty($s_lastname)) $s_lastname = "&nbsp;";
		if (empty($s_country)) $s_country = "&nbsp;";
		if(!$s_showemail) $note = 1;
		// GeoIP mode for country flags
		if(C_USE_FLAGS)
		{
			if(!isset($s_country_code) || $s_country_code == "")
			{
				$s_country_code = geoip_country_code_by_addr($gi, ltrim($s_ip,"p"));
				if (empty($s_country_code))
				{
					$s_country_code = "LAN";
					$s_country_name = "Other/LAN";
				}
				if ($s_country_code != "LAN") $s_country_name = $gi->GEOIP_COUNTRY_NAMES[$gi->GEOIP_COUNTRY_CODE_TO_NUMBER[$s_country_code]];
				if ($PROXY || substr($s_ip, 0, 1) == "p") $s_country_name .= " (Proxy Server)";
			}
			$s_country_code = stripslashes($s_country_code);
			$s_country_name = stripslashes($s_country_name);
			$s_flag = "&nbsp;<img src=\"./plugins/countryflags/flags/".strtolower($s_country_code).".gif\" alt=\"".$s_country_name."\" title=\"".$s_country_name."\" border=0>&nbsp;(".$s_country_code.")";
		}
		$DbLinkNew->query("SELECT ip,reason FROM ".C_BAN_TBL." WHERE ip='$s_ip' LIMIT 1");
		list($NbIP,$reasonIP) = $DbLinkNew->next_record();
		if ($reasonIP != "") $reasonIP = " (".L_HELP_REASON.": ".$reasonIP.")";
		if ($NbIP) $bannished_ip = "&nbsp;<img src=images/bannished.gif alt='".A_MENU_21.$reasonIP."' title='".A_MENU_21.$reasonIP."'>";
		$s_gender = stripslashes($s_gender);
		if($s_gender == 4) $gender = 'undefined';
		elseif($s_gender == 1) $gender = 'boy';
		elseif($s_gender == 2) $gender = 'girl';
		elseif($s_gender == 3) $gender = 'couple';
		else $gender = 'none';
		if ($s_gender != 0)
		{
			$alt_gender = ($s_gender == 1 ? L_REG_46 : ($s_gender == 2 ? L_REG_47 : ($s_gender == 3 ? L_REG_44 : L_REG_43)));
		}
		else $alt_gender = L_REG_48;
		$s_gender = "<img src=images/gender_".$gender.".gif alt='".$alt_gender."' title='".$alt_gender."'>";
		if (empty($s_birthday) || $s_birthday == "0000-00-00") $s_birthday = "&nbsp;";
		else
		{
			if($s_birthday && $s_birthday != "&nbsp;" && $s_birthday != "0000-00-00") $s_birthday = strftime(L_SHORT_DATE, strtotime($s_birthday));
			if(stristr(PHP_OS,'win'))
			{
				$s_birthday = utf_conv(WIN_DEFAULT,$Charset,$s_birthday);
				if(strstr($L,"chinese") || strstr($L,"korean") || strstr($L,"japanese")) $s_birthday = str_replace(" ","",$s_birthday);
			}
		}
		$checkbox = ($s_username == $pmc_username) ? "&nbsp;" : "<INPUT type=checkbox name=\"selected_$usrHash\" value=\"1\">";
		echo "<tr align=\"center\">\n
		<INPUT TYPE=\"hidden\" NAME=\"user_$usrHash\" VALUE=\"1\">\n
		<TD VALIGN=CENTER ALIGN=CENTER>\n$checkbox\n</td>\n
		<td width=100>$s_username$bannished_user</td>\n
		<td>".(!(strstr($L,"chinese") || strstr($L,"korean") || strstr($L,"japanese")) ? "$s_firstname</td>\n<td>$s_lastname" : "$s_lastname</td>\n<td>$s_firstname")."</td>\n
		<td>$s_country</td>\n
		<td nowrap=\"nowrap\"><a href=\"mailto:$s_email\" target=_blank>$s_email</a>".($s_showemail ? "" : "<font color=\"red\"> *</font>")."</td>\n
		<td>$s_perms</td>\n
		<td align=left nowrap=\"nowrap\">$s_ip".(isset($s_flag) ? $s_flag : "")."$bannished_ip</td>\n
		<td align=center>$s_gender</td>\n
		<td align=left nowrap=\"nowrap\">".$s_birthday.((!$s_show_age || !$s_show_bday) ? ($s_birthday == "&nbsp;" ? "" : "<font color=\"red\"> *</font>") : "")."</td>\n
		</tr>";
		if((!$s_show_age || $s_show_bday) && $s_birthday != "&nbsp;") $note = 1;
		unset($s_username,$s_firstname,$s_lastname,$s_country,$s_email,$s_perms,$s_ip,$s_gender,$s_birthday,$s_show_bday,$s_show_age,$s_ip,$s_country_code,$s_country_name,$s_flag,$gender,$alt_gender);
		$bannished_user = "";
		$bannished_ip = "";
		$DbLinkNew->clean_results();
	}
	// GeoIP Country flags initialization
	if(isset($gi) && $gi != "") geoip_close($gi);
	if(isset($gi6) && $gi6 != "") geoip_close($gi6);
	$DbLinkNew->close();
	$DbLink->close();
	if($note)
	{
	?>
		<tr>
			<td colspan=10 class=error>
				<b>* </b><i><font size="1"><?php echo(A_SEARCH_26); ?></font></i>
			</td>
		</tr>
	<?php
	}
	echo "</table>";
?>
			</TD>
		</TR>
		<TR>
			<TD VALIGN=CENTER ALIGN=CENTER COLSPAN=5>
				<INPUT TYPE="submit" NAME="submit_type" VALUE="<?php echo(A_SHEET1_6); ?>">
			</TD>
		</TR>
		<TR>
			<TD VALIGN=CENTER ALIGN=CENTER COLSPAN=5>
				<INPUT TYPE="submit" NAME="submit_type" VALUE="<?php echo(A_SHEET1_9); ?>"><br />
				<?php echo(A_SHEET1_12); ?>: <INPUT TYPE="text" NAME="reason" VALUE="">
			</TD>
		</TR>
		</TABLE>
		</FORM>
	</P>
<?php
	}
}
?>
<TABLE WIDTH=98% ALIGN=CENTER BORDER=0 CELLPADDING=3 CLASS="table">
<TR>
	<TD ALIGN=CENTER>
<FORM ACTION="<?php echo("$From?$URLQueryBody"); ?>" METHOD="POST" AUTOCOMPLETE="" NAME="Form7">
		<INPUT TYPE=hidden NAME="From" value="<?php echo($From); ?>">
		<INPUT TYPE=hidden NAME="pmc_username" value="<?php echo(htmlspecialchars(stripslashes($pmc_username))); ?>">
		<INPUT TYPE=hidden NAME="pmc_password" value="<?php echo($pmc_password); ?>">
		<INPUT TYPE=hidden NAME="FORM_SEND" value="7">
<table align="center" width="300" CLASS=table>
<tr>
	<td nowrap="nowrap"><b><?php echo A_SEARCH_21; ?>:</b></td>
	<td width="1%"><input name="searchTerm" type="text" size="20"></td>
</tr>
<tr>
	<td bgcolor="#9B9DFF"><b><?php echo A_SEARCH_22; ?>:</b></td>
	<td bgcolor="#9B9DFF">
		<select name="searchCategory">
			<option value="9"><?php echo (A_SEARCH_2) ; ?>
			<option value="1"><?php echo (A_SEARCH_3) ; ?>
			<option value="2"><?php echo (A_SEARCH_4) ; ?>
			<option value="10"><?php echo (A_SEARCH_16) ; ?>
			<option value="3"><?php echo (A_SEARCH_5) ; ?>
			<option value="4"><?php echo (A_SEARCH_6) ; ?>
			<option value="5"><?php echo (A_SEARCH_7) ; ?>
			<option value="6"><?php echo (A_SEARCH_8) ; ?>
			<option value="7"><?php echo (A_SEARCH_9) ; ?>
			<option value="8"><?php echo (L_PRO_7) ; ?>
		</select>

	</td></tr>
<tr>
	<td></td><td align="center"><input type="submit" name="submit_type" value="<?php echo (A_SEARCH_10) ; ?>"></td>
</tr>
</table>
</form>

<table align="center" CLASS=table>
<tr>
	<td>
	&nbsp;&nbsp;<b>**</b>&nbsp;<?php echo (A_SEARCH_11) ; ?><br />
	<b>***</b>&nbsp;<?php echo (A_SEARCH_12) ; ?><br />
</tr>
</table>
</center>
</P>