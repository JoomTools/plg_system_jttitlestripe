<?php
/**
 * @package      Joomla.Plugin
 * @subpackage   System.Jttitlestripe
 *
 * @author       Guido De Gobbis <support@joomtools.de>
 * @copyright    (c) 2017 JoomTools.de - All rights reserved.
 * @license      GNU General Public License version 3 or later
 **/

// no direct access
defined('_JEXEC') or die('Restricted access');


class plgSystemJttitlestripe extends JPlugin
{
	protected $stripe = array();
	protected $breakStripe = array();
	protected $tags = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
	protected $setCss = null;
	protected $css = null;

	public function __construct($subject, $params)
	{
		JLoader::register('JDocumentHTML', JPATH_PLUGINS . '/system/jttitlestripe/assets/HtmlDocument.php');

		parent::__construct($subject, $params);
	}

	public function onAfterInitialise()
	{
		if (JFactory::getApplication()->isAdmin()) return;

		$stripe      = explode(',', $this->params->get('stripe'));
		$breakStripe = explode(',', $this->params->get('breakstripe'));
		$tags        = explode(',', $this->params->get('tags'));

		foreach ($stripe as $value)
		{
			$this->stripe[] = trim($value);
		}

		foreach ($breakStripe as $value)
		{
			$this->breakStripe[] = trim($value);
		}

		foreach ($tags as $value)
		{
			$this->tags[] = strtolower(trim($value));
		}

		$this->setCss = $this->params->get('css');

		if ($this->setCss)
		{
			$this->css = 'plugins/' . $this->_type . '/' . $this->_name . '/assets/stripe.css';
		}
	}

	public function onRenderModule(&$module, $attribs)
	{
		if (JFactory::getApplication()->isAdmin()) return;

		if ($this->params->get('inModule', 0) == 0 && $module->module != 'mod_breadcrumbs') return;

		$moduleTitle   = str_replace('&nbsp;', ' ', $module->title);
		$moduleContent = str_replace('&nbsp;', ' ', $module->content);
		$clear         = ($module->module == 'mod_breadcrumbs') ? true : false;

		$findInTitle   = $this->_checkStripe($moduleTitle);
		$findInContent = $this->_checkStripe($moduleContent);

		$module->title   = ($findInTitle) ? $this->setMultilineTitle($moduleTitle) : $moduleTitle;
		$module->content = ($findInContent) ? $this->_renderXML($moduleContent, $clear) : $moduleContent;

		if (($findInTitle || $findInContent) && !$clear)
		{
			if ($this->setCss && !$this->css)
			{
				$this->css = 'plugins/' . $this->_type . '/' . $this->_name . '/assets/stripe.css';
			}
		}
	}

	protected function _checkStripe($string)
	{
		$stripe      = $this->stripe;
		$breakstripe = $this->breakStripe;

		foreach ($stripe as $_stripe)
		{
			if (strpos($string, $_stripe)) return true;
		}

		foreach ($breakstripe as $_breakstripe)
		{
			if (strpos($string, $_breakstripe)) return true;
		}

		return false;
	}

	protected function setMultilineTitle($title)
	{
		$stripe      = $this->stripe;
		$breakStripe = $this->breakStripe;
		$sub         = ' sub';

		foreach ($breakStripe as $value)
		{
			$title = str_replace($value, '|||&n|', $title);
		}

		foreach ($stripe as $value)
		{
			$title = str_replace($value . ' ', '||&nbsp;', $title);
			$title = str_replace(' ' . $value, '&nbsp;||', $title);
		}

		if (strpos($title, '||'))
		{
			list($maintitle, $_subtitle) = explode('||', $title, 2);
			$subtitle = ($_subtitle != '' && $_subtitle != '|&n|') ? explode('||', $_subtitle) : false;
		}
		else
		{
			$maintitle = $title;
			$subtitle  = false;
			$sub       = '';
		}

		$counter    = 1;
		$break      = '';
		$returnSubt = '';

		if ($subtitle) :
			foreach ($subtitle as $_title) :
				if (strpos($_title, '|&n|') !== false) :
					$break  = ' break';
					$_title = substr($_title, 4);
				else :
					$returnSubt = '';
				endif;
				$returnSubt .= '<span class="subtitle sub-' . $counter++ . $break . '">' . $_title . '</span>';
			endforeach;
		endif;
		$returnMailt = '<span class="maintitle' . $sub . '">' . $maintitle . '</span>';
		$return      = '<span class="jttitlestripe">' . $returnMailt . $returnSubt . '</span>';

		return $return;
	}

	protected function _renderXML(&$article, $clear = false)
	{
		$xmlString = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><container>' . $article . '</container>';

		$xml = new DOMDocument();
		libxml_use_internal_errors(true);
		$xml->loadHTML($xmlString);
		libxml_clear_errors();

		$xmlString = $xml->saveXML($xml->getElementsByTagName('container')->item(0));

		$findeStripes = simplexml_load_string($xmlString, 'SimpleXMLElement');

		$this->_findeStripes($findeStripes, false, $clear);

		$_article = new DOMDocument();
		libxml_use_internal_errors(true);
		$_article->loadXML($findeStripes->saveXML());
		libxml_clear_errors();

		$newArticle = $_article->saveHTML();

		preg_match('/<container>(.*?)<\/container>/uxis', $newArticle, $article);

		$article = html_entity_decode($article[1], ENT_QUOTES, 'utf-8');

		return $article;
	}

	protected function _findeStripes($xml, $inTag = false, $clear = false)
	{
		if (!$xml instanceof SimpleXMLElement)
		{
			return;
		}
		else
		{
			foreach ($xml as $node)
			{
				$nodeName = strtolower($node->getName());

				$children   = count($node->children());
				$attributes = $node->attributes();

				foreach ($attributes as $attrKey => $attrValue)
				{
					if ($attrKey == 'value' || $nodeName == 'textarea') continue;

					if ($this->_checkStripe((string) $attrValue))
					{
						$attributes[$attrKey] = $this->clearMultilineTitle((string) $attrValue);
					}
				}

				if (in_array($nodeName, $this->tags) || $inTag && !$clear)
				{
					if ($this->_checkStripe((string) $node[0]))
					{
						$node[0] = $this->setMultilineTitle(trim((string) $node[0]));
					}

					if ($children >= 1)
					{
						$this->_findeStripes($node->children(), true, $clear);
					}
				}
				else
				{
					if ($this->_checkStripe((string) $node[0]))
					{
						$node[0] = $this->clearMultilineTitle(trim((string) $node[0]));
					}
				}

				if ($children >= 1)
				{
					$this->_findeStripes($node->children(), $inTag, $clear);
				}
			}
		}
	}

	protected function clearMultilineTitle($title)
	{
		$stripe      = $this->stripe;
		$breakstripe = $this->breakStripe;

		foreach ($breakstripe as $value)
		{
			$title = str_replace($value, ' ', $title);
		}

		foreach ($stripe as $value)
		{
			$title = str_replace($value . ' ', '', $title);
			$title = str_replace(' ' . $value, ' ', $title);
		}

		if (strpos($title, '||'))
		{
			list($maintitle, $_subtitle) = explode('||', $title, 2);
			$subtitle = ($_subtitle != '' && $_subtitle != '\n') ? explode('||', $_subtitle) : false;
		}
		else
		{
			$maintitle = $title;
			$subtitle  = false;
		}

		$return = '';
		$return .= $maintitle;

		if ($subtitle) :
			foreach ($subtitle as $_title) :
				if (strpos($_title, '\n') !== false) :
					$return .= ' ';
					$_title = substr($_title, 2);
				endif;
				$return .= $_title;
			endforeach;
		endif;

		return $return;
	}

	public function onBeforeCompileHead()
	{
		$document = JFactory::getDocument();
		$title    = $document->getTitle();

		if ($this->_checkStripe($title))
		{
			$title = $this->clearMultilineTitle($title);
		}

		$document->setTitle($title);

		if (JFactory::getApplication()->isAdmin()) return;

		if (version_compare(JVERSION, '3.0', 'ge'))
		{
			$template = $document->getTemplateBuffer();
		}
		else
		{
			$template = $document->get('_template');
		}

		$component = $document->getBuffer('component');

		$findInComponent = $this->_checkStripe($component);

		if ($findInComponent)
		{
			$component = $this->_renderXML($component);
			$document->setBuffer($component, 'component');
		}

		$findInTemplate = $this->_checkStripe($template);

		if ($findInTemplate)
		{
			preg_match_all('#(<\s*body[^>]*>)(.*?)(<\s*/\s*body>)#siU', $template, $_template, PREG_SET_ORDER);

			$_template_buffer = preg_replace('@<jdoc:include(.*?)/>@uxis', '<include${1}></include>', $_template[0][2]);
			$_template_buffer = $this->_renderXML($_template_buffer);
			$_template_buffer = preg_replace('@<include(.*?)></include>@uxis', '<jdoc:include${1} />', $_template_buffer);
			$_template_buffer = $_template[0][1] . $_template_buffer . $_template[0][3];
			$template         = preg_replace('#(<\s*body[^>]*>)(.*?)(<\s*/\s*body>)#siU', $_template_buffer, $template);

			if (version_compare(JVERSION, '3.0', 'ge'))
			{
				$document->setTemplateBuffer($template);
			}
			else
			{
				$document->set('_template', $template);
			}
		}

		if ($findInComponent || $findInTemplate || ($this->setCss && $this->css))
		{
			$base = str_replace('/administrator', '', JURI::base(true));
			$document->addStylesheet($base . '/' . $this->css);
		}
	}
}
