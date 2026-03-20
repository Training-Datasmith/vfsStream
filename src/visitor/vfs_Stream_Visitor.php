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
use bovigo\vfs\Vfs_Stream_Content;
use bovigo\vfs\Vfs_Stream_Directory;
use bovigo\vfs\Vfs_Stream_File;
use function class_alias;
/**
 * Interface for a visitor to work on a vfsStream content structure.
 *
 * @see    https://github.com/mikey179/vfsStream/issues/10
 *
 * @since  0.10.0
 */
interface Vfs_Stream_Visitor
{
    /**
     * visit a content and process it
     */
    public function visit(Vfs_Stream_Content $content): self;
    /**
     * visit a file and process it
     */
    public function visit_file(Vfs_Stream_File $file): self;
    /**
     * visit a directory and process it
     */
    public function visit_directory(Vfs_Stream_Directory $dir): self;
    /**
     * visit a block device and process it
     */
    public function visit_block_device(Vfs_Stream_Block $block): self;
}
class_alias('bovigo\vfs\visitor\vfsStreamVisitor', 'org\bovigo\vfs\visitor\vfsStreamVisitor');