<?php
/**
* Action box for Omniture statistik embeds
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
* @version $Id: actbox_omniture.php 2 2013-12-09 14:16:49Z weinert $
*/

/**
* Action box for Omniture statistik embeds
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
* @author Thomas Weinert <info@papaya-cms.com>
*/
class actionbox_omniture extends base_actionbox {

  var $cacheDependency = array(
    'querystring' => TRUE,
    'page' => TRUE,
    'surfer' => FALSE
  );

  var $editFields = array(
    'account' => array ('Account', 'isAlphaNum', TRUE, 'input', 40, '', ''),
    'project_prefix' => array ('Project prefix', 'isAlphaNum', TRUE, 'input', 60, '', ''),
    'internal_ips' => array(
      'Internal IPs',
      '(^(\\d{1,3})(([ .]|(\\r?\\n))[\\d*]{1,3})*$)',
      FALSE,
      'textarea',
      8,
      '',
      ''
    )
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
        '<omniture account="%s" prefix="%s" internal="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->data['account']),
        papaya_strings::escapeHTMLChars($this->data['project_prefix']),
        $this->isInternalIp() ? 'yes' : 'no'
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
      $result .= '</omniture>';
      return $result;
    }
    return '';
  }

  /**
  * Check if current IP matches mask of internal ips
  * @return boolean
  */
  function isInternalIp() {
    if (!empty($this->data['internal_ips'])) {
      $ips = array_flip(preg_split('(\s+)', $this->data['internal_ips']));
      $currentIp = empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR'];
      $ipParts = explode('.', $currentIp);
      $compareIps = array(
        $currentIp,
        $ipParts[0].'.'.$ipParts[1].'.'.$ipParts[2].'.*',
        $ipParts[0].'.'.$ipParts[1].'.*.*',
        $ipParts[0].'.*.*.*'
      );
      foreach ($compareIps as $compare) {
        if (isset($ips[$compare])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
  * Make the cache id depend on the ip of the visitor
  * @return string
  */
  function getCacheId($additionalCacheString = '') {
    return parent::getCacheId(
      empty($_SERVER['REMOTE_ADDR']) ? '' : $_SERVER['REMOTE_ADDR']
    );
  }
}
