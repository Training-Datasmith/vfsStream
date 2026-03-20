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
use Exception;
/**
 * Exception for vfsStream errors.
 *
 * @api
 */
class Vfs_Stream_Exception extends Exception
{
    // intentionally empty
}
class_alias('bovigo\vfs\vfsStreamException', 'org\bovigo\vfs\vfsStreamException');