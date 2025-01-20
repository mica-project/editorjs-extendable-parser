<?php

namespace MicaProject\EJSParser;

use DOMDocument;
use DomXPath;
use JsonException;

class HtmlParser
{
    private Config $config;

    private mixed $html;

    private DOMDocument $dom;

    private array $blocks = [];

    private string $prefix;

    /**
     * @var string
     */
    private string $time;

    /**
     * @var string
     */
    private $version;

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    public function __construct(string $html)
    {
        $this->config = new Config();

        if (empty($html)) {
            throw new ParserException('Empty HTML!');
        }

        $this->prefix = $this->config->getPrefix();

        libxml_use_internal_errors(true);

        $this->html = $html;

        $this->dom = new DOMDocument(1.0, 'UTF-8');

        $this->dom->loadHTML(mb_encode_numericentity($this->html, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'), LIBXML_NOERROR);
    }

    public static function parse(string $html): static
    {
        return new static($html);
    }

    public function getPrefix(): string
    {
        return !empty($this->prefix) ? $this->prefix : $this->config->getPrefix();
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getTime(): float
    {
        return $this->time ? (float)$this->time : round(microtime(true) * 1000);
    }

    public function setTime(string $time): void
    {
        $this->time = $time;
    }

    public function getVersion(): string
    {
        return $this->version ?? $this->config->getVersion();
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function getHtml(): mixed
    {
        return $this->html ?? null;
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    public function toBlocks(): false|string
    {
        $this->init();

        $this->blocks = [
            'time' => $this->getTime(),
            'blocks' => $this->blocks,
            'version' => $this->getVersion(),
        ];

        try {
            $result = json_encode($this->blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new ParserException($e->getMessage());
        }

        return $result;
    }

    /**
     * @throws ParserException
     */
    protected function init(): void
    {
        if (!$this->hasHtml()) {
            throw new ParserException('No HTML to parse !');
        }

        $finder = new DomXPath($this->dom);

        $nodes = $finder->query("//*[contains(@class, '$this->prefix')]");

        foreach ($nodes as $node) {

            $nodeAttr = [];

            if (isset($node->attributes)) {
                foreach ($node->attributes as $attribute) {
                    $nodeAttr[$attribute->name] = $attribute->value;
                }
            }

            $classList = array_key_exists('class', $nodeAttr) ? explode(' ', $nodeAttr['class']) : [];

            $prefixClass = $this->getPrefix();

            // Get block type from class name
            $blockType = array_values(array_filter($classList, static function ($class) use ($prefixClass) {
                if (str_starts_with($class, $prefixClass . '-')) {
                    return true;
                }
                return false;
            }));

            $blockType = isset($blockType[0]) ? explode('-', $blockType[0])[1] : null;

            // Styles / Tunes list from class name
            $styles = array_values(array_filter($classList, static function ($class) use ($prefixClass) {
                if (str_starts_with($class, $prefixClass . '_')) {
                    return true;
                }
                return false;
            }));

            foreach ($styles as $k => $style) {
                $styles[$k] = ltrim(strstr($style, '_'), '_');
            }

            // Call block parse function
            $method = isset($blockType) ? 'parse' . ucfirst($blockType) : '';
            if (method_exists($this, $method)) {
                $data = $this->{$method}($node, $styles);
                $this->blocks[] = $data;
            } else if (!empty($method)) {
                throw new ParserException('Unknown block ' . $blockType . ' !');
            }
        }
    }

    protected function hasHtml(): bool
    {
        return get_object_vars($this->dom) !== null;
    }

    /**
     * Nodes by class name
     *
     * @param object $parentNode where find elements
     * @param string $tagName
     * @param string $className
     * @return array
     */
    protected function getElementsByClass(object $parentNode, string $tagName, string $className): array
    {
        $nodes = [];

        $childNodeList = $parentNode->getElementsByTagName($tagName);

        for ($i = 0; $i < $childNodeList->length; $i++) {
            $tmp = $childNodeList->item($i);
            if (stripos($tmp->getAttribute('class'), $className) !== false) {
                $nodes[] = $tmp;
            }
        }

        return $nodes;
    }

    /**
     * Prepare block value
     */
    protected function setInnerHtml(object $node): string
    {
        $innerHTML = '';

        // Check child elements exist
        if (isset($node->childNodes)) {
            foreach ($node->childNodes as $childNode) {
                $innerHTML .= $childNode->ownerDocument->saveHTML($childNode);
            }
        } else {
            $innerHTML .= $node->nodeValue ?? '';
        }

        return $innerHTML;
    }

    /**
     * Get alignment from class name
     */
    protected function setAlignment($styles): string
    {
        $filter = ['center', 'right', 'justify', 'left'];
        $alignment = array_values(array_intersect($styles, $filter));
        return !empty($alignment) ? $alignment[0] : 'left';
    }

    /**
     * Header Parser
     */
    protected function parseHeader(object $node, array $styles): array
    {
        $block['type'] = 'header';
        $block['data']['text'] = $this->setInnerHtml($node);
        $block['data']['level'] = ltrim($node->tagName, $node->tagName[0]);
        $block['data']['alignment'] = $this->setAlignment($styles);

        return $block;
    }

    /**
     * Paragraph Parser
     */
    protected function parseParagraph(object $node, array $styles): array
    {
        $block['type'] = 'paragraph';
        $block['data']['text'] = $this->setInnerHtml($node);
        $block['data']['alignment'] = $this->setAlignment($styles);

        return $block;
    }

    /**
     * List Parser
     */
    protected function parseList(object $node, array $styles): array
    {
        $style = in_array('ordered', $styles, true) ? 'ordered' : 'unordered';

        $items = [];
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeType === XML_ELEMENT_NODE) {
                $items[] = $childNode->nodeValue;
            }
        }

        $block['type'] = 'list';
        $block['data']['style'] = $style;
        $block['data']['items'] = $items;

        return $block;
    }

    /**
     * Raw HTML Parser
     */
    protected function parseRaw(object $node, array $styles): array
    {
        $block['type'] = 'raw';
        $block['data']['html'] = $this->setInnerHtml($node);

        return $block;
    }

    /**
     * LinkTool Parser
     */
    protected function parseLinkTool(object $node, array $styles): array
    {

        $title = $this->getElementsByClass($node, 'p', $this->prefix . '_title');
        $title = $title[0]->textContent;

        $description = $this->getElementsByClass($node, 'p', $this->prefix . '_description');
        $description = $description[0]->textContent;

        $sitename = $this->getElementsByClass($node, 'p', $this->prefix . '_sitename');
        $sitename = $sitename[0]->textContent;

        $block['type'] = 'linkTool';
        $block['data']['link'] = $node->getElementsByTagName('a')->item(0)->getAttribute('href');
        $block['data']['meta']['site_name'] = $sitename;
        $block['data']['meta']['image']['url'] = $node->getElementsByTagName('img')->item(0)->getAttribute('src');
        $block['data']['meta']['title'] = $title;
        $block['data']['meta']['description'] = $description;

        return $block;
    }

    /**
     * Delimiter Parser
     */
    protected function parseDelimiter(object $node, array $styles): array
    {

        $block['type'] = 'delimiter';
        $block['data'] = [];

        return $block;
    }

    /**
     * Alert Parser
     */
    protected function parseAlert(object $node, array $styles): array
    {

        $types = [
            'primary',
            'secondary',
            'info',
            'success',
            'warning',
            'danger',
            'light',
            'dark',
        ];
        $dataType = array_values(array_intersect($styles, $types));
        $dataType = !empty($dataType) ? $dataType[0] : 'primary';

        $block['type'] = 'alert';
        $block['data']['type'] = $dataType;
        $block['data']['align'] = $this->setAlignment($styles);
        $block['data']['message'] = $this->setInnerHtml($node);

        return $block;
    }

    /**
     * Table Parser
     */
    protected function parseTable(object $node, array $styles): array
    {

        $withHeadings = in_array('withheadings', $styles, true);

        $trs = [];

        // Parse thead
        $thead = $node->getElementsByTagName('thead');
        if ($thead->length > 0) {
            $tr = $thead->item(0)->getElementsByTagName('tr')->item(0);
            $theadTds = [];
            foreach ($tr->childNodes as $childNode) {
                if ($childNode->nodeType === XML_ELEMENT_NODE) {
                    $theadTds[] = $childNode->nodeValue;
                }
            }
            $trs[] = $theadTds;
        }

        // Parse tbody
        $tbody = $node->getElementsByTagName('tbody');
        if ($tbody->length > 0) {
            $rows = $tbody->item(0)->getElementsByTagName('tr');
            foreach ($rows as $tr) {
                $tbodyTds = [];
                foreach ($tr->childNodes as $childNode) {
                    if ($childNode->nodeType === XML_ELEMENT_NODE) {
                        $tbodyTds[] = $childNode->nodeValue;
                    }
                }
                $trs[] = $tbodyTds;
            }
        }

        $block['type'] = 'table';
        $block['data']['withHeadings'] = $withHeadings;
        $block['data']['content'] = $trs;

        return $block;
    }

    /**
     * Code Parser
     */
    protected function parseCode(object $node, array $styles): array
    {
        $block['type'] = 'code';
        $block['data']['code'] = $node->getElementsByTagName('code')->item(0)->nodeValue;

        return $block;
    }

    /**
     * Quote Parser
     */
    protected function parseQuote(object $node, array $styles): array
    {
        $block['type'] = 'quote';
        $block['data']['text'] = $this->setInnerHtml($node->getElementsByTagName('blockquote')->item(0));
        $block['data']['caption'] = $this->setInnerHtml($node->getElementsByTagName('figcaption')->item(0));
        $block['data']['alignment'] = $this->setAlignment($styles);

        return $block;
    }

    /**
     * Embed Parser
     */
    protected function parseEmbed(object $node, array $styles): array
    {
        $services = [
            'facebook',
            'instagram',
            'youtube',
            'twitter',
            'twitch-video',
            'miro',
            'vimeo',
            'gfycat',
            'imgur',
            'vine',
            'aparat',
            'yandex-music-track',
            'yandex-music-album',
            'yandex-music-playlist',
            'coub',
            'codepen',
            'pinterest',
            'github',
        ];
        $dataServices = array_values(array_intersect($styles, $services));
        $dataServices = !empty($dataServices) ? $dataServices[0] : '';

        $block['type'] = 'embed';
        $block['data']['service'] = $dataServices;
        $block['data']['source'] = $node->getElementsByTagName('iframe')->item(0)->getAttribute('src');
        $block['data']['embed'] = $node->getElementsByTagName('iframe')->item(0)->getAttribute('src');
        $block['data']['width'] = $node->getElementsByTagName('iframe')->item(0)->getAttribute('width');
        $block['data']['height'] = $node->getElementsByTagName('iframe')->item(0)->getAttribute('height');
        $block['data']['caption'] = $this->setInnerHtml($node->getElementsByTagName('figcaption')->item(0));

        return $block;
    }

    /**
     * Image Parser
     */
    protected function parseImage(object $node, array $styles): array
    {
        $withBorder = in_array('withborder', $styles, true);
        $withBackground = in_array('withbackground', $styles, true);
        $stretched = in_array('stretched', $styles, true);

        $block['type'] = 'image';
        $block['data']['url'] = $node->getElementsByTagName('img')->item(0)->getAttribute('src');
        $block['data']['caption'] = $this->setInnerHtml($node->getElementsByTagName('figcaption')->item(0));
        $block['data']['withBorder'] = $withBorder;
        $block['data']['withBackground'] = $withBackground;
        $block['data']['stretched'] = $stretched;

        return $block;
    }

    /**
     * Warning Parser
     */
    protected function parseWarning(object $node, array $styles): array
    {
        $block['type'] = 'warning';
        $block['data']['title'] = $node->getElementsByTagName('h4')->item(0)->nodeValue;
        $block['data']['message'] = $node->getElementsByTagName('p')->item(0)->nodeValue;

        return $block;
    }

}
