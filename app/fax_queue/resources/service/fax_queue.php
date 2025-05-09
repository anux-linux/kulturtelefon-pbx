<?php

//run from command line only
	if (!defined('STDIN')) {
		exit;
	}

//includes files
	require_once  dirname(__DIR__, 4) . "/resources/require.php";
	require_once "resources/pdo.php";

//increase limits
	set_time_limit(0);
	ini_set('max_execution_time', 0);
	ini_set('memory_limit', '512M');

//save the arguments to variables
	$script_name = $argv[0];
	if (!empty($argv[1])) {
		parse_str($argv[1], $_GET);
	}
	//print_r($_GET);

//set the variables
	if (isset($_GET['hostname'])) {
		$hostname = urldecode($_GET['hostname']);
	}
	if (isset($_GET['debug'])) {
		$debug = $_GET['debug'];
	}
	if (isset($_GET['sql'])) {
		$debug_sql = $_GET['sql'];
	}

//define the process id file
	$pid_file = "/var/run/fusionpbx/".basename( $argv[0], ".php") .".pid";
	//echo "pid_file: ".$pid_file."\n";

//function to check if the process exists
	function process_exists($file = false) {

		//set the default exists to false
		$exists = false;

		//check to see if the process is running
		if (file_exists($file)) {
			$pid = file_get_contents($file);
			if (function_exists('posix_getsid')) {
				if (posix_getsid($pid) === false) { 
					//process is not running
					$exists = false;
				}
				else {
					//process is running
					$exists = true;
				}
			}
			else {
				if (file_exists('/proc/'.$pid)) {
					//process is running
					$exists = true;
				}
				else {
					//process is not running
					$exists = false;
				}
			}
		}

		//return the result
		return $exists;
	}

//check to see if the process exists
	$pid_exists = process_exists($pid_file);

//prevent the process running more than once
	if ($pid_exists) {
		echo "Cannot lock pid file {$pid_file}\n";
		exit;
	}

//make sure the /var/run/fusionpbx directory exists
	if (!file_exists('/var/run/fusionpbx')) {
		$result = mkdir('/var/run/fusionpbx', 0777, true);
		if (!$result) {
			die('Failed to create /var/run/fusionpbx');
		}
	}

//create the process id file if the process doesn't exist
	if (!$pid_exists) {
		//remove the old pid file
		if (file_exists($file)) {
			unlink($pid_file);
		}

		//show the details to the user
		//echo "The process id is ".getmypid()."\n";
		//echo "pid_file: ".$pid_file."\n";

		//save the pid file
		file_put_contents($pid_file, getmypid());
	}

//get the fax queue settings
	$setting = new settings(["category" => "fax_queue"]);

//set the fax queue interval
	if (!empty($settings->get('fax_queue', 'interval'))) {
		$fax_queue_interval = $settings->get('fax_queue', 'interval');
	}
	else {
		$fax_queue_interval = '30';
	}

//set the fax queue limit
	if (!empty($settings->get('fax_queue', 'limit'))) {
		$fax_queue_limit = $settings->get('fax_queue', 'limit');
	}
	else {
		$fax_queue_limit = '30';
	}
	if (!empty($settings->get('fax_queue', 'debug'))) {
		$debug = $settings->get('fax_queue', 'debug');
	}

//set the fax queue retry interval
	if (!empty($settings->get('fax_queue', 'retry_interval'))) {
		$fax_retry_interval = $settings->get('fax_queue', 'retry_interval');
	}
	else {
		$fax_retry_interval = '180';
	}

//change the working directory
	chdir($_SERVER['DOCUMENT_ROOT']);

//get the messages waiting in the fax queue
	while (true) {

		//get the fax messages that are waiting to send
			$sql = "select * from v_fax_queue ";
			$sql .= "where hostname = :hostname ";
			$sql .= "and ( ";
			$sql .= "	( ";
			$sql .= "		(fax_status = 'waiting' or fax_status = 'trying' or fax_status = 'busy') ";
			$sql .= "		and (fax_retry_date is null or floor(extract(epoch from now()) - extract(epoch from fax_retry_date)) > :retry_interval) ";
			$sql .= "	)  ";
			$sql .= "	or ( ";
			$sql .= "		fax_status = 'sent' ";
			$sql .= "		and fax_email_address is not null ";
			$sql .= "		and fax_notify_date is null ";
			$sql .= "	) ";
			$sql .= ") ";
			$sql .= "order by domain_uuid asc ";
			$sql .= "limit :limit ";
			if (isset($hostname)) {
				$parameters['hostname'] = $hostname;
			}
			else {
				$parameters['hostname'] = gethostname();
			}
			$parameters['limit'] = $fax_queue_limit;
			$parameters['retry_interval'] = $fax_retry_interval;
			if (isset($debug_sql)) {
				echo $sql."\n";
				print_r($parameters);
			}
			$database = new database;
			$fax_queue = $database->select($sql, $parameters, 'all');
			unset($parameters);

		//show results from the database
			if (isset($debug_sql)) {
				echo $sql."\n";
				print_r($parameters);
			}

		//process the messages
			if (is_array($fax_queue) && @sizeof($fax_queue) != 0) {
				foreach($fax_queue as $row) {
					$command = PHP_BINARY." ".$_SERVER['DOCUMENT_ROOT']."/app/fax_queue/resources/job/fax_send.php ";
					$command .= "'action=send&fax_queue_uuid=".$row["fax_queue_uuid"]."&hostname=".$hostname."'";
					if (isset($debug)) {
						//run process inline to see debug info
						echo $command."\n";
						$result = system($command);
						echo $result."\n";
					}
					else {
						//starts process rapidly doesn't wait for previous process to finish (used for production)
						echo $command."\n";
						$handle = popen($command." > /dev/null &", 'r'); 
						echo "'$handle'; " . gettype($handle) . "\n";
						$read = fread($handle, 2096);
						echo $read;
						pclose($handle);
					}
				}
			}

		//pause to prevent excessive database queries
		sleep($fax_queue_interval);
	}

//remove the old pid file
	if (file_exists($pid_file)) {
		unlink($pid_file);
	}

//save output to
	//$fp = fopen(sys_get_temp_dir()."/mailer-app.log", "a");

//prepare the output buffers
	//ob_end_clean();
	//ob_start();

//message divider for log file
	//echo "\n\n=============================================================================================================================================\n\n";

//get and save the output from the buffer
	//$content = ob_get_contents(); //get the output from the buffer
	//$content = str_replace("<br />", "", $content);

	//ob_end_clean(); //clean the buffer

	//fwrite($fp, $content);
	//fclose($fp);

//notes
	//echo __line__."\n";
	// if not keeping the email then need to delete it after the voicemail is emailed

//how to use this feature
	// cd /var/www/fusionpbx; /usr/bin/php /var/www/fusionpbx/app/fax_queue/resources/send.php

?>
