<?php
/**
* Pixelcounter data management
*
* @copyright 2002-2006 by papaya Software GmbH - All rights reserved.
* @link http://www.papaya-cms.com/
* @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License, version 2
*
* You can redistribute and/or modify this script under the terms of the GNU General Public
* License (GPL) version 2, provided that the copyright and license notes, including these
* lines, remain unmodified. papaya is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
* @version $Id: edmodule_pixelcounter.php 6 2014-02-20 12:10:45Z SystemVCS $
*/

/**
* Basic class module
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_module.php');

/**
* Pixelcounter data management
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
*/
class edmodule_pixelcounter extends base_module {

  const PERMISSION_MANAGE = 1;

  /**
  * permissions
  * @var array $permissions
  */
  var $permissions = array(
    self::PERMISSION_MANAGE => 'Manage',
  );

  var $pluginOptionFields = array(
    'FIELD_CODE_VALIDATION' => array(
      'Code field validation', 'isNum', FALSE, 'combo', array(0 => 'IVW Code', 1 => 'Text'), '', 0
    )
  );


  /**
  * Function for execute module
  *
  * @access public
  */
  function execModule() {
    if ($this->hasPerm(self::PERMISSION_MANAGE, TRUE)) {
      include_once(dirname(__FILE__).'/admin_pixelcounter.php');
      $ivw = new admin_pixelcounter;
      $ivw->module = $this;
      $ivw->images = $this->images;
      $ivw->layout = $this->layout;
      $ivw->authUser = $this->authUser;
      $ivw->initialize();
      $ivw->execute();
      $ivw->getXML();
    }
  }
}

