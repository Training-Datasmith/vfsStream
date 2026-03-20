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
use InvalidArgumentException;
/**
 * Abstract base class providing an implementation for the visit() method.
 *
 * @see    https://github.com/mikey179/vfsStream/issues/10
 *
 * @since  0.10.0
 */
abstract class Vfs_Stream_Abstract_Visitor implements Vfs_Stream_Visitor
{
    /**
     * visit a content and process it
     *
     * @throws InvalidArgumentException
     */
    public function visit(Vfs_Stream_Content $content): Vfs_Stream_Visitor
    {
        if ($content instanceof Vfs_Stream_Block) {
            $this->visit_block_device($content);
        } elseif ($content instanceof Vfs_Stream_File) {
            $this->visit_file($content);
        } elseif ($content instanceof Vfs_Stream_Directory) {
            if (!$content->is_dot()) {
                $this->visit_directory($content);
            }
        } else {
            throw new InvalidArgumentException('Unknown content type ' . $content->get_type() . ' for ' . $content->get_name());
        }
        return $this;
    }
    /**
     * visit a block device and process it
     */
    public function visit_block_device(Vfs_Stream_Block $block): Vfs_Stream_Visitor
    {
        return $this->visit_file($block);
    }
}
class_alias('bovigo\vfs\visitor\vfsStreamAbstractVisitor', 'org\bovigo\vfs\visitor\vfsStreamAbstractVisitor');