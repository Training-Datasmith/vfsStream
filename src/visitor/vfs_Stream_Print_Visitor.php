<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs\visitor;

use bovigo\vfs\Vfs_Stream_Block;
use bovigo\vfs\Vfs_Stream_Directory;
use bovigo\vfs\Vfs_Stream_File;
use function class_alias;
use function fwrite;
use function get_resource_type;
use InvalidArgumentException;
use function is_resource;
use const STDOUT;
use function str_repeat;
/**
 * Visitor which traverses a content structure recursively to print it to an output stream.
 *
 * @see    https://github.com/mikey179/vfsStream/issues/10
 *
 * @since  0.10.0
 */
class Vfs_Stream_Print_Visitor extends Vfs_Stream_Abstract_Visitor
{
    /**
     * target to write output to
     *
     * @var  resource
     */
    protected $out;
    /**
     * current depth in directory tree
     *
     * @var  int
     */
    protected $depth = 0;
    /**
     * constructor
     *
     * If no file pointer given it will fall back to STDOUT.
     *
     * @param   resource $out optional
     *
     * @throws InvalidArgumentException
     *
     * @api
     */
    public function __construct($out = STDOUT)
    {
        if (!is_resource($out) || get_resource_type($out) !== 'stream') {
            throw new InvalidArgumentException('Given filepointer is not a resource of type stream');
        }
        $this->out = $out;
    }
    /**
     * visit a file and process it
     *
     * @return  vfsStreamPrintVisitor
     */
    public function visit_file(Vfs_Stream_File $file): Vfs_Stream_Visitor
    {
        $this->print_content($file->get_name());
        return $this;
    }
    /**
     * visit a block device and process it
     *
     * @return  vfsStreamPrintVisitor
     */
    public function visit_block_device(Vfs_Stream_Block $block): Vfs_Stream_Visitor
    {
        $name = '[' . $block->get_name() . ']';
        $this->print_content($name);
        return $this;
    }
    /**
     * visit a directory and process it
     *
     * @return  vfsStreamPrintVisitor
     */
    public function visit_directory(Vfs_Stream_Directory $dir): Vfs_Stream_Visitor
    {
        $this->print_content($dir->get_name());
        $this->depth++;
        foreach ($dir as $child) {
            $this->visit($child);
        }
        $this->depth--;
        return $this;
    }
    /**
     * helper method to print the content
     */
    protected function print_content(string $name): void
    {
        fwrite($this->out, str_repeat('  ', $this->depth) . '- ' . $name . "\n");
    }
}
class_alias('bovigo\vfs\visitor\vfsStreamPrintVisitor', 'org\bovigo\vfs\visitor\vfsStreamPrintVisitor');