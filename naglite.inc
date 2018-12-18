<?php

/* Main function */
function read_status_file()
{
  require 'config.php';

  if (!is_readable($nagios_cgi_ini)) {
    die("Failed to read Nagios configuration from " . $nagios_cgi_ini);
  }

  $nagios_status = array();
  $nagios_cfg  = get_value_from_key_in_file($nagios_cgi_ini, "main_config_file");
  $statusFile  = get_value_from_key_in_file($nagios_cfg, "status_file");
  $objectsFile = str_replace("status.dat", "objects.cache", $statusFile);

  if (!is_readable($statusFile)) {
    die("Failed to read nagios status from " . $statusFile);
  }

  if ($objectsFile and !is_readable($objectsFile)) {
    die("Failed to read objects file from " . $objectsFile);
  }

  $nagios_status['host_info']    = get_host_information($objectsFile);
  $nagios_status['service_info'] = get_service_information($statusFile);

  $statusFileMtime = filemtime($statusFile);
  $statusFileState = 'ok';

  if ((time() - $statusFileMtime) > $statusFileTimeout) {
    $statusFileState = 'critical';
  }

  $nagios_status['file_state'] = $statusFileState;

  return $nagios_status;
}

/* get all nagios service information */
function get_service_information($statusFile)
{
  require 'config.php';

  $information_about_service = array();

  $nagiosStatus = file($statusFile);
  $in           = false;
  $type         = "unknown";
  $status       = array();
  $host         = null;
  $lineCount    = count($nagiosStatus);

  for ($i = 0; $i < $lineCount; $i++) {
    if (false === $in) {
      $pos = strpos($nagiosStatus[$i], "{");
      if (false !== $pos) {
        $in   = true;
        $type = substr($nagiosStatus[$i], 0, $pos - 1);
        if (!empty($status[$type])) {
          $arrPos = count($status[$type]);
        } else {
          $arrPos = 0;
        }
        continue;
      }
    } else {
      $pos = strpos($nagiosStatus[$i], "}");
      if (false !== $pos) {
        $in   = false;
        $type = "unknown";
        continue;
      }
      // Line with data found
      list($key, $value) = explode("=", trim($nagiosStatus[$i]), 2);
      if ("hoststatus" === $type) {
        if ("host_name" === $key) {
          $host = $value;
        }
        $status[$type][$host][$key] = $value;
      } else {
        $status[$type][$arrPos][$key] = $value;
      }
    }
  }

  $information_about_service['in']        = $in;
  $information_about_service['type']      = "unknown";
  $information_about_service['status']    = $status;
  $information_about_service['host']      = $host;
  $information_about_service['variables'] = get_states($status, $host);

  return $information_about_service;

}

/* get all host and service states */
function get_states($status, $host)
{

  $nagios = get_nagios_states();

  $information_about_states = array();

  $counter = array();
  $states  = array();
  $hosts   = array();

  foreach (array_keys($status) as $type) {
    switch ($type) {
      case "hoststatus":
        $hosts = $status[$type];
        foreach ($hosts as $host) {
          if ((int) $host['scheduled_downtime_depth'] > 0) {
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
          if ((int) $service['scheduled_downtime_depth'] > 0) {
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

  $information_about_states['counter'] = $counter;
  $information_about_states['states']  = $states;
  $information_about_states['hosts']   = $hosts;

  return $information_about_states;

}

/* Get host information */
function get_host_information($objectsFile)
{
  require 'config.php';

  $information_about_hosts = array();
  $hostgroups = array();

  if ($objectsFile) {
    $nagiosObjects = file($objectsFile);
    $in            = false;
    $type          = null;
    $host          = null;
    $lineCount     = count($nagiosObjects);
    for ($i = 0; $i < $lineCount; $i++) {
      if (false === $in) {
        $pos = strpos($nagiosObjects[$i], "{");
        if (false !== $pos) {
          $in   = true;
          $type = trim(substr($nagiosObjects[$i], 6, $pos - 1 - 6));
          continue;
        }
      } else {
        $pos = strpos($nagiosObjects[$i], "}");
        if (false !== $pos) {
          $in   = false;
          $type = "unknown";
          continue;
        }
        // Line with data found
        list($key, $value) = explode("\t", trim($nagiosObjects[$i]), 2);
        if ("host" === $type) {
          if ("host_name" === $key) {
            $host                           = $value;
            if (!isset($information_about_hosts[$host])){
              $information_about_hosts[$host] = arra();
            }
            if (!isset($information_about_hosts[$host]['hostgroup'])){
              $information_about_hosts[$host]['hostgroup'] = array();
            }
          } else {
            $information_about_hosts[$host][$key] = $value;
          }
        }

        if ("hostgroup" === $type){
          if ("hostgroup_name" === $key){
            $hostgroup = $value;
            $hostgroups[$hostgroup] = array();
          } else {
            if ($key == "members"){
              $members = explode(",",$value);

              foreach ($members as $hmember){
                $hostgroups[$hostgroup][] = $hmember;

                if(!in_array($hostgroup,$information_about_hosts[$hmember]['hostgroup'])){
                  $information_about_hosts[$hmember]['hostgroup'][] = $hostgroup;
                }
              }
            }
          }
        }
      }
    }
  }

  $information_about_hosts['all_hostgroups'] = $hostgroups;

  return $information_about_hosts;
}

/* Get value from key in file */
function get_value_from_key_in_file($filename, $item, $length = 1024, $notrim = false)
{
  $fp = fopen($filename, "r");

  if (!$fp) {
    return false;
  }

  while (!feof($fp)) {
    $fileline = fgets($fp, $length);
    if (substr($fileline, 0, strlen($item) + 1) == $item . "=")
      if ($notrim)
        return substr($fileline, strlen($item) + 1, strlen($fileline) - strlen($item));
      else
        return trim(substr($fileline, strlen($item) + 1, strlen($fileline) - strlen($item)));
  }
  fclose($fp);
  return false;
}

function get_last_updated_status(){
  require 'config.php';
  $nagios_cfg  = get_value_from_key_in_file($nagios_cgi_ini, "main_config_file");
  $statusFile  = get_value_from_key_in_file($nagios_cfg, "status_file");
  $statusFileMtime = filemtime($statusFile);

  $statusFileState = 'statusFileStateOK';
  if ((time() - $statusFileMtime) > $statusFileTimeout) {
    $statusFileState = 'statusFileStateCRITICAL';
  }

  $filestatus['state'] = $statusFileState;
  $filestatus['time'] = $statusFileMtime;

  return $filestatus;
}