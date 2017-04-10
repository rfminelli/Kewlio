
Welcome to the Kewlio Looking Glass
===================================

0. REQUIREMENTS
	Web-server capable of executing PHP code (e.g. Apache)
	PHP 5.4+ (tested up to and including 5.6.x)
	PHP modules needed (some built-in these days):
		mysqli (optional)
		pcre
		sockets
		spl
		pecl-ssh2 (optional, required for ssh access)
	(optional) MySQL server for friendly name lookups

	Firewall requirements:
	The looking glass needs to communicate with your routers via telnet.
	It will also do lookups via WHOIS protocol where possible.

	You will require outbound connections on TCP ports 23 and 43.

1. QUICK INSTALL
	a) extract all files into your PHP-enabled webspace
	b) create a mysql user of your choice
	c) create a mysql db of your choice.  ensure the user above has the
	   full access control for the db.
	d) import the sql scheme into the mysql db as follows:
		mysql --user=username --password=password database < klg_schema.sql
	   where username, password and database are substituted for the details
	   in steps 1b and 1c.
	e) configure the looking glass configuration file, klg_config.php
	   You will need to configure at minimum the mysql details, page name and
	   of course add one or more routers!
	f) if you have enabled logging, please create the file and ensure it
	   is writable by the webserver/php user, as follows:
		touch klg.log; chown www klg.log
	g) go to http://www.your.url/klg.php and test the looking glass!

	If you do not wish to use MySQL, you can set the relevant configuration
	variable and skip steps b, c and d above.

2. BGP COMMUNITY NAMES
	The looking glass makes it possible to translate BGP communities into
	human readable textual format communities.  This is done MANUALLY as
	no unified method of looking up these has been identified.
	To add a community, you have to enter a record into the "BGPcommunities"
	table manually.  This can be done on the command line as follows:

		mysql --user=username --password=password database

	This will bring you into the mysql interactive mode (type "quit" to exit)
	As an example, we will add the community 3344:62999 (Kewlio.net null-route)

		mysql> INSERT INTO BGPcommunities VALUES("3344","62999","NULL-ROUTE");

	The "mysql>" prompt is already supplied for you.  MySQL should respond with
	a line indicating "OK" or Success (it is normal for it to say 0 lines
	affected)

	Repeat the INSERT command for each community you wish to add.  Communities
	added via this method do not expire.

	If you do not wish to use this feature, you can disable it in klg_config.php

3. AS NAMES
	Autonomous system numbers are automatically looked up and translated for
	you via this looking glass.  The looking glass will look up via WHOIS
	interface in the following order:

		RIPE,ARIN,APNIC

	When a match is found, it is entered into the local database and cached
	for 30 days (or any other time as configured via the configuration file)
	The looking glass first checks the local database for AS names to avoid
	excessive lookups.

	The looking glass uses non-recursive lookups with RIPE-based WHOIS
	servers to avoid getting automatically blocked for excessive lookups.

	If you wish to override an AS Name, you can modify the record in the
	"asnumbers" table.  To stop an overridden entry from expiring, set the
	"ts" field to "0" for any records you wish to override.

	If you do not wish to use this feature, you can disable it in klg_config.php

4. ROUTER PERMISSIONS
	This section details the router permissions in the configuration file.
	Some people may find the permissions confusing, this section tries to
	make this more understandable.

	Each router has a "flags" variable, which is a number (see below)

		$routers[0]["flags"] = 13;

	The number 32767 demonstrates the flags allowed for this router.  This
	number is calculated by the sum of all the permissions from the "flag_allow_"
	variables.
	The above example is comprised of the following flags:

		$flag_allow_ipv4		1
		$flag_allow_show_ip_bgp		4
		$flag_allow_show_ip_bgp_summary	8
		                                --
		TOTAL				13

	At the time of writing, the sum of all flags is 65535.

5. HTML OUTPUT TEMPLATE
	The looking glass allows you to format the output in (almost) any format.
	The template file is described in the klg_config.php file as follows:

		$config["page_template"] = "klg_template.html";

	The contents of the template are very basic, with all variables denoted
	with '%%' before and after the variable name.  ALL variables are required
	in the template and some simple sanity checking is used.

	The variable names should be self explanatory.

	The template may not contain any PHP code.

	The following variable may be set in klg_config.php to set max line length:

		$config["output_max_width"] = 124;

