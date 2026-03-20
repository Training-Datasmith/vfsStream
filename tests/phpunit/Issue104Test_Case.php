<?php

declare(strict_types=1);

/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace bovigo\vfs\tests;

use function bovigo\assert\assertThat;
use function bovigo\assert\assertTrue;
use function bovigo\assert\predicate\equals;

use bovigo\vfs\vfsStream;
use DOMDocument;

use function file_get_contents;

use PHPUnit\Framework\TestCase;

/**
 * @group  issue_104
 * @group  issue_128
 * @since  1.5.0
 */
class Issue104TestCase extends TestCase
{
    /** @var string */
    private $content;

    protected function setUp(): void
    {
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $this->content = '<xs:schema targetNamespace="http://www.example.com" xmlns:xs="http://www.w3.org/2001/XMLSchema">
                          <xs:complexType name="myType"></xs:complexType>
                        </xs:schema>';
        $structure = ['foo bar' => ['schema.xsd' => $this->content]];
        vfsStream::setup('root', null, $structure);
    }

    /**
     * @test
     */
    public function vfsStreamCanHandleUrlEncodedPathPassedByInternalPhpCode(): void
    {
        $doc = new DOMDocument();
        assertTrue($doc->load(vfsStream::url('root/foo bar/schema.xsd')));
    }

    /**
     * @test
     */
    public function vfsStreamCanHandleUrlEncodedPath(): void
    {
        assertThat(
            file_get_contents(vfsStream::url('root/foo bar/schema.xsd')),
            equals($this->content)
        );
    }
}
