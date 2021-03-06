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

// prevent direct calls
if (!defined('__CHRIS_ENTRY_POINT__'))
  die('Invalid access.');

// grab the super class for all entities
require_once 'object.model.php';

/**
 *
 * The Data class which describes the Data entity of the database.
 *
 */
class Data extends Object {

  /**
   * The data unique ID.
   * We use it to make sure data we will add to the database doesn't already exists.
   *
   * @var string $uid
   */
  public $uid = '';

  /**
   * The description of the data.
   * Text file name, dicom protocol, etc...
   *
   * @var string $description
   */
  public $description = '';
  
  /**
   * The name of the data.
   * Text file name, dicom protocol, etc...
   *
   * @var string $name
   */
  public $name = '';

  /**
   * The time of the data creation.
   *
   * @var string $time
   */
  public $time = '';
  
  /**
   * The number of files in this data.
   *
   * @var int $nb_files
   */
  public $nb_files = -1;

  /**
   * The status of this dataset
   *
   * @var int $status
   */
  public $status = 0;

  /**
   * The plugin which introduced this dataset
   *
   * @var string $plugin
   */
  public $plugin = '';
}
?>