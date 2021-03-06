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
if(!defined('__CHRIS_ENTRY_POINT__')) die('Invalid access.');

// grab the super class for all entities
require_once 'object.model.php';


/**
 *
 * The Patient class which describes the Patient entity of the database.
 *
 */
class Patient extends Object {
  
  /**
   * The name of this patient following the dicom convention.
   * LASTNAME^FIRSTNAME^MIDDLENAME
   *
   * @var string $name
   */
  public $name = null;

  /**
   * The date of birth of this patient.
   *
   * @var string $dob
   */
  public $dob = null;

  /**
   * The sex of this patient (M|F).
   *
   * @var string $sex
   */
  public $sex = null;

  /**
   * The patient_id of this patient. This is also known as the MRN.
   *
   * @var string $uid
   */
  public $uid = null;

}

?>