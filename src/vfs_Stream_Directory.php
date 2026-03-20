<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use function array_values;
use function class_alias;
use function count;
use Iterator;
use function strlen;
use function substr;
use function time;
/**
 * Directory container.
 *
 * @api
 */
class Vfs_Stream_Directory extends Vfs_Stream_Abstract_Content implements Vfs_Stream_Container
{
    /**
     * list of directory children
     *
     * @var  vfsStreamContent[]
     */
    protected $children = [];
    /**
     * constructor
     *
     * @param   int|null $permissions optional
     *
     * @throws  vfsStreamException
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        $this->type = Vfs_Stream_Content::TYPE_DIR;
        parent::__construct($name, $permissions);
    }
    /**
     * returns default permissions for concrete implementation
     *
     * @since   0.8.0
     */
    protected function get_default_permissions(): int
    {
        return 0777;
    }
    /**
     * returns size of directory
     *
     * The size of a directory is always 0 bytes. To calculate the summarized
     * size of all children in the directory use sizeSummarized().
     */
    public function size(): int
    {
        return 0;
    }
    /**
     * returns summarized size of directory and its children
     */
    public function size_summarized(): int
    {
        $size = 0;
        foreach ($this->children as $child) {
            if ($child instanceof self) {
                $size += $child->size_summarized();
            } else {
                $size += $child->size();
            }
        }
        return $size;
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
        parent::set_parent_path($parent_path);
        foreach ($this->children as $child) {
            $child->set_parent_path($this->path());
        }
    }
    /**
     * adds child to the directory
     */
    public function add_child(Vfs_Stream_Content $child): void
    {
        $child->set_parent_path($this->path());
        $this->children[$child->get_name()] = $child;
        $this->update_modifications();
    }
    /**
     * removes child from the directory
     */
    public function remove_child(string $name): bool
    {
        foreach ($this->children as $key => $child) {
            if ($child->applies_to($name)) {
                $child->remove_parent_path();
                unset($this->children[$key]);
                $this->update_modifications();
                return true;
            }
        }
        return false;
    }
    /**
     * updates internal timestamps
     */
    protected function update_modifications(): void
    {
        $time = time();
        $this->last_attribute_modified = $time;
        $this->last_modified = $time;
    }
    /**
     * checks whether the container contains a child with the given name
     */
    public function has_child(string $name): bool
    {
        return $this->get_child($name) !== null;
    }
    /**
     * returns the child with the given name
     */
    public function get_child(string $name): ?Vfs_Stream_Content
    {
        $child_name = $this->get_real_child_name($name);
        foreach ($this->children as $child) {
            if ($child->get_name() === $child_name) {
                return $child;
            }
            if (!$child instanceof Vfs_Stream_Container) {
                continue;
            }
            if ($child->applies_to($child_name) && $child->has_child($child_name)) {
                return $child->get_child($child_name);
            }
        }
        return null;
    }
    /**
     * helper method to detect the real child name
     */
    protected function get_real_child_name(string $name): string
    {
        if ($this->applies_to($name) === true) {
            return self::get_child_name($name, $this->name);
        }
        return $name;
    }
    /**
     * helper method to calculate the child name
     */
    protected static function get_child_name(string $name, string $own_name): string
    {
        if ($name === $own_name) {
            return $name;
        }
        return substr($name, strlen($own_name) + 1);
    }
    /**
     * checks whether directory contains any children
     *
     * @since   0.10.0
     */
    public function has_children(): bool
    {
        return count($this->children) > 0;
    }
    /**
     * returns a list of children for this directory
     *
     * @return  vfsStreamContent[]
     */
    public function get_children(): array
    {
        return array_values($this->children);
    }
    /**
     * returns iterator for the children
     *
     * @return  vfsStreamContainerIterator
     */
    public function getIterator(): Iterator
    {
        return new Vfs_Stream_Container_Iterator($this->children);
    }
    /**
     * checks whether dir is a dot dir
     */
    public function is_dot(): bool
    {
        return $this->name === '.' || $this->name === '..';
    }
}
class_alias('bovigo\vfs\vfsStreamDirectory', 'org\bovigo\vfs\vfsStreamDirectory');