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

require_once("rdns_system_config.php");

define("CLIENTAREA",true);
//define("FORCESSL",true); # Uncomment to force the page to use https://

require_once("init.php");

$ca = new WHMCS_ClientArea();

$ca->setPageTitle($whmcs->get_lang('clientareatitle'));
$ca->initPage();
$ca->requireLogin();

$rdns_csrf_token = md5(uniqid(mt_rand(), true));
setcookie("rdns_csrf_token", $rdns_csrf_token, 0, "/", "", $_SERVER["HTTPS"], TRUE);
$smartyvalues["rdns_csrf_token"] = $rdns_csrf_token;

$smartyvalues["ipv4addresses"] = array();
$smartyvalues["services"] = array();
$solus_master_url = solus_master_url;
$postfields["id"] = solus_master_id;
$postfields["key"] = solus_master_key;
$lsn_api_key = limestone_networks_api_key;

# Check login status
if ($ca->isLoggedIn()) {

	$client_id = (int)html_sanitise(sql_sanitise($ca->getUserID()));
	if(mysql_num_rows(mysql_query("SELECT * FROM tblclients WHERE id='$client_id'")))
	{
		$adminuser = WHMCS_admin_user;
		if($_GET["q"])
		{
			$templatefile = "rdns";
			$client_service_id = html_sanitise(sql_sanitise($_GET["q"]));

			$command = "getclientsproducts";
			$values["clientid"] = $client_id;
			$values["serviceid"] = $client_service_id;
			$results = localAPI($command,$values,$adminuser);
			if($results)
			{
				$client_server_id = $results["products"]["product"]["0"]["customfields"]["customfield"]["0"]["value"];
				//solus

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

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, "https://one.limestonenetworks.com/webservices/clientapi.php?key=" . $lsn_api_key . "&mod=ipaddresses&action=list");
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
				$server_data = simplexml_load_string($data);

				$result = array();
				foreach ($match[1] as $x => $y)
				{
					$result[$y] = $match[2][$x];
				}

				if($result["status"] == "success")
				{
					$ip_array = explode(",", $result["ipaddresses"]);
					$ipv4_array = array();
					foreach($ip_array as $value)
					{
						$value = preg_replace('/\s+/', '', $value);
						if(filter_var($value, FILTER_VALIDATE_IP) == true && inet_pton($value) == true && is_ipv6($value) == false)
						{
							foreach ($server_data->ipaddress as $serverItem)
							{
								if(strval($serverItem->attributes()->ip) == $value)
								{
									$ipv4_array[$value] = strval($serverItem->ptr);
									continue;
								}
							}
							unset($serverItem);
						}
					}
					unset($ip);
					$smartyvalues["ipv4addresses"] = $ipv4_array;
					$smartyvalues["serviceid"] = $client_service_id;
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
			$templatefile = "rdns_viewservices";
			$command = "getclientsproducts";
			$values["clientid"] = $client_id;
			$results = localAPI($command,$values,$adminuser);
			$smartyvalues["services"] = $results["products"]["product"];
		}
		$ca->setTemplate($templatefile);
		$ca->output();
	}
	else
	{
		$goto = 'rdns';
		include 'login.php';
	}

} else {
	$goto = 'rdns';
	include 'login.php';
}

?>
