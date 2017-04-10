<?php
/*
 * Kewlio Looking Glass, klg.php
 *
 * Copyright (c) 2012, Daniel Austin MBCS <daniel@kewlio.net>
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 * 
 *  * Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *  * Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *  * Neither the name of the Daniel Austin MBCS nor the names of its contributors
 *    may be used to endorse or promote products derived from this software without
 *    specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
 * NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 * WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 */

	/* bring in configuration */
	require("klg_config.php");
	require("klg_router_functions.php");
	if ($config["default_form_method"] == "")
		$config["default_form_method"] = "post";

	/* define variables for configurations without register_globals */
	$server = $action = $args = "";
	if (isset($_REQUEST["server"]))
		$server = htmlentities($_REQUEST["server"]);
	if (isset($_REQUEST["action"]))
		$action = htmlentities($_REQUEST["action"]);
	if (isset($_REQUEST["args"]))
		$args = htmlentities($_REQUEST["args"]);
	/* if register_globals is enabled, ensure that we overwrite $error below anyway */
	$error = "";

	/* check for defaults here */
	if ($server=="")
	{
		$args = $_SERVER["REMOTE_ADDR"];
		if (strpos($args, ":")>0)
		{
			/* ipv6 client */
			$server = $config["default_ipv6_router"];
			$action = $config["default_ipv6_command"];
		} else {
			/* ipv4 client */
			$server = $config["default_ipv4_router"];
			$action = $config["default_ipv4_command"];
		}
	}

	/* define our functions here */
	Function DisplayCriticalError($errortext)
	{
		/* display error text and exit application */
		Global $config;
		echo "<HTML>\n";
		echo "<HEAD><TITLE>" . $config["page_title"] . "</TITLE></HEAD>\n";
		echo "<BODY>\n";
		echo "An error occured!<br /><br />\n";
		echo "$errortext\n";
		echo "</BODY>\n";
		echo "</HTML>\n";
		die;
	}

	Function CleanOutput($text)
	{
		/* clean the output of router commands to make them work in our environment */
		Global $config;
		$tmp = "";
		while (strlen($text) > $config["output_max_width"])
		{
			/* add linebreaks in to format output */
			$tmp .= substr($text, 0, $config["output_max_width"]);
			$tmp .= "\n";
			$text = substr($text, $config["output_max_width"], strlen($text));
		}
		$tmp .= $text;
		/* get rid of any double line breaks (just in case) */
		$tmp = str_replace("\n\n","\n",$tmp);
		/* clean output a little more */
		$tmp = str_replace("> quit","&gt; quit", $tmp);
		return $tmp;
	}

	Function ValidateIP($ip)
	{
		/* validate an IP address based on the required action/address family */
		Global $action;
		$tmp = "";
		for ($i=0; $i<strlen($ip); $i++)
		{
			if ($action=="show ip bgp" || $action=="show ip route")
			{
				switch (substr($ip, $i, 1)) {
					case "0": case "1": case "2": case "3":
					case "4": case "5": case "6": case "7":
					case "8": case "9": case ".": case "/":	$tmp .= substr($ip, $i, 1); break;
					default:				break;
				}
			}
			if ($action=="show bgp ipv6 unicast" || $action=="show ipv6 route")
			{
				switch (strtolower(substr($ip, $i, 1))) {
					case "0": case "1": case "2": case "3":
					case "4": case "5": case "6": case "7":
					case "8": case "9": case "a": case "b":
					case "c": case "d": case "e": case "f":
					case ":": case "/":			$tmp .= substr($ip, $i, 1); break;
					default:				break;
				}
			}
			if ($action=="ping" || $action=="traceroute" || $action=="show route")
			{
				switch (strtolower(substr($ip, $i, 1))) {
					case "0": case "1": case "2": case "3":
					case "4": case "5": case "6": case "7":
					case "8": case "9": case "a": case "b":
					case "c": case "d": case "e": case "f":
					case ":": case "/": case ".":		$tmp .= substr($ip, $i, 1); break;
					default:				break;
				}
			}
			if ($action=="show ip bgp regexp" || $action=="show bgp ipv6 unicast regexp")
			{
				switch (substr($ip, $i, 1)) {
					case "0": case "1": case "2": case "3":
					case "4": case "5": case "6": case "7":
					case "8": case "9": case "^": case "$":
					case "_": case " ": case ".":		$tmp .= substr($ip, $i, 1); break;
					default:				break;
				}
			}
		}
		return $tmp;
	}

	Function LookupCommunity($asn, $community)
	{
		/* lookup a Community from our database */
		Global $sql_id, $cache_community, $config;
		if ($config["lookup_communities"] == "0")
			return "";
		if (!isset($cache_community["$asn"]))
		{
			$query = "SELECT `asn`,`community`,`name` FROM `BGPcommunities` WHERE `asn`='$asn' AND `community`='$community'";
			$result = mysqli_query($sql_id, $query);
			if (mysqli_num_rows($result)>0)
			{
				$row = mysqli_fetch_object($result);
				$cname = $row->name;
			} else {
				$cname = "";
			}
		} else {
			if (isset($cache_community["$asn"]))
				$cname = $cache_community["$asn"]["$community"];
			else
				$cname = "";
		}
		return $cname;
	}

	Function LookupRIPEbased($host, $asn)
	{
		/* lookup an AS name via a RIPE-based whois server */
		Global $sql_id, $cache_asnums;
		$fd = @fsockopen($host,43,$errno,$errmsg);
		if ($fd)
		{
			@fwrite($fd, "-T aut-num -r AS$asn\n");
			while ($buf = @fgets($fd, 8192))
			{
				if (substr($buf, 0, 8)=="as-name:")
				{
					$buf = str_replace("\r","",$buf);
					$buf = str_replace("\n","",$buf);
					while (strpos($buf, "\t")>0)
						$buf = str_replace("\t", " ", $buf);
					while (strpos($buf, "  ")>0)
						$buf = str_replace("  ", " ", $buf);
					$asname = substr($buf, strpos($buf, " ")+1, strlen($buf));
					$query = "REPLACE INTO `asnumbers` (`asnum`,`name`,`ts`)VALUES(";
					$query .= "\"$asn\",\"" . AddSlashes($asname) ."\"," . time() . ")";
					$result = mysqli_query($sql_id, $query);
					$cache_asnums["$asn"] = $asname;
				}
			}
			@fclose($fd);
		}
		return $asname;
	}

	Function LookupAS($asn)
	{
		/* lookup an AS and return the result - check local cache first */
		Global $sql_id, $config, $cache_asnums;
		if ($config["lookup_asnumbers"] == "0")
			return $asn;
		if (!isset($cache_asnums["$asn"]))
		{
			$ts = time() - $config["cache_time_asnumbers"];
			$query = "SELECT `asnum`,`name`,`ts` FROM `asnumbers` WHERE `asnum`='$asn' AND (`ts`>=$ts OR `ts`=0)";
			$result = mysqli_query($sql_id, $query);
			if (mysqli_num_rows($result)>0)
			{
				$row = mysqli_fetch_object($result);
				$cache_asnums["$asn"] = $row->name;
				$asname = $row->name;
			} else {
				/* unknown locally - try RIPE */
				$asname = LookupRIPEbased("whois.ripe.net",$asn);
				if ($asname=="")
				{
					/* unknown at RIPE - try ARIN instead */
					$fd2 = @fsockopen("whois.arin.net",43,$errno,$errmsg);
					if ($fd2)
					{
						@fwrite($fd2, "AS$asn\n");
						while ($buf = @fgets($fd2, 8192))
						{
							if (substr($buf, 0, 8)=="ASName: ")
							{
								$buf = str_replace("\r","",$buf);
								$buf = str_replace("\n","",$buf);
								while (strpos($buf, "  ")>0)
									$buf = str_replace("  ", " ", $buf);
								$asname = substr($buf, strpos($buf, " ")+1, strlen($buf));
								if (substr($asname, 0, 6)=="APNIC-")
								{
									/* force an APNIC lookup */
									$asname = "";
								} else {
									$query = "REPLACE INTO `asnumbers` (`asnum`,`name`,`ts`) ";
									$query .= "VALUES(\"$asn\",\"" . AddSlashes($asname) . "\"," . time() . ")";
									$result = mysqli_query($sql_id, $query);
									$cache_asnums["$asn"] = $asname;
								}
							}
						}
						@fclose($fd2);
					}
				}
				if ($asname=="")
				{
					/* unknown at RIPE or ARIN - try APNIC instead */
					$asname = LookupRIPEbased("whois.apnic.net",$asn);
				}
			}
		} else {
			/* cached entry - report local cache name */
			if (isset($cache_asnums["$asn"]))
				$asname = $cache_asnums["$asn"];
			else
				$asname = "";
		}
		return $asname;
	}

	/* Load the template */
	$fd = @fopen($config["page_template"], "r");
	if (!$fd)
	{
		/* can not read the template */
		DisplayCriticalError("Unable to open output template.");
	}
	$klg_template = @fread($fd, 128000);
	@fclose($fd);
	/* do some sanity checks on template */
	if (!strstr($klg_template, "##KLG_TITLE##"))
		DisplayCriticalError("Output template does not contain '##KLG_TITLE##' variable!");
	if (!strstr($klg_template, "##KLG_FORM_START##"))
		DisplayCriticalError("Output template does not contain '##KLG_FORM_START##' variable!");
	if (!strstr($klg_template, "##KLG_ROUTER_LIST##"))
		DisplayCritialError("Output template does not contain '##KLG_ROUTER_LIST##' variable!");
	if (!strstr($klg_template, "##KLG_ACTION_LIST##"))
		DisplayCritialError("Output template does not contain '##KLG_ACTION_LIST##' variable!");
	if (!strstr($klg_template, "##KLG_ARGS##"))
		DisplayCriticalError("Output template does not contain '##KLG_ARGS##' variable!");
	if (!strstr($klg_template, "##KLG_SUBMIT_BUTTON##"))
		DisplayCriticalError("Output template does not contain '##KLG_SUBMIT_BUTTON##' variable!");
	if (!strstr($klg_template, "##KLG_FORM_END##"))
		DisplayCriticalError("Output template does not contain '##KLG_FORM_END##' variable!");
	if (!strstr($klg_template, "##KLG_OUTPUT##"))
		DisplayCriticalError("Output template does not contain '##KLG_OUTPUT##' variable!");
	/* do some extra sanity checks on template (order of variables) */
	if (strpos($klg_template, "##KLG_FORM_END##") < strpos($klg_template, "##KLG_FORM_START##"))
		DisplayCriticalError("Output template can not have '##KLG_FORM_END##' before '##KLG_FORM_START##'!");

	if ($config["mysql_enable"] == "1")
	{
		/* connect to database */
		$sql_id = @mysqli_connect($config["mysql_host"], $config["mysql_user"], $config["mysql_pass"], $config["mysql_db"]);
		if (!$sql_id)
		{
			/* could not connect */
			$error .= "Unable to connect to SQL (" . mysqli_error($sql_id) . ")<br />\n";
		}
	} else {
		/* override some other config variables to disable lookups */
		$config["lookup_asnumbers"] = "0";
		$config["lookup_communities"] = "0";
	}

	/* final validation checks */
	$i = 0;
	$found_router = 0;
	$enable_juniper_commands = 0;
	while ((isset($routers[$i])) && ($routers[$i]["id"] != ""))
	{
		if ($routers[$i]["type"] == "juniper")
			$enable_juniper_commands = 1;
		if ($routers[$i]["id"] == $server)
		{
			/* this is the router we're interested in! */
			$found_router = 1;
			$lg_server = $routers[$i]["host"];
			$lg_port = $routers[$i]["bgpd_port"];
			if ($routers[$i]["type"] == "cisco" || $routers[$i]["type"] == "juniper")
				$lg_port2 = $lg_port;
			else
				$lg_port2 = $routers[$i]["zebra_port"];
			$lg_login = $routers[$i]["login"];
			$lg_type = $routers[$i]["type"];
			$lg_ssh = $routers[$i]["bgpd_ssh"];
			$lg_ssh_user = $routers[$i]["ssh_user"];
			$lg_ssh_pass = $routers[$i]["ssh_pass"];
			$flags = $routers[$i]["flags"];
			/* check the the action is valid for this router */
			/* check global commands first */
			if (($config["allow_ipv4_commands"] != "1") && (substr($action,0,8)=="show ip "))
			{
				$error .= "IPv4 commands are not available on this looking glass<br />\n";
			}
			if (($config["allow_ipv6_commands"] != "1") && ((substr($action,0,9)=="show bgp ") || (substr($action,0,10)=="show ipv6 ")))
			{
				$error .= "IPv6 commands are not available on this looking glass<br />\n";
			}
			if ($config["allow_ping"] != "1" && $action=="ping")
			{
				$error .= "The PING command is not available on this looking glass<br />\n";
			}
			if ($config["allow_traceroute"] != "1" && $action=="traceroute")
			{
				$error .= "The TRACEROUTE command is not available on this looking glass<br />\n";
			}
			if ($config["allow_show_interface"] != "1" && $action=="show interface")
			{
				$error .= "The 'SHOW INTERFACE' command is not available on this looking glass<br />\n";
			}
			if ($config["allow_environmental"] != "1" && $action=="show environment all")
			{
				$error .= "The 'SHOW ENVIRONMENT ALL' command is not available on this looking glass<br />\n";
			}
			/* ok, global checks done - do local router checks - these are not really errors, but report them anyway */
			if (($flags & $flag_allow_ipv4) == 0)
			{
				/* this router does not allow ipv4 commands */
				if (substr($action,0,8)=="show ip ")
					$error .= "The selected router ($server) does not support IPv4 commands<br />\n";
			}
			if (($flags & $flag_allow_ipv6) == 0)
			{
				/* this router does not allow ipv6 commands */
				if ((substr($action,0,9)=="show bgp ") || (substr($action,0,10)=="show ipv6 "))
					$error .= "The selected router ($server) does not support IPv6 commands<br />\n";
			}
			if ((($flags & $flag_allow_ping) == 0) && ($action=="ping"))
				$error .= "The selected router ($server) does not support the PING command<br />\n";
			if ((($flags & $flag_allow_traceroute) == 0) && ($action=="traceroute"))
				$error .= "The selected router ($server) does not support the TRACEROUTE command<br />\n";
			if ((($flags & $flag_allow_show_interface) == 0) && ($action=="show interface"))
				$error .= "The selected router ($server) does not support the 'SHOW INTERFACE' command<br />\n";
			if ((($flags & $flag_allow_environmental) == 0) && ($action=="show environment all"))
				$error .= "The selected router ($server) does not support the 'SHOW ENVIRONMENT ALL' command<br />\n";
			if ((($flags & $flag_allow_dampening) == 0) && (strpos($action,"dampening")>0))
				$error .= "The selected router ($server) does not support the '" . strtoupper($action) . " command<br />\n";
			/* specific commands now */
			switch ($action) {
				case "show route":		if ((($flags & $flag_allow_junos_show_route) == 0) || ($lg_type != "juniper"))
									$error .= "The selected router ($server) does not support the 'show route' command<br />\n";
								break;
				case "show ip bgp":		if (($flags & $flag_allow_show_ip_bgp) == 0)
									$error .= "The selected router ($server) does not support the 'show ip bgp' command<br />\n";
								break;
				case "show ip bgp summary":	if (($flags & $flag_allow_show_ip_bgp_summary) == 0)
									$error .= "The selected router ($server) does not support the 'show ip bgp summary' command<br />\n";
								break;
				case "show ip route":		if (($flags & $flag_allow_show_ip_route) == 0)
									$error .= "The selected router ($server) does not support the 'show ip route' command<br />\n";
								break;
				case "show ip bgp regexp":	if (($flags & $flag_allow_show_ip_bgp_regexp) == 0)
									$error .= "The selected router ($server) does not support the 'show ip bgp regexp' command<br />\n";
								break;
				case "show bgp ipv6 unicast":	if (($flags & $flag_allow_show_bgp_ipv6) == 0)
									$error .= "The selected router ($server) does not support the 'show bgp ipv6 unicast' command<br />\n";
								break;
				case "show bgp ipv6 unicast summary":	if (($flags & $flag_allow_show_bgp_summary) == 0)
									$error .= "The selected router ($server) does not support the 'show bgp ipv6 unicast summary' command<br />\n";
								break;
				case "show ipv6 route":		if (($flags & $flag_allow_show_ipv6_route) == 0)
									$error .= "The selected router ($server) does not support the 'show ipv6 route' command<br />\n";
								break;
				case "show bgp ipv6 unicast regexp":	if (($flags & $flag_allow_show_bgp_ipv6_regexp) == 0)
									$error .= "The selected router ($server) does not support the 'show bgp ipv6 unicast regexp' command<br />\n";
								break;
				default:			break;
			}
		}
		$i++;
	}

	if ($found_router==0)
		$error .= "The router requested ($server) was not found in my configuration file!<br />\n";

	/* main program below */
	$args = ValidateIP($args);
	$cache_asnums = Array();
	$cache_community = Array();

	/* output routers here */
	$klg_router_list = "<select name=\"server\">\n";
	$i = 0;
	while (isset($routers[$i]) && ($routers[$i]["id"] != ""))
	{
		$klg_router_list .= "<option value=\"" . $routers[$i]["id"] . "\"";
		if ($server==$routers[$i]["id"])
			$klg_router_list .= " selected=\"selected\"";
		$klg_router_list .= ">" . $routers[$i]["name"] . "</option>\n";
		$i++;
	}
	$klg_router_list .= "</select>";

	$klg_action_list = "<select name=\"action\">\n";

	/* check if each command is allowed */
	if ($config["allow_ipv4_commands"] == "1")
	{
		$klg_action_list .= "<option class=\"box1\" value=\"\"";
		if ($action == "")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">-- IPv4 --</option>\n";

		if ($config["allow_show_ip_bgp"] == "1")
		{
			$klg_action_list .= "<option class=\"box1\" value=\"show ip bgp\"";
			if ($action == "show ip bgp")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ip bgp &lt;ip&gt;</option>\n";
		}

		if ($config["allow_show_ip_bgp_summary"] == "1")
		{
			$klg_action_list .= "<option class=\"box1\" value=\"show ip bgp summary\"";
			if ($action == "show ip bgp summary")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ip bgp summary</option>\n";
		}

		if ($config["allow_show_ip_route"] == "1")
		{
			$klg_action_list .= "<option class=\"box1\" value=\"show ip route\"";
			if ($action == "show ip route")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ip route &lt;ip&gt;</option>\n";
		}

		if ($config["allow_show_ip_bgp_regexp"] == "1")
		{
			$klg_action_list .= "<option class=\"box1\" value=\"show ip bgp regexp\"";
			if ($action == "show ip bgp regexp")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ip bgp regexp &lt;expression&gt;</option>\n";
		}

		if ($config["allow_dampening"] == "1")
		{
			$klg_action_list .= "<option class=\"box1\" value=\"show ip bgp dampening dampened-paths\"";
			if ($action == "show ip bgp dampening dampened-paths")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ip bgp dampening dampened-paths</option>\n";

			$klg_action_list .= "<option class=\"box1\" value=\"show ip bgp dampening flap-statistics\"";
			if ($action == "show ip bgp dampening flap-statistics")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ip bgp dampening flap-statistics</option>\n";
		}
	}

	if ($config["allow_ipv6_commands"] == "1")
	{
		$klg_action_list .= "<option class=\"box2\" value=\"\"";
		if ($action == "")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">-- IPv6 --</option>\n";

		if ($config["allow_show_bgp_ipv6"] == "1")
		{
			$klg_action_list .= "<option class=\"box2\" value=\"show bgp ipv6 unicast\"";
			if ($action == "show bgp ipv6 unicast")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show bgp ipv6 unicast &lt;ip/prefixlen&gt;</option>\n";
		}

		if ($config["allow_show_bgp_summary"] == "1")
		{
			$klg_action_list .= "<option class=\"box2\" value=\"show bgp ipv6 unicast summary\"";
			if ($action == "show bgp ipv6 unicast summary")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show bgp ipv6 unicast summary</option>\n";
		}

		if ($config["allow_show_ipv6_route"] == "1")
		{
			$klg_action_list .= "<option class=\"box2\" value=\"show ipv6 route\"";
			if ($action=="show ipv6 route")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show ipv6 route &lt;ip/prefixlen&gt;</option>\n";
		}

		if ($config["allow_show_bgp_ipv6_regexp"] == "1")
		{
			$klg_action_list .= "<option class=\"box2\" value=\"show bgp ipv6 unicast regexp\"";
			if ($action == "show bgp ipv6 unicast regexp")
				$klg_action_list .= " selected=\"selected\"";
			$klg_action_list .= ">show bgp ipv6 unicast regexp &lt;expression&gt;</option>\n";
		}
	}

	if ($enable_juniper_commands==1)
	{
		$klg_action_list .= "<option class=\"box3\" value=\"\"";
		if ($action == "")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">-- Juniper commands --</option>\n";

		$klg_action_list .= "<option class=\"box3\" value=\"show route\"";
		if ($action == "show route")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">show route &lt;ip/prefix&gt; detail</option>\n";
	}

	if (($config["allow_ping"] == "1") || ($config["allow_show_interface"] == "1") ||
		($config["allow_traceroute"] == "1"))
	{
		$klg_action_list .= "<option class=\"box3\" value=\"\"";
		if ($action == "")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">-- Non-specific --</option>\n";
	}

	if ($config["allow_ping"] == "1")
	{
		$klg_action_list .= "<option class=\"box3\" value=\"ping\"";
		if ($action == "ping")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">ping &lt;ip&gt;</option>\n";
	}

	if ($config["allow_traceroute"] == "1")
	{
		$klg_action_list .= "<option class=\"box3\" value=\"traceroute\"";
		if ($action == "traceroute")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">traceroute &lt;ip&gt;</option>\n";
	}

	if ($config["allow_show_interface"] == "1")
	{
		$klg_action_list .= "<option class=\"box3\" value=\"show interface\"";
		if ($action == "show interface")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">show interface</option>\n";
	}

	if ($config["allow_environmental"] == "1")
	{
		$klg_action_list .= "<option class=\"box3\" value=\"show environment all\"";
		if ($action == "show environment all")
			$klg_action_list .= " selected=\"selected\"";
		$klg_action_list .= ">show environment all</option>\n";
	}
	$klg_action_list .= "</select>";
	$klg_args = "<input type=\"text\" name=\"args\" value=\"$args\" />";
	$klg_submit_button = "<input type=\"submit\" value=\"Query\" />";
	$klg_form_start = "<form action=\"" . $_SERVER["PHP_SELF"] . "\" method=\"" . $config["default_form_method"] . "\">\n";
	$klg_form_end = "</form>";
	$klg_output_footer = "<br /><span style=\"font-size: 1;\">";
        $klg_output_footer .= "// \$Id: klg.php,v 1.23 2015/10/25 18:57:51 danielaustin Exp $ //<br />\n";
	$klg_output_footer .= "Source code available at: <a href=\"http://sourceforge.net/projects/klg/\">";
	$klg_output_footer .= "http://sourceforge.net/projects/klg/</a> - Author: ";
	$klg_output_footer .= "<a href=\"https://www.dan.me.uk/\">Daniel Austin MBCS</a></span><br /><br />\n";

	/* check if we need to lookup */
	if ($config["log_queries"] == "1")
	{
	        $logfd = @fopen($config["log_file"],"a");
		if (!$logfd)
		{
			/* can't open log file for writing */
			$error .= "Unable to open logfile for writing (check permissions!)<br />\n";
		}
	        $datestamp = date("d-m-Y H:i:s");
	        @fwrite($logfd, "$datestamp | ");
	        @fwrite($logfd, $_SERVER["REMOTE_ADDR"]);
	        @fwrite($logfd, " | $server | $action $args\n");
	        @fclose($logfd);
	}

	/* ok, do variable replacements */           
	$output = $klg_template;
	$output = str_replace("##KLG_TITLE##",$config["page_title"],$output);
	$output = str_replace("##KLG_FORM_START##",$klg_form_start,$output);
	$output = str_replace("##KLG_FORM_END##",$klg_form_end,$output);
	$output = str_replace("##KLG_ROUTER_LIST##",$klg_router_list,$output);
	$output = str_replace("##KLG_ACTION_LIST##",$klg_action_list,$output);
	$output = str_replace("##KLG_ARGS##",$klg_args,$output);
	$output = str_replace("##KLG_SUBMIT_BUTTON##",$klg_submit_button,$output);

	$klg_output = "";

	/* if we have any errors, display them NOW before processing any requests */
	if ($error != "")
	{
		$klg_output .= "Sorry! An error has occured (see below)<br /><br />\n";
		$klg_output .= "<span style=\"color: red;\">\n";
		$klg_output .= $error;
		$klg_output .= "</span>\n";
		$klg_output .= $klg_output_footer;
		$output = str_replace("##KLG_OUTPUT##",$klg_output,$output);
		echo $output;
		die;
	}

	switch ($lg_type) {
		case "juniper":		/* juniper routers */
					if ($action=="show bgp ipv6 unicast summary" || $action=="traceroute")
						KLG_Router_Generic_Command("$action $args");
					if ($action=="show route")
						KLG_Router_Juniper_BGP_Formatted_Command("$action $args detail");
					break;
		case "cisco":
		case "zebra":
		case "quagga":		/* cisco/zebra/quagga routers */
					if ($action=="show ip bgp summary" || $action=="show bgp ipv6 unicast summary" ||
						(($action=="show ip bgp regexp" || $action=="show bgp ipv6 unicast regexp") && ($args!="")))
					{
						$cmd = $action;
						if ($action=="show ip bgp regexp" || $action=="show bgp ipv6 unicast regexp")
							$cmd .= " $args";

						KLG_Router_Generic_BGP_Command($cmd);
					}

					if ($action=="show interface" || $action=="show environment all" ||
						$action=="show ip bgp dampening dampened-paths" ||
						$action=="show ip bgp dampening flap-statistics" ||
						(($action=="show ip route" || $action=="show ipv6 route" ||
							$action=="ping" || $action=="traceroute") && $args!="") )
					{
						$cmd = $action;
						if ($action=="show ip route" || $action=="show ipv6 route" ||
							$action=="ping" || $action=="traceroute")
							$cmd .= " $args";

						KLG_Router_Generic_Command($cmd);
					}

					if (($action=="show ip bgp" || $action=="show bgp ipv6 unicast") && ($args!=""))
					{
						KLG_Router_BGP_Formatted_Command("$action $args");
					}
					break;
		default:		/* unknown router type */
					break;
	}

	/* add footer to output and display */
	$klg_output .= $klg_output_footer;
	$output = str_replace("##KLG_OUTPUT##",$klg_output,$output);
	echo $output;
?>
