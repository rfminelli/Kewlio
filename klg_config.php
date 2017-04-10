<?php	/* Kewlio Looking Glass -- Example Configuration */

/* SQL connection details */
$config["mysql_enable"] = "1";
$config["mysql_host"] = "localhost";
$config["mysql_user"] = "USERNAME";
$config["mysql_pass"] = "PASSWORD";
$config["mysql_db"] = "DATABASE";

/* Output configuration */
$config["page_template"] = "klg_template.html";
$config["page_title"] = "Company Name [ASxxxxx] -- Looking Glass";
$config["output_max_width"] = 124;
$config["output_font"] = "\"Courier\", \"Fixedsys\", \"OCR Extended A\"";

/* external whois lookup configuration */
$config["lookup_asnumbers"] = "1";
$config["lookup_communities"] = "1";
$config["cache_time_asnumbers"] = (30 * 86400);			/* expire AS names every 30 days */

/* Allowed commands (Global level) */
$config["allow_ipv4_commands"] = "1";
$config["allow_ipv6_commands"] = "1";

/* ipv4 commands (if allow_ipv4_commands is enabled above) */
$config["allow_show_ip_bgp"] = "1";
$config["allow_show_ip_bgp_summary"] = "1";
$config["allow_show_ip_route"] = "1";
$config["allow_show_ip_bgp_regexp"] = "1";
$config["allow_dampening"] = "1";

/* ipv6 commands (if allow_ipv6_commands is enabled above) */
$config["allow_show_bgp_ipv6"] = "1";
$config["allow_show_bgp_summary"] = "1";
$config["allow_show_ipv6_route"] = "1";
$config["allow_show_bgp_ipv6_regexp"] = "1";

/* non-specific commands */
$config["allow_ping"] = "1";
$config["allow_traceroute"] = "1";
$config["allow_show_interface"] = "1";
$config["allow_environmental"] = "1";

/* Logging configuration */
$config["log_file"] = "klg.log";
$config["log_queries"] = "1";

/* default servers and commands (based on client connection type) */
$config["default_ipv4_router"] = "router1";
$config["default_ipv4_command"] = "show ip bgp";
$config["default_ipv6_router"] = "router2";
$config["default_ipv6_command"] = "show bgp ipv6";
$config["default_form_method"] = "GET";

/* flag definitions - DO NOT CHANGE THESE! */
$flag_allow_ipv4			= 1;
$flag_allow_ipv6			= 2;
$flag_allow_show_ip_bgp			= 4;
$flag_allow_show_ip_bgp_summary		= 8;
$flag_allow_show_ip_route		= 16;
$flag_allow_show_ip_bgp_regexp		= 32;
$flag_allow_dampening			= 64;
$flag_allow_show_bgp_ipv6		= 128;
$flag_allow_show_bgp_summary		= 256;
$flag_allow_show_ipv6_route		= 512;
$flag_allow_show_bgp_ipv6_regexp	= 1024;
$flag_allow_ping			= 2048;
$flag_allow_traceroute			= 4096;
$flag_allow_show_interface		= 8192;
$flag_allow_environmental		= 16384;
$flag_allow_junos_show_route		= 32768;

/* router definitions */
$routers[0]["id"] = "router1";
$routers[0]["name"] = "router1 -- example zebra router";
$routers[0]["host"] = "10.0.0.1";
$routers[0]["type"] = "zebra";
$routers[0]["bgpd_port"] = "2605";
$routers[0]["zebra_port"] = "2601";
$routers[0]["bgpd_ssh"] = false;			/* no SSH2 for zebra/quagga routers */
$routers[0]["ssh_user"] = "usernamehere";
$routers[0]["ssh_pass"] = "passwordhere";
$routers[0]["login"] = "passwordhere";			/* ssh_user/pass for SSH, login for telnet access */
// $routers[0]["flags"] = $flag_allow_ipv4;		/* allow ipv4 commands */
// $routers[0]["flags"] += $flag_allow_ipv6;		/* add ipv6 commands, repeat as necessary */
// $routers[0]["flags"] = 32767;			/* all flags (numerically from above) */
$routers[0]["flags"] = 10239;				/* all but ping, traceroute, environmental */

$routers[1]["id"] = "router2";
$routers[1]["name"] = "router2 -- example cisco router";
$routers[1]["host"] = "172.31.255.254";
$routers[1]["type"] = "cisco";
$routers[1]["bgpd_port"] = "23";			/* see bgpd_ssh setting below for ssh/telnet option */
// $routers[1]["zebra_port"] = "23";			/* not needed for cisco routers */
$routers[1]["bgpd_ssh"] = true;				/* true=SSH2, false=TELNET */
$routers[1]["ssh_user"] = "usernamehere";
$routers[1]["ssh_pass"] = "passwordhere";
$routers[1]["login"] = "username\npassword";		/* for AAA auth, use 'username\npassword' here */
$routers[1]["flags"] = 32767;				/* all flags */

$routers[2]["id"] = "router3";
$routers[2]["name"] = "router3 -- example juniper router";
$routers[2]["host"] = "192.168.254.73";
$routers[2]["type"] = "juniper";
$routers[2]["bgpd_port"] = "23";			/* see bgpd_ssh setting below for ssh/telnet option */
$routers[2]["zebra_port"] = "23";			/* not needed for juniper routers */
$routers[2]["bgpd_ssh"] = true;				/* true=SSH2, false=TELNET */
$routers[2]["ssh_user"] = "usernamehere";
$routers[2]["ssh_pass"] = "passwordhere";
$routers[2]["login"] = "username\npassword";		/* needed for juniper auth, user 'username\npassword' here */
$routers[2]["flags"] = 37122;				/* ipv6 + show bgp summary */

/* $Id: klg_config.php,v 1.9 2015/10/25 18:57:51 danielaustin Exp $ */
?>
