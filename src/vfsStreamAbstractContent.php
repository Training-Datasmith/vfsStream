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
use function clearstatcache;
use function strlen;
use function strncmp;
use function strstr;
use function time;
/**
 * Base stream contents container.
 */
abstract class Vfs_Stream_Abstract_Content implements Vfs_Stream_Content
{
    /**
     * name of the container
     *
     * @var  string
     */
    protected $name;
    /**
     * type of the container
     *
     * @var  int
     */
    protected $type;
    /**
     * timestamp of last access
     *
     * @var  int
     */
    protected $last_accessed;
    /**
     * timestamp of last attribute modification
     *
     * @var  int
     */
    protected $last_attribute_modified;
    /**
     * timestamp of last modification
     *
     * @var  int
     */
    protected $last_modified;
    /**
     * permissions for content
     *
     * @var  int
     */
    protected $permissions;
    /**
     * owner of the file
     *
     * @var  int
     */
    protected $user;
    /**
     * owner group of the file
     *
     * @var  int
     */
    protected $group;
    /**
     * path to to this content
     *
     * @var  string|null
     */
    private $parent_path;
    /**
     * constructor
     *
     * @param  int|null $permissions optional
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        if (strstr($name, '/') !== false) {
            throw new Vfs_Stream_Exception('Name can not contain /.');
        }
        $this->name = $name;
        $time = time();
        if ($permissions === null) {
            $permissions = $this->get_default_permissions() & ~Vfs_Stream::umask();
        }
        $this->last_accessed = $time;
        $this->last_attribute_modified = $time;
        $this->last_modified = $time;
        $this->permissions = $permissions;
        $this->user = Vfs_Stream::get_current_user();
        $this->group = Vfs_Stream::get_current_group();
    }
    /**
     * returns default permissions for concrete implementation
     *
     * @since   0.8.0
     */
    abstract protected function get_default_permissions(): int;
    /**
     * returns the file name of the content
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * renames the content
     */
    public function rename(string $new_name): void
    {
        if (strstr($new_name, '/') !== false) {
            throw new Vfs_Stream_Exception('Name can not contain /.');
        }
        $this->name = $new_name;
    }
    /**
     * checks whether the container can be applied to given name
     */
    public function applies_to(string $name): bool
    {
        if ($name === $this->name) {
            return true;
        }
        $segment_name = $this->name . '/';
        return strncmp($segment_name, $name, strlen($segment_name)) === 0;
    }
    /**
     * returns the type of the container
     */
    public function get_type(): int
    {
        return $this->type;
    }
    /**
     * sets the last modification time of the stream content
     */
    public function last_modified(int $filemtime): Vfs_Stream_Content
    {
        $this->last_modified = $filemtime;
        return $this;
    }
    /**
     * returns the last modification time of the stream content
     */
    public function filemtime(): int
    {
        return $this->last_modified;
    }
    /**
     * sets last access time of the stream content
     *
     * @since   0.9
     */
    public function last_accessed(int $fileatime): Vfs_Stream_Content
    {
        $this->last_accessed = $fileatime;
        return $this;
    }
    /**
     * returns the last access time of the stream content
     *
     * @since   0.9
     */
    public function fileatime(): int
    {
        return $this->last_accessed;
    }
    /**
     * sets the last attribute modification time of the stream content
     *
     * @since   0.9
     */
    public function last_attribute_modified(int $filectime): Vfs_Stream_Content
    {
        $this->last_attribute_modified = $filectime;
        return $this;
    }
    /**
     * returns the last attribute modification time of the stream content
     *
     * @since   0.9
     */
    public function filectime(): int
    {
        return $this->last_attribute_modified;
    }
    /**
     * adds content to given container
     */
    public function at(Vfs_Stream_Container $container): Vfs_Stream_Content
    {
        $container->add_child($this);
        return $this;
    }
    /**
     * change file mode to given permissions
     */
    public function chmod(int $permissions): Vfs_Stream_Content
    {
        $this->permissions = $permissions;
        $this->last_attribute_modified = time();
        clearstatcache();
        return $this;
    }
    /**
     * returns permissions
     */
    public function get_permissions(): int
    {
        return $this->permissions;
    }
    /**
     * checks whether content is readable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_readable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0400;
        } elseif ($this->group === $group) {
            $check = 040;
        } else {
            $check = 04;
        }
        return (bool) ($this->permissions & $check);
    }
    /**
     * checks whether content is writable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_writable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0200;
        } elseif ($this->group === $group) {
            $check = 020;
        } else {
            $check = 02;
        }
        return (bool) ($this->permissions & $check);
    }
    /**
     * checks whether content is executable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_executable(int $user, int $group): bool
    {
        if ($this->user === $user) {
            $check = 0100;
        } elseif ($this->group === $group) {
            $check = 010;
        } else {
            $check = 01;
        }
        return (bool) ($this->permissions & $check);
    }
    /**
     * change owner of file to given user
     */
    public function chown(int $user): Vfs_Stream_Content
    {
        $this->user = $user;
        $this->last_attribute_modified = time();
        return $this;
    }
    /**
     * checks whether file is owned by given user
     */
    public function is_owned_by_user(int $user): bool
    {
        return $this->user === $user;
    }
    /**
     * returns owner of file
     */
    public function get_user(): int
    {
        return $this->user;
    }
    /**
     * change owner group of file to given group
     */
    public function chgrp(int $group): Vfs_Stream_Content
    {
        $this->group = $group;
        $this->last_attribute_modified = time();
        return $this;
    }
    /**
     * checks whether file is owned by group
     */
    public function is_owned_by_group(int $group): bool
    {
        return $this->group === $group;
    }
    /**
     * returns owner group of file
     */
    public function get_group(): int
    {
        return $this->group;
    }
    /**
     * sets parent path
     *
     * @internal  only to be set by parent
     *
     * @since   1.2.0
     */
    public function set_parent_path(string $parent_path): void
    {
        $this->parent_path = $parent_path;
    }
    /**
     * removes parent path
     *
     * @internal  only to be set by parent
     *
     * @since   2.0.0
     */
    public function remove_parent_path(): void
    {
        $this->parent_path = null;
    }
    /**
     * returns path to this content
     *
     * @since   1.2.0
     */
    public function path(): string
    {
        if ($this->parent_path === null) {
            return $this->name;
        }
        return $this->parent_path . '/' . $this->name;
    }
    /**
     * returns complete vfsStream url for this content
     *
     * @since   1.2.0
     */
    public function url(): string
    {
        return Vfs_Stream::url($this->path());
    }
}
class_alias('bovigo\vfs\vfsStreamAbstractContent', 'org\bovigo\vfs\vfsStreamAbstractContent');