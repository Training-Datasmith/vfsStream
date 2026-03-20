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
/**
 * Block container.
 *
 * @api
 */
class Vfs_Stream_Block extends Vfs_Stream_File
{
    /**
     * constructor
     *
     * @param  int|null $permissions optional
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        if (empty($name)) {
            throw new Vfs_Stream_Exception('Name of Block device was empty');
        }
        parent::__construct($name, $permissions);
        $this->type = Vfs_Stream_Content::TYPE_BLOCK;
    }
}
class_alias('bovigo\vfs\vfsStreamBlock', 'org\bovigo\vfs\vfsStreamBlock');