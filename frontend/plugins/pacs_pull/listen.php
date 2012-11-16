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
 * (c) 2012 Fetal-Neonatal Neuroimaging & Developmental Science Center4352490
 *                   Boston Children's Hospital
 *
 *              http://childrenshospital.org/FNNDSC/
 *                        dev@babyMRI.org
 *
 */
// we define a valid entry point
if(!defined('__CHRIS_ENTRY_POINT__')) define('__CHRIS_ENTRY_POINT__', 666);
// include the configuration file
 if(!defined('CHRIS_CONFIG_PARSED'))
  require_once(dirname(dirname(dirname ( __FILE__ ))).'/config.inc.php');

// build the storescp command
// storescp will move incoming files to temp directory "CHRIS_INCOMINGDATA"
// then each incoming data is processed by $process_command
/* $process_command = joinPaths(CHRIS_CONTROLLER_FOLDER, 'pacs_process.php -p #p -f #f'); */
$process_command = joinPaths(CHRIS_PLUGINS_FOLDER, 'pacs_pull/post_process.php -p #p -f #f');
$listen_command = '/usr/bin/storescp -id -od ' . CHRIS_TMP . ' -pm -xcr  \'' . $process_command . '\' -ss RX -tos 120';

// open log file
$logFile = joinPaths(CHRIS_LOG, 'pacs_pull_listen.log');
$fh = fopen($logFile, 'a')  or die("can't open file");
//write date
fwrite($fh, "=========================================". PHP_EOL);
fwrite($fh, date('D, F d Y - h:i:s A'). PHP_EOL);
//write command
fwrite($fh, $listen_command. PHP_EOL);
// execute the command
$stringOutput = exec($listen_command);
// write output
fwrite($fh, $stringOutput. PHP_EOL);
fclose($fh);
?>