<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use function array_merge;
use function array_pop;
use function array_values;
use function class_alias;
use function clearstatcache;
use function count;
use const E_USER_WARNING;
use function explode;
use function implode;
use function in_array;
use const LOCK_NB;
use const LOCK_UN;
use function spl_object_id;
use function str_replace;
use function stream_get_wrappers;
use const STREAM_META_ACCESS;
use const STREAM_META_GROUP;
use const STREAM_META_GROUP_NAME;
use const STREAM_META_OWNER;
use const STREAM_META_OWNER_NAME;
use const STREAM_META_TOUCH;
use const STREAM_OPTION_BLOCKING;
use const STREAM_OPTION_READ_TIMEOUT;
use const STREAM_OPTION_WRITE_BUFFER;
use const STREAM_REPORT_ERRORS;
use const STREAM_URL_STAT_QUIET;
use function stream_wrapper_register;
use function stream_wrapper_unregister;
use function strlen;
use function strpos;
use function strrpos;
use function strstr;
use function substr;
use function time;
use function trigger_error;
/**
 * Stream wrapper to mock file system requests.
 */
class Vfs_Stream_Wrapper
{
    /**
     * open file for reading
     */
    public const READ = 'r';
    /**
     * truncate file
     */
    public const TRUNCATE = 'w';
    /**
     * set file pointer to end, append new data
     */
    public const APPEND = 'a';
    /**
     * set file pointer to start, overwrite existing data
     */
    public const WRITE = 'x';
    /**
     * set file pointer to start, overwrite existing data; or create file if
     * does not exist
     */
    public const WRITE_NEW = 'c';
    /**
     * file mode: read only
     */
    public const READONLY = 0;
    /**
     * file mode: write only
     */
    public const WRITEONLY = 1;
    /**
     * file mode: read and write
     */
    public const ALL = 2;
    /**
     * The current context or null if none passed.
     *
     * @var resource|null
     */
    public $context;
    /**
     * switch whether class has already been registered as stream wrapper or not
     *
     * @var  bool
     */
    protected static $registered = false;
    /**
     * root content
     *
     * @var  vfsStreamDirectory|null
     */
    protected static $root;
    /**
     * disk space quota
     *
     * @var  Quota
     */
    private static $quota;
    /**
     * file mode: read only, write only, all
     *
     * @var  int
     */
    protected $mode;
    /**
     * shortcut to file container
     *
     * @var  OpenedFile|null
     */
    protected $content;
    /**
     * shortcut to directory container
     *
     * @var  vfsStreamDirectory|null
     */
    protected $dir;
    /**
     * shortcut to directory container iterator
     *
     * @var  vfsStreamContainerIterator|null
     */
    protected $dir_iterator;
    /**
     * method to register the stream wrapper
     *
     * Please be aware that a call to this method will reset the root element
     * to null.
     * If the stream is already registered the method returns silently. If there
     * is already another stream wrapper registered for the scheme used by
     * vfsStream a vfsStreamException will be thrown.
     *
     * @throws  vfsStreamException
     */
    public static function register(): void
    {
        self::$root = null;
        self::$quota = Quota::unlimited();
        if (self::$registered === true) {
            return;
        }
        if (@stream_wrapper_register(Vfs_Stream::SCHEME, self::class) === false) {
            throw new Vfs_Stream_Exception('A handler has already been registered for the ' . Vfs_Stream::SCHEME . ' protocol.');
        }
        self::$registered = true;
    }
    /**
     * Unregisters a previously registered URL wrapper for the vfs scheme.
     *
     * If this stream wrapper wasn't registered, the method returns silently.
     *
     * If unregistering fails, or if the URL wrapper for vfs:// was not
     * registered with this class, a vfsStreamException will be thrown.
     *
     * @throws vfsStreamException
     *
     * @since  1.6.0
     */
    public static function unregister(): void
    {
        if (!self::$registered) {
            if (in_array(Vfs_Stream::SCHEME, stream_get_wrappers())) {
                throw new Vfs_Stream_Exception('The URL wrapper for the protocol ' . Vfs_Stream::SCHEME . ' was not registered with this version of vfsStream.');
            }
            return;
        }
        if (!@stream_wrapper_unregister(Vfs_Stream::SCHEME)) {
            throw new Vfs_Stream_Exception('Failed to unregister the URL wrapper for the ' . Vfs_Stream::SCHEME . ' protocol.');
        }
        self::$registered = false;
    }
    /**
     * sets the root content
     */
    public static function set_root(Vfs_Stream_Directory $root): Vfs_Stream_Directory
    {
        self::$root = $root;
        clearstatcache();
        return self::$root;
    }
    /**
     * returns the root content
     */
    public static function get_root(): ?Vfs_Stream_Directory
    {
        return self::$root;
    }
    /**
     * sets quota for disk space
     *
     * @since  1.1.0
     */
    public static function set_quota(Quota $quota): void
    {
        self::$quota = $quota;
    }
    /**
     * returns content for given path
     */
    protected function get_content(string $path): ?Vfs_Stream_Content
    {
        if (self::$root === null) {
            return null;
        }
        if (self::$root->get_name() === $path) {
            return self::$root;
        }
        if ($this->is_in_root($path) && self::$root->has_child($path) === true) {
            return self::$root->get_child($path);
        }
        return null;
    }
    /**
     * helper method to detect whether given path is in root path
     */
    private function is_in_root(string $path): bool
    {
        return substr($path, 0, strlen(self::$root->get_name())) === self::$root->get_name();
    }
    /**
     * returns content for given path but only when it is of given type
     */
    protected function get_content_of_type(string $path, int $type): ?Vfs_Stream_Content
    {
        $content = $this->get_content($path);
        if ($content !== null && $content->get_type() === $type) {
            return $content;
        }
        return null;
    }
    /**
     * splits path into its dirname and the basename
     *
     * @return  string[]
     */
    protected function split_path(string $path): array
    {
        $last_slash_pos = strrpos($path, '/');
        if ($last_slash_pos === false) {
            return ['dirname' => '', 'basename' => $path];
        }
        return ['dirname' => substr($path, 0, $last_slash_pos), 'basename' => substr($path, $last_slash_pos + 1)];
    }
    /**
     * helper method to resolve a path from /foo/bar/. to /foo/bar
     */
    protected function resolve_path(string $path): string
    {
        $new_path = [];
        foreach (explode('/', $path) as $path_part) {
            if ($path_part === '.') {
                continue;
            }
            if ($path_part !== '..') {
                $new_path[] = $path_part;
            } elseif (count($new_path) > 1) {
                array_pop($new_path);
            }
        }
        return implode('/', $new_path);
    }
    /**
     * open the stream
     *
     * @param string      $path        the path to open
     * @param string      $mode        mode for opening
     * @param int         $options     options for opening
     * @param string|null $opened_path full path that was actually opened
     */
    public function stream_open(string $path, string $mode, int $options, ?string $opened_path = null): bool
    {
        $extended = strstr($mode, '+') !== false ? true : false;
        $mode = str_replace(['t', 'b', '+'], '', $mode);
        if (in_array($mode, ['r', 'w', 'a', 'x', 'c']) === false) {
            if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                trigger_error('Illegal mode ' . $mode . ', use r, w, a, x  or c, flavoured with t, b and/or +', E_USER_WARNING);
            }
            return false;
        }
        $this->mode = $this->calculate_mode($mode, $extended);
        $path = $this->resolve_path(Vfs_Stream::path($path));
        $this->content = null;
        /** @var vfsStreamFile|null $content */
        $content = $this->get_content_of_type($path, Vfs_Stream_Content::TYPE_FILE);
        if ($content !== null) {
            $this->content = new Opened_File($content);
            if ($mode === self::WRITE) {
                if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                    trigger_error('File ' . $path . ' already exists, can not open with mode x', E_USER_WARNING);
                }
                return false;
            }
            if (($mode === self::TRUNCATE || $mode === self::APPEND) && $this->content->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
                return false;
            }
            if ($mode === self::TRUNCATE) {
                $this->content->open_with_truncate();
            } elseif ($mode === self::APPEND) {
                $this->content->open_for_append();
            } else {
                if (!$this->content->is_readable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group())) {
                    if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                        trigger_error('Permission denied', E_USER_WARNING);
                    }
                    return false;
                }
                $this->content->open();
            }
            return true;
        }
        $content = $this->create_file($path, $mode, $options);
        if ($content === false) {
            return false;
        }
        $this->content = new Opened_File($content);
        return true;
    }
    /**
     * creates a file at given path
     *
     * @param string      $path    the path to open
     * @param string|null $mode    mode for opening
     * @param int|null    $options options for opening
     *
     * @return  vfsStreamFile|false
     */
    private function create_file(string $path, ?string $mode = null, ?int $options = null)
    {
        $names = $this->split_path($path);
        if (empty($names['dirname']) === true) {
            if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                trigger_error('File ' . $names['basename'] . ' does not exist', E_USER_WARNING);
            }
            return false;
        }
        /** @var vfsStreamDirectory|null $dir */
        $dir = $this->get_content_of_type($names['dirname'], Vfs_Stream_Content::TYPE_DIR);
        if ($dir === null) {
            if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                trigger_error('Directory ' . $names['dirname'] . ' does not exist', E_USER_WARNING);
            }
            return false;
        }
        if ($dir->has_child($names['basename']) === true) {
            if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                trigger_error('Directory ' . $names['dirname'] . ' already contains a director named ' . $names['basename'], E_USER_WARNING);
            }
            return false;
        }
        if ($mode === self::READ) {
            if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                trigger_error('Can not open non-existing file ' . $path . ' for reading', E_USER_WARNING);
            }
            return false;
        }
        if ($dir->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
            if (($options & STREAM_REPORT_ERRORS) === STREAM_REPORT_ERRORS) {
                trigger_error('Can not create new file in non-writable path ' . $names['dirname'], E_USER_WARNING);
            }
            return false;
        }
        /** @var vfsStreamFile $file */
        $file = Vfs_Stream::new_file($names['basename'])->at($dir);
        return $file;
    }
    /**
     * calculates the file mode
     *
     * @param string $mode     opening mode: r, w, a or x
     * @param bool   $extended true if + was set with opening mode
     */
    protected function calculate_mode(string $mode, bool $extended): int
    {
        if ($extended === true) {
            return self::ALL;
        }
        if ($mode === self::READ) {
            return self::READONLY;
        }
        return self::WRITEONLY;
    }
    /**
     * closes the stream
     *
     * @see     https://github.com/mikey179/vfsStream/issues/40
     */
    public function stream_close(): void
    {
        $this->content->lock($this, LOCK_UN);
    }
    /**
     * read the stream up to $count bytes
     *
     * @param int $count amount of bytes to read
     */
    public function stream_read(int $count): string
    {
        if ($this->mode === self::WRITEONLY) {
            return '';
        }
        if ($this->content->is_readable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
            return '';
        }
        return $this->content->read($count);
    }
    /**
     * writes data into the stream
     *
     * @return  int     amount of bytes written
     */
    public function stream_write(string $data): int
    {
        if ($this->mode === self::READONLY) {
            return 0;
        }
        if ($this->content->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
            return 0;
        }
        if (self::$quota->is_limited()) {
            $data = substr($data, 0, self::$quota->space_left(self::$root->size_summarized()));
        }
        return $this->content->write($data);
    }
    /**
     * truncates a file to a given length
     *
     * @param int $size length to truncate file to
     *
     * @since   1.1.0
     */
    public function stream_truncate(int $size): bool
    {
        if ($this->mode === self::READONLY) {
            return false;
        }
        if ($this->content->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
            return false;
        }
        if ($this->content->get_type() !== Vfs_Stream_Content::TYPE_FILE) {
            return false;
        }
        if (self::$quota->is_limited() && $this->content->size() < $size) {
            $max_size = self::$quota->space_left(self::$root->size_summarized());
            if ($max_size === 0) {
                return false;
            }
            if ($size > $max_size) {
                $size = $max_size;
            }
        }
        return $this->content->truncate($size);
    }
    /**
     * sets metadata like owner, user or permissions
     *
     * @param mixed $var
     *
     * @since   1.1.0
     */
    public function stream_metadata(string $path, int $option, $var): bool
    {
        $path = $this->resolve_path(Vfs_Stream::path($path));
        /** @var vfsStreamAbstractContent|null $content */
        $content = $this->get_content($path);
        switch ($option) {
            case STREAM_META_TOUCH:
                if ($content === null) {
                    $content = $this->create_file($path, null, STREAM_REPORT_ERRORS);
                    // file creation may not be allowed at provided path
                    if ($content === false) {
                        return false;
                    }
                }
                $current_time = time();
                $content->last_modified($var[0] ?? $current_time);
                $content->last_accessed($var[1] ?? $current_time);
                return true;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_GROUP_NAME:
            default:
                return false;
            case STREAM_META_OWNER:
                if ($content === null) {
                    return false;
                }
                return $this->do_perm_change($path, $content, static function () use ($content, $var): void {
                    $content->chown($var);
                });
            case STREAM_META_GROUP:
                if ($content === null) {
                    return false;
                }
                return $this->do_perm_change($path, $content, static function () use ($content, $var): void {
                    $content->chgrp($var);
                });
            case STREAM_META_ACCESS:
                if ($content === null) {
                    return false;
                }
                return $this->do_perm_change($path, $content, static function () use ($content, $var): void {
                    $content->chmod($var);
                });
        }
    }
    /**
     * executes given permission change when necessary rights allow such a change
     */
    private function do_perm_change(string $path, Vfs_Stream_Abstract_Content $content, callable $change): bool
    {
        if (!$content->is_owned_by_user(Vfs_Stream::get_current_user())) {
            return false;
        }
        if (self::$root->get_name() !== $path) {
            $names = $this->split_path($path);
            $parent = $this->get_content($names['dirname']);
            if (!$parent->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group())) {
                return false;
            }
        }
        $change();
        return true;
    }
    /**
     * checks whether stream is at end of file
     */
    public function stream_eof(): bool
    {
        return $this->content->eof();
    }
    /**
     * returns the current position of the stream
     */
    public function stream_tell(): int
    {
        return $this->content->get_bytes_read();
    }
    /**
     * seeks to the given offset
     */
    public function stream_seek(int $offset, int $whence): bool
    {
        return $this->content->seek($offset, $whence);
    }
    /**
     * flushes unstored data into storage
     */
    public function stream_flush(): bool
    {
        return true;
    }
    /**
     * returns status of stream
     *
     * @return int[]|false
     */
    public function stream_stat()
    {
        $atime = $this->content->fileatime();
        $ctime = $this->content->filectime();
        $mtime = $this->content->filemtime();
        $size = $this->content->size();
        if ($atime === -1 || $ctime === -1 || $mtime === -1 || $size === -1) {
            return false;
        }
        $file_stat = ['dev' => 0, 'ino' => spl_object_id($this->content->get_base_file()), 'mode' => $this->content->get_type() | $this->content->get_permissions(), 'nlink' => 0, 'uid' => $this->content->get_user(), 'gid' => $this->content->get_group(), 'rdev' => 0, 'size' => $size, 'atime' => $atime, 'mtime' => $mtime, 'ctime' => $ctime, 'blksize' => -1, 'blocks' => -1];
        return array_merge(array_values($file_stat), $file_stat);
    }
    /**
     * retrieve the underlaying resource
     *
     * Please note that this method always returns false as there is no
     * underlaying resource to return.
     *
     * @see     https://github.com/mikey179/vfsStream/issues/3
     *
     * @since   0.9.0
     */
    public function stream_cast(int $cast_as): bool
    {
        return false;
    }
    /**
     * set lock status for stream
     *
     * @see     https://github.com/mikey179/vfsStream/issues/6
     * @see     https://github.com/mikey179/vfsStream/issues/31
     * @see     https://github.com/mikey179/vfsStream/issues/40
     *
     * @since   0.10.0
     */
    public function stream_lock(int $operation): bool
    {
        if ((LOCK_NB & $operation) === LOCK_NB) {
            $operation -= LOCK_NB;
        }
        return $this->content->lock($this, $operation);
    }
    /**
     * sets options on the stream
     *
     * @see     https://github.com/mikey179/vfsStream/issues/15
     * @see     http://www.php.net/manual/streamwrapper.stream-set-option.php
     */
    //phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
    public function stream_set_option(int $option, $arg1, $arg2): bool
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
            // break omitted
            case STREAM_OPTION_READ_TIMEOUT:
            // break omitted
            case STREAM_OPTION_WRITE_BUFFER:
            // break omitted
            default:
        }
        return false;
    }
    /**
     * remove the data under the given path
     */
    public function unlink(string $path): bool
    {
        $real_path = $this->resolve_path(Vfs_Stream::path($path));
        $content = $this->get_content($real_path);
        if ($content === null) {
            trigger_error('unlink(' . $path . '): No such file or directory', E_USER_WARNING);
            return false;
        }
        if ($content->get_type() !== Vfs_Stream_Content::TYPE_FILE) {
            trigger_error('unlink(' . $path . '): Operation not permitted', E_USER_WARNING);
            return false;
        }
        return $this->do_unlink($real_path);
    }
    /**
     * removes a path
     */
    protected function do_unlink(string $path): bool
    {
        if (self::$root->get_name() === $path) {
            // delete root? very brave. :)
            self::$root = null;
            clearstatcache();
            return true;
        }
        $names = $this->split_path($path);
        /** @var vfsStreamDirectory $content */
        $content = $this->get_content($names['dirname']);
        if (!$content->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group())) {
            return false;
        }
        clearstatcache();
        return $content->remove_child($names['basename']);
    }
    /**
     * rename from one path to another
     *
     * @author  Benoit Aubuchon
     */
    public function rename(string $path_from, string $path_to): bool
    {
        $src_real_path = $this->resolve_path(Vfs_Stream::path($path_from));
        $dst_real_path = $this->resolve_path(Vfs_Stream::path($path_to));
        $src_content = $this->get_content($src_real_path);
        if ($src_content === null) {
            trigger_error('No such file or directory', E_USER_WARNING);
            return false;
        }
        $dst_names = $this->split_path($dst_real_path);
        /** @var vfsStreamDirectory|null $dstParentContent */
        $dst_parent_content = $this->get_content($dst_names['dirname']);
        if ($dst_parent_content === null) {
            trigger_error('No such file or directory', E_USER_WARNING);
            return false;
        }
        if (!$dst_parent_content->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group())) {
            trigger_error('Permission denied', E_USER_WARNING);
            return false;
        }
        if ($dst_parent_content->get_type() !== Vfs_Stream_Content::TYPE_DIR) {
            trigger_error('Target is not a directory', E_USER_WARNING);
            return false;
        }
        // remove old source first, so we can rename later
        // (renaming first would lead to not being able to remove the old path)
        if (!$this->do_unlink($src_real_path)) {
            return false;
        }
        $dst_content = $src_content;
        // Renaming the filename
        $dst_content->rename($dst_names['basename']);
        // Copying to the destination
        $dst_parent_content->add_child($dst_content);
        return true;
    }
    /**
     * creates a new directory
     */
    public function mkdir(string $path, int $mode, int $options): bool
    {
        $umask = Vfs_Stream::umask();
        if (0 < $umask) {
            $permissions = $mode & ~$umask;
        } else {
            $permissions = $mode;
        }
        $path = $this->resolve_path(Vfs_Stream::path($path));
        if ($this->get_content($path) !== null) {
            trigger_error('mkdir(): Path vfs://' . $path . ' exists', E_USER_WARNING);
            return false;
        }
        if (self::$root === null) {
            self::$root = Vfs_Stream::new_directory($path, $permissions);
            return true;
        }
        $max_depth = count(explode('/', $path));
        $names = $this->split_path($path);
        $new_dirs = $names['basename'];
        $dir = null;
        $i = 0;
        while ($dir === null && $i < $max_depth) {
            $dir = $this->get_content($names['dirname']);
            $names = $this->split_path($names['dirname']);
            if ($dir === null) {
                $new_dirs = $names['basename'] . '/' . $new_dirs;
            }
            $i++;
        }
        if ($dir === null || $dir->get_type() !== Vfs_Stream_Content::TYPE_DIR || $dir->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
            return false;
        }
        $recursive = (STREAM_MKDIR_RECURSIVE & $options) !== 0 ? true : false;
        if (strpos($new_dirs, '/') !== false && $recursive === false) {
            return false;
        }
        Vfs_Stream::new_directory($new_dirs, $permissions)->at($dir);
        return true;
    }
    /**
     * removes a directory
     *
     * @todo    consider $options with STREAM_MKDIR_RECURSIVE
     */
    public function rmdir(string $path, int $options): bool
    {
        $path = $this->resolve_path(Vfs_Stream::path($path));
        /** @var vfsStreamDirectory|null $child */
        $child = $this->get_content_of_type($path, Vfs_Stream_Content::TYPE_DIR);
        if ($child === null) {
            return false;
        }
        // can only remove empty directories
        if (count($child->get_children()) > 0) {
            return false;
        }
        if (self::$root->get_name() === $path) {
            // delete root? very brave. :)
            self::$root = null;
            clearstatcache();
            return true;
        }
        $names = $this->split_path($path);
        /** @var vfsStreamDirectory $dir */
        $dir = $this->get_content_of_type($names['dirname'], Vfs_Stream_Content::TYPE_DIR);
        if ($dir->is_writable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group()) === false) {
            return false;
        }
        clearstatcache();
        return $dir->remove_child($child->get_name());
    }
    /**
     * opens a directory
     */
    public function dir_opendir(string $path, int $options): bool
    {
        $path = $this->resolve_path(Vfs_Stream::path($path));
        $this->dir = null;
        /** @var vfsStreamDirectory|null $dir */
        $dir = $this->get_content_of_type($path, Vfs_Stream_Content::TYPE_DIR);
        if ($dir === null) {
            return false;
        }
        $this->dir = $dir;
        if (!$this->dir->is_readable(Vfs_Stream::get_current_user(), Vfs_Stream::get_current_group())) {
            return false;
        }
        $this->dir_iterator = $this->dir->getIterator();
        return true;
    }
    /**
     * reads directory contents
     *
     * @return  string|bool
     */
    public function dir_readdir()
    {
        $dir = $this->dir_iterator->current();
        if ($dir === null) {
            return false;
        }
        $this->dir_iterator->next();
        return $dir->get_name();
    }
    /**
     * reset directory iteration
     */
    public function dir_rewinddir(): bool
    {
        $this->dir_iterator->rewind();
        return true;
    }
    /**
     * closes directory
     */
    public function dir_closedir(): bool
    {
        $this->dir_iterator = null;
        return true;
    }
    /**
     * returns status of url
     *
     * @param string $path  path of url to return status for
     * @param int    $flags flags set by the stream API
     *
     * @return  mixed[]|bool
     */
    public function url_stat(string $path, int $flags)
    {
        /** @var vfsStreamAbstractContent|null $content */
        $content = $this->get_content($this->resolve_path(Vfs_Stream::path($path)));
        if ($content === null) {
            if (($flags & STREAM_URL_STAT_QUIET) !== STREAM_URL_STAT_QUIET) {
                trigger_error(' No such file or directory: ' . $path, E_USER_WARNING);
            }
            return false;
        }
        $atime = $content->fileatime();
        $ctime = $content->filectime();
        $mtime = $content->filemtime();
        $size = $content->size();
        if ($atime === -1 || $ctime === -1 || $mtime === -1 || $size === -1) {
            return false;
        }
        $file_stat = ['dev' => 0, 'ino' => spl_object_id($content), 'mode' => $content->get_type() | $content->get_permissions(), 'nlink' => 0, 'uid' => $content->get_user(), 'gid' => $content->get_group(), 'rdev' => 0, 'size' => $size, 'atime' => $atime, 'mtime' => $mtime, 'ctime' => $ctime, 'blksize' => -1, 'blocks' => -1];
        return array_merge(array_values($file_stat), $file_stat);
    }
}
class_alias('bovigo\vfs\vfsStreamWrapper', 'org\bovigo\vfs\vfsStreamWrapper');