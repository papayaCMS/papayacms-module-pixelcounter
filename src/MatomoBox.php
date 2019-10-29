<?php

namespace Papaya\Pixelcounter {

  use Papaya\Administration\Plugin\Editor\Dialog as PluginDialog;
  use Papaya\Application\Access as ApplicationAccess;
  use Papaya\Plugin\Editable as EditablePlugin;
  use Papaya\UI\Dialog;
  use Papaya\UI\Dialog\Field\Input;
  use Papaya\UI\Text\Translated as TranslatedText;
  use Papaya\UI\Text\Translated\Collection as TranslatedList;
  use Papaya\Content\View\Modes as ViewModes;
  use Papaya\Plugin\Appendable;
  use Papaya\XML\Element;
  use base_pixelcounter;

  class MatomoBox extends \Papaya\Application\BaseObject implements Appendable, EditablePlugin {

    use EditablePlugin\Content\Aggregation;

    const FIELD_SERVER_URL = 'matomo-server-url';
    const FIELD_WEBSITE_ID = 'matomo-domain-id';

    const FIELD_ALLOW_PREVIEW = 'matomo-allow-preview';

    const FIELD_USE_COOKIES = 'matomo-use-cookies';
    const FIELD_SECURE_COOKIES = 'matomo-secure-cookies';
    const FIELD_COOKIES_DOMAIN = 'matomo-cookies-domain';
    const FIELD_COOKIES_PREFIX = 'matomo-cookies-prefix';
    const FIELD_COOKIES_PATH = 'matomo-cookies-path';
    const FIELD_VISITOR_COOKIE_TIMEOUT = 'matomo-visitor-cookie-timeout';
    const FIELD_REFERRAL_COOKIE_TIMEOUT = 'matomo-referral-cookie-timeout';
    const FIELD_SESSION_COOKIE_TIMEOUT = 'matomo-session-cookie-timeout';

    const FIELD_EVENT_EXTENSIONS_CLICK = 'event-extensions-click';
    const FIELD_EVENT_EXTENSIONS_DOWNLOAD = 'event-extensions-download';
    const FIELD_EVENT_EXTENSIONS_LINK = 'event-extensions-link';

    const FIELD_DIMENSION_LANGUAGE = 'matomo-dimension-language';
    const FIELD_DIMENSION_OUTPUT_MODE = 'matomo-dimension-output-mode';
    const FIELD_DIMENSION_PAGE_ID = 'matomo-dimension-page-id';
    const FIELD_DIMENSION_CATEGORY = 'matomo-dimension-category';

    const _DEFAULTS = [
      self::FIELD_SERVER_URL => '',
      self::FIELD_WEBSITE_ID => '',
      self::FIELD_ALLOW_PREVIEW => false,
      self::FIELD_USE_COOKIES => 0,
      self::FIELD_SECURE_COOKIES => 0,
      self::FIELD_COOKIES_DOMAIN => '',
      self::FIELD_COOKIES_PATH => '/',
      self::FIELD_COOKIES_PREFIX => 'pk',
      self::FIELD_VISITOR_COOKIE_TIMEOUT => 13 * 30 * 86400,
      self::FIELD_REFERRAL_COOKIE_TIMEOUT => 6 * 30 * 86400,
      self::FIELD_SESSION_COOKIE_TIMEOUT => 30 * 60,
      self::FIELD_EVENT_EXTENSIONS_CLICK => [],
      self::FIELD_EVENT_EXTENSIONS_DOWNLOAD => [],
      self::FIELD_EVENT_EXTENSIONS_LINK => []
    ];

    const _DIMENSION_FIELDS = [
      self::FIELD_DIMENSION_CATEGORY => 'Category',
      self::FIELD_DIMENSION_LANGUAGE => 'Languages',
      self::FIELD_DIMENSION_OUTPUT_MODE => 'Output Mode',
      self::FIELD_DIMENSION_PAGE_ID => 'Page Id'
    ];
    const _DIMENSION_ATTRIBUTES = [
      self::FIELD_DIMENSION_CATEGORY => 'category',
      self::FIELD_DIMENSION_LANGUAGE => 'language',
      self::FIELD_DIMENSION_OUTPUT_MODE => 'output-mode',
      self::FIELD_DIMENSION_PAGE_ID => 'page-id'
    ];

    private $_viewModes;
    private $_parentPage;
    private $_pixelCounter;

    public function __construct($parentPage = NULL) {
      $this->_parentPage = $parentPage;
    }

    public function appendTo(Element $parent) {
      $content = $this->content();
      $tracker = $parent->appendElement(
        'tracker',
        [
          'href' => $content->get(self::FIELD_SERVER_URL, self::_DEFAULTS[self::FIELD_SERVER_URL]),
          'website-id' => $content->get(self::FIELD_WEBSITE_ID, self::_DEFAULTS[self::FIELD_WEBSITE_ID]),
          'allow-preview' => $content->get(self::FIELD_ALLOW_PREVIEW, self::_DEFAULTS[self::FIELD_ALLOW_PREVIEW])
            ? 'true' : 'false',
          'language' => $this->papaya()->request->language->code,
          'page-id' => $this->papaya()->request->pageId
        ]
      );
      $useCookies = $content->get(self::FIELD_USE_COOKIES, self::_DEFAULTS[self::FIELD_USE_COOKIES]);
      $secureCookies = $content->get(self::FIELD_SECURE_COOKIES, self::_DEFAULTS[self::FIELD_SECURE_COOKIES]);
      $cookies = $tracker->appendElement(
        'cookies',
        [
          'enabled' => $useCookies ? 'true' : 'false'
        ]
      );
      if ($useCookies) {
        $cookies->setAttribute('secure', $secureCookies ? 'true' : 'false');
        $cookies->setAttribute(
          'domain', $content->get(self::FIELD_COOKIES_DOMAIN, self::_DEFAULTS[self::FIELD_COOKIES_DOMAIN])
        );
        $cookies->setAttribute(
          'path', $content->get(self::FIELD_COOKIES_PATH, self::_DEFAULTS[self::FIELD_COOKIES_PATH])
        );
        $cookies->setAttribute(
          'prefix', $content->get(self::FIELD_COOKIES_PREFIX, self::_DEFAULTS[self::FIELD_COOKIES_PREFIX])
        );
        $timeouts = $cookies->appendElement(
          'timeouts',
          [
            'visitor' => $content->get(
              self::FIELD_VISITOR_COOKIE_TIMEOUT, self::_DEFAULTS[self::FIELD_VISITOR_COOKIE_TIMEOUT]
            ),
            'referral' => $content->get(
              self::FIELD_REFERRAL_COOKIE_TIMEOUT, self::_DEFAULTS[self::FIELD_REFERRAL_COOKIE_TIMEOUT]
            ),
            'session' => $content->get(
              self::FIELD_SESSION_COOKIE_TIMEOUT, self::_DEFAULTS[self::FIELD_SESSION_COOKIE_TIMEOUT]
            )
          ]
        );
      }
      $dimensions = $tracker->appendElement('dimensions');
      foreach (self::_DIMENSION_ATTRIBUTES as $fieldName => $attributeName) {
        $dimensionId = $content->get($fieldName, 0);
        if ($dimensionId > 0) {
          $dimensions->setAttribute($attributeName, $dimensionId);
        }
      }
      if ($this->_parentPage instanceof \base_topic) {
        $pixelCounter = $this->pixelCounter();
        $pageData = $pixelCounter->loadCounterStatus($this->_parentPage);
        $viewmode = $this->papaya()->request->mode['extension'];
        if (empty($pageData['pixelcounter_code'])) {
          $tracker->appendElement(
            'page',
            [
              "currentview" => $viewmode,
              "source" => 'page-properties'
            ],
            $this->_parentPage->topic['TRANSLATION']['topic_title']
          );
        } else {
          $tracker->appendElement(
            'page',
            [
              "currentview" => $viewmode,
              "source" => 'pixelcounter'
            ],
            $pageData['pixelcounter_code']
          );
        }
        if (!empty($pageData['pixelcounter_comment'])) {
          $pathNode = $tracker->appendElement('path');
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
                ['type' => 'category', 'level' => $i],
                $pathParts[$i]
              );
            }
            $pathNode->appendElement(
              'item',
              ['type' => 'page', 'level' => $i],
              $pageName
            );
          }
          if (!empty($elementName)) {
            $pathNode->appendElement(
              'item',
              ['type' => 'element', 'level' => ++$i],
              $elementName
            );
          }
        }
        $eventsNode = $tracker->appendElement('events');
        $eventsNode->appendElement(
          'event',
          [
            'name' => 'click',
            'extensions' => $this->getTokenString(
              $content->get(
                self::FIELD_EVENT_EXTENSIONS_CLICK, self::_DEFAULTS[self::FIELD_EVENT_EXTENSIONS_CLICK]
              )
            )
          ]
        );
        $eventsNode->appendElement(
          'event',
          [
            'name' => 'download',
            'extensions' => $this->getTokenString(
              $content->get(
                self::FIELD_EVENT_EXTENSIONS_DOWNLOAD, self::_DEFAULTS[self::FIELD_EVENT_EXTENSIONS_DOWNLOAD]
              )
            )
          ]
        );
        $eventsNode->appendElement(
          'event',
          [
            'name' => 'link',
            'extensions' => $this->getTokenString(
              $content->get(
                self::FIELD_EVENT_EXTENSIONS_LINK, self::_DEFAULTS[self::FIELD_EVENT_EXTENSIONS_LINK]
              )
            )
          ]
        );
      }
    }

    public function createEditor(EditablePlugin\Content $content) {
      $editor = new PluginDialog($content);
      $editor->papaya($this->papaya());
      $dialog = $editor->dialog();
      $dialog->fields[] = $field = new Input\URL(
        new TranslatedText('Server URL'), self::FIELD_SERVER_URL, self::_DEFAULTS[self::FIELD_SERVER_URL]
      );
      $dialog->fields[] = $field = new Input(
        new TranslatedText('Website Id'), self::FIELD_WEBSITE_ID, 10, self::_DEFAULTS[self::FIELD_WEBSITE_ID]
      );
      $dialog->fields[] = $field = new Dialog\Field\Select\Radio(
        new TranslatedText('Track Preview'),
        self::FIELD_ALLOW_PREVIEW,
        new TranslatedList([0 => 'no', 1 => 'yes']),
        FALSE
      );

      $dialog->fields[] = $group = new Dialog\Field\Group(new TranslatedText('Cookies'));
      $group->fields[] = $field = new Dialog\Field\Select\Radio(
        new TranslatedText('Use Cookies'),
        self::FIELD_USE_COOKIES,
        new TranslatedList(['no', 'yes']),
        FALSE
      );
      $field->setDefaultValue(self::_DEFAULTS[self::FIELD_USE_COOKIES]);
      $group->fields[] = $field = new Dialog\Field\Select\Radio(
        new TranslatedText('Secure Cookies Only'),
        self::FIELD_SECURE_COOKIES,
        new TranslatedList(['no', 'yes']),
        FALSE
      );
      $field->setDefaultValue(self::_DEFAULTS[self::FIELD_SECURE_COOKIES]);
      $group->fields[] = $field = new Input(
        new TranslatedText('Domain'),
        self::FIELD_COOKIES_DOMAIN,
        200,
        self::_DEFAULTS[self::FIELD_COOKIES_DOMAIN]
      );
      $group->fields[] = $field = new Input(
        new TranslatedText('Path'),
        self::FIELD_COOKIES_PATH,
        200,
        self::_DEFAULTS[self::FIELD_COOKIES_PATH]
      );
      $group->fields[] = $field = new Input(
        new TranslatedText('Prefix'),
        self::FIELD_COOKIES_PREFIX,
        10,
        self::_DEFAULTS[self::FIELD_COOKIES_PREFIX]
      );
      $group->fields[] = $field = new Input(
        new TranslatedText('Visitor Cookie Timeout'),
        self::FIELD_VISITOR_COOKIE_TIMEOUT,
        10,
        self::_DEFAULTS[self::FIELD_VISITOR_COOKIE_TIMEOUT]
      );
      $group->fields[] = $field = new Input(
        new TranslatedText('Referral Cookie Timeout'),
        self::FIELD_REFERRAL_COOKIE_TIMEOUT,
        10,
        self::_DEFAULTS[self::FIELD_REFERRAL_COOKIE_TIMEOUT]
      );
      $group->fields[] = $field = new Input(
        new TranslatedText('Session Cookie Timeout'),
        self::FIELD_SESSION_COOKIE_TIMEOUT,
        10,
        self::_DEFAULTS[self::FIELD_SESSION_COOKIE_TIMEOUT]
      );

      $dialog->fields[] = $group = new Dialog\Field\Group(new TranslatedText('Event Extensions'));
      $group->fields[] = $field = new Dialog\Field\Select\Checkboxes(
        new TranslatedText('Click Action'),
        self::FIELD_EVENT_EXTENSIONS_CLICK,
        new \Papaya\Iterator\ArrayMapper($this->viewModes(), 'extension'),
        FALSE
      );
      $group->fields[] = $field = new Dialog\Field\Select\Checkboxes(
        new TranslatedText('Download'),
        self::FIELD_EVENT_EXTENSIONS_DOWNLOAD,
        new \Papaya\Iterator\ArrayMapper($this->viewModes(), 'extension'),
        FALSE
      );
      $group->fields[] = $field = new Dialog\Field\Select\Checkboxes(
        new TranslatedText('Link'),
        self::FIELD_EVENT_EXTENSIONS_LINK,
        new \Papaya\Iterator\ArrayMapper($this->viewModes(), 'extension'),
        FALSE
      );

      $dialog->fields[] = $group = new Dialog\Field\Group(new TranslatedText('Dimensions'));
      foreach (self::_DIMENSION_FIELDS as $fieldName => $label) {
        $group->fields[] = $field = new Input(new TranslatedText($label), $fieldName, 10, 0);
      }

      return $editor;
    }

    public function viewModes(ViewModes $viewModes = NULL) {
      if (NULL !== $viewModes) {
        $this->_viewModes = $viewModes;
      } elseif (NULL === $this->_viewModes) {
        $this->_viewModes = new \Papaya\Content\View\Modes();
        $this->_viewModes->papaya($this->papaya());
        $this->_viewModes->activateLazyLoad();
      }
      return $this->_viewModes;
    }

    public function pixelCounter(base_pixelcounter $pixelCounter = NULL) {
      if (NULL !== $pixelCounter) {
        $this->_pixelCounter = $pixelCounter;
      } elseif (NULL === $this->_pixelCounter) {
        include_once(dirname(__FILE__).'/base_pixelcounter.php');
        $this->_pixelCounter = new base_pixelcounter();
        $this->_pixelCounter->papaya($this->papaya());
      }
      return $this->_pixelCounter;
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
}
