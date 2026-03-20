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
 * Interface for stream contents.
 */
interface Vfs_Stream_Content
{
    /**
     * stream content type: file
     *
     * @see  getType()
     */
    public const TYPE_FILE = 0100000;
    /**
     * stream content type: directory
     *
     * @see  getType()
     */
    public const TYPE_DIR = 040000;
    /**
     * stream content type: symbolic link
     *
     * @see  getType();
     */
    // const TYPE_LINK = 0120000;
    /**
     * stream content type: block
     *
     * @see getType()
     */
    public const TYPE_BLOCK = 060000;
    /**
     * returns the file name of the content
     */
    public function get_name(): string;
    /**
     * renames the content
     */
    public function rename(string $new_name): void;
    /**
     * checks whether the container can be applied to given name
     */
    public function applies_to(string $name): bool;
    /**
     * returns the type of the container
     */
    public function get_type(): int;
    /**
     * returns size of content
     */
    public function size(): int;
    /**
     * sets the last modification time of the stream content
     */
    public function last_modified(int $filemtime): self;
    /**
     * returns the last modification time of the stream content
     */
    public function filemtime(): int;
    /**
     * adds content to given container
     */
    public function at(Vfs_Stream_Container $container): self;
    /**
     * change file mode to given permissions
     */
    public function chmod(int $permissions): self;
    /**
     * returns permissions
     */
    public function get_permissions(): int;
    /**
     * checks whether content is readable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_readable(int $user, int $group): bool;
    /**
     * checks whether content is writable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_writable(int $user, int $group): bool;
    /**
     * checks whether content is executable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_executable(int $user, int $group): bool;
    /**
     * change owner of file to given user
     */
    public function chown(int $user): self;
    /**
     * checks whether file is owned by given user
     */
    public function is_owned_by_user(int $user): bool;
    /**
     * returns owner of file
     */
    public function get_user(): int;
    /**
     * change owner group of file to given group
     */
    public function chgrp(int $group): self;
    /**
     * checks whether file is owned by group
     */
    public function is_owned_by_group(int $group): bool;
    /**
     * returns owner group of file
     */
    public function get_group(): int;
    /**
     * sets parent path
     *
     * @internal  only to be set by parent
     *
     * @since   1.2.0
     */
    public function set_parent_path(string $parent_path): void;
    /**
     * removes parent path
     *
     * @internal  only to be set by parent
     *
     * @since   2.0.0
     */
    public function remove_parent_path(): void;
    /**
     * returns path to this content
     *
     * @since   1.2.0
     */
    public function path(): string;
    /**
     * returns complete vfsStream url for this content
     *
     * @since   1.2.0
     */
    public function url(): string;
}
class_alias('bovigo\vfs\vfsStreamContent', 'org\bovigo\vfs\vfsStreamContent');