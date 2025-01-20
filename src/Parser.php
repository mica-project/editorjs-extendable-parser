<?php

namespace MicaProject\EJSParser;

use DOMDocument;
use DOMElement;
use DOMText;
use JsonException;
use Masterminds\HTML5;

class Parser
{
    protected Config $config;

    protected mixed $data;

    protected DOMDocument $dom;

    protected HTML5 $html5;

    protected string $prefix;

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    public function __construct(string $data)
    {
        $this->config = new Config();

        $this->prefix = $this->config->getPrefix();

        try {
            $this->data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ParserException($e->getMessage());
        }

        $this->dom = new DOMDocument(1.0, 'UTF-8');

        $this->html5 = new HTML5([
            'target_document' => $this->dom,
            'disable_html_ns' => true,
        ]);
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    public static function parse($data): static
    {
        return new static($data);
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return !empty($this->prefix) ? $this->prefix : $this->config->getPrefix();
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
        return $this->data->time ?? null;
    }

    public function getVersion()
    {
        return $this->data->version ?? null;
    }

    public function getBlocks()
    {
        return $this->data->blocks ?? null;
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    public function toHtml(): string
    {
        $this->init();

        return html_entity_decode($this->dom->saveHTML());
    }

    /**
     * @throws ParserException
     */
    protected function init(): void
    {
        if (!$this->hasBlocks()) {
            throw new ParserException('No blocks to parse!');
        }

        foreach ($this->data->blocks as $block) {
            $method = 'parse'.ucfirst($block->type);
            if (method_exists($this, $method)) {
                $this->{$method}($block);
            } else {
                throw new ParserException('Unknown block '.$block->type.'!');
            }
        }
    }

    protected function hasBlocks(): bool
    {
        return count($this->data->blocks) !== 0;
    }

    protected function addClass(string $type, ?string $alignment = null, ?string $style = null): string
    {
        $class[] = $this->prefix.'-'.$type;

        if ($alignment) {
            $class[] = $this->prefix.'_'.$alignment;
        }

        if ($style) {
            $styles = explode(' ', $style);
            foreach ($styles as $v) {
                $class[] = $this->prefix.'_'.$v;
            }
        }

        return implode(' ', $class);
    }

    /**
     * @throws \DOMException
     */
    protected function parseHeader(object $block): void
    {
        $text = new DOMText($block->data->text);

        $alignment = $block->data->alignment ?? false;

        $class = $this->addClass($block->type, $alignment);

        $header = $this->dom->createElement('h' . $block->data->level);

        $header->setAttribute('class', $class);

        $header->appendChild($text);

        $this->dom->appendChild($header);
    }

    /**
     * @throws \DOMException
     */
    protected function parseDelimiter(object $block): void
    {
        $node = $this->dom->createElement('hr');

        $node->setAttribute('class', $this->addClass($block->type));

        $this->dom->appendChild($node);
    }

    /**
     * @throws \DOMException
     */
    protected function parseCode(object $block): void
    {
        $pre = $this->dom->createElement('pre');

        $pre->setAttribute('class', $this->addClass($block->type));

        $code = $this->dom->createElement('code');

        $content = new DOMText($block->data->code);

        $code->appendChild($content);

        $pre->appendChild($code);

        $this->dom->appendChild($pre);
    }

    /**
     * @throws \DOMException
     */
    protected function parseParagraph(object $block): void
    {
        $alignment = $block->data->alignment ?? false;

        $class = $this->addClass($block->type, $alignment);

        $node = $this->dom->createElement('p');

        $node->setAttribute('class', $class);

        $node->appendChild($this->html5->loadHTMLFragment($block->data->text));

        $this->dom->appendChild($node);
    }

    /**
     * @throws \DOMException
     */
    protected function parseEmbed(object $block): void
    {
        $figure = $this->dom->createElement('figure');

        $class = $this->addClass($block->type, false, $block->data->service);

        $figure->setAttribute('class', $class);

        $attrs = match ($block->data->service) {
            'youtube' => [
                'width' => $block->data->width,
                'height' => $block->data->height,
                'src' => $block->data->embed,
                'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                'allowfullscreen' => true,
            ],
            default => [
                'height' => $block->data->height,
                'src' => $block->data->embed,
            ],
        };

        $figure->appendChild($this->createIframe($attrs));

        if ($block->data->caption) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    /**
     * @throws \DOMException
     */
    protected function createIframe(array $attrs): DOMElement
    {
        $iframe = $this->dom->createElement('iframe');

        foreach ($attrs as $key => $attr) {
            $iframe->setAttribute($key, $attr);
        }

        return $iframe;
    }

    /**
     * @throws \DOMException
     */
    protected function parseRaw(object $block): void
    {
        $class = $this->addClass($block->type);

        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', $class);

        $wrapper->appendChild($this->html5->loadHTMLFragment($block->data->html));

        $this->dom->appendChild($wrapper);
    }

    /**
     * @throws \DOMException
     */
    protected function parseList(object $block): void
    {
        $class = $this->addClass($block->type, false, $block->data->style);

        $list = match ($block->data->style) {
            'ordered' => $this->dom->createElement('ol'),
            default => $this->dom->createElement('ul'),
        };

        foreach ($block->data->items as $item) {
            $li = $this->dom->createElement('li');
            $li->appendChild($this->html5->loadHTMLFragment($item));
            $list->appendChild($li);
        }

        $list->setAttribute('class', $class);

        $this->dom->appendChild($list);
    }

    /**
     * @throws \DOMException
     */
    protected function parseWarning(object $block): void
    {
        $title = new DOMText($block->data->title);
        $message = new DOMText($block->data->message);

        $class = $this->addClass($block->type);

        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', $class);

        $textWrapper = $this->dom->createElement('div');
        $titleWrapper = $this->dom->createElement('h4');

        $titleWrapper->appendChild($title);
        $messageWrapper = $this->dom->createElement('p');

        $messageWrapper->appendChild($message);

        $textWrapper->appendChild($titleWrapper);
        $textWrapper->appendChild($messageWrapper);

        $icon = $this->dom->createElement('i');

        $wrapper->appendChild($icon);
        $wrapper->appendChild($textWrapper);

        $this->dom->appendChild($wrapper);
    }

    /**
     * @throws \DOMException
     */
    protected function parseAlert(object $block): void
    {
        $alignment = $block->data->align ?? false;

        $style = $block->data->type ?? false;

        $class = $this->addClass($block->type, $alignment, $style);

        $node = $this->dom->createElement('p');

        $node->setAttribute('class', $class);

        $node->appendChild(new DOMText($block->data->message));

        $this->dom->appendChild($node);
    }

    /**
     * @throws \DOMException
     */
    protected function generateGenericImageFigure(object $block, string $src): DOMElement
    {
        $figure = $this->dom->createElement('figure');

        $attrs = [];
        if ($block->data->withBorder) {
            $attrs[] = 'withborder';
        }
        if ($block->data->withBackground) {
            $attrs[] = 'withbackground';
        }
        if ($block->data->stretched) {
            $attrs[] = 'stretched';
        }

        $style = (count($attrs) > 0) ? implode(' ', $attrs) : false;
        $class = $this->addClass($block->type, false, $style);
        $figure->setAttribute('class', $class);

        $caption = (!empty($block->data->caption)) ? $block->data->caption : '';
        $img = $this->dom->createElement('img');
        $img->setAttribute('src', $src);
        $img->setAttribute('alt', $caption);

        $figure->appendChild($img);

        if (!empty($caption)) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($caption));
            $figure->appendChild($figCaption);
        }

        return $figure;
    }

    /**
     * @throws \DOMException
     */
    protected function parseImage(object $block): void
    {
        $figure = $this->generateGenericImageFigure($block, $block->data->file->url);
        $this->dom->appendChild($figure);
    }

    /**
     * @throws \DOMException
     */
    protected function parseSimpleImage(object $block): void
    {
        $figure = $this->generateGenericImageFigure($block, $block->data->url);
        $this->dom->appendChild($figure);
    }

    /**
     * @throws \DOMException
     */
    protected function parseQuote(object $block): void
    {
        $alignment = $block->data->alignment ?? false;

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

    /**
     * @throws \DOMException
     */
    protected function parseTable(object $block): void
    {
        $style = !empty($block->data->withHeadings) ? 'withheadings' : false;

        $class = $this->addClass($block->type, false, $style);

        $table = $this->dom->createElement('table');
        $table->setAttribute('class', $class);

        $dataset = $block->data->content;

        if (!empty($block->data->withHeadings)) {
            $tr_top = $this->dom->createElement('tr');
            $thead = $this->dom->createElement('thead');
            // $tbody = $this->dom->createElement('tbody');
            $thead->appendChild($tr_top);
            $table->appendChild($thead);
            // $table->appendChild($tbody);

            foreach ($block->data->content[0] as $head) {
                $th = $this->dom->createElement('th', $head);
                $tr_top->appendChild($th);
            }

            // $dataset = $block->data->content;
            unset($dataset[0]);
        }

        $tbody = $this->dom->createElement('tbody');
        $table->appendChild($tbody);

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

    protected function parseLink(object $block): void
    {
        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', $this->addClass($block->type));

        $site_name = !empty($block->data->meta->site_name) ? $block->data->meta->site_name : parse_url($block->data->link, PHP_URL_HOST);

        $link = $this->dom->createElement('a');
        $link->setAttribute('href', $block->data->link);
        $link->setAttribute('target', '_blank');

        if (property_exists($block->data, 'meta')) {
            $link_title = $this->dom->createElement('p');
            $link_title->setAttribute('class', "{$this->prefix}_title");
            $link_title->appendChild($this->html5->loadHTMLFragment($block->data->meta->title));
            $link->appendChild($link_title);

            $link_description = $this->dom->createElement('p');
            $link_description->setAttribute('class', "{$this->prefix}_description");
            $link_description->appendChild($this->html5->loadHTMLFragment($block->data->meta->description));
            $link->appendChild($link_description);

            if (property_exists($block->data->meta, 'image')) {
                $img = $this->dom->createElement('img');
                $img->setAttribute('src', $block->data->meta->image->url);
                $img->setAttribute('alt', '');

                $link->appendChild($img);
            }
        }

        $link_name = $this->dom->createElement('p');
        $link_name->setAttribute('class', "{$this->prefix}_sitename");
        $link_name->appendChild($this->html5->loadHTMLFragment($site_name));
        $link->appendChild($link_name);

        $figure->appendChild($link);

        $this->dom->appendChild($figure);
    }
}
