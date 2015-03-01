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
	$return = htmlspecialchars($obj, ENT_QUOTES, "UTF-8");
	return $return;
}

function sql_sanitise($data)
{
	if($return = mysql_real_escape_string(stripslashes(trim($data))))
	{
		//sanitise complete
		return $return;
	}
	else
	{
		//mysql error here
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

// Check login status
if ($ca->isLoggedIn()) {

	$client_id = (int)$ca->getUserID(); //get the user id from the database
	if(mysql_num_rows(mysql_query("SELECT * FROM tblclients WHERE id='$client_id'")))
	{
		$adminuser = WHMCS_admin_user; //set the admin user
		if($_GET["q"])
		{
			//it is a specified query
			$templatefile = "rdns";
			$client_service_id = html_sanitise(sql_sanitise($_GET["q"])); //get the client's service id

			$command = "getclientsproducts";
			$values["clientid"] = $client_id;
			$values["serviceid"] = $client_service_id;
			$results = localAPI($command,$values,$adminuser); //get the client products using WHMCS
			if($results && $results["products"]["product"]["0"]["status"] == "Active")
			{
				$client_server_id = $results["products"]["product"]["0"]["customfields"]["customfield"]["0"]["value"]; //get the client server's id from the whmcs api
				
				//solus part
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

				preg_match_all('/<(.*?)>([^<]+)<\/\\1>/i', $data, $match); //match the solus data
				$result = array();
				foreach ($match[1] as $x => $y)
				{
					$result[$y] = $match[2][$x]; //do a matching
				}

				if($result["status"] == "success") //its a solusvm vserver-id!
				{
					//carry out LSN rDNS records retrieval
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, "https://one.limestonenetworks.com/webservices/clientapi.php?key=" . $lsn_api_key . "&mod=ipaddresses&action=list"); //list out the stuff
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
					//end of LSN rDNS records retrieval
					
					$ip_array = explode(",", $result["ipaddresses"]);
					$ipv4_array = array();
					foreach($ip_array as $value) //look through the list of IP addresses that solusvm has given us
					{
						$value = preg_replace('/\s+/', '', $value);
						if(filter_var($value, FILTER_VALIDATE_IP) && inet_pton($value) && !is_ipv6($value))
						{
							foreach ($server_data->ipaddress as $serverItem)
							{
								if(strval($serverItem->attributes()->ip) == $value)
								{
									$ipv4_array[$value] = strval($serverItem->ptr); //yes, this is the IP we are looking for
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
			//get all the services
			$templatefile = "rdns_viewservices";
			$command = "getclientsproducts";
			$values["clientid"] = $client_id;
			$results = localAPI($command,$values,$adminuser); //call upon the WHMCS api for the list of services the client has
			
			$output = array();
			foreach($results["products"]["product"] as $product) {
				if($product["serverid"] == solus_service_serverid && $product["status"] == "Active") {
					//this is a solus VPS and it is also active
					$output[] = $product;
				}
			}
			$smartyvalues["services"] = $output;
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
