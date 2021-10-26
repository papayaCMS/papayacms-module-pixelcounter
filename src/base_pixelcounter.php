<?php
/**
* Basic Pixelcounter database functions
*
* @copyright 2002-2007 by papaya Software GmbH - All rights reserved.
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
* @version $Id: base_pixelcounter.php 2 2013-12-09 14:16:49Z weinert $
*/

/**
* Basic Pixelcounter database functions
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
*/
class base_pixelcounter extends base_db {

  var $tablePages;
  var $tableSubPages;
  var $tableParameters;

  function __construct() {
    $this->tablePages = PAPAYA_DB_TABLEPREFIX.'_pixelcounter';
    $this->tableSubPages = PAPAYA_DB_TABLEPREFIX.'_pixelcounter_subpages';
    $this->tableParameters = PAPAYA_DB_TABLEPREFIX.'_pixelcounter_params';
  }

  function loadCounterStatus($page) {
    $propertyCount = 0;
    $pageIds = array();
    $result = array();
    if (!empty($page->currentSubPageIdentifier)) {
      $sql = "SELECT pixelcounter_code, pixelcounter_mode, pixelcounter_comment
                FROM %s
               WHERE page_id = '%d'
                 AND subpage_ident = '%s'";
      $params = array(
        $this->tableSubPages,
        (int)$page->topicId,
        $page->currentSubPageIdentifier
      );
      if ($res = $this->databaseQueryFmt($sql, $params)) {
        if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
          $result['pixelcounter_code'] = $row['pixelcounter_code'];
          $result['pixelcounter_mode'] = empty($row['pixelcounter_mode']) ? 0 : 1;
          $result['pixelcounter_comment'] = $row['pixelcounter_comment'];
        }
      }
    } elseif ((isset($_POST) && is_array($_POST) && count($_POST) > 0) ||
              (isset($_GET) && is_array($_GET) && count($_GET) > 0)) {
      $sql = "SELECT parameter_name, parameter_data
                FROM %s
               WHERE page_id = '%d'
               ORDER BY parameter_name";
      $params = array(
        $this->tableParameters,
        (int)$page->topicId
      );
      if ($res = $this->databaseQueryFmt($sql, $params)) {
        $data = array();
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
          $parts = $this->_decodeParameterName($row['parameter_name']);
          $currentData = &$data;
          foreach ($parts as $part) {
            if (!isset($currentData[$part])) {
              $currentData[$part] = array();
            }
            $currentData = &$currentData[$part];
          }
          $currentData = $this->_decodeParameterData($row['parameter_data']);
          if (defined('PAPAYA_URL_LEVEL_SEPARATOR') &&
              in_array(PAPAYA_URL_LEVEL_SEPARATOR, $this->urlLevelSeparators)) {
            $parameterName = $this->_encodeParameterName(
              $parts,
              PAPAYA_URL_LEVEL_SEPARATOR
            );
            $data[$parameterName] = &$currentData;
          }
        }
        if (count($data) > 0) {
          if (isset($_GET) && is_array($_GET) && count($_GET) > 0) {
            $result = $this->_checkParameterData($data, $_GET);
          }
          if (empty($result) &&
              isset($_POST) && is_array($_POST) && count($_POST) > 0) {
            $result = $this->_checkParameterData($data, $_POST);
          }
        }
      }
    }
    $records = array();
    if (empty($result['pixelcounter_code']) ||
        $result['pixelcounter_mode'] == 0) {
      if (preg_match_all('/\d+/', $page->topic['prev_path'], $regs, PREG_PATTERN_ORDER)) {
        if (is_array($regs[0]) && count($regs[0]) > 0) {
          $pageIds = $regs[0];
        }
      }
      $pageIds[] = (int)$page->topic['prev'];
      $pageIds[] = (int)$page->topicId;
      $pageIds = array_reverse($pageIds);

      $filter = str_replace('%', '%%', $this->databaseGetSQLCondition('page_id', $pageIds));
      $sql = "SELECT page_id,
                     pixelcounter_code,
                     pixelcounter_mode,
                     pixelcounter_comment
                FROM %s
               WHERE pixelcounter_status > 0
                 AND $filter";
      if ($res = $this->databaseQueryFmt($sql, $this->tablePages)) {
        while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
          $records[$row['page_id']] = $row;
        }
        if (count($records) > 0) {
          if (empty($result['pixelcounter_code'])) {
            foreach ($pageIds as $pageId) {
              if (isset($records[$pageId])) {
                $page = $records[$pageId];
                if (empty($result['pixelcounter_code']) &&
                    !empty($page['pixelcounter_code'])) {
                  $result['pixelcounter_code'] = $page['pixelcounter_code'];
                  break;
                }
              }
            }
          }
        }
      }
    } else {
      $records = array($result);
    }
    if (!empty($result['pixelcounter_comment'])) {
      $path = $result['pixelcounter_comment'];
    } else {
      $path = '';
    }
    foreach ($pageIds as $pageId) {
      if (isset($records[$pageId])) {
        $pageRecord = $records[$pageId];
        if (FALSE != strpos($pageRecord['pixelcounter_comment'], ';')) {
          $comment = substr(
            $pageRecord['pixelcounter_comment'], 0, strpos($pageRecord['pixelcounter_comment'], ';')
          );
        } else {
          $comment = $pageRecord['pixelcounter_comment'];
        }
        if (!empty($comment)) {
          $path = $comment.'/'.$path;
        }
        if (isset($pageRecord['pixelcounter_mode']) && $pageRecord['pixelcounter_mode'] == 1) {
          break;
        }
      }
    }
    $path = preg_replace('(//+)', '/', $path);
    if (substr($path, -1) == '/') {
      $path = substr($path, 0, -1);
    }
    $result['pixelcounter_comment'] = $path;
    return $result;
  }

  function _decodeParameterName($parameterName) {
    if (defined('PAPAYA_URL_LEVEL_SEPARATOR') &&
        in_array(PAPAYA_URL_LEVEL_SEPARATOR, $this->urlLevelSeparators)) {
      $splitChar = preg_quote(PAPAYA_URL_LEVEL_SEPARATOR);
      $result = preg_split('(\]\[|\[|\]|'.$splitChar.')', $parameterName, -1, PREG_SPLIT_NO_EMPTY);
    } else {
      $result = preg_split('(\]\[|\[|\])', $parameterName, -1, PREG_SPLIT_NO_EMPTY);
    }
    if (is_array($result) && count($result) > 0) {
      return $result;
    } else {
      return array($parameterName);
    }
  }

  function _encodeParameterName($parts, $separator = '') {
    if (is_array($parts)) {
      if (count($parts) > 0) {
        if ($separator != '' && in_array($separator, $this->urlLevelSeparators)) {
          $result = implode($separator, $parts);
        } else {
          $result = array_shift($parts);
          foreach ($parts as $part) {
            $result .= '['.$part.']';
          }
        }
        return $result;
      }
    }
    return FALSE;
  }

  function _normalizeParameterName($parameterName, $separator = '') {
    return strtolower(
      (string)$this->_encodeParameterName($this->_decodeParameterName($parameterName), $separator)
    );
  }

  /**
  * Decodes the parameter data by splitting the string into lines and
  * putting each value, code, and comment element of a line into an array.
  *
  * @param $data string with parameter data.
  * @param $reportErrors TRUE to report wrong input in one line,
  *        otherwise FALSE (default)
  * @return array() with matched value, code, and comment
  */
  function _decodeParameterData($data, $reportErrors = FALSE) {
    $result = array();
    $linePattern = '(^
      (?P<value>[^\s=+]+)(?P<mode>\+?=)
      (?P<code>[a-zA-Z\d_]+)?
      (?:[|\s](?P<comment>[^<>/=+&?;\\r\\n\\t\'"|]+(?:[/;][^<>/=+&?;\\r\\n\\t\'"|]+)*))?
    $)Dix';
    $lines = preg_split('(\r\n|\n\r|[\r\n])', $data);
    if (is_array($lines) && count($lines) > 0) {
      foreach ($lines as $lineIdx => $line) {
        if (trim($line) != '') {
          if (preg_match($linePattern, trim($line), $match)) {
            $row = array(
              'value' => $match['value'],
              'pixelcounter_code' => $match['code'],
              'pixelcounter_mode' => ($match['mode'] == '+=') ? 0 : 1,
              'pixelcounter_comment' => empty($match['comment']) ? '' : $match['comment']
            );
            $result[$row['value']] = $row;
          } elseif ($reportErrors) {
            $this->addMsg(
              MSG_ERROR,
              sprintf($this->_gt('Wrong input in line %d.'), $lineIdx + 1)
            );
            return FALSE;
          }
        }
      }
    }
    return $result;
  }

  function _checkParameterData($data, $parameters, $recursions = 10) {
    static $stripSlashes;
    if (!isset($stripSlashes)) {
      if (get_magic_quotes_gpc() || get_magic_quotes_runtime()) {
        $stripSlashes = TRUE;
      } else {
        $stripSlashes = FALSE;
      }
    }
    $parametersObject = new PapayaRequestParameters();
    $parameters = $parametersObject->prepareParameter($parameters, $stripSlashes);
    return $this->_checkPreparedParameterData($data, $parameters, $recursions);
  }

  function _checkPreparedParameterData($data, $parameters, $recursions) {
    $result = array();
    if ($recursions > 0) {
      foreach ($parameters as $parameterName => $parameter) {
        if (isset($data[$parameterName])) {
          if (is_array($parameter)) {
            $result = $this->_checkPreparedParameterData(
              $data[$parameterName],
              $parameter,
              $recursions - 1
            );
          } else {
            $parameterValue = strtolower($parameter);
            if (isset($data[$parameterName][$parameterValue])) {
              $counterData = $data[$parameterName][$parameterValue];
              if (!empty($counterData['pixelcounter_code'])) {
                $result['pixelcounter_code'] = $counterData['pixelcounter_code'];
              }
              $result['pixelcounter_mode'] =
                empty($counterData['pixelcounter_mode']) ? 0 : $counterData['pixelcounter_mode'];
              if (!empty($counterData['pixelcounter_comment'])) {
                $result['pixelcounter_comment'] = $counterData['pixelcounter_comment'];
              }
            }
          }
        }
      }
    }
    return $result;
  }
}
