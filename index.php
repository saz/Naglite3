<?php
/** 
 *	Naglite3 - Nagios Status Monitor
 *	Inspired by Naglite (http://www.monitoringexchange.org/inventory/Utilities/AddOn-Projects/Frontends/NagLite)
 *	and Naglite2 (http://laur.ie/blog/2010/03/naglite2-finally-released/)
 *
 *	@author		Steffen Zieger <me@saz.sh>
 *	@version	1.0
 *	@license	GPL
 **/

/**
 *
 * Configuration
 *
 **/

// Set file path to your nagios status log
$statusFile = '/var/cache/nagios3/status.dat';

// Default refresh time in seconds
$refresh = 10;

// Enable fortune output
$enableFortune = false;
$fortunePath = "/usr/games/fortune";

/* 
 * Nothing to change below
 */

// Disable caching and set refresh interval
header("Pragma: no-cache");
if (!empty($_GET["refresh"]) && is_numeric($_GET["refresh"])) {
	$refresh = $_GET["refresh"];
}
header("Refresh: " .$refresh);

// Nagios Status Map
$nagios["host"]["ok"] = 0;
$nagios["host"]["down"] = 1;
$nagios["host"]["unreachable"] = 2;
$nagios["host"] += array_keys($nagios["host"]);
$nagios["service"]["ok"] = 0;
$nagios["service"]["warning"] = 1;
$nagios["service"]["critical"] = 2;
$nagios["service"]["unknown"] = 3;
$nagios["service"] += array_keys($nagios["service"]);

/**
 *
 * Functions
 *
 **/

function duration($end) {
	$DAY = 86400;
	$HOUR = 3600;

	$now = time();
	$diff = $now - $end;
	$days = floor($diff / $DAY);
	$hours = floor(($diff % $DAY) / $HOUR);
	$minutes = floor((($diff % $DAY) % $HOUR) / 60);
	$secs = $diff % 60;
	return sprintf("%dd, %02d:%02d:%02d", $days, $hours, $minutes, $secs);
}

function serviceTable($nagios, $services, $select = false, $type = false) {
	if (false === $type) {
		print("<table><tr>\n");
	} else {
		print(sprintf("<table><tr class='%s'>\n", $type));
	}
	print("<th>Host</th><th>Service</th><th>Status</th><th>Duration</th><th>Attempts</th><th>Plugin Output</th>\n");
	print("</tr>");

    foreach ($select as $selectedType) {
        foreach ($services[$selectedType] as $service) {
            $state = $nagios["service"][$service["current_state"]];
            if (false === $type) {
                $rowType = $state;
            } else {
                $rowType = $type;
                if ("acknowledged" !== $type) {
                    $state = $type;
                } 
            }
            print(sprintf("<tr class='%s'>\n", $rowType));
            print(sprintf("<td class='hostname'>%s</td>\n", $service['host_name']));
            print(sprintf("<td class='service'>%s</td>\n", $service['service_description']));
            print(sprintf("<td class='state'>%s", $state));
            if ($service["current_attempt"] < $service["max_attempts"]) {
                print(" (Soft)");
            }
            print("</td>\n");
            print(sprintf("<td class='duration'>%s</td>\n", duration($service['last_state_change'])));
            print(sprintf("<td class='attempts'>%s/%s</td>\n", $service['current_attempt'], $service['max_attempts']));
            print(sprintf("<td class='output'>%s</td>\n", htmlspecialchars($service['plugin_output'])));
            print("</tr>\n");
        }
    }
	print("</table>\n");
}

function sectionHeader($type, $counter) {
    print(sprintf('<div id="%s" class="section">', $type));
    print(sprintf('<h2 class="title">%s Status</h2>', ucfirst($type)));
    print('<div class="stats">');
    foreach($counter[$type] as $type => $value) {
        print(sprintf('<div class="stat %s">%s %s</div>', $type, $value, ucfirst($type)));
    }
    print('</div></div>');
}

/**
 *
 * Parse Nagios status
 *
 **/

// Check if status file is readable
if (!is_readable($statusFile)) {
    die("Failed to read nagios status from '$statusFile'");
}

$nagiosStatus = file($statusFile);
$in = false;
$type = "unknown";
$status = array();
$host = null;

$lineCount = count($nagiosStatus);
for($i = 0; $i < $lineCount; $i++) {
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
$counter = array();
$states = array();

foreach (array_keys($status) as $type) {
	switch ($type) {
	case "hoststatus":
		$hosts = $status[$type];
		foreach ($hosts as $host) {
			if ($host['problem_has_been_acknowledged'] == '1') {
                $counter['hosts']['acknowledged']++;
                $states['hosts']['acknowledged'][] = $host['host_name'];
            } else if ($host['notifications_enabled'] == 0) {
                $counter['hosts']['notification']++;
                $states['hosts']['notification'][] = $host['host_name'];
            } else if ($host['has_been_checked'] == 0) {
                $counter['hosts']['pending']++;
                $states['hosts']['pending'][] = $host['host_name'];
			} else {
				switch ($host['current_state']) {
                case $nagios['host']['ok']:
                    $counter['hosts']['ok']++;
					break;
                case $nagios['host']['down']:
                    $counter['hosts']['down']++;
                    $states['hosts']['down'][] = $host;
					break;
                case $nagios['host']['unreachable']:
                    $counter['hosts']['unreachable']++;
                    $states['hosts']['unreachable'][] = $host['host_name'];
    				break;
				}
			}
		}
		break;

	case "servicestatus":
		$services = $status[$type];
		foreach ($services as $service) {
			// Ignore all services if host state is not ok
			$state = $status['hoststatus'][$service['host_name']]['current_state'];
			if ($nagios['host']['ok'] != $state) {
				continue;
			}

            if ($service['problem_has_been_acknowledged'] == '1') {
                $counter['services']['acknowledged']++;
                $states['services']['acknowledged'][] = $service;
            } else if ($service['notifications_enabled'] == '0') {
                $counter['services']['notification']++;
                $states['services']['notification'][] = $service;
            } else if ($service['has_been_checked'] == '0') {
                $counter['services']['pending']++;
                $states['services']['pending'][] = $service;
			} else {
				switch ($service['current_state']) {
                case $nagios['service']['ok']:
                    $counter['services']['ok']++;
					break;
                case $nagios['service']['warning']:
                    $counter['services']['warning']++;
                    $states['services']['warning'][] = $service;
					break;
                case $nagios['service']['critical']:
                    $counter['services']['critical']++;
                    $states['services']['critical'][] = $service;
					break;
                case $nagios['service']['unknown']:
                    $counter['services']['unknown']++;
                    $states['services']['unknown'][] = $service;
					break;
				}
			}
		}
		break;
	}
}

/**
 *
 * Status output
 *
 **/

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
echo '<div id="content">';

sectionHeader('hosts', $counter);

if ($counter['hosts']['down']) {
	echo "<table>";
	echo "<tr><th>Host</th><th>Status</th><th>Duration</th><th>Status Information</th></tr>";
	foreach($states['hosts']['down'] as $host) {
		$state = $nagios["host"][$host["current_state"]];
		echo "<tr class='".$state."'>\n";
		echo "<td class='hostname'>{$host["host_name"]}</td>\n";
		echo "<td class='state'>{$state}</td>\n";
		echo "<td class='duration'>".duration($host["last_state_change"])."</td>\n";
        print(sprintf("<td class='output'>%s</td>\n", htmlspecialchars($host['plugin_output'])));
		echo "</tr>\n";
	}
	echo "</table>";
} else {
	echo "<div class='state up'>ALL MONITORED HOSTS UP</div>\n";
}

foreach(array('unreachable', 'acknowledged', 'pending', 'notification') as $type) {
    if ($counter['hosts'][$type]) {
        print(sprintf('<div class="subhosts %s"><b>%s:</b> %s</div>', $type, ucfirst($type), implode(', ', $states['hosts'][$type])));
    }
}

sectionHeader('services', $counter);

if ($counter['services']['warning'] || $counter['services']['critical'] || $counter['services']['unknown']) {
	serviceTable($nagios, $states['services'], array('critical', 'warning', 'unknown'));
} else {
	print("<div class='state up'>ALL MONITORED SERVICES OK</div>\n");
}

foreach(array('acknowledged', 'notification', 'pending') as $type) {
    if ($counter['services'][$type]) {
        print(sprintf('<h3 class="title">%s</h3>', ucfirst($type)));
        print('<div class="subsection">');
        serviceTable($nagios, $states['services'], array($type), $type);
        print('</div>');
    }
}

if($enableFortune === true) {
    echo "<div class='fortune'>";
    print(shell_exec($fortunePath));
    echo "</div>";
}

?>
</div>
</body>
</html>
