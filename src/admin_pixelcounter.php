<?php
/**
* Pixelcounter data administration
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
* @version $Id: admin_pixelcounter.php 6 2014-02-20 12:10:45Z SystemVCS $
*/


/**
* Basic database functions for the pixlcounter modules.
*/
require_once(dirname(__FILE__).'/base_pixelcounter.php');

/**
* Pixelcounter data administration
*
* @package Papaya-Commercial
* @subpackage Pixelcounter
*/
class admin_pixelcounter extends base_pixelcounter {

  /**
  * param name for forms
  * @var string
  * @access public
  */
  var $paramName = 'pxc';

  var $tablePages;
  var $tableSubPages;

  var $pageId = 0;
  var $page = NULL;

  var $_counterDataList = array();
  var $_counterDataRecord = NULL;

  var $_subPages = array();
  var $_parameters = array();

  var $deleteEmptyRecords = TRUE;

  /**
  * initialize basic data and params
  *
  * @access public
  */
  function initialize() {
    $this->initializeParams();
    $sessionParams = $this->getSessionValue('PAPAYA_SESS_tt');
    if (isset($this->params['page_id'])) {
      $this->pageId = (int)$this->params['page_id'];
      $sessionParams['page_id'] = $this->pageId;
    } elseif (isset($sessionParams['page_id'])) {
      $this->pageId = (int)$sessionParams['page_id'];
    } else {
      $this->pageId = 0;
    }
    $this->setSessionValue('PAPAYA_SESS_tt', $sessionParams);
  }

  /**
  * change and modify data
  *
  * @access public
  */
  function execute() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_language_select.php');
    $this->lngSelect = &base_language_select::getInstance();

    include_once(PAPAYA_INCLUDE_PATH.'system/base_topic_edit.php');
    $this->page = new base_topic_edit();

    if (isset($this->params['cmd'])) {
      switch ($this->params['cmd']) {
      case 'edit' :
        if (isset($this->pageId) &&
            $this->page->load($this->pageId, $this->lngSelect->currentLanguageId)) {
          $this->_initCounterDataDialog(FALSE);
          if ($this->_dialogCounterData->checkDialogInput()) {
            if ($this->_save($this->_dialogCounterData->data)) {
              $this->addMsg(MSG_INFO, $this->_gt('Changes saved.'));
            }
          }
        }
        break;
      case 'edit_subpage' :
        if (isset($this->pageId) &&
            $this->page->load($this->pageId, $this->lngSelect->currentLanguageId) &&
            $this->_load($this->pageId)) {
          $this->_initCounterDataDialog(TRUE);
          if ($this->_dialogCounterData->checkDialogInput()) {
            if (empty($this->params['subpage_ident'])) {
              if ($newId = $this->_saveSubPage(
                    $this->pageId,
                    NULL,
                    $this->_dialogCounterData->data
                  )) {
                $this->addMsg(MSG_INFO, $this->_gt('Subpage added.'));
                $this->params['subpage_ident'] = $newId;
                unset($this->_dialogCounterData);
              }
            } else {
              //avoid saving subpages with empty subpage_idents
              if (isset($this->params['new_subpage_ident']) &&
                  trim($this->params['new_subpage_ident'])) {
                if ($newId = $this->_saveSubPage(
                      $this->pageId,
                      $this->params['subpage_ident'],
                      $this->_dialogCounterData->data
                    )) {
                  $this->addMsg(MSG_INFO, $this->_gt('Changes saved.'));
                  $this->params['subpage_ident'] = $newId;
                  unset($this->_dialogCounterData);
                }
              } else {
                $this->addMsg(
                  MSG_ERROR,
                  $this->_gt(
                    'Please provide a subpage identifier. Empty identifiers are not supported.'
                  )
                );
              }
            }
          }
        }
        break;
      case 'delete_subpage' :
        if (isset($this->pageId) &&
            isset($this->params['subpage_ident']) &&
            isset($this->params['confirm_delete']) &&
            $this->params['confirm_delete']) {
          if ($this->_deleteSubPage($this->pageId, $this->params['subpage_ident'])) {
            $this->addMsg(MSG_INFO, $this->_gt('Subpage deleted.'));
            unset($this->params['cmd']);
            unset($this->params['subpage_ident']);
          }
        }
      case 'edit_parameter' :
        if (isset($this->pageId) &&
            $this->page->load($this->pageId, $this->lngSelect->currentLanguageId) &&
            $this->_load($this->pageId)) {
          $this->_initCounterDataParameterDialog();
          if ($this->_dialogCounterData->checkDialogInput() &&
              $data = $this->_decodeParameterData(
                $this->_dialogCounterData->data['parameter_data'], TRUE
              )
             ) {
            $values = $this->_dialogCounterData->data;
            $values['DATA'] = $data;
            if (empty($this->params['parameter_name'])) {
              if ($newId = $this->_saveParameter(
                    $this->pageId,
                    NULL,
                    $values
                  )) {
                $this->addMsg(MSG_INFO, $this->_gt('Parameter added.'));
                $this->params['parameter_name'] = $newId;
                unset($this->_dialogCounterData);
              }
            } else {
              if (isset($this->params['new_parameter_name']) &&
                  trim($this->params['new_parameter_name'])) {
                if ($newId = $this->_saveParameter(
                      $this->pageId,
                      $this->params['parameter_name'],
                      $values
                    )) {
                  $this->addMsg(MSG_INFO, $this->_gt('Changes saved.'));
                  $this->params['parameter_name'] = $newId;
                  unset($this->_dialogCounterData);
                }
              } else {
                $this->addMsg(
                  MSG_ERROR,
                  $this->_gt(
                    'Please provide a valid parameter name.'.
                    ' Empty strings cannot be used as parameter names.'
                  )
                );
              }
            }
          }
        }
        break;
      case 'delete_parameter' :
        if (isset($this->pageId) &&
            isset($this->params['parameter_name']) &&
            isset($this->params['confirm_delete']) &&
            $this->params['confirm_delete']) {
          if ($this->_deleteParameter($this->pageId, $this->params['parameter_name'])) {
            $this->addMsg(MSG_INFO, $this->_gt('Parameter deleted.'));
            unset($this->params['cmd']);
            unset($this->params['parameter_name']);
          }
        }
      }
    }

    if (isset($this->pageId) && $this->pageId > 0) {
      $this->page->load($this->pageId, $this->lngSelect->currentLanguageId);
      $this->_load($this->pageId);
    }
  }

  /**
  * get the output for the backend
  *
  * @access public
  */
  function getXML() {
    $this->layout->setParam('COLUMNWIDTH_CENTER', '50%');
    $this->layout->setParam('COLUMNWIDTH_RIGHT', '50%');
    $this->getXMLPageTree();
    if (isset($this->params['cmd']) && $this->params['cmd'] == 'delete_subpage') {
      $this->getSubPageDeleteDialog();
      $this->getXMLCounterStatus();
    } elseif (isset($this->params['cmd']) && $this->params['cmd'] == 'delete_parameter') {
      $this->getParameterDeleteDialog();
      $this->getXMLCounterStatus();
    } else {
      $this->getXMLCounterStatus();
      $this->getCounterDataDialog();
    }
    $this->getXMLButtons();
  }

  function getXMLButtons() {
    include_once(PAPAYA_INCLUDE_PATH.'system/base_btnbuilder.php');
    $menu = new base_btnbuilder();
    $menu->addButton(
      'Add subpage',
      $this->getLink(
        array(
          'cmd' => 'edit_subpage'
        )
      ),
      $this->images['actions-page-child-add'],
      ''
    );
    if (isset($this->params['subpage_ident']) &&
        isset($this->_subPages[$this->params['subpage_ident']])) {
      $menu->addButton(
        'Delete subpage',
        $this->getLink(
          array(
            'cmd' => 'delete_subpage',
            'subpage_ident' => $this->params['subpage_ident']
          )
        ),
        $this->images['actions-page-child-delete'],
        '',
        (isset($this->params['cmd']) && $this->params['cmd'] == 'delete_subpage')
      );
    }
    $menu->addSeperator();
    $menu->addButton(
      'Add parameter',
      $this->getLink(
        array(
          'cmd' => 'edit_parameter'
        )
      ),
      $this->images['actions-page-child-add'],
      ''
    );
    if (isset($this->params['parameter_name']) &&
        isset($this->_parameters[$this->params['parameter_name']])) {
      $menu->addButton(
        'Delete parameter',
        $this->getLink(
          array(
            'cmd' => 'delete_parameter',
            'parameter_name' => $this->params['parameter_name']
          )
        ),
        $this->images['actions-page-child-delete'],
        '',
        (isset($this->params['cmd']) && $this->params['cmd'] == 'parameter_name')
      );
    }
    if ($str = $menu->getXML()) {
      $this->layout->addMenu('<menu ident="edit">'.$str.'</menu>');
    }
  }

  function getXMLPageTree() {
    $result = '';
    include_once(PAPAYA_INCLUDE_PATH.'system/papaya_topic_tree.php');
    $this->topicTree = new papaya_topic_tree($this->paramName);
    $this->topicTree->images = $this->images;
    $this->topicTree->layout = $this->layout;
    $this->topicTree->authUser = $this->authUser;

    list($base, $nodes) = $this->topicTree->initPartTree($this->page);
    if (isset($this->topicTree->topics) &&
        is_array($this->topicTree->topics) &&
        count($this->topicTree->topics) > 0) {
      $this->_loadCounterDataList(array_keys($this->topicTree->topics));

      $result .= sprintf(
        '<listview title="%s">'.LF,
        papaya_strings::escapeHTMLChars($this->_gt('Pages'))
      );
      $result .= '<cols>';
      $result .= '<col/>';
      $result .= sprintf(
        '<col>%s</col>',
        papaya_strings::escapeHTMLChars($this->_gt('Page code'))
      );
      $result .= sprintf(
        '<col>%s</col>',
        papaya_strings::escapeHTMLChars($this->_gt('Page comment'))
      );
      $result .= '<col/>';
      $result .= '</cols>';
      $result .= '<items>';
      if ($base > 0) {
        $baseTopic = new base_topic_edit();
        $baseTopic->load($base, $this->lngSelect->currentLanguageId);
        $baseCounterStatus = $this->loadCounterStatus($baseTopic);
        $result .= sprintf(
          '<listitem title="%s" image="%s" href="%s">',
          papaya_strings::escapeHTMLChars($this->_gt('Parent page')),
          papaya_strings::escapeHTMLChars($this->images['actions-go-superior']),
          papaya_strings::escapeHTMLChars(
            $this->getLink(array('page_id' => $base))
          )
        );
        $result .= sprintf(
          '<subitem>%s</subitem>',
          empty($baseCounterStatus['pixelcounter_code'])
            ? ''
            : papaya_strings::escapeHTMLChars($baseCounterStatus['pixelcounter_code'])
        );
        $comment = empty($baseCounterStatus['pixelcounter_comment'])
          ? ''
          : $baseCounterStatus['pixelcounter_comment'];
        if (papaya_strings::strlen($comment) > 30) {
          $comment = '...'.papaya_strings::substr($comment, -30);
        }
        $result .= sprintf(
          '<subitem>%s</subitem>',
          papaya_strings::escapeHTMLChars($comment)
        );
        $result .= '<subitem/>';
        $result .= '</listitem>';
        $indent = 1;
      } else {
        $indent = 0;
      }
      $result .= $this->getStaticSubTree($nodes, $indent);
      $result .= '</items></listview>'."\r\n";
    }

    $this->layout->add($result);
  }

  /**
  * Get stratic sub tree
  *
  * @param array $nodes
  * @param integer $indent optional, default value 0
  * @access public
  * @return string
  */
  function getStaticSubTree($nodes, $indent=0) {
    $result = '';
    if (isset($nodes) && is_array($nodes)) {
      foreach ($nodes as $id) {
        $page = $this->topicTree->topics[$id];
        switch ($page['topic_status']) {
        case 4:
          $imageIdx = 'status-page-deleted';
          break; // deleted
        case 3:
          $imageIdx = 'status-page-modified';
          break; // published and modified
        case 2:
          $imageIdx = 'status-page-published';
          break; // published and up to date
        default:
          $imageIdx = 'status-page-created';
          break; // created - no public version
        }
        if ($id == $this->pageId && empty($this->params['subpage_ident'])) {
          $selected = ' selected="selected"';
        } else {
          $selected = '';
        }
        if (isset($page['topic_title']) && trim($page['topic_title']) != '') {
          $title = papaya_strings::escapeHTMLChars($page['topic_title']);
        } elseif (isset($page['mlang_topic_title']) &&
                  trim($page['mlang_topic_title']) != '') {
          $title = papaya_strings::escapeHTMLChars('['.$page['mlang_topic_title'].']');
        } else {
          $title = papaya_strings::escapeHTMLChars($this->_gt('No title'));
        }
        $result .= sprintf(
          '<listitem title="%s" href="%s" indent="%d" image="%s"%s>'.LF,
          papaya_strings::escapeHTMLChars($title),
          papaya_strings::escapeHTMLChars($this->getLink(array('page_id'=>$id))),
          (int)$indent,
          papaya_strings::escapeHTMLChars($this->images[$imageIdx]),
          $selected
        );
        if (isset($this->_counterDataList[$id]) && is_array($this->_counterDataList[$id])) {
          $result .= sprintf(
            '<subitem>%s</subitem>'.LF,
            papaya_strings::escapeHTMLChars(
              PapayaUtilString::truncate(
                $this->_counterDataList[$id]['pixelcounter_code'], 20, TRUE, '...'
              )
            )
          );
          $result .= sprintf(
            '<subitem>%s</subitem>',
            papaya_strings::escapeHTMLChars(
              $this->getCommentTruncated(
                $this->_counterDataList[$id]['pixelcounter_comment'],
                $this->_counterDataList[$id]['pixelcounter_mode']
              )
            )
          );
        } else {
          $result .= '<subitem/>';
          $result .= '<subitem/>';
        }
        $result .= sprintf(
          '<subitem align="right"><a href="%s"><glyph src="%s" hint="%s"/></a></subitem>'.LF,
          papaya_strings::escapeHTMLChars(
            $this->getLink(array('page_id' => $id), 'tt', 'topic.php')
          ),
          papaya_strings::escapeHTMLChars($this->images['actions-edit']),
          papaya_strings::escapeHTMLChars($this->_gt('Edit page'))
        );
        $result .= '</listitem>'.LF;
        if ($id == $this->pageId) {
          if (count($this->_subPages) > 0) {
            $result .= sprintf(
              '<listitem title="%s" indent="%d" span="4" image="%s"/>'.LF,
              papaya_strings::escapeHTMLChars($this->_gt('Subpages')),
              $indent + 1,
              papaya_strings::escapeHTMLChars($this->images['categories-sitemap'])
            );
            foreach ($this->_subPages as $subPageIdent => $subPage) {
              if (isset($this->params['subpage_ident']) &&
                  $subPageIdent == $this->params['subpage_ident']) {
                $selected = ' selected="selected"';
              } else {
                $selected = '';
              }
              $result .= sprintf(
                '<listitem title="%s" href="%s" indent="%d" image="%s"%s>'.LF,
                papaya_strings::escapeHTMLChars($subPageIdent),
                papaya_strings::escapeHTMLChars(
                  $this->getLink(
                    array(
                      'page_id' => $id,
                      'subpage_ident' => $subPageIdent
                    )
                  )
                ),
                $indent + 2,
                papaya_strings::escapeHTMLChars($this->images['items-page-child']),
                $selected
              );
              $result .= sprintf(
                '<subitem>%s</subitem>'.LF,
                papaya_strings::escapeHTMLChars($subPage['pixelcounter_code'])
              );
              $result .= sprintf(
                '<subitem>%s</subitem>',
                papaya_strings::escapeHTMLChars(
                  $this->getCommentTruncated(
                    $subPage['pixelcounter_comment'], $subPage['pixelcounter_mode']
                  )
                )
              );
              $result .= '<subitem/>';
              $result .= '</listitem>'.LF;
            }
          }
          if (count($this->_parameters) > 0) {
            $result .= sprintf(
              '<listitem title="%s" indent="%d" span="4" image="%s"/>'.LF,
              papaya_strings::escapeHTMLChars($this->_gt('Parameters')),
              $indent + 1,
              papaya_strings::escapeHTMLChars($this->images['categories-sitemap'])
            );
            foreach ($this->_parameters as $parameterName => $parameterData) {
              if (isset($this->params['parameter_name']) &&
                  $parameterName == $this->params['parameter_name']) {
                $selected = ' selected="selected"';
              } else {
                $selected = '';
              }
              if (defined('PAPAYA_URL_LEVEL_SEPARATOR') &&
                  in_array(PAPAYA_URL_LEVEL_SEPARATOR, $this->urlLevelSeparators)) {
                $parameterDisplayName = $this->_normalizeParameterName(
                  $parameterName, PAPAYA_URL_LEVEL_SEPARATOR
                );
              } else {
                $parameterDisplayName = $parameterName;
              }
              $result .= sprintf(
                '<listitem span="4" title="%s" href="%s" indent="%d" image="%s"%s>'.LF,
                papaya_strings::escapeHTMLChars($parameterDisplayName),
                papaya_strings::escapeHTMLChars(
                  $this->getLink(
                    array(
                      'page_id' => $id,
                      'parameter_name' => $parameterName,
                      'cmd' => 'edit_parameter'
                    )
                  )
                ),
                $indent + 2,
                papaya_strings::escapeHTMLChars($this->images['items-page-child']),
                $selected
              );
              $result .= '</listitem>'.LF;
              //list of paremter values
              if (isset($parameterData['DATA']) && is_array($parameterData['DATA'])) {
                foreach ($parameterData['DATA'] as $data) {
                  $result .= sprintf(
                    '<listitem title="%s" indent="%d" image="%s">'.LF,
                    papaya_strings::escapeHTMLChars($data['value']),
                    $indent + 3,
                    papaya_strings::escapeHTMLChars($this->images['items-page-child'])
                  );
                  $result .= sprintf(
                    '<subitem>%s</subitem>'.LF,
                    papaya_strings::escapeHTMLChars($data['pixelcounter_code'])
                  );
                  $result .= sprintf(
                    '<subitem>%s</subitem>',
                    papaya_strings::escapeHTMLChars(
                      $this->getCommentTruncated(
                        $data['pixelcounter_comment'], $data['pixelcounter_mode']
                      )
                    )
                  );
                  $result .= '<subitem/>';
                  $result .= '</listitem>'.LF;
                }
              }
            }
          }
        }
        if (isset($this->topicTree->topicLinks[$id]['children']) &&
            is_array($this->topicTree->topicLinks[$id]['children'])) {
          $result .= $this->getStaticSubTree(
            $this->topicTree->topicLinks[$id]['children'], $indent + 1
          );
        }
      }
    }
    return $result;
  }

  function _loadCounterDataList($pageIds) {
    $this->_counterDataList = array();
    $filter = str_replace('%', '%%', $this->databaseGetSQLCondition('page_id', $pageIds));
    $sql = "SELECT page_id,
                   pixelcounter_code,
                   pixelcounter_mode,
                   pixelcounter_comment,
                   pixelcounter_status
              FROM %s
             WHERE $filter";
    if ($res = $this->databaseQueryFmt($sql, $this->tablePages)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $data = array(
          'page_id' => (int)$row['page_id'],
          'pixelcounter_code' => '',
          'pixelcounter_mode' => 0,
          'pixelcounter_comment' => ''
        );
        if ($row['pixelcounter_status'] > 0) {
          $data['pixelcounter_code'] = $row['pixelcounter_code'];
          $data['pixelcounter_mode'] = $row['pixelcounter_mode'];
          $data['pixelcounter_comment'] = $row['pixelcounter_comment'];
        }
        $this->_counterDataList[$data['page_id']] = $data;
      }
    }
  }

  function _initCounterDataDialog($forceSubPageDialog) {
    if (!(isset($this->_dialogCounterData) && is_object($this->_dialogCounterData))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if ($forceSubPageDialog || isset($this->params['subpage_ident'])) {
        if (isset($this->params['subpage_ident']) &&
            isset($this->_subPages[$this->params['subpage_ident']])) {
          $hidden = array(
            'cmd' => 'edit_subpage',
            'page_id' => $this->pageId,
            'subpage_ident' => $this->params['subpage_ident']
          );
          $data = $this->_subPages[$this->params['subpage_ident']];
          $data['new_subpage_ident'] = $data['subpage_ident'];
        } else {
          $hidden = array(
            'cmd' => 'edit_subpage',
            'page_id' => $this->pageId
          );
          $data = array();
        }
        $fields = array(
          'new_subpage_ident' => array(
            'Subpage', 'isAlphaNum', FALSE, 'input', 40
          )
        );
      } else {
        $hidden = array(
          'cmd' => 'edit',
          'page_id' => $this->pageId,
        );
        if (isset($this->_counterDataRecord) &&
            is_array($this->_counterDataRecord)) {
          $data = $this->_counterDataRecord;
        } else {
          $data = array();
        }
        $fields = array();
      }
      $pluginOptions = $this->papaya()->plugins->options[$this->module->guid];
      switch ($pluginOptions->get('FIELD_CODE_VALIDATION', 0)) {
      case 1 :
        $fields['pixelcounter_code'] = array(
          'Code', 'isSomeText', FALSE, 'input', 255
        );
        break;
      default :
        $fields['pixelcounter_code'] = array(
          'Code', 'isAlphaNum', FALSE, 'input', 12
        );
      }
      $fields[] = 'Hierarchy';
      $modes = array(
        0 => $this->_gt('Append'),
        1 => $this->_gt('New')
      );
      $fields['pixelcounter_mode'] = array(
        'Mode', 'isNum', FALSE, 'combo', $modes, '', 0
      );
      $fields['pixelcounter_comment'] = array(
        'Path',
        '~^[^<>/=+&?;\\r\\n\\t\'"|]+([/;][^<>/=+&?;\\r\\n\\t\'"|]+)*$~',
        FALSE,
        'input',
        250,
        'category/subcategory;element'
      );

      $this->_dialogCounterData = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->_dialogCounterData->dialogTitle = $this->_gt('Properties');
      $this->_dialogCounterData->baseLink = $this->baseLink;
      $this->_dialogCounterData->loadParams();
    }
  }

  function _initCounterDataParameterDialog() {
    if (!(isset($this->_dialogCounterData) && is_object($this->_dialogCounterData))) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_dialog.php');
      if (isset($this->params['parameter_name']) &&
          isset($this->_parameters[$this->params['parameter_name']])) {
        $hidden = array(
          'cmd' => 'edit_parameter',
          'page_id' => $this->pageId,
          'parameter_name' => $this->params['parameter_name']
        );
        $data = $this->_parameters[$this->params['parameter_name']];
        if (defined('PAPAYA_URL_LEVEL_SEPARATOR') &&
                  in_array(PAPAYA_URL_LEVEL_SEPARATOR, $this->urlLevelSeparators)) {
          $data['new_parameter_name'] = $this->_normalizeParameterName(
            $data['parameter_name'], PAPAYA_URL_LEVEL_SEPARATOR
          );
        } else {
          $data['new_parameter_name'] = $data['parameter_name'];
        }
      } else {
        $hidden = array(
          'cmd' => 'edit_parameter',
          'page_id' => $this->pageId
        );
        $data = array();
      }
      $fields = array(
        'new_parameter_name' =>
          array('Name', '(^[a-zA-Z\d[\\],:/*!_]+$)D', FALSE, 'input', 50),
        'parameter_data' =>
          array('Data', 'isSomeText', FALSE, 'textarea', 12, 'value=code|path')
      );
      $this->_dialogCounterData = new base_dialog(
        $this, $this->paramName, $fields, $data, $hidden
      );
      $this->_dialogCounterData->dialogTitle = $this->_gt('Parameter properties');
      $this->_dialogCounterData->baseLink = $this->baseLink;
      $this->_dialogCounterData->loadParams();
    }
  }

  function getCounterDataDialog() {
    if ($this->pageId > 0) {
      if (isset($this->params['cmd']) &&
          $this->params['cmd'] == 'edit_subpage') {
        $this->_initCounterDataDialog(TRUE);
      } elseif (isset($this->params['cmd']) &&
          $this->params['cmd'] == 'edit_parameter') {
        $this->_initCounterDataParameterDialog();
      } else {
        $this->_initCounterDataDialog(FALSE);
      }
      $this->layout->addRight($this->_dialogCounterData->getDialogXML());
    }
  }

  function getXMLCounterStatus() {
    if (isset($this->page) && is_object($this->page)) {
      if (isset($this->params['subpage_ident'])) {
        $this->page->currentSubPageIdentifier = $this->params['subpage_ident'];
      }
      $currentCounterStatus = $this->loadCounterStatus($this->page);
      $result = sprintf(
        '<listview title="%s">',
        papaya_strings::escapeHTMLChars($this->_gt('Current data'))
      );
      $result .= '<items>';
      $imageIndex = empty($currentCounterStatus['pixelcounter_code'])
        ? 'status-sign-problem' : 'status-sign-ok';
      $result .= sprintf(
        '<listitem title="%s" image="%s"><subitem>%s</subitem></listitem>',
        papaya_strings::escapeHTMLChars($this->_gt('Page code')),
        papaya_strings::escapeHTMLChars($this->images[$imageIndex]),
        papaya_strings::escapeHTMLChars(
          empty($currentCounterStatus['pixelcounter_code'])
            ? '' : $currentCounterStatus['pixelcounter_code']
        )
      );
      $imageIndex = empty($currentCounterStatus['pixelcounter_comment'])
        ? 'status-sign-warning' : 'status-sign-ok';
      $result .= sprintf(
        '<listitem title="%s" image="%s"><subitem>%s</subitem></listitem>',
        papaya_strings::escapeHTMLChars($this->_gt('Page path')),
        papaya_strings::escapeHTMLChars($this->images[$imageIndex]),
        papaya_strings::escapeHTMLChars(
          empty($currentCounterStatus['pixelcounter_comment'])
            ? '' : $currentCounterStatus['pixelcounter_comment']
        )
      );
      $result .= '</items>';
      $result .= '</listview>';
      $this->layout->addRight($result);
    }
  }

  function _load($pageId) {
    $this->_counterDataRecord = array(
      'page_id' => (int)$pageId,
      'pixelcounter_code' => '',
      'pixelcounter_mode' => 0,
      'pixelcounter_comment' => ''
    );
    $sql = "SELECT page_id,
                   pixelcounter_code,
                   pixelcounter_mode,
                   pixelcounter_comment,
                   pixelcounter_status
              FROM %s
             WHERE page_id = '%d' AND pixelcounter_status > 0";
    if ($res = $this->databaseQueryFmt($sql, array($this->tablePages, $pageId))) {
      if ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        if ($row['pixelcounter_status'] > 0) {
          $this->_counterDataRecord['page_id'] = (int)$row['page_id'];
          $this->_counterDataRecord['pixelcounter_code'] = $row['pixelcounter_code'];
          $this->_counterDataRecord['pixelcounter_mode'] = $row['pixelcounter_mode'];
          $this->_counterDataRecord['pixelcounter_comment'] = $row['pixelcounter_comment'];
        }
      }
      if ($this->_loadSubPageList($pageId)) {
        return $this->_loadParameterList($pageId);
      }
    }
    return FALSE;
  }

  function _save($values) {
    if (empty($values['pixelcounter_code']) &&
        empty($values['pixelcounter_mode']) &&
        empty($values['pixelcounter_comment'])) {
      $hasData = FALSE;
    } else {
      $hasData = TRUE;
    }
    $sql = "SELECT COUNT(*)
              FROM %s
             WHERE page_id = '%d'";
    $params = array($this->tablePages, $this->pageId);
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $hasDBData = ($res->fetchField() > 0);
      if ($hasDBData && $hasData) {
        //db record needs update
        $data = array(
          'pixelcounter_code' => (string)$values['pixelcounter_code'],
          'pixelcounter_mode' => (int)$values['pixelcounter_mode'],
          'pixelcounter_comment' => (string)$values['pixelcounter_comment'],
          'pixelcounter_status' => 1
        );
        $filter = array(
          'page_id' => $this->pageId
        );
        return (FALSE !== $this->databaseUpdateRecord($this->tablePages, $data, $filter));
      } elseif ($hasData) {
        //new db record
        $data = array(
          'pixelcounter_code' => (string)$values['pixelcounter_code'],
          'pixelcounter_mode' => (int)$values['pixelcounter_mode'],
          'pixelcounter_comment' => (string)$values['pixelcounter_comment'],
          'pixelcounter_status' => 1,
          'page_id' => $this->pageId
        );
        return (FALSE !== $this->databaseInsertRecord($this->tablePages, NULL, $data));
      } elseif ($hasDBData && $this->deleteEmptyRecords) {
        //if an record is empty it gets deleted
        $filter = array(
          'page_id' => $this->pageId
        );
        return (FALSE !== $this->databaseDeleteRecord($this->tablePages, $filter));
      } elseif ($hasDBData) {
        //only set status to 0 (mark as not used)
        $data = array(
          'pixelcounter_status' => 0
        );
        $filter = array(
          'page_id' => $this->pageId
        );
        return (FALSE !== $this->databaseUpdateRecord($this->tablePages, $data, $filter));
      }
      return FALSE;
    }
    return FALSE;
  }

  function _saveSubPage($pageId, $subPageIdent, $values) {
    $newSubPageIdent = strtoupper($values['new_subpage_ident']);
    $filter = str_replace(
      '%',
      '%%',
      $this->databaseGetSQLCondition(
        'subpage_ident',
        array($subPageIdent, $newSubPageIdent)
      )
    );
    $sql = "SELECT subpage_ident
              FROM %s
             WHERE page_id = '%d'
               AND $filter";
    $params = array(
      $this->tableSubPages,
      $pageId
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $existing = array();
      while ($row = $res->fetchRow()) {
        $existing[] = $row[0];
      }
      if (count($existing) == 0) {
        //record does not exists and no conflict - add it
        $data = array(
          'page_id' => $pageId,
          'subpage_ident' => $newSubPageIdent,
          'pixelcounter_code' => $values['pixelcounter_code'],
          'pixelcounter_mode' => $values['pixelcounter_mode'],
          'pixelcounter_comment' => $values['pixelcounter_comment']
        );
        if (FALSE !== $this->databaseInsertRecord($this->tableSubPages, NULL, $data)) {
          return $newSubPageIdent;
        }
      } elseif (isset($subPageIdent) && in_array($subPageIdent, $existing)) {
        //record exists
        if (count($existing) == 1) {
          //no conflict (same ident or new ident not existing)
          $data = array(
            'subpage_ident' => $newSubPageIdent,
            'pixelcounter_code' => $values['pixelcounter_code'],
            'pixelcounter_mode' => $values['pixelcounter_mode'],
            'pixelcounter_comment' => $values['pixelcounter_comment']
          );
          $filter = array(
            'page_id' => $pageId,
            'subpage_ident' => $subPageIdent
          );
          if (FALSE !== $this->databaseUpdateRecord(
               $this->tableSubPages, $data, $filter)) {
            return $newSubPageIdent;
          }
        } else {
          $this->addMsg(MSG_ERROR, $this->_gt('Subpage identifier must be unique.'));
        }
      } else {
        $this->addMsg(MSG_ERROR, $this->_gt('Subpage identifier must be unique.'));
      }
    }
    return FALSE;
  }

  function _deleteSubPage($pageId, $subPageIdent) {
    $filter = array(
      'page_id' => $pageId,
      'subpage_ident' => $subPageIdent
    );
    return FALSE !== $this->databaseDeleteRecord($this->tableSubPages, $filter);
  }


  function _loadSubPageList($pageId) {
    $this->_subPages = array();
    $sql = "SELECT subpage_ident, pixelcounter_code, pixelcounter_mode, pixelcounter_comment
              FROM %s
             WHERE page_id = '%d'
             ORDER BY subpage_ident";
    $params = array(
      $this->tableSubPages,
      $pageId
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $this->_subPages[$row['subpage_ident']] = $row;
      }
      return TRUE;
    }
    return FALSE;
  }

  function getSubPageDeleteDialog() {
    if (isset($this->params['subpage_ident']) &&
        isset($this->_subPages[$this->params['subpage_ident']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'delete_subpage',
        'page_id' => $this->pageId,
        'subpage_ident' => $this->params['subpage_ident'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt('Delete subpage "%s"?'),
        papaya_strings::escapeHTMLChars($this->params['subpage_ident'])
      );
      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->buttonTitle = 'Delete';
      $this->layout->addRight($dialog->getMsgDialog());
    }
  }

  function _encodeParameterData($values) {
    if (is_array($values) && count($values) > 0) {
      $result = '';
      foreach ($values as $row) {
        if (trim($row['value']) != '' && FALSE === strpos($row['value'], '=')) {
          $result .= "\n".$row['value'];
          $result .= ($row['pixelcounter_mode'] == 0) ? '+=' : '=';
          $result .= $row['pixelcounter_code'].'|'.$row['pixelcounter_comment'];
        }
      }
      return substr($result, 1);
    }
    return '';
  }

  function _saveParameter($pageId, $parameterName, $values) {
    $parameterName = $this->_normalizeParameterName($parameterName);
    $newParameterName = $this->_normalizeParameterName($values['new_parameter_name']);
    $filter = str_replace(
      '%',
      '%%',
      $this->databaseGetSQLCondition(
        'parameter_name',
        array($parameterName, $newParameterName)
      )
    );
    $sql = "SELECT parameter_name
              FROM %s
             WHERE page_id = '%d'
               AND $filter";
    $params = array(
      $this->tableParameters,
      $pageId
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      $existing = array();
      while ($row = $res->fetchRow()) {
        $existing[] = $row[0];
      }
      if (count($existing) == 0) {
        //record does not exists and no conflict - add it
        $data = array(
          'page_id' => $pageId,
          'parameter_name' => $newParameterName,
          'parameter_data' => $this->_encodeParameterData($values['DATA'])
        );
        if (FALSE !== $this->databaseInsertRecord($this->tableParameters, NULL, $data)) {
          return $newParameterName;
        }
      } elseif (isset($parameterName) && in_array($parameterName, $existing)) {
        //record exists
        if (count($existing) == 1) {
          //no conflict (same ident or new ident not existing)
          $data = array(
            'parameter_name' => $newParameterName,
            'parameter_data' => $this->_encodeParameterData($values['DATA'])
          );
          $filter = array(
            'page_id' => $pageId,
            'parameter_name' => $parameterName
          );
          if (FALSE !== $this->databaseUpdateRecord(
               $this->tableParameters, $data, $filter)) {
            return $newParameterName;
          }
        } else {
          $this->addMsg(MSG_ERROR, $this->_gt('Parameter value must be unique.'));
        }
      } else {
        $this->addMsg(MSG_ERROR, $this->_gt('Parameter value must be unique.'));
      }
    }
    return FALSE;
  }

  function _deleteParameter($pageId, $parameterName) {
    $filter = array(
      'page_id' => $pageId,
      'parameter_name' => $parameterName
    );
    return FALSE !== $this->databaseDeleteRecord($this->tableParameters, $filter);
  }

  function _loadParameterList($pageId) {
    $this->_parameters = array();
    $sql = "SELECT parameter_name, parameter_data
              FROM %s
             WHERE page_id = '%d'
             ORDER BY parameter_name";
    $params = array(
      $this->tableParameters,
      $pageId
    );
    if ($res = $this->databaseQueryFmt($sql, $params)) {
      while ($row = $res->fetchRow(DB_FETCHMODE_ASSOC)) {
        $row['DATA'] = $this->_decodeParameterData($row['parameter_data']);
        $this->_parameters[$row['parameter_name']] = $row;
      }
      return TRUE;
    }
    return FALSE;
  }

  function getParameterDeleteDialog() {
    if (isset($this->params['parameter_name']) &&
        isset($this->_parameters[$this->params['parameter_name']])) {
      include_once(PAPAYA_INCLUDE_PATH.'system/base_msgdialog.php');
      $hidden = array(
        'cmd' => 'delete_parameter',
        'page_id' => $this->pageId,
        'parameter_name' => $this->params['parameter_name'],
        'confirm_delete' => 1,
      );
      $msg = sprintf(
        $this->_gt('Delete parameter "%s"?'),
        papaya_strings::escapeHTMLChars($this->params['parameter_name'])
      );
      $dialog = new base_msgdialog($this, $this->paramName, $hidden, $msg, 'question');
      $dialog->buttonTitle = 'Delete';
      $this->layout->addRight($dialog->getMsgDialog());
    }
  }

  function getCommentTruncated($comment, $mode) {
    PapayaUtilString::truncate($comment, 30, TRUE, '...');
    if ($mode == 1) {
      $comment = '/'.$comment;
    }
    return $comment;
  }
}

