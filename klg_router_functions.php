<?php
/*
 * Kewlio Looking Glass, klg_router_functions.php
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

	Function KLG_Execute_Command($cmd, $alt_port = false)
	{
		Global $ssh_id, $lg_server, $lg_port, $lg_port2, $lg_type, $lg_login, $lg_ssh, $lg_ssh_user, $lg_ssh_pass, $config;

		$ssh_id = $fd = -1;

		if ($lg_ssh)
		{
			/* ssh session */
			$ssh_id = @ssh2_connect($lg_server,$lg_port);
			if (!$ssh_id)
				return $fd;
			if (!@ssh2_auth_password($ssh_id, $lg_ssh_user, $lg_ssh_pass))
				return $fd;
			$fd = @ssh2_shell($ssh_id, 'xterm', null, 200, 200, SSH2_TERM_UNIT_CHARS);
			stream_set_blocking($fd, true);
		} else {
			/* telnet session */
			$fd = @fsockopen($lg_server,$alt_port?$lg_port2:$lg_port,$errno,$errmsg);
			if ($fd)
			{
				KLG_Send_Telnet_Negotiation($fd);
				@fwrite($fd, "$lg_login\n");
			}
		}
		if ($fd)
		{
			if ($lg_type=="juniper")
				@fwrite($fd, "set cli screen-length 0\nset cli prompt cli>\n$cmd | no-more\nexit\n");
			else
				@fwrite($fd, "terminal length 0\n$cmd\nquit\n");
		}
		/* return the stream resource back */
		return $fd;
	}

	Function KLG_Send_Telnet_Negotiation($fd)
	{
		/* send negotiation information to $fd socket */
		fputs($fd,chr(0xFF).chr(0xFB).chr(0x1F).chr(0xFF).chr(0xFB).
		chr(0x20).chr(0xFF).chr(0xFB).chr(0x18).chr(0xFF).chr(0xFB).
		chr(0x27).chr(0xFF).chr(0xFD).chr(0x01).chr(0xFF).chr(0xFB).
		chr(0x03).chr(0xFF).chr(0xFD).chr(0x03).chr(0xFF).chr(0xFC).
		chr(0x23).chr(0xFF).chr(0xFC).chr(0x24).chr(0xFF).chr(0xFA).
		chr(0x1F).chr(0x00).chr(0x50).chr(0x00).chr(0x18).chr(0xFF).
		chr(0xF0).chr(0xFF).chr(0xFA).chr(0x20).chr(0x00).chr(0x33).
		chr(0x38).chr(0x34).chr(0x30).chr(0x30).chr(0x2C).chr(0x33).
		chr(0x38).chr(0x34).chr(0x30).chr(0x30).chr(0xFF).chr(0xF0).
		chr(0xFF).chr(0xFA).chr(0x27).chr(0x00).chr(0xFF).chr(0xF0).
		chr(0xFF).chr(0xFA).chr(0x18).chr(0x00).chr(0x58).chr(0x54).
		chr(0x45).chr(0x52).chr(0x4D).chr(0xFF).chr(0xF0));
		usleep(125000);

		fputs($fd,chr(0xFF).chr(0xFC).chr(0x01).chr(0xFF).chr(0xFC).
		chr(0x22).chr(0xFF).chr(0xFE).chr(0x05).chr(0xFF).chr(0xFC).chr(0x21));
		usleep(125000);

		return;
	}

	Function KLG_Router_Generic_BGP_Command($cmd)
	{
		/* run a generic BGP command (output all data after "BGP") */
		Global $lg_server, $lg_port, $lg_type, $lg_login, $lg_ssh, $lg_ssh_user, $lg_ssh_pass, $klg_output, $config;

		$klg_output .= "<pre><span style=\"font-family: " . $config["output_font"] . "; font-size: 2; color: black;\">";
		$fd = KLG_Execute_Command($cmd);
		if ($fd)
		{
			$start = 0;
			while ($buf = @fgets($fd, 8192))
			{
				$buf = str_replace("\r","",$buf);
				$buf = str_replace("\n","",$buf);
				if ($start==0)
				{
					if (substr($buf, 0, 4)=="BGP ")
					{
						$start = 1;
						$klg_output .= CleanOutput(htmlentities($buf) . "\n");
					}
				} else {
					$klg_output .= CleanOutput(htmlentities($buf) . "\n");
				}
			}
		}
		fclose($fd);
		$klg_output .= "</span></pre>\n";
	}

	Function KLG_Router_Generic_Command($cmd)
	{
		/* Run a generic command, output raw data */
		Global $lg_server, $lg_port2, $lg_type, $lg_login, $lg_ssh, $lg_ssh_user, $lg_ssh_pass, $klg_output, $config;

		$klg_output .= "<pre><span style=\"font-family: " . $config["output_font"] . "; font-size: 2; color: black;\">";
		$fd = KLG_Execute_Command($cmd, true);
		if ($fd)
		{
			if ($lg_type=="juniper")
			{
				while ($buf = @fgets($fd, 8192))
				{
					if (strpos($buf, "cli>") > 0)
						break;
				}
			}
			$start = 0;
			while ($buf = @fgets($fd, 8192))
			{
				$buf = str_replace("\r","",$buf);
				$buf = str_replace("\n","",$buf);
				if ($start==0)
				{
					if (strpos($buf, ">")>0)
						$start = 1;
				} else {
					if ($lg_type != "juniper")
					{
						if (strpos($buf, "uccess")>0)
							@fwrite($fd, "quit\n");
						if (strpos($buf, ">")>0)
							@fwrite($fd, "quit\n");
					}
					if (substr($buf, -4, 4) == "exit")
						continue;
					$klg_output .= CleanOutput(htmlentities($buf) . "\n");
				}
			}
		}
		fclose($fd);
		$klg_output .= "</span></pre>\n";
	}

	Function KLG_Router_Juniper_BGP_Formatted_Command($cmd)
	{
		/* Run a BGP command, format the output (JunOS) */
		Global $lg_server, $lg_port, $lg_login, $lg_ssh, $lg_ssh_user, $lg_ssh_pass, $klg_output, $config;

		$klg_output .= "<pre><span style=\"font-family: " . $config["output_font"] . "; font-size: 2; color: black;\">";
		$fd = KLG_Execute_Command($cmd);
		if ($fd)
		{
			$start = 0;
			$bestpath = 0;
			while ($buf = @fgets($fd, 8192))
			{
				$buf = str_replace("\r","",$buf);
				$buf = str_replace("\n","",$buf);
				if ($start==0)
				{
					if (strpos($buf, $cmd)>0)
					{
						$start = 1;
						@fwrite($fd, "exit\n");
					}
				} else {
					if (strpos($buf, "Preference")>0)
					{
						if ((strpos($buf, "*BGP")>0 || strpos($buf, "*Static")) && ($bestpath==0))
						{
							/* bestpath */
							$bestpath = 1;
							$klg_output .= "<span style=\"color: red;\">";
							$klg_output .= CleanOutput(htmlentities($buf) . "\n");
							continue;
						}
						if ((strpos($buf, "BGP")>0 || strpos($buf, "Static")) && ($bestpath==1))
						{
							/* end of bestpath, got a new bestpath */
							$bestpath = 0;
							$klg_output .= "</span>";
							$klg_output .= CleanOutput(htmlentities($buf) . "\n");
							continue;
						}
					}
					if ((strpos($buf, "announced)")>0) && ($bestpath==1))
					{
						/* new prefix, reset bestpath variable */
						$bestpath = 0;
						$klg_output .= "</span>";
					}
					if (strpos($buf, "AS path: ")>0)
					{
						/* do AS path translation */
						$tmpout = "";
						$tmp = substr($buf, strpos($buf, ":")+2, strlen($buf));
						$asnums = preg_split("/ /", $tmp);
						for ($i=0; $i<count($asnums); $i++)
						{
							/* ensure as number is valid */
							$temp = $asnums[$i];
							settype($temp, "integer");
							if ($temp==$asnums[$i])
								$as_name = LookupAS($asnums[$i]);
							else
								$as_name = "";
							if ($as_name!="")
								$asname = "$as_name [" . $asnums[$i] . "]";
							else
								$asname = $asnums[$i];
							$tmpout .= $asname . " ";
						}
						while (substr($tmpout, -1, 1)==" ")
							$tmpout = substr($tmpout, 0, strlen($tmpout)-1);
						$tmpout = substr($buf, 0, strpos($buf, ":")+2) . $tmpout;
						$klg_output .= CleanOutput(htmlentities($tmpout) . "\n");
						continue;
					}
					if (strpos($buf, "Communities: ")>0)
					{
						/* do community lookups */
						$tmpout = "";
						$tmp = substr($buf, strpos($buf, ":")+2, strlen($buf));
						$comms = preg_split("/ /", $tmp);
						for ($i=0; $i<count($comms); $i++)
						{
							$community = preg_split("/\:/", $comms[$i]);
							$tmp3 = LookupCommunity($community[0], $community[1]);
							if ($tmp3=="")
							{
								$tmpout .= " " . $comms[$i];
							} else {
								$tmpout .= " " . LookupAS($community[0]) . ":" . $tmp3 . " [" . $comms[$i] . "]";
							}
						}
						if (substr($tmpout, 0, 1)==" ")
							$tmpout = substr($tmpout, 1, strlen($tmpout));
						$tmpout = substr($buf, 0, strpos($buf, ":")+2) . $tmpout;
						$klg_output .= CleanOutput(htmlentities($tmpout) . "\n");
						continue;
					}
					$klg_output .= CleanOutput(htmlentities($buf) . "\n");
				}
			}
		}
		/* if we end on a bestpath, close span tag */
		if ($bestpath==1)
			$klg_output .= "</span>";
		$klg_output .= "</span></pre>\n";
	}

	Function KLG_Router_BGP_Formatted_Command($cmd)
	{
		/* Run a BGP command, format the output */
		Global $lg_server, $lg_port, $lg_login, $lg_ssh, $lg_ssh_user, $lg_ssh_pass, $klg_output, $config;

		$klg_output .= "<pre><span style=\"font-family: " . $config["output_font"] . "; font-size: 2; color: black;\">";
		$fd = KLG_Execute_Command($cmd);
		if ($fd)
		{
			$start = 0;
			$showingbestpath=0;
			while ($buf = @fgets($fd, 8192))
			{
				$buf = str_replace("\r","",$buf);
				$buf = str_replace("\n","",$buf);
				if ($start==0)
				{
					if (substr($buf, 0, 4)=="BGP " || substr($buf, 0, 1)=="%")
					{
						$start = 1;
						$klg_output .= CleanOutput(htmlentities($buf) . "\n");
					}
				} else {
					if (strpos($buf, "Community: ")>0)
					{
						/* community entry */
						$tmp = substr($buf, 0, strpos($buf, ":")+2);
						$tmp2 = substr($buf, strpos($buf, ":")+2, strlen($buf));
						$comms = preg_split("/ /", $tmp2);
						for ($i=0; $i<count($comms); $i++)
						{
							$community = preg_split("/\:/", $comms[$i]);
							$tmp3 = LookupCommunity($community[0], $community[1]);
							if ($tmp3=="")
							{
								$tmp .= " " . $comms[$i];
							} else {
								$tmp .= " " . LookupAS($community[0]) . ":" . $tmp3 . " [" . $comms[$i] . "]";
							}
						}
						$buf = CleanOutput(htmlentities($tmp));
					}
					if (substr($buf, 0, 7)=="Paths: ")
					{
						$tmp = substr($buf, 0, strpos($buf, "best #"));
						$tmp .= "<span style=\"color: red; font-weight: bold;\">";
						$tmp2 = substr($buf, strpos($buf, "best #"), strlen($buf));
						$bestpath = substr($tmp2, strpos($tmp2, "#")+1, strlen($tmp2));
						$bestpath = substr($bestpath, 0, strpos($bestpath, ","));
						settype($bestpath, "integer");
						$tmp .= substr($tmp2, 0, strpos($tmp2, ","));
						$tmp .= "</span>";
						$tmp .= substr($tmp2, strpos($tmp2, ","), strlen($tmp2));
						$buf = $tmp;
						$count = 0;
					}
					if ((substr($buf, 0, 2)=="  ") && (substr($buf, 2, 1)!=" ") && 
						(substr($buf, 2, 1)!="A") && (substr($buf, 2, 1)!="N") && 
						((strpos($buf, ".")<1 && strpos($buf, "2001:")<1 && strpos(strtolower($buf), "3ffe:")<1) || (strpos($buf,")")>0)))
					{
						$count++;
						if ($count==$bestpath)
						{
							$tmpout = "<span style=\"color: red;\">";
							$showingbestpath=1;
						} else {
							if ($showingbestpath==1)
							{
								$tmpout = "</span>";
								$showingbestpath=0;
							} else {
								$tmpout = "";
							}
						}
						if (strpos($buf, ",")>0)
						{
							$tmp = substr($buf, 2, strpos($buf, ",")-2);
							$tmpleft = substr($buf, strpos($buf, ","), strlen($buf));
						} else {
							$tmp = substr($buf, 2, strlen($buf));
							$tmpleft = "";
						}
						$asnums = preg_split("/ /", $tmp);
						$tmpout .= "  ";
						for ($i=0; $i<count($asnums); $i++)
						{
							$as_name = LookupAS($asnums[$i]);
							if ($as_name!="")
								$asname = "$as_name [" . $asnums[$i] . "]";
							else
								$asname = $asnums[$i];
							$tmpout .= $asname . " ";
						}
						while (substr($tmpout, strlen($tmpout)-1, 1)==" ")
							$tmpout = substr($tmpout, 0, strlen($tmpout)-1);
						$buf = $tmpout . $tmpleft;
					}
					if ($buf!="")
						$klg_output .= CleanOutput($buf . "\n");
					else
						$klg_output .= "<br />\n";
				}
			}
		}
		$klg_output .= "</span></span></pre>\n";
	}
?>
