#!/usr/bin/php
<?php
/**
 *
 *            sSSs   .S    S.    .S_sSSs     .S    sSSs
 *           d%%SP  .SS    SS.  .SS~YS%%b   .SS   d%%SP
 *          d%S'    S%S    S%S  S%S   `S%b  S%S  d%S'
 *          S%S     S%S    S%S  S%S    S%S  S%S  S%|
 *          S&S     S%S SSSS%S  S%S    d* S  S&S  S&S
 *          S&S     S&S  SSS&S  S&S   .S* S  S&S  Y&Ss
 *          S&S     S&S    S&S  S&S_sdSSS   S&S  `S&&S
 *          S&S     S&S    S&S  S&S~YSY%b   S&S    `S*S
 *          S*b     S*S    S*S  S*S   `S%b  S*S     l*S
 *          S*S.    S*S    S*S  S*S    S%S  S*S    .S*P
 *           SSSbs  S*S    S*S  S*S    S&S  S*S  sSS*S
 *            YSSP  SSS    S*S  S*S    SSS  S*S  YSS'
 *                         SP   SP          SP
 *                         Y    Y           Y
 *
 *                     R  E  L  O  A  D  E  D
 *
 * (c) 2012 Fetal-Neonatal Neuroimaging & Developmental Science Center
 *                   Boston Children's Hospital
 *
 *              http://childrenshospital.org/FNNDSC/
 *                        dev@babyMRI.org
 *
 */
if(!defined('__CHRIS_ENTRY_POINT__')) define('__CHRIS_ENTRY_POINT__', 666);

// include the configuration
require_once (dirname(dirname(__FILE__)).'/config.inc.php');
//require_once (joinPaths(CHRIS_CONTROLLER_FOLDER, 'security.controller.php'));

// include the controller
require_once (joinPaths(CHRIS_CONTROLLER_FOLDER, 'user.controller.php'));
require_once (joinPaths(CHRIS_MODEL_FOLDER, 'user.model.php'));

require_once (joinPaths(CHRIS_CONTROLLER_FOLDER, 'feed.controller.php'));
require_once (joinPaths(CHRIS_MODEL_FOLDER, 'feed.model.php'));

require_once (joinPaths(CHRIS_CONTROLLER_FOLDER, 'token.controller.php'));

require_once ('Net/SSH2.php');
require_once('Crypt/RSA.php');

// tempnam actually creates a file in addition to
// generating a name.
//$of = tempnam(sys_get_temp_dir(), 'PHPconsole-');
//if (file_exists($tempfile)) { unlink($tempfile); }


// check if we are invoked by commandline
$commandline_mode = (php_sapi_name() == 'cli');

if ($commandline_mode) {

  // parse the options if we are in commandline mode

  // define the options
  $shortopts = "c:u::f::i::j::h";
  $longopts  = array(
      "command:",     // Required value
      "username::",    // Optional value
      "password::",
      "feedname::",    // Optional value
      "feedid::",    // Optional value
      "jobid::",    // Optional value
      "status::", // Optional value
      "statusstep::",
      "memory::", // Optional value
      "help"    // Optional value
  );

  $options = getopt($shortopts, $longopts);

  if( array_key_exists('h', $options) || array_key_exists('help', $options))
  {
    echo "this is the help!";
    echo "\n";
    return;
  }

  //if no command provided, exit
  $command = '';
  if( array_key_exists('c', $options))
  {
    $command = $options['c'];
  }
  elseif (array_key_exists('command', $options))
  {
    $command = $options['command'];
  }
  else{
    echo "no command provided!";
    echo "\n";
    return;
  }

  // is username given?
  $username = 'cli_user';
  if( array_key_exists('u', $options))
  {
    $username = $options['u'];
  }
  elseif (array_key_exists('username', $options))
  {
    $username = $options['username'];
  }

  // is password given?
  $password = 'secret';
  if( array_key_exists('p', $options))
  {
    $username = $options['p'];
  }
  elseif (array_key_exists('password', $options))
  {
    $password = $options['password'];
  }

  // is feedname given?
  $feedname = 'cli_feed';
  if( array_key_exists('f', $options))
  {
    $feedname = sanitize($options['f']);
  }
  elseif (array_key_exists('feedname', $options))
  {
    $feedname = sanitize($options['feedname']);
  }
  // is there a job id
  $jobid = '';
  if( array_key_exists('j', $options))
  {
    $jobid = $options['j'];
  }
  elseif (array_key_exists('jobid', $options))
  {
    $jobid = $options['jobid'];
  }

  $feed_id = -1;
  $feed_id = $options['feedid'];

  // set the initial status, if --status is provided, use this value
  $status = 0;
  if (array_key_exists('status', $options)) {
    $status = $options['status'];
  }

  // set the initial status, if --status is provided, use this value
  $status_step = 100;
  if (array_key_exists('statusstep', $options)) {
    $status_step = $options['statusstep'];
  }

  // set the initial memory, if --status is provided, use this value
  $memory = 2048;
  if (array_key_exists('memory', $options)) {
    $memory = $options['memory'];
  }

}


// *****************
// here we either entered via CLI or via PHP
// meaning that the following variables must have been set
// $command
// $username
// $password
// $feedname
// $feed_id
// $jobid
// $memory
// $status
// $status_step
// *****************


//
// get the name of the executable as plugin name
// get the list of parameters
//

$plugin_command_array = explode ( ' ' , $command );
$plugin_name_array = explode ( '/' , $plugin_command_array[0]);
$plugin_name = end($plugin_name_array);
array_shift($plugin_command_array);
$parameters = implode(' ', $plugin_command_array);


//
// initiate ssh connection
//

$sshLocal = new Net_SSH2('localhost');
if (!$sshLocal->login($username, $password)) {
  die('Server login Failed');
}
$force_chris_local = in_array($plugin_name,explode(',', CHRIS_RUN_AS_CHRIS_LOCAL));
if ($status == 100 || $force_chris_local) {
  $host = 'localhost';
} else {
  $host = CLUSTER_HOST;
  $sshCluster  = new Net_SSH2($host);
  if (!$sshCluster->login($username, $password)) {
    die('Cluster login Failed');
  }
}


//
// get username's id
//
$user_id = UserC::getID($username);

//
// create the feed in the database if first batch job
// if $feed_id has already been defined (bash job), we do not generate a new id
//

if($feed_id == -1){
  $feed_id = FeedC::create($user_id, $plugin_name, $feedname, $status);
}


//
// create the feed directory
//

$user_path = joinPaths(CHRIS_USERS, $username);
$plugin_path = joinPaths($user_path, $plugin_name);
$feed_path = joinPaths($plugin_path, $feedname.'-'.$feed_id);

// create job directory
$job_path = $feed_path;
if($jobid != ''){
  $job_path .= '/'.$jobid;
}
//$job_path_output = createDir($sshLocal, $job_path, '');
$job_path_output = createDir($sshLocal, $job_path);

//
// replace ${OUTPUT} pattern in the command and in the parameters
//

$command = str_replace("{OUTPUT}", $job_path, $command);
$command = str_replace("{FEED_ID}", $feed_id, $command);
$command = str_replace("{USER_ID}", $user_id, $command);
$parameters = str_replace("{OUTPUT}", $job_path, $parameters);
$parameters = str_replace("{FEED_ID}", $feed_id, $parameters);
$parameters = str_replace("{USER_ID}", $user_id, $parameters);

// append the log files to the command
$command .= ' >> '.$job_path_output.'/chris.std 2> '.$job_path_output.'/chris.err';


//
// add meta information to the feed in the database
//

FeedC::addMetaS($feed_id, 'parameters', $parameters, 'simple');
FeedC::addMetaS($feed_id, 'root_id', (string)$feed_id, 'extra');


//
// create the file containing the chris env variables which are required by the plugin
// and fill it
//

$envfile = joinPaths($job_path_output, 'chris.env');
$sshLocal->exec(bash('echo "export ENV_CHRISRUN_DIR='.$job_path_output.'" >>  '.$envfile));
$sshLocal->exec(bash('echo "export ENV_REMOTEUSER='.$username.'" >>  '.$envfile));
$sshLocal->exec(bash('echo "export ENV_CLUSTERTYPE='.CLUSTER_TYPE.'" >>  '.$envfile));
$sshLocal->exec(bash('echo "export ENV_REMOTEHOST='.CLUSTER_HOST.'" >>  '.$envfile));
// should be renamed CLUSTER_CHRIS_PYTHONPATH
$sshLocal->exec(bash('echo "export PYTHONPATH=$PYTHONPATH:'.CHRIS_ENV_PYTHONPATH.'" >>  '.$envfile));
$sshLocal->exec(bash('echo "export PATH=$PATH:'.CLUSTER_CHRIS_BIN.'" >>  '.$envfile));
$sshLocal->exec(bash('echo "export LD_LIBRARY_PATH=$LD_LIBRARY_PATH:'.CLUSTER_CHRIS_LIB.'" >>  '.$envfile));
$sshLocal->exec(bash('echo "umask 0002" >> '.$envfile));

// make sure to update the permissions of the file
$sshLocal->exec("chmod 644 $envfile");

//
// create the file containing the chris run commannds
// and fill it
// 1- source en
// 2- set status to 1
// 3- log date/hostname
// 4- run plugin
// 5- generate json scene for the viewer
// 6- set status to 100
// 7- update permissions of the files
//

$runfile = joinPaths($job_path_output, 'chris.run');

// the set status command, to update a job status through curl
$setStatus = '';
// retry 5 times with a 5 seconds delay
// connection timeout: 5s
// max query time: 30
if ($status != 100) {
//  $setStatus .= '/bin/sleep $(( RANDOM%=10 )) ; /usr/bin/curl --retry 5 --retry-delay 5 --connect-timeout 5 --max-time 30 -v -k --data ';
  $setStatus .= '/bin/sleep $(( RANDOM%=10 )) ; /usr/bin/curl -k --data ';
}

// 1- source the environment
$sshLocal->exec(bash('echo "source '.$envfile.';" >> '.$runfile));

// 2- set status to 1 if necessary
// status == 1 means the job has started
// status == 100 means this a 'non-blocking' plugin that is just gonna run without scheduling
// this is defined in the plugin's configuration
// for instance, the file_browser is a non-blocking plugin

// 3- log to the chris.std the time and machine on which the plugin is running (useful for debugging)
$sshLocal->exec(bash('echo "echo \"\$(date) Running on \$HOSTNAME\" > '.$job_path_output.'/chris.std" >> '.$runfile));

// 4- run the actual plugin
$sshLocal->exec(bash('echo "'.$command.'" >> '.$runfile));

// 6- make sure to update file permissions
$sshLocal->exec("echo 'chmod 775 $user_path $plugin_path; chmod 755 $feed_path; cd $feed_path ; find . -type d -exec chmod o+rx,g+rx {} \; ; find . -type f -exec chmod o+r,g+r {} \;' >> $runfile;");


//
// the chris.run is ready now
// execute the chris.run how it is supposed to (local, remote, as chris, etc.)
// update the chris.run file permissions to be executable
//

$sshLocal->exec("chmod 755 $runfile");

if ($force_chris_local) {
  // get user group id
  $groupID =  $sshLocal->exec("id -g");
  $groupID = trim($groupID);

  // make sure the permissions are correct
  // and give all files ownership to users after the job finished.
  $sshLocal->exec("echo 'sudo chmod -R 755 $feed_path;' >> $runfile;");
  $sshLocal->exec("echo 'sudo chown -R $user_id:$groupID $feed_path;' >> $runfile;");

  // update path to tmp path
  $tmp_path = CHRIS_TMP.'/'.$feedname.'-'.$feed_id;
  $escaped_tmp_path = str_replace("/", "\/", $tmp_path);
  $escaped_path  = str_replace("/", "\/", $feed_path);
  $sshLocal->exec("sed -i 's/$escaped_path/$escaped_tmp_path/g' $runfile");

  // copy files back to network space, whith the right permissions
  $sshLocal->exec("echo 'sudo su $username -c \"cp -rfp $tmp_path $plugin_path\";' >> $runfile;");
  // create the json db for the viewer plugin once the data is in its final location
  $viewer_plugin = CHRIS_PLUGINS_FOLDER.'/viewer/viewer';
  $sshLocal->exec("echo 'sudo su $username  -c \"$viewer_plugin --directory $job_path --output $job_path/..\";' >> $runfile;");

  // update status to 100%
  if($status != 100){
    // prepend
    $start_token = TokenC::create();
    $sshLocal->exec('sed -i "1i '.$setStatus.'\'action=set&what=feed_status&feedid='.$feed_id.'&op=set&status=1&token='.$start_token.'\' '.CHRIS_URL.'/api.php > '.$job_path_output.'/curlA.std 2> '.$job_path_output.'/curlA.err" '.$runfile);

    // append
    // we need sudo su to run it at the right location after the data has been copied back
    $end_token = TokenC::create();
    $sshLocal->exec('echo "sudo su '.$username.' -c \"'.$setStatus.'\'action=set&what=feed_status&feedid='.$feed_id.'&op=inc&status=+'.$status_step.'&token='.$end_token.'\' '.CHRIS_URL.'/api.php > '.$job_path_output.'/curlB.std 2> '.$job_path_output.'/curlB.err\"" >> '.$runfile);
  }

  // open permissions so user can see its plugin running
  $local_command = "/bin/chgrp -R $groupID $feed_path; /bin/chmod g+rxw -R $feed_path";
  $sshLocal->exec($local_command);

  unset($sshLocal);

  // run command as locally ChRIS!
  // *** IMPORTANT ***
  // do not need to ssh as chris because we assume
  // plugins which run locally are started from the front end
  // therefore calling launcher.php as chris

  // create local directory
  mkdir('/tmp/'.$feedname.'-'.$feed_id);
  shell_exec("cp -R $feed_path ".CHRIS_TMP);

  $local_command = "/bin/bash umask 0002;/bin/bash $runfile;";
  $local_command .= "sudo rm -rf $tmp_path;";
  $nohup_wrap = 'bash -c \'nohup bash -c "'.$local_command.'" > /dev/null 2>&1 &\'';
  shell_exec($nohup_wrap);
  $pid = -1;
} else if ($status == 100 ) {
  // create the json db for the viewer plugin once the data is in its final location
  $viewer_plugin = CHRIS_PLUGINS_FOLDER.'/viewer/viewer';
  $sshLocal->exec("echo '$viewer_plugin --directory $job_path --output $job_path/..;' >> $runfile;");

  // run locally
  $sshLocal->exec('bash -c \' /bin/bash '.$runfile.'\'');
  $pid = -1;
}
else
{ //
  // run on cluster and return pid
  //
  if (!CLUSTER_SHARED_FS) {
    $cluster_user_path = joinPaths(CLUSTER_CHRIS_USERS, $username);
    $cluster_plugin_path = joinPaths($cluster_user_path, $plugin_name);
    $cluster_feed_path = joinPaths($cluster_plugin_path, $feedname.'-'.$feed_id);
    // create job directory
    $cluster_job_path = $cluster_feed_path;
    if($jobid != ''){
      $cluster_job_path .= '/'.$jobid;
    }
    $cluster_job_path_output = createDir($sshCluster, $cluster_job_path);
    $cluster_job_path_output.PHP_EOL;

    // replace chris server's paths in chris.env by cluster's paths
    $envfile_str = file_get_contents($envfile);
    $envfile_str = str_replace($job_path_output, $cluster_job_path_output, $envfile_str);
    $envfile = joinPaths($cluster_job_path_output, 'chris.env');
    $sshCluster->exec('echo "'.$envfile_str.'"'.' > '.$envfile);

    // create _chrisInput_ dir
    $chrisInputDirectory = '_chrisInput_';
    $sshLocal->exec('cd ' . $job_path.'; mkdir '.$chrisInputDirectory.'; chmod 755 '.$chrisInputDirectory);

    // run the plugin with the --inputs switch on the chris server
    $plugin_command_array = explode(' ', $command);
    // $inputs_options is a string containing a list of input options separated by comma
    $input_options = $sshLocal->exec($plugin_command_array[0].' --inputs');
    //remove EOL and white spaces
    $input_options = trim(preg_replace('/\s+/', ' ', $input_options));
    $input_options_array = explode(',', $input_options);

    // replace chris server's paths in chris.run by cluster's paths
    $runfile_str = file_get_contents($runfile);
    $runfile_str = str_replace($user_path, $cluster_user_path, $runfile_str);
    //A tmp dir within _chrisInput_ is necessary to put the data previous to anonymization
    $tmp = $chrisInputDirectory;
    if (ANONYMIZE_DICOM) {
      $tmp = joinPaths($tmp, 'tmp');
      $sshLocal->exec('cd ' . joinPaths($job_path, $chrisInputDirectory) . '; mkdir tmp;');
    }
    foreach ($input_options_array as $in) {
      // get location of input in the command array
      $input_key = array_search($in, $plugin_command_array);
      if($input_key !== false){
        // get value of the input in the command array
        // the value of the input should be the next element in the $command_array
        $value_key = $input_key + 1;
        $value = $plugin_command_array[$value_key];
        if (is_dir($value)) {
          $value_dirname = $value;
        } else {
          $value_dirname = dirname($value);
        }
        // need to add the full absolute path to make it unique
        $value_chris_path = joinPaths($job_path, $tmp, $value_dirname);
        // -n to not overwrite file if already there
        // -L to dereference links (copy actual file rather than link)
        $sshLocal->exec('cp -Lrn ' . $value_dirname . ' ' . dirname($value_chris_path));
        $value = str_replace($user_path, $cluster_user_path, $value);
        $runfile_str = str_replace($plugin_command_array[$input_key].' '.$value, $plugin_command_array[$input_key].' '.joinPaths($cluster_job_path, $chrisInputDirectory, $value_dirname), $runfile_str);
      }
    }
    //anonymization
    if (ANONYMIZE_DICOM) {
      //$dir_array contains the list of all subdirectories in the tree with root joinPaths($job_path, $tmp)
      $dir_iter = new RecursiveDirectoryIterator(joinPaths($job_path, $tmp), RecursiveDirectoryIterator::SKIP_DOTS);
      $iter = new RecursiveIteratorIterator($dir_iter, RecursiveIteratorIterator::SELF_FIRST);
      $tmp_path = joinPaths($job_path, $tmp);
      $dir_array = array($tmp_path);
      foreach ($iter as $dir => $dirObj) {
        if ($dirObj->isDir()) {
          $dir_array[] = $dir;
        }
      }
      //for each subdirectory in the tree find out if it contains dicom files and if so then run anonymization
      //the output goes to the same directory structure but without the intermediate tmp (directly below _chrisInput_)
      //if no dicom files is found the directory is just copied as it is
      foreach ($dir_array as $dir) {
        $dicomFiles = glob($dir.'/*.dcm');
        if (count($dicomFiles)) {
          $sshLocal->exec(CHRIS_SRC.'/../scripts/dcmanon_meta.bash -P -O ' . $dir . ' -D ' . str_replace($tmp_path, joinPaths($job_path, $chrisInputDirectory), $dir));
        } else {
          $outDir = str_replace($tmp_path, joinPaths($job_path, $chrisInputDirectory), $dir);
          $sshLocal->exec('mkdir -p ' . $outDir . '; cp -r ' . $dir . ' ' . dirname($outDir));
        }
      }
      //remove the tmp directory
      $sshLocal->exec('rm -r '. $tmp_path);
    }
    $runfile_str = str_replace(CHRIS_PLUGINS_FOLDER, CHRIS_PLUGINS_FOLDER_NET, $runfile_str);

    //
    // MOVE DATA ($chrisInputDirectory) FROM SERVER TO CLUSTER
    //

    if (CLUSTER_PORT==22) {
      $tunnel_host = CHRIS_HOST;
    } else {
      $tunnel_host = CLUSTER_HOST;
    }

    // command to compress _chrisInput_ dir on the chris server
    $cmd = '\"cd '.$job_path.'; tar -zcf '.$chrisInputDirectory.'.tar.gz '.$chrisInputDirectory.';\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' -o StrictHostKeyChecking=no ' . $username.'@'.$tunnel_host. ' '.$cmd;

    // command to copy over the compressed _chrisIput_ dir to the cluster
    $cmd = $cmd.PHP_EOL.'scp -P ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host.':'.$job_path.'/'.$chrisInputDirectory.'.tar.gz ' .$cluster_job_path.';';

    // command to remove the compressed file on the chris server
    $cmd = $cmd.PHP_EOL.'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' rm '.$job_path.'/'.$chrisInputDirectory.'.tar.gz;';

    // command to uncompress the compressed file on the cluster
    $cmd = $cmd.PHP_EOL.'cd '.$cluster_job_path.'; tar -zxf '.$chrisInputDirectory.'.tar.gz;';

    // command to remove the compressed file from the cluster
    $cmd = $cmd.PHP_EOL.'cd '.$cluster_job_path.'; rm '.$chrisInputDirectory.'.tar.gz;';
    $runfile_str = $cmd.PHP_EOL.$runfile_str;

    //
    // MOVE DATA ($job_path directory) FROM CLUSTER TO SERVER
    //

    // command to compress $cluster_job_path dir on the cluster (excluding _chrisInput_ dir)
    $data = basename($job_path);
    $cmd = 'cd '.$cluster_feed_path.'; tar -zcf '.$data.'.tar.gz '.$data.' --exclude ' . joinPaths($data, $chrisInputDirectory). ';';
    $runfile_str = $runfile_str.$cmd;

    // command to copy over the compressed $cluster_job_path dir to the chris server
    $cmd = 'scp -P ' .CLUSTER_PORT. ' ' . $cluster_feed_path.'/'.$data.'.tar.gz ' . $username.'@'.$tunnel_host.':'.$feed_path.';';
    $runfile_str = $runfile_str.PHP_EOL.$cmd;

    // command to uncompress and remove the compressed file on the chris server
    $cmd = '\"cd '.$feed_path.'; tar -zxf '.$data.'.tar.gz; rm '.$data.'.tar.gz;\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' '.$cmd;
    $runfile_str = $runfile_str.PHP_EOL.$cmd;

    // command to remove the compressed file from the cluster
    $cmd = 'cd '.$cluster_feed_path.'; rm '.$data.'.tar.gz &';
    $runfile_str = $runfile_str.PHP_EOL.$cmd;

    //
    // CREATE VIEWER SCENE
    //

    $viewer_plugin = CHRIS_PLUGINS_FOLDER.'/viewer/viewer';
    $cmd = '\"'.$viewer_plugin.' --directory '.$job_path.' --output '.$job_path.'/..;\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' '.$cmd;
    $runfile_str = $runfile_str.PHP_EOL.$cmd;

    //
    // UPDATE FEED STATUS
    //
    $start_token = TokenC::create();
    $cmd = '\"'.$setStatus.'\'action=set&what=feed_status&feedid='.$feed_id.'&op=set&status=1&token='.$start_token.'\' '.CHRIS_URL.'/api.php > '.$job_path_output.'/curlA.std 2> '.$job_path_output.'/curlA.err;\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' '.$cmd;
    $runfile_str = $cmd.PHP_EOL.$runfile_str;

    $end_token = TokenC::create();
    $cmd = '\"'.$setStatus.'\'action=set&what=feed_status&feedid='.$feed_id.'&op=inc&status=+'.$status_step.'&token='.$end_token.'\' '.CHRIS_URL.'/api.php > '.$job_path_output.'/curlB.std 2> '.$job_path_output.'/curlB.err;\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' '.$cmd;
    $runfile_str = $runfile_str.PHP_EOL.$cmd;


    $runfile = joinPaths($cluster_job_path_output, 'chris.run');
    $sshCluster->exec('echo "'.$runfile_str.'"'.' > '.$runfile);
    $sshCluster->exec('chmod 775 '.$runfile);

    ////
    // WHEN DO WE DELETE THE REMOTE DATA????
    /////
  }
  else{

    // create the json db for the viewer plugin once the data is in its final location
    $viewer_plugin = CHRIS_PLUGINS_FOLDER_NET.'/viewer/viewer';
    $sshLocal->exec("echo '$viewer_plugin --directory $job_path --output $job_path/..;' >> $runfile;");

    //
    // UPDATE FEED STATUS
    //

    if (CLUSTER_PORT==22) {
      $tunnel_host = CHRIS_HOST;
    } else {
      $tunnel_host = CLUSTER_HOST;
    }

    $start_token = TokenC::create();
    $cmd = '\"'.$setStatus.'\'action=set&what=feed_status&feedid='.$feed_id.'&op=set&status=1&token='.$start_token.'\' '.CHRIS_URL.'/api.php > '.$job_path_output.'/curlA.std 2> '.$job_path_output.'/curlA.err;\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' '.$cmd;
    $sshLocal->exec('sed -i "1i '.$cmd.'" '.$runfile);

    $end_token = TokenC::create();
    $cmd = '\"'.$setStatus.'\'action=set&what=feed_status&feedid='.$feed_id.'&op=inc&status=+'.$status_step.'&token='.$end_token.'\' '.CHRIS_URL.'/api.php > '.$job_path_output.'/curlB.std 2> '.$job_path_output.'/curlB.err;\"';
    $cmd = 'ssh -p ' .CLUSTER_PORT. ' ' . $username.'@'.$tunnel_host . ' '.$cmd;
    $sshLocal->exec('echo "'.$cmd.'" >> '.$runfile);
  }

  $cluster_command = str_replace("{MEMORY}", $memory, CLUSTER_RUN);
  $cluster_command = str_replace("{FEED_ID}", $feed_id, $cluster_command);
  $cluster_command = str_replace("{COMMAND}", "/bin/bash ".$runfile, $cluster_command);
  $pid = $sshCluster->exec(bash($cluster_command));
}

// attach pid to feed
$metaObject = new Meta();
$metaObject->name = "pid";
$metaObject->value = $pid;
FeedC::addMeta($feed_id, Array(0 => $metaObject));

echo $feed_id;
?>
