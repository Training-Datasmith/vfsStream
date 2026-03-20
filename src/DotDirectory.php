<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use ArrayIterator;
use function class_alias;
use Iterator;
/**
 * Directory container.
 */
class Dot_Directory extends Vfs_Stream_Directory
{
    /**
     * returns iterator for the children
     *
     * @return Iterator<mixed[]>
     */
    public function getIterator(): Iterator
    {
        return new ArrayIterator([]);
    }
    /**
     * checks whether dir is a dot dir
     */
    public function is_dot(): bool
    {
        return true;
    }
}
class_alias('bovigo\vfs\DotDirectory', 'org\bovigo\vfs\DotDirectory');