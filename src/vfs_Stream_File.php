<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package  bovigo\vfs
 */
namespace bovigo\vfs;

use bovigo\vfs\content\File_Content;
use bovigo\vfs\content\String_Based_File_Content;
use function class_alias;
use InvalidArgumentException;
use function is_resource;
use function is_string;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_SH;
use const SEEK_END;
use const SEEK_SET;
use function spl_object_hash;
use function sprintf;
use function stream_get_meta_data;
use function time;
/**
 * File container.
 *
 * @api
 */
class Vfs_Stream_File extends Vfs_Stream_Abstract_Content
{
    /**
     * content of the file
     *
     * @var  FileContent
     */
    private $content;
    /**
     * Resource id which exclusively locked this file
     *
     * @var  string|null
     */
    protected $exclusive_lock;
    /**
     * Resources ids which currently holds shared lock to this file
     *
     * @var  array<string, bool>
     */
    protected $shared_lock = [];
    /**
     * constructor
     *
     * @param int|null $permissions optional
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        $this->content = new String_Based_File_Content('');
        $this->type = Vfs_Stream_Content::TYPE_FILE;
        parent::__construct($name, $permissions);
    }
    /**
     * returns default permissions for concrete implementation
     *
     * @since   0.8.0
     */
    protected function get_default_permissions(): int
    {
        return 0666;
    }
    /**
     * checks whether the container can be applied to given name
     */
    public function applies_to(string $name): bool
    {
        return $this->name === $name;
    }
    /**
     * alias for withContent()
     *
     * @see     withContent()
     *
     * @param string|FileContent $content
     */
    public function set_content($content): Vfs_Stream_File
    {
        return $this->with_content($content);
    }
    /**
     * sets the contents of the file
     *
     * Setting content with this method does not change the time when the file
     * was last modified.
     *
     * @param string|FileContent $content
     *
     * @throws InvalidArgumentException
     */
    public function with_content($content): Vfs_Stream_File
    {
        if (is_string($content)) {
            $this->content = new String_Based_File_Content($content);
        } elseif ($content instanceof File_Content) {
            $this->content = $content;
        } else {
            throw new InvalidArgumentException(sprintf('Given content must either be a string or an instance of %s', File_Content::class));
        }
        return $this;
    }
    /**
     * returns the contents of the file
     *
     * Getting content does not change the time when the file
     * was last accessed.
     */
    public function get_content(): string
    {
        return $this->content->content();
    }
    /**
     * returns the raw content object.
     *
     * @internal
     */
    public function get_content_object(): File_Content
    {
        return $this->content;
    }
    /**
     * simply open the file
     *
     * @since  0.9
     */
    public function open(): void
    {
        $this->content->seek(0, SEEK_SET);
        $this->last_accessed = time();
    }
    /**
     * open file and set pointer to end of file
     *
     * @since  0.9
     */
    public function open_for_append(): void
    {
        $this->content->seek(0, SEEK_END);
        $this->last_accessed = time();
    }
    /**
     * open file and truncate content
     *
     * @since  0.9
     */
    public function open_with_truncate(): void
    {
        $this->open();
        $this->content->truncate(0);
        $time = time();
        $this->last_accessed = $time;
        $this->last_modified = $time;
    }
    /**
     * reads the given amount of bytes from content
     *
     * Using this method changes the time when the file was last accessed.
     */
    public function read(int $count): string
    {
        $this->last_accessed = time();
        return $this->content->read($count);
    }
    /**
     * returns the content until its end from current offset
     *
     * Using this method changes the time when the file was last accessed.
     *
     * @internal  since 1.3.0
     */
    public function read_until_end(): string
    {
        $this->last_accessed = time();
        return $this->content->read_until_end();
    }
    /**
     * writes an amount of data
     *
     * Using this method changes the time when the file was last modified.
     *
     * @return  int     amount of written bytes
     */
    public function write(string $data): int
    {
        $this->last_modified = time();
        return $this->content->write($data);
    }
    /**
     * Truncates a file to a given length
     *
     * @param int $size length to truncate file to
     *
     * @since   1.1.0
     */
    public function truncate(int $size): bool
    {
        $this->content->truncate($size);
        $this->last_modified = time();
        return true;
    }
    /**
     * checks whether pointer is at end of file
     */
    public function eof(): bool
    {
        return $this->content->eof();
    }
    /**
     * returns the current position within the file
     *
     * @internal  since 1.3.0
     */
    public function get_bytes_read(): int
    {
        return $this->content->bytes_read();
    }
    /**
     * seeks to the given offset
     */
    public function seek(int $offset, int $whence): bool
    {
        return $this->content->seek($offset, $whence);
    }
    /**
     * returns size of content
     */
    public function size(): int
    {
        return $this->content->size();
    }
    /**
     * locks file for
     *
     * @see     https://github.com/mikey179/vfsStream/issues/6
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     *
     * @since   0.10.0
     */
    public function lock($resource, int $operation): bool
    {
        if ((LOCK_NB & $operation) === LOCK_NB) {
            $operation -= LOCK_NB;
        }
        // call to lock file on the same file handler firstly releases the lock
        $this->unlock($resource);
        if ($operation === LOCK_EX) {
            if ($this->is_locked()) {
                return false;
            }
            $this->set_exclusive_lock($resource);
        } elseif ($operation === LOCK_SH) {
            if ($this->has_exclusive_lock()) {
                return false;
            }
            $this->add_shared_lock($resource);
        }
        return true;
    }
    /**
     * Removes lock from file acquired by given resource
     *
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     */
    public function unlock($resource): void
    {
        if ($this->has_exclusive_lock($resource)) {
            $this->exclusive_lock = null;
        }
        if (!$this->has_shared_lock($resource)) {
            return;
        }
        unset($this->shared_lock[$this->get_resource_id($resource)]);
    }
    /**
     * Set exlusive lock on file by given resource
     *
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     */
    protected function set_exclusive_lock($resource): void
    {
        $this->exclusive_lock = $this->get_resource_id($resource);
    }
    /**
     * Add shared lock on file by given resource
     *
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     */
    protected function add_shared_lock($resource): void
    {
        $this->shared_lock[$this->get_resource_id($resource)] = true;
    }
    /**
     * checks whether file is locked
     *
     * @see     https://github.com/mikey179/vfsStream/issues/6
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     *
     * @since   0.10.0
     */
    public function is_locked($resource = null): bool
    {
        if ($this->has_shared_lock($resource)) {
            return true;
        }
        return $this->has_exclusive_lock($resource);
    }
    /**
     * checks whether file is locked in shared mode
     *
     * @see     https://github.com/mikey179/vfsStream/issues/6
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     *
     * @since   0.10.0
     */
    public function has_shared_lock($resource = null): bool
    {
        if ($resource !== null) {
            return isset($this->shared_lock[$this->get_resource_id($resource)]);
        }
        return !empty($this->shared_lock);
    }
    /**
     * Returns unique resource id
     *
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     */
    public function get_resource_id($resource): string
    {
        if (is_resource($resource)) {
            $data = stream_get_meta_data($resource);
            $resource = $data['wrapper_data'];
        }
        return spl_object_hash($resource);
    }
    /**
     * checks whether file is locked in exclusive mode
     *
     * @see     https://github.com/mikey179/vfsStream/issues/6
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @param resource|vfsStreamWrapper $resource
     *
     * @since   0.10.0
     */
    public function has_exclusive_lock($resource = null): bool
    {
        if ($resource !== null) {
            return $this->exclusive_lock === $this->get_resource_id($resource);
        }
        return $this->exclusive_lock !== null;
    }
}
class_alias('bovigo\vfs\vfsStreamFile', 'org\bovigo\vfs\vfsStreamFile');