<?php

## @author    Samoylov Nikolay
## @project   Wget GUI Light
## @copyright 2014 <samoylovnn@gmail.com>
## @license   MIT <http://opensource.org/licenses/MIT>
## @github    https://github.com/tarampampam/wget-gui-light
## @version   0.0.3
##
## @depends   *nix, php5, wget, ps, kill


# *****************************************************************************
# ***                               Config                                   **
# *****************************************************************************

## Errors reporting level (set '0' when publish)
error_reporting(0);

## Settings paths
##   Path to directory, where this script located
define('BASEPATH', realpath(dirname(__FILE__)));
##   Path to downloads directory. Any files will download to this path
define('download_path', BASEPATH.'/downloads');
##   Path to temp files directory. Temp files will created by 'wget' for 
##   getting progress in background job, and WILL NOT DELETED AUTOMATICLY
##   (add to crontab some like this - "rm -f /tmp/wget*.log.tmp").
##   Comment this line for disable this feature
define('tmp_path', '/tmp');

## Path to 'wget' (check in console '> which wget'). UNcomment and set if
## downloads tasks will not work
#define('wget', '/usr/bin/wget');

## Path to 'ps' (check in console '> which ps'). UNcomment and set if listing
## of active tasks will now shown
#define('ps',   '/bin/ps');

## Path to 'kill' (check in console '> which kill'). UNcomment and set if
## canceling tasks will not work
#define('kill', '/bin/kill');

## Wget speed limit for tasks in KiB (numeric only). Uncomment for enable this
##   feature
define('wget_download_limit', '1024');

## Wget 'Secret flag'. By this parameter we understand - task in background
##   created by this script, or not. DO NOT change this
define('wget_secret_flag', '--max-redirect=4321');

# *****************************************************************************
# ***                            END Config                                  **
# *****************************************************************************

header('Content-Type: application/json; charset=UTF-8'); // Default header

if(!defined('wget')) define('wget', 'wget');
if(!defined('ps'))   define('ps', 'ps');
if(!defined('kill')) define('kill', 'kill');

// Prepare url before downloading
function prepareUrl($url) {
    return escapeshellarg($url);
}

// Make system command call, return result as string or array
function bash($cmd, $result_type) {
    $out = ''; $result = '';
    
    if(empty($cmd))
        return false;
        
    exec($cmd, $out);
    
    $result_type = empty($result_type) ? 'string' : $result_type;
    
    switch ($result_type) {
        case 'string':
            foreach($out as $line)
                $result .= $line."\n";
            break;
        case 'array':
                $result = $out;
            break;
    }
    return $result;
}

// Check PID value
function validatePid($pid) {
    // 32768 is maximum pid by default
    return (is_numeric($pid) && ($pid > 0) && ($pid <= 32768)) ? true : false;
}

// IMPORTANT FUNCTION
// Remove download task. Just kill process
function removeWgetTask($pid) {
    if(!validatePid($pid))
        return false;

    $task = bash(kill.' -15 '.$pid, 'string');

    return true;
}
//var_dump(removeWgetTask(16268)); // Debug call

// IMPORTANT FUNCTION
// Add task for a work
function addWgetTask($url) {
    $speedLimit = (defined('wget_download_limit')) ? ' --limit-rate='.wget_download_limit.'k ' : '';
    $tmpFile = (defined('tmp_path')) ? ' --output-file="'.tmp_path.'/wget$(($RANDOM%15000+100)).log.tmp" ' : '';
    
    $task = bash(wget.' '.
        '--progress=bar:force '.
        '--background '.
        '--tries=0 '.
        '--no-cache '.
        '--user-agent="Mozilla/5.0 (X11; Linux amd64; rv:21.0) Gecko/20100101 Firefox/21.0" '.
        '--directory-prefix="'.download_path.'" '.
        $speedLimit.
        $tmpFile.
        wget_secret_flag.' '.
        prepareUrl($url), 'string');
    preg_match("/pid\s(\d{1,5})\./i", $task, $founded);
    $pid = $founded[1];

    return (validatePid($pid)) ? $pid : -1;
}
//echo addWgetTask('http://goo.gl/v7Ujhg'); // Debug call

// IMPORTANT FUNCTION
// Get list of active jobs
function getWgetTasks() {
    $tasks = ''; $result = array();
    $tasks = bash(ps.' -ax', 'array');
    foreach($tasks as $task) {
        // make FAST search:
        if(strpos($task, 'wget') !== false) {
            $logfile = '';
            // make detailed search:
            if(defined('tmp_path')) {
                preg_match("/(\d{1,5}).*wget.*--output-file=(\/.*\d{3,6}\.log\.tmp).*".wget_secret_flag."\s(.*)/i", $task, $founded);
                $pid = $founded[1]; $logfile = $founded[2]; $url = $founded[3];
            } else {
                preg_match("/(\d{1,5}).*wget.*".wget_secret_flag."\s(.*)/i", $task, $founded);
                $pid = $founded[1]; $url = $founded[2];
            }
            $preogress = 0; 
            if(is_numeric($pid) && ($pid > 0) && is_string($url) && !empty($url)) {
                if (!empty($logfile) && file_exists($logfile)) {
                    // Read last line <http://stackoverflow.com/a/1510248>
                    $lastline = (string) ''; $f = fopen($logfile, 'r'); $cursor = -1;
                    fseek($f, $cursor, SEEK_END); $char = fgetc($f);
                    
                    while ($char === "\n" || $char === "\r") {
                        fseek($f, $cursor--, SEEK_END);
                        $char = fgetc($f);
                    }
                    
                    while ($char !== false && $char !== "\n" && $char !== "\r") {
                        $lastline = $char . $lastline;
                        fseek($f, $cursor--, SEEK_END);
                        $char = fgetc($f);
                    }
                    //echo "\n\n\n".$lastline."\n\n\n";
                    preg_match("/(\d{1,2}).*\[.*].*/i", $lastline, $founded);
                    $preogress = $founded[1];
                    //print_r($founded);
                }
                
                array_push($result, array(
                    'task'     => (string) $task, /* for debug */
                    'pid'      => (int) $pid, 
                    'logfile'  => (string) $logfile, 
                    'progress' => (int) $preogress, 
                    'url'      => (string) $url
                ));
            }
        }
    }
    return $result;
}
//print_r(getWgetTasks()); // Debug call

$Ajax = true; // Request by AJAX or POST?
// Result array {status: 0, msg: 'message'}
//   -1 - script started without errors
//    0 - error
//    1 - script finished without errors
$result = array('status' => -1, 'msg' => 'No input data');

// If _POST and _GET empty - is direct run
if(empty($_POST) and empty($_GET)) {
    header("HTTP/1.0 503 Service Unavailable");
    die();
}

// AJAX send data in GET, html in POST
if((count($_POST) > 0) and (count($_GET) === 0)) {
    $Ajax = false;
    // Move data from $_POST to $_GET
    $_GET = $_POST;
    // clear $_POST
    unset($_POST);
}

//$result['input'] = $_GET; // For debug

if(!empty($_GET['action'])) {
    // Set value from GET array to $formData
    $formData = array(
        'action' => $_GET['action'],
        'url' => $_GET['url'],
        'id' => $_GET['id']
    );
    // Make some clear
    foreach ($formData as $key => $value) {
        $formData[$key] = htmlspecialchars(strip_tags($value));
    }
    
    switch ($formData['action']) {
        ## Action - Get Tasks List
        ##########################
        case 'get_list':
            $result['tasks'] = array();
            
            foreach (getWgetTasks() as $task) {
                array_push($result['tasks'], array(
                    'url'      => (string) $task['url'],
                    'progress' => (int) $task['progress'],
                    'id'       => (int) $task['pid']
                ));
            }
            
            $result['msg']    = 'Active tasks list';
            $result['status'] = 1;
            break;
        ## Action - Add Task
        ####################
        case 'add_task':
            $url = $formData['url'];
            
            $taskPid = addWgetTask($url);
            
            if($taskPid > 0) {
                $result['msg']    = 'Task added';
                $result['id']     = (int) $taskPid;
                $result['status'] = 1;
            } else {
                $result['msg']    = 'Error task add';
                $result['status'] = 0;
            }
            break;
        ## Action - Cancel (remove) Task
        ################################
        case 'remove_task':
            $url = $formData['url'];
            $id = $formData['id'];
            
            if(removeWgetTask($id)) {
                $result['msg']    = 'Task removed';
                $result['status'] = 1;
            } else {
                $result['msg']    = 'Task remove error';
                $result['status'] = 0;
            }
            break;
            
        default:
            $result['msg']    = 'No action';
            $result['status'] = 0;
    }

}


echo(json_encode($result));