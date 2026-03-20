<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use function class_alias;
use IteratorAggregate;
/**
 * Interface for stream contents that are able to store other stream contents.
 */
interface Vfs_Stream_Container extends IteratorAggregate
{
    /**
     * adds child to the directory
     */
    public function add_child(Vfs_Stream_Content $child): void;
    /**
     * removes child from the directory
     */
    public function remove_child(string $name): bool;
    /**
     * checks whether the container contains a child with the given name
     */
    public function has_child(string $name): bool;
    /**
     * returns the child with the given name
     */
    public function get_child(string $name): ?Vfs_Stream_Content;
    /**
     * checks whether directory contains any children
     *
     * @since   0.10.0
     */
    public function has_children(): bool;
    /**
     * returns a list of children for this directory
     *
     * @return  vfsStreamContent[]
     */
    public function get_children(): array;
}
class_alias('bovigo\vfs\vfsStreamContainer', 'org\bovigo\vfs\vfsStreamContainer');