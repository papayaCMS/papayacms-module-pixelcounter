<?php
/**
* Action box for IVW counter
*
* @package module_ivw
* @author Thomas Weinert <info@papaya-cms.com>
* @version $Id: actbox_ivw.php 5 2014-02-13 15:35:38Z SystemVCS $
*/

/**
* Basic class Aktion box
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_actionbox.php');

/**
* Action box for IVW counter
*
* @package module_ivw
* @author Thomas Weinert <info@papaya-cms.com>
*/
class actionbox_ivw extends base_actionbox {

  var $cacheDependency = array(
    'querystring' => TRUE,
    'page' => TRUE,
    'surfer' => TRUE
  );

  var $editFields = array(
    'project' => array ('Project code', 'isAlphaNum', TRUE, 'input', 8),
    'contenttype' => array ('Content type', 'isAlphaNum', TRUE, 'combo',
      array('CP' => 'Content pixel', 'XP' => 'Test pixel'), '', 'XP'),
    'Survey',
    'frabo_active' => array ('Use survey', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'Multi client',
    'multiclient_active' => array ('Use multi client', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'multiclient_https' => array ('Use https', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'multiclient_server' => array ('MC server', 'isAlphaNum', FALSE, 'input', 20, '', ''),
    'multiclient_version' => array (
      'Version', '~^\d{2}(1[0-2]|0\d)$~', TRUE, 'input', 4, '', '0308'
    ),
    'multiclient_salt' => array (
      'Hash prefix', 'isAlphaNumChar', TRUE, 'input', 20, '', 'pleasechange'
    )
  );

  /**
  * Get parsed data
  *
  * @access public
  * @return string
  */
  function getParsedData() {
    if (isset($this->parentObj) && is_subclass_of($this->parentObj, 'base_topic')) {
      include_once(dirname(__FILE__).'/base_pixelcounter.php');
      $pageDataObject = new base_pixelcounter();
      $pageData = $pageDataObject->loadCounterStatus($this->parentObj);
      if (!empty($this->data['project']) &&
          !empty($pageData['pixelcounter_code'])) {
        $this->setDefaultData();
        $project = urlencode($this->data['project']);
        $contentcode = urlencode($pageData['pixelcounter_code']);
        // Do we have handle-specific counter codes?
        if (!empty($pageData['pixelcounter_param']) &&
            !empty($pageData['handle_pixelcounter_codes'])) {
          $code = urlencode(
            $this->_getCodeByHandle(
              $pageData['pixelcounter_param'], $pageData['handle_pixelcounter_codes']
            )
          );
          if ($code != '') {
            $contentcode = $code;
          }
        }
        if (!empty($pageData['pixelcounter_comment'])) {
          $contentcomment = urlencode($pageData['pixelcounter_comment']);
        } else {
          $contentcomment = '';
        }
        if (isset($this->data['contenttype']) && $this->data['contenttype'] == 'CP') {
          $contenttype = 'CP';
        } else {
          $contenttype = 'XP';
        }
        $ivw = 'http://'.$project.'.ivwbox.de/cgi-bin/ivw/'.$contenttype.'/'.
          $contentcode.';'.$contentcomment;
        $result = "\n".'<!-- SZM VERSION="1.5" -->'."\n";
        $result .= '<script type="text/javascript">'."\n";
        $result .= '<!--'."\n";
        $result .= 'var IVW="'.$ivw.'";'."\n";
        $result .= 'document.write("<img src=\\""+IVW+"?r="+escape(document.referrer)+'.
          '"&amp;d="+(Math.random()*100000)+"\\"'.
          ' width=\\"1\\" height=\\"1\\" alt=\\"szmtag\\" />");'."\n";
        $result .= '//-->'."\n";
        $result .= '</script>'."\n";
        $result .= '<noscript>'."\n";
        $result .= '<img src="'.$ivw.'" width="1" height="1" alt="szmtag" />'."\n";
        $result .= '</noscript>'."\n";
        $result .= '<!-- /SZM -->'."\n";
        if ($this->data['frabo_active']) {
          $result .= '<!--SZMFRABO VERSION="1.2" -->'."\n";
          $result .= '<script type="text/javascript">'."\n";
          $result .= '<!--'."\n";
          $result .= 'var szmvars="'.$project.'//'.$contenttype.'//'.$contentcode.'";'."\n";
          $result .= '// -->'."\n";
          $result .= '</script>'."\n";
          $result .= '<script src="http://'.$project.
            '.ivwbox.de/2004/01/survey.js" type="text/javascript">'."\n";
          $result .= '</script>'."\n";
          $result .= '<!-- /SZMFRABO -->'."\n";
        }
        if ($this->data['multiclient_active'] && !empty($this->data['multiclient_server'])) {
          include_once(PAPAYA_INCLUDE_PATH.'system/base_surfer.php');
          $surfer = base_surfer::getInstance(FALSE);
          if ($surfer->isValid) {
            $surferHash = md5($surfer->surferId.$this->data['multiclient_salt']);

            $mclientProtocol = ($this->data['multiclient_https'])
              ? 'https' : 'http';
            $mclientServer = urlencode($this->data['multiclient_server']);
            $mclientVersion = urlencode($this->data['multiclient_version']);

            $result .= '<!-- SZM MC VERSION="1.2" -->'."\n";
            $result .= '<img src="'.$mclientProtocol.'://'.$mclientServer.
              '.ivwbox.de/cgi-bin/ivw/'.$contenttype.'/'.
              $mclientVersion.'/'.$project.'/'.$surferHash.
              '" width="1" height="1" alt="szmmctag" />'."\n";
            $result .= '<!-- /SZM MC-->'."\n";
          }
        }
        return $result;
      }
    }
    return '';
  }

  function _getCodeByHandle($param, $handlesAndCodes) {
    $result = '';
    // Search whether we've got the param and get its value
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
      $params = $_GET;
    } else {
      $params = $_POST;
    }
    if (is_array($params)) {
      foreach ($params as $key => $value) {
        if (is_array($value)) {
          foreach ($value as $subKey => $subVal) {
            if ($subKey == $param) {
              $result = $handlesAndCodes[$subVal];
            }
          }
        } else {
          if ($key == $param) {
            $result = $handlesAndCodes[$value];
          }
        }
      }
    }
    return $result;
  }
}
