<?php
/**
 * Action box for eTracker statistik embeds
 *
 * @package Papaya-Commercial
 * @subpackage Pixelcounter
 * @version $Id: actbox_etracker.php 8 2015-04-24 13:08:10Z weinert $
 */

/**
 * Basic class Aktion box
 */
require_once(PAPAYA_INCLUDE_PATH.'system/base_actionbox.php');

/**
 * Action box for eTracker statistik embeds
 *
 * @package Papaya-Commercial
 * @subpackage Pixelcounter
 */
class actionbox_etracker extends base_actionbox {

  var $cacheDependency = array(
    'querystring' => TRUE,
    'page' => TRUE,
    'surfer' => TRUE
  );

  private $_viewModes = NULL;

  var $editFields = array(
    'account' => array('Account code', 'isAlphaNum', TRUE, 'input', 40, '', ''),
    'tracklet_version' => array(
      'Tracklet Version',
      'isAlphaNumChar',
      TRUE,
      'combo',
      array('3.0' => '3.0', '4.0' => '4.0'), '', '3.0'
    ),
    'Track Actions',
    'event-extensions-click' => array(
      'Click', 'isAlpha', FALSE, 'function', 'callbackGetOutputModes', '', array()),
    'event-extensions-download' => array(
      'Download', 'isAlpha', FALSE, 'function', 'callbackGetOutputModes', '', array()),
    'event-extensions-link' => array(
      'Link', 'isAlpha', FALSE, 'function', 'callbackGetOutputModes', '', array())
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
      $document = new PapayaXmlDocument();
      include_once(dirname(__FILE__).'/base_pixelcounter.php');
      $pageDataObject = new base_pixelcounter();
      $pageData = $pageDataObject->loadCounterStatus($this->parentObj);
      $viewmode = $GLOBALS['PAPAYA_PAGE']->mode;
      $etracker = $document->appendElement(
        'etracker',
        array(
          'account' => $this->data['account'],
          'version' => $this->data['tracklet_version'],
          'language' => $this->parentObj->currentLanguage['code']
        )
      );
      if (empty($pageData['pixelcounter_code'])) {
        $etracker->appendElement(
          'page',
          array(
            "currentview" => $viewmode,
            "source" => 'page-properties'
          ),
          $this->parentObj->topic['TRANSLATION']['topic_title']
        );
      } else {
        $etracker->appendElement(
          'page',
          array(
            "currentview" => $viewmode,
            "source" => 'pixelcounter'
          ),
          $pageData['pixelcounter_code']
        );
      }
      if (!empty($pageData['pixelcounter_comment'])) {
        $pathNode = $etracker->appendElement('path');
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
            $pathNode->appendElement(
              'item',
              array('type' => 'category', 'level' => $i),
              $pathParts[$i]
            );
          }
          $pathNode->appendElement(
            'item',
            array('type' => 'page', 'level' => $i),
            $pageName
          );
        }
        if (!empty($elementName)) {
          $pathNode->appendElement(
            'item',
            array('type' => 'element', 'level' => ++$i),
            $elementName
          );
        }
      }
      $eventsNode = $etracker->appendElement('events');
      $eventsNode->appendElement(
        'event',
        array(
          'name' => 'click',
          'extensions' => $this->getTokenString($this->data['event-extensions-click'])
        )
      );
      $eventsNode->appendElement(
        'event',
        array(
          'name' => 'download',
          'extensions' => $this->getTokenString($this->data['event-extensions-download'])
        )
      );
      $eventsNode->appendElement(
        'event',
        array(
          'name' => 'link',
          'extensions' => $this->getTokenString($this->data['event-extensions-link'])
        )
      );
      return $etracker->saveXml();
    }
    return '';
  }

  public function getParsedAttributes() {
    $this->setDefaultData();
    if (isset($this->parentObj) && is_subclass_of($this->parentObj, 'base_topic')) {
      include_once(dirname(__FILE__).'/base_pixelcounter.php');
      $pageDataObject = new base_pixelcounter();
      $pageData = $pageDataObject->loadCounterStatus($this->parentObj);
      return array(
        'etracker_account' => $this->data['account'],
        'etracker_version' => $this->data['tracklet_version'],
        'page_code' => empty($pageData['pixelcounter_code']) ? '' : $pageData['pixelcounter_code']
      );
    } else {
      return array(
        'etracker_account' => $this->data['account'],
        'etracker_version' => $this->data['tracklet_version']
      );
    }
  }

  public function callbackGetOutputModes($name, $field, $data) {
    $result = '';
    foreach ($this->viewModes() as $mode) {
      $extension = $mode['viewmode_ext'];
      if (is_array($data) && in_array($extension, $data)) {
        $selected = ' checked="checked"';
      } else {
        $selected = '';
      }
      $result .= sprintf(
        '<input type="checkbox" name="%s[%s][]" value="%s" %s/>'.LF,
        PapayaUtilStringXml::escapeAttribute($this->paramName),
        PapayaUtilStringXml::escapeAttribute($name),
        PapayaUtilStringXml::escapeAttribute($extension),
        $selected
      );
      $result .= PapayaUtilStringXml::escapeAttribute($extension);
    }
    return $result;
  }

  public function viewModes(array $viewModes = NULL) {
    if (isset($viewModes)) {
      $this->_viewModes = $viewModes;
    } elseif (NULL === $this->_viewModes) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_viewlist.php');
      $viewList = new base_viewlist();
      $viewList->papaya($this->papaya());
      $viewList->loadViewModesList();
      $this->_viewModes = $viewList->viewModes;
    }
    return $this->_viewModes;
  }

  private function getTokenString($list) {
    $result = '';
    if (is_array($list)) {
      foreach ($list as $element) {
        if (FALSE === strpos($element, ' ')) {
          $result .= ' '.$element;
        }
      }
    }
    return substr($result, 1);
  }
}