<?php
/** 
 *	Naglite3 - Nagios Status Monitor
 *	Inspired by Naglite (http://www.monitoringexchange.org/inventory/Utilities/AddOn-Projects/Frontends/NagLite)
 *	and Naglite2 (http://laur.ie/blog/2010/03/naglite2-finally-released/)
 *
 *	@author		Steffen Zieger <me@saz.sh>
 *	@version	1.0
 *	@license	GPL
 */

// Set file path to your nagios status log
$status_file = "/var/cache/nagios3/status.dat";

// Default refresh time in seconds
$refresh = 10;

// Enable fortune output
$enableFortune = false;
$fortunePath = "/usr/games/fortune";

/* Nothing to change below this line */

# Disable caching and set refresh interval
header("Pragma: no-cache");
if (!empty($_GET["refresh"]) && is_numeric($_GET["refresh"])) {
	$refresh = $_GET["refresh"];
}
header("Refresh: " .$refresh);

// Nagios Status Map
$nagios["host"]["OK"] = 0;
$nagios["host"]["DOWN"] = 1;
$nagios["host"]["UNREACHABLE"] = 2;
$nagios["host"] += array_keys($nagios["host"]);
$nagios["service"]["OK"] = 0;
$nagios["service"]["WARNING"] = 1;
$nagios["service"]["CRITICAL"] = 2;
$nagios["service"]["UNKNOWN"] = 3;
$nagios["service"] += array_keys($nagios["service"]);

function duration($end) {
	$DAY = 86400;
	$HOUR = 3600;

	$now = time();
	$diff = $now - $end;
	$days = floor($diff / $DAY);
	$hours = floor(($diff % $DAY) / $HOUR);
	$minutes = floor((($diff % $DAY) % $HOUR) / 60);
	$secs = $diff % 60;
	$ret = sprintf("%dd, %02d:%02d:%02d", $days, $hours, $minutes, $secs);
	return $ret;
}

function serviceTable($nagios, $services, $type = false) {
	if (false === $type) {
		echo "<table>\n";
	} else {
		echo "<table><tr class='service_{$type}'>\n";
	}
	echo "<th>Host</th><th>Service</th><th>Status</th><th>Duration</th><th>Attempts</th><th>Plugin Output</th>\n";
	echo "</tr>";

	foreach ($services as $service) {
		$state = $nagios["service"][$service["current_state"]];
		if (false === $type) {
			$rowType = $state;
		} else {
			$rowType = $type;
			if ("ACKNOWLEDGED" !== $type) {
				$state = $type;
			} 
		}
		echo "<tr class='service_{$rowType}'>\n";
		echo "<td class='hostname'>{$service["host_name"]}</td>";
		echo "<td class='service'>{$service["service_description"]}</td>";
		echo "<td class='state_{$rowType}'>";
		if ($service["current_attempt"] == $service["max_attempts"]) {
			echo "$state";
		} else {
			echo "$state (Soft)";
		}
		echo "</td>\n";
		echo "<td class='duration'>".duration($service["last_state_change"])."</td>";
		echo "<td>{$service["current_attempt"]}/{$service["max_attempts"]}</td>";
		echo "<td>{$service["plugin_output"]}</td>";
		echo "</tr>";
	}
	echo "</table>";
}

echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"\n";
echo "       \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";

echo "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
echo "<head>\n";
echo "	<title>Nagios Monitoring System - Naglite3</title>\n";
echo "	<meta http-equiv=\"content-type\" content=\"text/html;charset=utf-8\" />\n";
echo "	<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"default.css\" />\n";
if (is_readable("custom.css")) {
	echo "	<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"custom.css\" />\n";
}
echo "</head>\n";
echo "<body>\n";

if (is_readable($status_file)) {
	$nagiosStatus = file($status_file);
} else {
	echo "<div class='statusFileError'>Failed to open status file: $status_file</div>\n";
	echo "<div class='loading'><img src='loading.gif' /></div>\n";
	echo "</body></html>\n";
	die ();
}

$in = false;
$type = "unknown";
$status = array();
$host = null;
for($i = 0; $i < count($nagiosStatus); $i++) {
	if(false === $in) {
		$pos = strpos($nagiosStatus[$i], "{");
		if (false !== $pos) {
			$in = true;
			$type = substr($nagiosStatus[$i], 0, $pos-1);
            if(!empty($status[$type])) {
    			$arrPos = count($status[$type]);
            } else {
                $arrPos = 0;
            }
			continue;
		}
	} else {
		$pos = strpos($nagiosStatus[$i], "}");
		if(false !== $pos) {
			$in = false;
			$type = "unknown";
			continue;
		}

		// Line with data found
		list($key, $value) = explode("=", trim($nagiosStatus[$i]), 2);
		if("hoststatus" === $type) {
			if("host_name" === $key) {
				$host = $value;
			}
			$status[$type][$host][$key] = $value;
		} else {
			$status[$type][$arrPos][$key] = $value;
		}
	}
}

// Initialize some variables
$hostsAcked = 0;
$hostsNotifications = 0;
$hostsPending = 0;
$hostsGood = 0;
$hostsDown = 0;
$hostsUnreachable = 0;
$servicesGood = 0;
$servicesNotifications = 0;
$servicesCrit = 0;
$servicesAcked = 0;
$servicesUnknown = 0;
$servicesWarn = 0;
$servicesPending = 0;

foreach (array_keys($status) as $type) {
	switch ($type) {
	case "hoststatus":
		$hosts = $status[$type];
		foreach ($hosts as $host) {
			if ($host["problem_has_been_acknowledged"] == "1") {
				$hostsAcked++;
				$hostsAckedList[] = $host["host_name"];
			} else if ($host["notifications_enabled"] == 0) {
				$hostsNotifications++;
				$hostsNotificationsList[] = $host["host_name"];
			} else if ($host["has_been_checked"] == 0) {
				$hostsPending++;
				$hostsPendingList[] = $host["host_name"];
			} else {
				switch ($host["current_state"]) {
					case $nagios["host"]["OK"]:
						$hostsGood++;
						break;
					case $nagios["host"]["DOWN"]:
						$hostsDown++;
						$hostsDownList[] = $host;
						break;
					case $nagios["host"]["UNREACHABLE"]:
						$hostsUnreachable++;
						$hostsUnreachableList[] = $host["host_name"];
						break;
				}
			}
		}
		break;

	case "servicestatus":
		$services = $status[$type];
		foreach ($services as $service) {
			// Ignore all services if host state is not OK
			$state = $status["hoststatus"][$service["host_name"]]["current_state"];
			if ($nagios["host"]["OK"] != $state) {
				continue;
			}
			// Service is in warning level
			if ($service["problem_has_been_acknowledged"] == "1") {
				$servicesAcked++;
				$servicesAckedList[] = $service;
			} else if ($service["notifications_enabled"] == "0") {
				$servicesNotifications++;
				$servicesNotificationsList[] = $service;
			} else if ($service["has_been_checked"] == "0") {
				$servicesPending++;
				$servicesPendingList[] = $service;
			} else {
				switch ($service["current_state"]) {
					case $nagios["service"]["OK"]:
						$servicesGood++;
						break;
					case $nagios["service"]["WARNING"]:
						$servicesWarn++;
						break;
					case $nagios["service"]["CRITICAL"]:
						$servicesCrit++;
						break;
					case $nagios["service"]["UNKNOWN"]:
						$servicesUnknown++;
						break;
				}

				if ($nagios["service"]["OK"] != $service["current_state"]) {
					$servicesNOKList[] = $service;
				}
			}
		}
		break;
	}
}

echo "<div class='description first'><div class='title'>Host Status</div><div class='statistics'>";
if ($hostsGood) echo "<span class='sh_UP'>$hostsGood UP</span>";

if ($hostsUnreachable) echo "<span class='sh_UNREACHABLE'> - $hostsUnreachable UNREACHABLE</span>";

if ($hostsDown) echo "<span class='sh_DOWN'> - $hostsDown DOWN</span>";

if ($hostsAcked) echo "<span class='sh_ACKNOWLEDGED'> - $hostsAcked ACK'ed</span>";

if ($hostsPending) echo "<span class='sh_PENDING'> - $hostsPending PENDING</span>";

if ($hostsNotifications) echo "<span class='sh_NOTIFICATION'> - $hostsNotifications Notifications Off</span>";
echo "</div></div>";

if ($hostsDown) {
	echo "<table>";
	echo "<tr><th>Host</th><th>Status</th><th>Duration</th><th>Status Information</th></tr>";
	foreach($hostsDownList as $host) {
		$state = $nagios["host"][$host["current_state"]];
		echo "<tr class='host_DOWN'>\n";
		echo "<td class='hostname'>{$host["host_name"]}</td>\n";
		echo "<td class='state_{$state}'>{$state}</td>\n";
		echo "<td class='duration'>".duration($host["last_state_change"])."</td>\n";
		echo "<td>{$host["plugin_output"]}</td>\n";
		echo "</tr>\n";
	}
	echo "</table>";
} else {
	echo "<div class='state_UP'>ALL MONITORED HOSTS UP</div>\n";
}

if ($hostsUnreachable) echo "<div class='host_UNREACHABLE'><b>Unreachable: </b>".implode(", ", $hostsUnreachableList)."</div>\n";

if ($hostsAcked) echo "<div class='host_ACKNOWLEDGED'><b>Acknowledged: </b>".implode(", ", $hostsAckedList)."</div>\n";

if ($hostsPending) echo "<div class='host_PENDING'><b>Pending: </b>".implode(", ", $hostsPendingList)."</div>\n";

if ($hostsNotifications) echo "<div class='host_NOTIFICATION'><b>Notifications off: </b>".implode(", ", $hostsNotificationsList)."</div>\n";

echo "<div class='description'><div class='title'>Service Status</div><div class='statistics'>";
if ($servicesGood) echo "<span class='ss_OK'>$servicesGood OK</span>";

if ($servicesWarn) echo "<span class='ss_WARNING'> - $servicesWarn WARN</span>";

if ($servicesCrit) echo "<span class='ss_CRITICAL'> - $servicesCrit CRIT</span>";

if ($servicesUnknown) echo "<span class='ss_UNKNOWN'> - $servicesUnknown UNKNOWN</span>";

if ($servicesAcked) echo "<span class='ss_ACKNOWLEDGED'> - $servicesAcked ACK'ed</span>";

if ($servicesPending) echo "<span class='ss_PENDING'> - $servicesPending Pending</span>";

if ($servicesNotifications) echo "<span class='ss_NOTIFICATION'> - $servicesNotifications Notifications Off</span>";
echo "</div></div>";

if ($servicesWarn || $servicesCrit || $servicesUnknown) {
	serviceTable($nagios, $servicesNOKList);
} else {
	echo "<div class='state_UP'>ALL MONITORED SERVICES OK</div>/n";
}

if ($servicesAcked) {
	echo "<div class='description'><div class='title'>Acknowledged Services</div><div class='statistics'>";
	echo "<span class='ss_ACKNOWLEDGED'>$servicesAcked ACK'ed</span>";
	echo "</div></div>";

	serviceTable($nagios, $servicesAckedList, "ACKNOWLEDGED");
}

if ($servicesNotifications) {
	echo "<div class='description'><div class='title'>Notifications Off</div><div class='statistics'>";
	echo "<span class='ss_NOTIFICATION'>$servicesNotifications Notifications Off</span>";
	echo "</div></div>";

	serviceTable($nagios, $servicesNotificationsList, "NOTIFICATION");
}

if ($servicesPending) {
	echo "<div class='description'><div class='title'>Pending Services</div><div class='statistics'>";
	echo "<span class='ss_PENDING'>$servicesPending Pending</span>";
	echo "</div></div>";

	serviceTable($nagios, $servicesPendingList, "PENDING");
}

if($enableFortune === true) {
    echo "<div class='fortune'>";
    print(shell_exec($fortunePath));
    echo "</div>";
}

?>
</body>
</html>
