<?php

namespace Durlecode\EJSParser;

use DOMDocument;
use DOMText;
use Exception;
use Masterminds\HTML5;
use StdClass;

class Parser
{
    /**
     * @var StdClass
     */
    private $data;

    /**
     * @var DOMDocument
     */
    private $dom;

    /**
     * @var HTML5
     */
    private $html5;

    /**
     * @var string
     */
    private $prefix = "prs";

    public function __construct(string $data)
    {
        $this->data = json_decode($data);

        $this->dom = new DOMDocument(1.0, 'UTF-8');

        $this->html5 = new HTML5([
            'target_document' => $this->dom,
            'disable_html_ns' => true
        ]);
    }

    static function parse($data)
    {
        return new self($data);
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getTime()
    {
        return isset($this->data->time) ? $this->data->time : null;
    }

    public function getVersion()
    {
        return isset($this->data->version) ? $this->data->version : null;
    }

    public function getBlocks()
    {
        return isset($this->data->blocks) ? $this->data->blocks : null;
    }

    public function toHtml()
    {
        $this->init();

        return html_entity_decode($this->dom->saveHTML());
    }

    /**
     * @throws Exception
     */
    private function init()
    {
        if (!$this->hasBlocks()) throw new Exception('No blocks to parse !');
        foreach ($this->data->blocks as $block) {
            $method = 'parse'.ucfirst($block->type);
            if (method_exists($this, $method)) {
                $this->{$method}($block);
            } else {
                throw new Exception('Unknow block '.$block->type.' !');
            }
        }
    }

    private function hasBlocks()
    {
        return count($this->data->blocks) !== 0;
    }

    private function addClass($type, $alignment = false, $style = false, $custom = false)
    {
        $class[] = $this->prefix.'-'.$type;
        
        if ($alignment) {
            $class[] = $this->prefix.'-'.$type.'--'.$alignment;
        }

        if ($style) {
            $class[] = $this->prefix.'-'.$type.'--'.$style;
        }
        
        return implode(' ', $class);
    }

    private function parseHeader($block)
    {
        $text = new DOMText($block->data->text);

        $alignment = isset($block->data->alignment) ? $block->data->alignment : false;

        $class = $this->addClass($block->type, $alignment);

        $header = $this->dom->createElement('h' . $block->data->level);

        $header->setAttribute('class', $class);

        $header->appendChild($text);

        $this->dom->appendChild($header);
    }

    private function parseDelimiter($block)
    {
        $node = $this->dom->createElement('hr');

        $node->setAttribute('class', $this->addClass($block->type));

        $this->dom->appendChild($node);
    }

    private function parseCode($block)
    {
        $pre = $this->dom->createElement('pre');

        $pre->setAttribute('class', $this->addClass($block->type));

        $code = $this->dom->createElement('code');

        $content = new DOMText($block->data->code);

        $code->appendChild($content);

        $pre->appendChild($code);

        $this->dom->appendChild($pre);
    }

    private function parseParagraph($block)
    {
        $alignment = isset($block->data->alignment) ? $block->data->alignment : false;

        $class = $this->addClass($block->type, $alignment);

        $node = $this->dom->createElement('p');

        $node->setAttribute('class', $class);

        $node->appendChild($this->html5->loadHTMLFragment($block->data->text));

        $this->dom->appendChild($node);
    }

    private function parseLink($block)
    {
        $link = $this->dom->createElement('a');

        $link->setAttribute('href', $block->data->link);
        $link->setAttribute('target', '_blank');
        $link->setAttribute('class', "{$this->prefix}-link");

        $innerContainer = $this->dom->createElement('div');
        $innerContainer->setAttribute('class', "{$this->prefix}-link-container");

        $hasTitle = isset($block->data->meta->title);
        $hasDescription = isset($block->data->meta->description);
        $hasImage = isset($block->data->meta->image);

        if ($hasTitle) {
            $titleNode = $this->dom->createElement('div');
            $titleNode->setAttribute('class', "{$this->prefix}-link-title");
            $titleText = new DOMText($block->data->meta->title);
            $titleNode->appendChild($titleText);
            $innerContainer->appendChild($titleNode);
        }

        if ($hasDescription) {
            $descriptionNode = $this->dom->createElement('div');
            $descriptionNode->setAttribute('class', "{$this->prefix}-link-description");
            $descriptionText = new DOMText($block->data->meta->description);
            $descriptionNode->appendChild($descriptionText);
            $innerContainer->appendChild($descriptionNode);
        }

        $linkContainer = $this->dom->createElement('div');
        $linkContainer->setAttribute('class', "{$this->prefix}-link-url");
        $linkText = new DOMText($block->data->link);
        $linkContainer->appendChild($linkText);
        $innerContainer->appendChild($linkContainer);

        $link->appendChild($innerContainer);

        if ($hasImage) {
            $imageContainer = $this->dom->createElement('div');
            $imageContainer->setAttribute('class', "{$this->prefix}-link-img-container");
            $image = $this->dom->createElement('img');
            $image->setAttribute('src', $block->data->meta->image->url);
            $imageContainer->appendChild($image);
            $link->appendChild($imageContainer);
            $innerContainer->setAttribute('class', "{$this->prefix}-link-container-with-img");
        }

        $this->dom->appendChild($link);
    }

    private function parseEmbed($block)
    {
        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', $block->type);

        switch ($block->data->service) {
            case 'youtube':

                $attrs = [
                    'height' => $block->data->height,
                    'src' => $block->data->embed,
                    'allow' => 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
                    'allowfullscreen' => true
                ];

                break;
            // case 'codepen' || 'gfycat':
            default:

                $attrs = [
                    'height' => $block->data->height,
                    'src' => $block->data->embed,
                ];
        }

        $figure->appendChild($this->createIframe($attrs));

        if ($block->data->caption) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function createIframe(array $attrs)
    {
        $iframe = $this->dom->createElement('iframe');

        foreach ($attrs as $key => $attr) $iframe->setAttribute($key, $attr);

        return $iframe;
    }

    private function parseRaw($block)
    {
        $class = $this->addClass($block->type);

        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', $class);

        $wrapper->appendChild($this->html5->loadHTMLFragment($block->data->html));

        $this->dom->appendChild($wrapper);
    }

    private function parseList($block)
    {
        $class = $this->addClass($block->type, false, $block->data->style);

        $list = null;

        switch ($block->data->style) {
            case 'ordered':
                $list = $this->dom->createElement('ol');
                break;
            default:
                $list = $this->dom->createElement('ul');
                break;
        }

        foreach ($block->data->items as $item) {
            $li = $this->dom->createElement('li');
            $li->appendChild($this->html5->loadHTMLFragment($item));
            $list->appendChild($li);
        }

        $list->setAttribute('class', $class);

        $this->dom->appendChild($list);
    }

    private function parseWarning($block)
    {
        $title = new DOMText($block->data->title);
        $message = new DOMText($block->data->message);

        $wrapper = $this->dom->createElement('div');
        $wrapper->setAttribute('class', "{$this->prefix}-warning");

        $textWrapper = $this->dom->createElement('div');
        $titleWrapper = $this->dom->createElement('p');

        $titleWrapper->appendChild($title);
        $messageWrapper = $this->dom->createElement('p');

        $messageWrapper->appendChild($message);

        $textWrapper->appendChild($titleWrapper);
        $textWrapper->appendChild($messageWrapper);

        $icon = $this->dom->createElement('ion-icon');
        $icon->setAttribute('name', 'information-outline');
        $icon->setAttribute('size', 'large');

        $wrapper->appendChild($icon);
        $wrapper->appendChild($textWrapper);

        $this->dom->appendChild($wrapper);
    }

    private function parseAlert($block)
    {
        $alignment = isset($block->data->align) ? $block->data->align : false;

        $style = isset($block->data->type) ? $block->data->type : false;

        $class = $this->addClass($block->type, $alignment, $style);

        $node = $this->dom->createElement('p');

        $node->setAttribute('class', $class);

        $node->appendChild(new DOMText($block->data->message));

        $this->dom->appendChild($node);
    }

    private function parseImage($block)
    {
        $figure = $this->dom->createElement('figure');

        $figure->setAttribute('class', "{$this->prefix}-image");

        $img = $this->dom->createElement('img');

        $img->setAttribute('src', $block->data->url);

        $imgAttrs = [];

        if ($block->data->withBorder) $imgAttrs[] = "{$this->prefix}-image-border";
        if ($block->data->withBackground) $imgAttrs[] = "{$this->prefix}-image-background";
        if ($block->data->stretched) $imgAttrs[] = "{$this->prefix}-image-stretched";

        if (count($imgAttrs) > 0) {
            $img->setAttribute('class', implode(' ', $imgAttrs));
        }
        
        $figure->appendChild($img);

        if (!empty($block->data->caption)) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseStandardImage($block)
    {
        $figure = $this->dom->createElement('figure');

        $figure->setAttribute('class', "{$this->prefix}-image");

        $img = $this->dom->createElement('img');

        $imgAttrs = [];

        if ($block->data->withBorder) $imgAttrs[] = "{$this->prefix}-image-border";
        if ($block->data->withBackground) $imgAttrs[] = "{$this->prefix}-image-background";
        if ($block->data->stretched) $imgAttrs[] = "{$this->prefix}-image-stretched";
        $imgAttrs = array_merge($imgAttrs, $this->customImgAttrs);

        $img->setAttribute('src', $block->data->file->url);
        $img->setAttribute('class', implode(' ', $imgAttrs));

        $figure->appendChild($img);

        if ($block->data->caption) {

            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseQuote($block)
    {
        $alignment = isset($block->data->alignment) ? $block->data->alignment : false;

        $class = $this->addClass($block->type, $alignment);

        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', $class);

        $blockquote = $this->dom->createElement('blockquote');

        $blockquote->appendChild($this->html5->loadHTMLFragment($block->data->text));
        $figure->appendChild($blockquote);

        if ($block->data->caption) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseTable($block)
    {
        $style = !empty($block->data->withHeadings) ? 'headings' : false;

        $class = $this->addClass($block->type, false, $style);

        $table = $this->dom->createElement('table');
        $table->setAttribute('class', $class);

        $tr_top = $this->dom->createElement('tr');
        $thead = $this->dom->createElement('thead');
        $tbody = $this->dom->createElement('tbody');
        $thead->appendChild($tr_top);
        $table->appendChild($thead);
        $table->appendChild($tbody);

        foreach ($block->data->content[0] as $head) {
            $th = $this->dom->createElement('th', $head);
            $tr_top->appendChild($th);
        }

        $dataset = $block->data->content;
        unset($dataset[0]);

        foreach ($dataset as $data) {
            $tr = $this->dom->createElement('tr');
            foreach ($data as $item) {
                $td = $this->dom->createElement('td', $item);
                $tr->appendChild($td);
            }
            $tbody->appendChild($tr);
        }

        $this->dom->appendChild($table);
    }

    private function parseLinkTool($block)
    {
        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', $this->addClass($block->type));

        $link = $this->dom->createElement('a');
        $link->setAttribute('href', $block->data->link);

        $img = $this->dom->createElement('img');
        $img->setAttribute('src', $block->data->meta->image->url);

        $link->appendChild($img);

        $link_title = $this->dom->createElement('p');
        $link_title->setAttribute('class', "{$this->prefix}-{$block->type}--title");
        $link_title->appendChild($this->html5->loadHTMLFragment($block->data->meta->title));
        $link->appendChild($link_title);

        $link_description = $this->dom->createElement('p');
        $link_description->setAttribute('class', "{$this->prefix}-{$block->type}--description");
        $link_description->appendChild($this->html5->loadHTMLFragment($block->data->meta->description));
        $link->appendChild($link_description);

        $figure->appendChild($link);

        $this->dom->appendChild($figure);
    }
}
