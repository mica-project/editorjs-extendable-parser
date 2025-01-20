<?php

namespace MicaProject\EJSParser\Tests;

use MicaProject\EJSParser\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Exception;

#[CoversClass(Parser::class)]
final class ParserTest extends TestCase
{
    public static function seedDataProvider(): array
    {
        $data = file_get_contents(__DIR__ . '/data/seed.json');
        return [
            [$data],
        ];
    }

    public static function emptyBlockDataProvider(): array
    {
        $data = file_get_contents(__DIR__ . '/data/empty-blocks.json');
        return [
            [$data],
        ];
    }

    public static function invalidBlockDataProvider(): array
    {
        $data = file_get_contents(__DIR__ . '/data/invalid-blocks.json');
        return [
            [$data],
        ];
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    #[DataProvider('seedDataProvider')]
    public function testToHtml(string $seed): void
    {
        $this->assertIsString(Parser::parse($seed)->toHtml());
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    #[DataProvider('seedDataProvider')]
    public function testGetters(string $seed): void
    {
        $parser = new Parser($seed);

        $prefix = "trd";

        $this->assertEquals("prs", $parser->getPrefix());

        $parser->setPrefix($prefix);

        $this->assertEquals($prefix, $parser->getPrefix());

        $this->assertIsInt($parser->getTime());

        $this->assertIsArray($parser->getBlocks());

        $this->assertIsString($parser->getVersion());
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */

    #[DataProvider('emptyBlockDataProvider')]
    public function testToHtmlWithoutBlocks(string $emptyBlocks): void
    {
        $this->expectException(Exception::class);

        $this->expectExceptionMessage('No blocks to parse!');

        Parser::parse($emptyBlocks)->toHtml();
    }

    /**
     * @throws \Durlecode\EJSParser\ParserException
     */
    #[DataProvider('invalidBlockDataProvider')]
    public function testToHtmlInvalidBlocks(string $invalidBlocks): void
    {
        $this->expectException(Exception::class);

        $this->expectExceptionMessage('Unknown block hello!');

        Parser::parse($invalidBlocks)->toHtml();
    }
}
