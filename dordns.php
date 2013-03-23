<?php

function is_ipv6($ip)
{
	if (!preg_match("/^([0-9a-f\.\/:]+)$/",strtolower($ip))) { return false; }
	if (substr_count($ip,":") < 2) { return false; }
	$part = preg_split("/[:\/]/",$ip);
	foreach ($part as $i) { if (strlen($i) > 4) { return false; } }
	return true;
}

function html_sanitise($obj)
{
	$return = htmlentities($obj, ENT_QUOTES | ENT_IGNORE, "UTF-8");
	return $return;
}

function sql_sanitise($data)
{
	if($return = mysql_real_escape_string(stripslashes(trim($data))))
	{
		return $return;
	}
	else
	{
		$error = "Database sanitise error: (" . mysql_errno() . ") \"" . mysql_error() . "\" .";
		error_log($error);
		return FALSE;
	}
}

if(!$_POST["submit_rdns"])
{
	header('Location: rdns.php');
	exit();
}
else
{
	$rdns_ip_addresses = array();
	foreach($_POST as $key => $value)
	{
		$key = str_replace("_", ".", $key);
		if(filter_var($key, FILTER_VALIDATE_IP) == true && inet_pton($key) == true && is_ipv6($key) == false)
		{
			$rdns_ip_addresses[$key] = $value;
		}
	}
	unset($key);
	unset($value);
}

require_once("rdns_system_config.php");

define("CLIENTAREA",true);
//define("FORCESSL",true); # Uncomment to force the page to use https://

if(file_exists("init.php"))
{
	require_once("init.php");
}
else
{
	require_once("dbconnect.php");
}
require("includes/functions.php");
require("includes/clientareafunctions.php");

$pagetitle = $_LANG['clientareatitle'];

initialiseClientArea($pagetitle,'');

$solus_master_url = solus_master_url;
$postfields["id"] = solus_master_id;
$postfields["key"] = solus_master_key;
$lsn_api_key = limestone_networks_api_key;
$smartyvalues["ipv4addresses"] = array(); 

# Check login status
if ($_SESSION['uid']) {

	$client_id = (int)html_sanitise(sql_sanitise($_SESSION['uid']));
	$table = "tblclients";
	$fields = "*";
	$where = array(
		"id"=>array("sqltype"=>"EQ","value"=>$client_id)
	);
	if(mysql_num_rows(select_query($table,$fields,$where)))
	{
		$templatefile = "dordns";
		if($_POST["rdns_csrf_token"] == "" || $_POST["rdns_csrf_token"] != $_COOKIE["rdns_csrf_token"])
		{
			$smartyvalues["ipv4addresses"]["error"] = "Incorrect CSRF token.";
			outputClientArea($templatefile);
			exit();
		}

		if($_POST["serviceid"])
		{
			$client_service_id = $_POST["serviceid"];

			$command = "getclientsproducts";
			$adminuser = WHMCS_admin_user;
			$values["clientid"] = $client_id;
			$values["serviceid"] = $client_service_id;
			$results = localAPI($command,$values,$adminuser);
			if($results)
			{
				$client_server_id = $results["products"]["product"]["0"]["customfields"]["customfield"]["0"]["value"];
				$postfields["action"] = "vserver-infoall";
				$postfields["vserverid"] = $client_server_id;

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $solus_master_url . "/command.php");
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_TIMEOUT, 20);
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
				$data = curl_exec($ch);
				curl_close($ch);

				preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $match);
		
				$result = array();
				foreach ($match[1] as $x => $y)
				{
					$result[$y] = $match[2][$x];
				}

				if($result["status"] == "success")
				{
					$ip_array = explode(",", $result["ipaddresses"]);
					$ipv4_array = array();
					foreach($rdns_ip_addresses as $key => $value)
					{
						foreach ($ip_array as $ip)
						{
							$ip = preg_replace('/\s+/', '', $ip);
							if(inet_pton($ip) == true && is_ipv6($ip) == false)
							{
								if($key == $ip)
								{
									$ch = curl_init();
									curl_setopt($ch, CURLOPT_URL, "https://one.limestonenetworks.com/webservices/clientapi.php?key=" . $lsn_api_key . "&mod=dns&action=setreverse&ipaddress=" . $key . "&value=" . $value);
									curl_setopt($ch, CURLOPT_TIMEOUT, 20);
									curl_setopt($ch, CURLOPT_HEADER, 0);
									curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
									curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
									curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
									curl_setopt($ch, CURLOPT_TIMEOUT, 30);
									curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
									curl_setopt($ch, CURLOPT_VERBOSE, 0);
									curl_setopt($ch, CURLOPT_HTTP_VERSION, '1.0');
									$data = curl_exec($ch);
									curl_close($ch);
									continue;
								}
							}
						}
						unset($ip);
					}
					unset($key);
					unset($value);
					$smartyvalues["ipv4addresses"]["success"] = "Success! However, rDNS propagation may take up to 2 hours.";
				}
				else
				{
					$smartyvalues["ipv4addresses"]["error"] = $result["statusmsg"];
				}
			}
			else
			{
				$smartyvalues["ipv4addresses"]["error"] = $LANG["norecordsfound"];
			}
		}
		else
		{
			$smartyvalues["ipv4addresses"]["error"] = "Service ID not included.";
		}

		outputClientArea($templatefile);

	}
	else
	{
		$goto = 'rdns';
		include 'rdns.php';
	}

} else {
	$goto = 'rdns';
	include 'login.php';
}
?>
