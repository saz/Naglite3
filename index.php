<?php
/** 
 *	Naglite3 - Nagios Status Monitor
 *	Inspired by Naglite (http://www.monitoringexchange.org/inventory/Utilities/AddOn-Projects/Frontends/NagLite)
 *	and Naglite2 (http://laur.ie/blog/2010/03/naglite2-finally-released/)
 *
 *	@author		Steffen Zieger <me@saz.sh>
 *	@version	1.6
 *	@license	GPL
 **/

/**
 *
 * Please do not change values below, as this will make it harder
 * for you to update in the future.
 * Rename config.php.example to config.php and change the values there.
 *
 **/

// Set file path to your nagios status log
$statusFile = '/var/cache/nagios3/status.dat';

// Objects file
$objectsFile = '/var/cache/icinga/objects.cache';

// Default refresh time in seconds
$refresh = 10;

// Show warning state if status file was last updated <num> seconds ago
// Set this to a higher value then status_update_interval in your nagios.cfg
$statusFileTimeout = 60;


$hostFilter = function ($match) { return TRUE; };

// Enable fortune output
$enableFortune = false;
$fortunePath = "/usr/games/fortune";

// Uncomment to show custom heading
//$nagliteHeading = '<Your Custom Heading>';

// Show IP addresses of hosts
$showAddresses = FALSE;


/* 
 * Nothing to change below
 */

// If there is a config file, require it to overwrite some values
$config = 'config.php';
if (file_exists($config)) {
    require $config;
}

// Disable E_NOTICE error reporting
$errorReporting = error_reporting();
if ($errorReporting & E_NOTICE) {
    error_reporting($errorReporting ^ E_NOTICE);
}

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

function serviceTable($nagios, $services, $hostInfo, $select = false, $type = false) {
	if (false === $type) {
		print("<table><tr>\n");
	} else {
		print(sprintf("<table><tr class='%s'>\n", $type));
	}
	$addressColumn = empty($hostInfo)?'':'<th>Address</th>';
	print("<th>Host</th>$addressColumn<th>Service</th><th>Status</th><th>Duration</th><th>Attempts</th><th>Plugin Output</th>\n");
	print("</tr>");

    foreach ($select as $selectedType) {
        if ($services[$selectedType]) {
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
		if ($hostInfo) {
			$hostName = $service["host_name"];
			print("<td class='hostname'>$hostName</td><td class='address'>{$hostInfo[$hostName]["address"]}</td>\n");
		} else {
			print(sprintf("<td class='hostname'>%s</td>\n", $service['host_name']));
		}
                print(sprintf("<td class='service'>%s</td>\n", $service['service_description']));
                print(sprintf("<td class='state'>%s", $state));
                if ($service["current_attempt"] < $service["max_attempts"]) {
                    print(" (Soft)");
                }
                print("</td>\n");
                print(sprintf("<td class='duration'>%s</td>\n", duration($service['last_state_change'])));
                print(sprintf("<td class='attempts'>%s/%s</td>\n", $service['current_attempt'], $service['max_attempts']));
                print(sprintf("<td class='output'>%s</td>\n", strip_tags($service['plugin_output'], '<a>')));
                print("</tr>\n");
            }
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


$statusFileMtime = filemtime($statusFile);
$statusFileState = 'ok';
if ((time() - $statusFileMtime) > $statusFileTimeout) {
    $statusFileState = 'critical';
}

$nagiosStatus = file($statusFile);
$in = false;
$type = "unknown";
$status = array();
$host = null;

$lineCount = count($nagiosStatus);
for($i = 0; $i < $lineCount; $i++) {
	if(false === $in) {
		preg_match('/(info|programstatus|hoststatus|servicestatus|servicecomment) {/', trim($nagiosStatus[$i]), $matches);
		if ($matches) {
			$in = true;
			$type = $matches[1];
            if(!empty($status[$type])) {
    			$arrPos = count($status[$type]);
            } else {
                $arrPos = 0;
            }
			continue;
		}
	} else {
		$pos = strpos($nagiosStatus[$i], "}");
		if(false !== $pos && strlen(trim($nagiosStatus[$i])) == 1) {
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

/* drop unwanted status entries 
 */
foreach (array_keys($status) as $a_type) {
	foreach ($status[$a_type] as $a_key => $a_value) {
		if (!array_key_exists('host_name', $a_value)) 
			continue;
		if (!$hostFilter($a_value['host_name'])) {
			unset($status[$a_type][$a_key]);
		}
	}
}
/** 
 *
 * Parse Nagios objects cache
 *
 **/
if ($objectsFile and !is_readable($objectsFile)) {
    die("Failed to read objects file from '$objectsFile'");
}

$hostInfo = array();
if ($objectsFile and $showAddresses) {
	$nagiosObjects = file($objectsFile);
	$in = false;
	$type = null;
	$host = null;
	$lineCount = count($nagiosObjects);
	for($i = 0; $i < $lineCount; $i++) {
		if(false === $in) {
			$pos = strpos($nagiosObjects[$i], "{");
			if (false !== $pos) {
				$in = true;
				$type = trim(substr($nagiosObjects[$i], 6, $pos-1-6));
				continue;
			}
		} else {
			$pos = strpos($nagiosObjects[$i], "}");
			if(false !== $pos) {
				$in = false;
				$type = "unknown";
				continue;
			}

			// Line with data found
			list($key, $value) = explode("\t", trim($nagiosObjects[$i]), 2);
			if("host" === $type) {
				if("host_name" === $key) {
					$host = $value;
					$hostInfo[$host] = array();
				} else {
					$hostInfo[$host][$key] = $value;
				}
			}
		}
	}
}


// Initialize some variables
$counter = array();
$states = array();
$hosts = array();

foreach (array_keys($status) as $type) {
	switch ($type) {
	case "hoststatus":
		$hosts = $status[$type];
		foreach ($hosts as $host) {
            if ((int)$host['scheduled_downtime_depth'] > 0) {
                continue;
            } else if ($host['problem_has_been_acknowledged'] == '1') {
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

            if ((int)$service['scheduled_downtime_depth'] > 0) {
                continue;
            } else if ($service['problem_has_been_acknowledged'] == '1') {
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
//echo " <meta http-equiv=\"refresh\" content=\"10; url=http://172.16.4.4/naglite/kim.htm\">\"\n";
echo "	<meta http-equiv=\"content-type\" content=\"text/html;charset=utf-8\" />\n";
echo "	<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"default.css\" />\n";
if (is_readable("custom.css")) {
	echo "	<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"custom.css\" />\n";
}
if (isset($_GET['css']) && is_readable(basename($_GET['css']) . '.css')) {
	echo "	<link rel=\"stylesheet\" type=\"text/css\" media=\"screen\" href=\"".basename($_GET['css']).".css\" />\n";
}
echo "</head>\n";
echo "<body>\n";

print("</div>\n");
print(sprintf('<div class="statusFileState %s">', $statusFileState));
    print(sprintf('Status file last updated at %s', date(DATE_RFC2822, $statusFileMtime)));
print("</div>\n");

echo "<!--\n";
// var_dump($counter);
// var_dump($states);
// var_dump($hostInfo);
echo "-->\n";
echo '<div id="content">';
if(is_callable($nagliteHeading)) {
	$nagliteHeading(); 
} elseif ($nagliteHeading) {
    echo '<h1>'.$nagliteHeading.'</h1>';
}

sectionHeader('hosts', $counter);
if (!$showAddresses)
	$hostInfo = False;
$addressColumn = empty($hostInfo)?'':'<th>Address</th>';

if ($counter['hosts']['down']) {
	echo "<table>";
	echo "<tr><th>Host</th>$addressColumn<th>Status</th><th>Duration</th><th>Status Information</th></tr>";
	foreach($states['hosts']['down'] as $host) {
		$state = $nagios["host"][$host["current_state"]];
		echo "<tr class='".$state."'>\n";
		if ($showAddresses) {
			$hostName = $host["host_name"];
			echo "<td class='hostname'>$hostName</td><td class='address'>{$hostInfo[$hostName]["address"]}</td>\n";
		} else {
			echo "<td class='hostname'>{$host["host_name"]}</td>\n";
		}
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
	serviceTable($nagios, $states['services'], $hostInfo, array('critical', 'warning', 'unknown'));
} else {
	print("<div class='state up'>ALL MONITORED SERVICES OK</div>\n");
}

foreach(array('acknowledged', 'notification', 'pending') as $type) {
    if ($counter['services'][$type]) {
        print(sprintf('<h3 class="title">%s</h3>', ucfirst($type)));
        print('<div class="subsection">');
        serviceTable($nagios, $states['services'], $hostInfo, array($type), $type);
        print('</div>');
    }
}

if($enableFortune === true) {
    echo "<div class='fortune'>";
    print(shell_exec($fortunePath));
    echo "</div>";
}

print("</body>\n");
print("</html>\n");
