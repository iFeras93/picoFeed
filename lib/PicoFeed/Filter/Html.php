<?php

namespace PicoFeed\Filter;

use \PicoFeed\Url;
use \PicoFeed\Filter;
use \PicoFeed\XmlParser;

/**
 * HTML Filter class
 *
 * @author  Frederic Guillot
 * @package filter
 */
class Html
{
    /**
     * Config object
     *
     * @access private
     * @var \PicoFeed\Config
     */
    private $config = null;

    /**
     * Unfiltered XML data
     *
     * @access private
     * @var string
     */
    private $input = '';

    /**
     * Filtered XML data
     *
     * @access private
     * @var string
     */
    private $output = '';

    /**
     * List of empty tags
     *
     * @access private
     * @var array
     */
    private $empty_tags = array();

    /**
     * Empty flag
     *
     * @access private
     * @var boolean
     */
    private $empty = true;

    /**
     * Tag instance
     *
     * @access public
     * @var \PicoFeed\Filter\Tag
     */
    public $tag = '';

    /**
     * Attribute instance
     *
     * @access public
     * @var \PicoFeed\Filter\Attribute
     */
    public $attribute = '';

    /**
     * Initialize the filter, all inputs data must be encoded in UTF-8 before
     *
     * @access public
     * @param  string  $html      HTML content
     * @param  string  $website   Site URL (used to build absolute URL)
     */
    public function __construct($html, $website)
    {
        $this->input = XmlParser::HtmlToXml($html);
        $this->output = '';
        $this->tag = new Tag;
        $this->attribute = new Attribute(new Url($website));
    }

    /**
     * Set config object
     *
     * @access public
     * @param  \PicoFeed\Config  $config   Config instance
     * @return \PicoFeed\Html
     */
    public function setConfig($config)
    {
        $this->config = $config;

        if ($this->config !== null) {
            $this->attribute->setIframeWhitelist($this->config->getFilterIframeWhitelist(array()));
            $this->attribute->setIntegerAttributes($this->config->getFilterIntegerAttributes(array()));
            $this->attribute->setAttributeOverrides($this->config->getFilterAttributeOverrides(array()));
            $this->attribute->setRequiredAttributes($this->config->getFilterRequiredAttributes(array()));
            $this->attribute->setMediaBlacklist($this->config->getFilterMediaBlacklist(array()));
            $this->attribute->setMediaAttributes($this->config->getFilterMediaAttributes(array()));
            $this->attribute->setSchemeWhitelist($this->config->getFilterSchemeWhitelist(array()));
            $this->attribute->setWhitelistedAttributes($this->config->getFilterWhitelistedTags(array()));
            $this->tag->setWhitelistedTags(array_keys($this->config->getFilterWhitelistedTags(array())));
        }

        return $this;
    }

    /**
     * Run tags/attributes filtering
     *
     * @access public
     * @return string
     */
    public function execute()
    {
        $parser = xml_parser_create();

        xml_set_object($parser, $this);
        xml_set_element_handler($parser, 'startTag', 'endTag');
        xml_set_character_data_handler($parser, 'dataTag');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_parse($parser, $this->input, true);
        xml_parser_free($parser);

        $this->postFilter();

        return $this->output;
    }

    public function postFilter()
    {
        $this->output = $this->tag->removeEmptyTags($this->output);
        $this->output = trim($this->output);
    }

    /**
     * Parse opening tag
     *
     * @access public
     * @param  resource  $parser       XML parser
     * @param  string    $name         Tag name
     * @param  array     $attributes   Tag attributes
     */
    public function startTag($parser, $tag, array $attributes)
    {
        $this->empty = true;

        if ($this->tag->isAllowed($tag, $attributes)) {

            $attributes = $this->attribute->filter($tag, $attributes);

            if ($this->attribute->hasRequiredAttributes($tag, $attributes)) {

                $attributes = $this->attribute->addAttributes($tag, $attributes);

                $this->output .= $this->tag->openHtmlTag($tag, $this->attribute->toHtml($attributes));
                $this->empty = false;
            }
        }

        $this->empty_tags[] = $this->empty;
    }

    /**
     * Parse closing tag
     *
     * @access public
     * @param  resource  $parser    XML parser
     * @param  string    $name      Tag name
     */
    public function endTag($parser, $tag)
    {
        if (! array_pop($this->empty_tags) && $this->tag->isAllowedTag($tag)) {
            $this->output .= $this->tag->closeHtmlTag($tag);
        }
    }

    /**
     * Parse tag content
     *
     * @access public
     * @param  resource  $parser    XML parser
     * @param  string    $content   Tag content
     */
    public function dataTag($parser, $content)
    {
        if (! $this->empty) {

            // Replace &nbsp; with normal space
            $content = str_replace("\xc2\xa0", ' ', $content);

            $this->output .= Filter::escape($content);
        }
    }
}