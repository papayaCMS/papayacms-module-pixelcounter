<?php
/**
* Action box for WebTrends statistik embeds
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
* @version $Id: actbox_webtrends.php 2 2013-12-09 14:16:49Z weinert $
*/

/**
* Basic class Aktion box
*/
require_once(PAPAYA_INCLUDE_PATH.'system/base_actionbox.php');

/**
* Action box for WebTrends statistik embeds
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
* @author Thomas Weinert <info@papaya-cms.com>
*/
class actionbox_webtrends extends base_actionbox {

  var $cacheDependency = array(
    'querystring' => TRUE,
    'page' => TRUE,
    'surfer' => TRUE
  );

  var $editFields = array(
    'webtrends_https' => array ('Use https', 'isNum', TRUE, 'yesno', NULL, '', 0),
    'webtrends_server' => array ('Server', 'isHTTPHost', FALSE, 'input', 100, ''),
    'webtrends_dcs_id' => array ('DCS ID', '~^[a-z\d]{22}_[a-z\d]{4}$~', FALSE, 'input', 27)
  );

  /**
  * Get parsed data
  *
  * @access public
  * @return string
  */
  function getParsedData() {
    $this->setDefaultData();
    if (isset($this->parentObj) && is_subclass_of($this->parentObj, 'base_topic')) {
      include_once(dirname(__FILE__).'/base_pixelcounter.php');
      $pageDataObject = new base_pixelcounter();
      $pageData = $pageDataObject->loadCounterStatus($this->parentObj);
      $result = sprintf(
        '<webtrends protocol="%s" domain="%s" dcs-id="%s">'.LF,
        $this->data['webtrends_https'] ? 'https' : 'http',
        papaya_strings::escapeHTMLChars($this->data['webtrends_server']),
        papaya_strings::escapeHTMLChars($this->data['webtrends_dcs_id'])
      );
      if (!empty($pageData['pixelcounter_code'])) {
        $result .= sprintf(
          '<code>%s</code>'.LF,
          papaya_strings::escapeHTMLChars($pageData['pixelcounter_code'])
        );
      }
      if (!empty($pageData['pixelcounter_comment'])) {
        $result .= '<path>';
        if (FALSE !== ($p = strpos($pageData['pixelcounter_comment'], ';'))) {
          $elementName = substr($pageData['pixelcounter_comment'], $p + 1);
          $path = substr($pageData['pixelcounter_comment'], 0, $p);
        } else {
          $elementName = NULL;
          $path = $pageData['pixelcounter_comment'];
        }
        $pathParts = explode('/', $path);
        $i = 0;
        if (count($pathParts) > 0) {
          $pageName = array_pop($pathParts);
          $partCount = count($pathParts);
          for ($i = 0; $i < $partCount; $i++) {
            $result .= sprintf(
              '<item type="category" level="%d">%s</item>'.LF,
              $i,
              papaya_strings::escapeHTMLChars($pathParts[$i])
            );
          }
          $result .= sprintf(
            '<item type="page" level="%d">%s</item>'.LF,
            $i++,
            papaya_strings::escapeHTMLChars($pageName)
          );
        }
        if (!empty($elementName)) {
          $result .= sprintf(
            '<item type="element" level="%d">%s</item>'.LF,
            $i,
            papaya_strings::escapeHTMLChars($elementName)
          );
        }
        $result .= '</path>';
        include_once(PAPAYA_INCLUDE_PATH.'system/base_surfer.php');
        $surfer = base_surfer::getInstance(FALSE);
        $result .= sprintf(
          '<surfer registered="%s" />'.LF,
          ($surfer->isValid) ? 'yes' : 'no'
        );
        if (isset($GLOBALS['PAPAYA_PAGE']) &&
            is_object($GLOBALS['PAPAYA_PAGE']) &&
            isset($GLOBALS['PAPAYA_PAGE']->error) &&
            is_array($GLOBALS['PAPAYA_PAGE']->error)) {
          $result .= sprintf(
            '<response status="%d" code="%d">%s</response>',
            (int)$GLOBALS['PAPAYA_PAGE']->error['status'],
            papaya_strings::escapeHTMLChars($GLOBALS['PAPAYA_PAGE']->error['code']),
            papaya_strings::escapeHTMLChars($GLOBALS['PAPAYA_PAGE']->error['message'])
          );
        } else {
          $result .= '<response status="200">OK</response>';
        }
      }
      $result .= '</webtrends>';
      return $result;
    }
    return '';
  }
}
