<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use function array_map;
use bovigo\vfs\content\File_Content;
use bovigo\vfs\content\Large_File_Content;
use bovigo\vfs\visitor\Vfs_Stream_Visitor;
use function class_alias;
use Directory_Iterator;
use function explode;
use function file_get_contents;
use function filetype;
use function function_exists;
use function implode;
use InvalidArgumentException;
use function is_array;
use function is_string;
use function octdec;
use function posix_getgid;
use function posix_getuid;
use function preg_match;
use function rawurldecode;
use function sprintf;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function trim;
/**
 * Some utility methods for vfsStream.
 *
 * @api
 */
class Vfs_Stream
{
    /**
     * url scheme
     */
    public const SCHEME = 'vfs';
    /**
     * owner: root
     */
    public const OWNER_ROOT = 0;
    /**
     * owner: user 1
     */
    public const OWNER_USER_1 = 1;
    /**
     * owner: user 2
     */
    public const OWNER_USER_2 = 2;
    /**
     * group: root
     */
    public const GROUP_ROOT = 0;
    /**
     * group: user 1
     */
    public const GROUP_USER_1 = 1;
    /**
     * group: user 2
     */
    public const GROUP_USER_2 = 2;
    /**
     * initial umask setting
     *
     * @var  int
     */
    protected static $umask = 00;
    /**
     * switch whether dotfiles are enabled in directory listings
     *
     * @var  bool
     */
    private static $dot_files = true;
    /**
     * prepends the scheme to the given URL
     *
     * @param string $path path to translate to vfsStream url
     */
    public static function url(string $path): string
    {
        return self::SCHEME . '://' . implode('/', array_map(
            'rawurlencode',
            // ensure single path parts are correctly urlencoded
            explode('/', str_replace('\\', '/', $path))
        ));
    }
    /**
     * restores the path from the url
     *
     * @param string $url vfsStream url to translate into path
     */
    public static function path(string $url): string
    {
        // remove line feeds and trailing whitespaces and path separators
        $path = trim($url, " \t\r\n\x00\v/\\");
        $path = substr($path, strlen(self::SCHEME . '://'));
        $path = str_replace('\\', '/', $path);
        // replace double slashes with single slashes
        $path = str_replace('//', '/', $path);
        return rawurldecode($path);
    }
    /**
     * sets new umask setting and returns previous umask setting
     *
     * If no value is given only the current umask setting is returned.
     *
     * @param int|null $umask new umask setting
     *
     * @since 0.8.0
     */
    public static function umask(?int $umask = null): int
    {
        $old_umask = self::$umask;
        if ($umask !== null) {
            self::$umask = $umask;
        }
        return $old_umask;
    }
    /**
     * helper method for setting up vfsStream in unit tests
     *
     * Instead of
     * vfsStreamWrapper::register();
     * vfsStreamWrapper::setRoot(vfsStream::newDirectory('root'));
     * you can simply do
     * vfsStream::setup()
     * which yields the same result. Additionally, the method returns the
     * freshly created root directory which you can use to make further
     * adjustments to it.
     *
     * Assumed $structure contains an array like this:
     * <code>
     * [
     *     'Core' => [
     *         'AbstractFactory' => [
     *             'test.php'    => 'some text content',
     *             'other.php'   => 'Some more text content',
     *             'Invalid.csv' => 'Something else',
     *         ],
     *         'AnEmptyFolder'   => [],
     *         'badlocation.php' => 'some bad content',
     *     ]
     * ]
     * </code>
     * the resulting directory tree will look like this:
     * <pre>
     * root
     * `- Core
     *  |- badlocation.php
     *  |- AbstractFactory
     *  | |- test.php
     *  | |- other.php
     *  | `- Invalid.csv
     *  `- AnEmptyFolder
     * </pre>
     * Arrays will become directories with their key as directory name, and
     * strings becomes files with their key as file name and their value as file
     * content.
     *
     * @see     https://github.com/mikey179/vfsStream/issues/14
     * @see     https://github.com/mikey179/vfsStream/issues/20
     *
     * @param string                      $rootDirName name of root directory
     * @param int|null                    $permissions file permissions of root directory
     * @param array<string, string|array> $structure   directory structure to add under root directory
     *
     * @since   0.7.0
     */
    public static function setup(string $root_dir_name = 'root', ?int $permissions = null, array $structure = []): Vfs_Stream_Directory
    {
        Vfs_Stream_Wrapper::register();
        return self::create($structure, Vfs_Stream_Wrapper::set_root(self::new_directory($root_dir_name, $permissions)));
    }
    /**
     * creates vfsStream directory structure from an array and adds it to given base dir
     *
     * Assumed $structure contains an array like this:
     * <code>
     * [
     *     'Core' => [
     *         'AbstractFactory' => [
     *             'test.php'    => 'some text content',
     *             'other.php'   => 'Some more text content',
     *             'Invalid.csv' => 'Something else',
     *         ],
     *         'AnEmptyFolder'   => [],
     *         'badlocation.php' => 'some bad content',
     *     ]
     * ]
     * </code>
     * the resulting directory tree will look like this:
     * <pre>
     * baseDir
     * `- Core
     *  |- badlocation.php
     *  |- AbstractFactory
     *  | |- test.php
     *  | |- other.php
     *  | `- Invalid.csv
     *  `- AnEmptyFolder
     * </pre>
     * Arrays will become directories with their key as directory name, and
     * strings becomes files with their key as file name and their value as file
     * content.
     *
     * If no baseDir is given it will try to add the structure to the existing
     * root directory without replacing existing childs except those with equal
     * names.
     *
     * @see     https://github.com/mikey179/vfsStream/issues/14
     * @see     https://github.com/mikey179/vfsStream/issues/20
     *
     * @param array<string, string|array> $structure directory structure to add under root directory
     * @param vfsStreamDirectory|null     $baseDir   base directory to add structure to
     *
     * @throws InvalidArgumentException
     *
     * @since   0.10.0
     */
    public static function create(array $structure, ?Vfs_Stream_Directory $base_dir = null): Vfs_Stream_Directory
    {
        if ($base_dir === null) {
            $base_dir = Vfs_Stream_Wrapper::get_root();
        }
        if ($base_dir === null) {
            throw new InvalidArgumentException('No baseDir given and no root directory set.');
        }
        return self::add_structure($structure, $base_dir);
    }
    /**
     * helper method to create subdirectories recursively
     *
     * @param mixed[]            $structure subdirectory structure to add
     * @param vfsStreamDirectory $baseDir   directory to add the structure to
     */
    protected static function add_structure(array $structure, Vfs_Stream_Directory $base_dir): Vfs_Stream_Directory
    {
        foreach ($structure as $name => $data) {
            $name = (string) $name;
            if (is_array($data) === true) {
                self::add_structure($data, self::new_directory($name)->at($base_dir));
            } elseif (is_string($data) === true) {
                $matches = null;
                preg_match('/^\[(.*)\]$/', $name, $matches);
                if ($matches !== []) {
                    self::new_block($matches[1])->with_content($data)->at($base_dir);
                } else {
                    self::new_file($name)->with_content($data)->at($base_dir);
                }
            } elseif ($data instanceof File_Content) {
                self::new_file($name)->with_content($data)->at($base_dir);
            } elseif ($data instanceof Vfs_Stream_File) {
                $base_dir->add_child($data);
            }
        }
        return $base_dir;
    }
    /**
     * copies the file system structure from given path into the base dir
     *
     * If no baseDir is given it will try to add the structure to the existing
     * root directory without replacing existing childs except those with equal
     * names.
     * File permissions are copied as well.
     * Please note that file contents will only be copied if their file size
     * does not exceed the given $maxFileSize which defaults to 1024 KB. In case
     * the file is larger file content will be mocked, see
     * https://github.com/mikey179/vfsStream/wiki/MockingLargeFiles.
     *
     * @see     https://github.com/mikey179/vfsStream/issues/4
     *
     * @param string                  $path        path to copy the structure from
     * @param vfsStreamDirectory|null $baseDir     directory to add the structure to
     * @param int                     $maxFileSize maximum file size of files to copy content from
     *
     * @throws InvalidArgumentException
     *
     * @since   0.11.0
     */
    public static function copy_from_file_system(string $path, ?Vfs_Stream_Directory $base_dir = null, int $max_file_size = 1048576): Vfs_Stream_Directory
    {
        if ($base_dir === null) {
            /** @var vfsStreamDirectory|null $baseDir **/
            $base_dir = Vfs_Stream_Wrapper::get_root();
        }
        if ($base_dir === null) {
            throw new InvalidArgumentException('No baseDir given and no root directory set.');
        }
        $dir = new Directory_Iterator($path);
        foreach ($dir as $fileinfo) {
            switch (filetype($fileinfo->get_pathname())) {
                case 'file':
                    if ($fileinfo->get_size() <= $max_file_size) {
                        $content = file_get_contents($fileinfo->get_pathname());
                    } else {
                        $content = new Large_File_Content($fileinfo->get_size());
                    }
                    self::new_file($fileinfo->get_filename(), octdec(substr(sprintf('%o', $fileinfo->get_perms()), -4)))->with_content($content)->at($base_dir);
                    break;
                case 'dir':
                    if (!$fileinfo->is_dot()) {
                        self::copy_from_file_system($fileinfo->get_pathname(), self::new_directory($fileinfo->get_filename(), octdec(substr(sprintf('%o', $fileinfo->get_perms()), -4)))->at($base_dir), $max_file_size);
                    }
                    break;
                case 'block':
                    self::new_block($fileinfo->get_filename(), octdec(substr(sprintf('%o', $fileinfo->get_perms()), -4)))->at($base_dir);
                    break;
            }
        }
        return $base_dir;
    }
    /**
     * returns a new file with given name
     *
     * @param string   $name        name of file to create
     * @param int|null $permissions permissions of file to create
     */
    public static function new_file(string $name, ?int $permissions = null): Vfs_Stream_File
    {
        return new Vfs_Stream_File($name, $permissions);
    }
    /**
     * Returns a new erroneous file with given name.
     *
     * Allows for throwing an error during fopen, fwrite, etc.
     *
     * For example:
     *
     * $file = vfsStream::newErroneousFile('foo.txt', ['open' => 'error message']);
     *
     * Will generate a file that always fails on open and displays "error message".
     *
     * You can set errors for: open, read, write, truncate, tell, seek, stat,
     * eof, and lock.
     *
     * @param string   $name          name of file to create
     * @param string[] $errorMessages Formatted as [action => message], e.g. ['open' => 'error message']
     * @param int|null $permissions   permissions of file to create
     */
    public static function new_erroneous_file(string $name, array $error_messages, ?int $permissions = null): Vfs_Stream_Erroneous_File
    {
        return new Vfs_Stream_Erroneous_File($name, $error_messages, $permissions);
    }
    /**
     * returns a new directory with given name
     *
     * If the name contains slashes, a new directory structure will be created.
     * The returned directory will always be the parent directory of this
     * directory structure.
     *
     * @param string   $name        name of directory to create
     * @param int|null $permissions permissions of directory to create
     */
    public static function new_directory(string $name, ?int $permissions = null): Vfs_Stream_Directory
    {
        if (substr($name, 0, 1) === '/') {
            $name = substr($name, 1);
        }
        $first_slash = strpos($name, '/');
        if ($first_slash === false) {
            return new Vfs_Stream_Directory($name, $permissions);
        }
        $own_name = substr($name, 0, $first_slash);
        $sub_dirs = substr($name, $first_slash + 1);
        $directory = new Vfs_Stream_Directory($own_name, $permissions);
        if (strlen($sub_dirs) > 0) {
            self::new_directory($sub_dirs, $permissions)->at($directory);
        }
        return $directory;
    }
    /**
     * returns a new block with the given name
     *
     * @param string   $name        name of the block device
     * @param int|null $permissions permissions of block to create
     */
    public static function new_block(string $name, ?int $permissions = null): Vfs_Stream_Block
    {
        return new Vfs_Stream_Block($name, $permissions);
    }
    /**
     * returns current user
     *
     * If the system does not support posix_getuid() the current user will be root (0).
     */
    public static function get_current_user(): int
    {
        return function_exists('posix_getuid') ? posix_getuid() : self::OWNER_ROOT;
    }
    /**
     * returns current group
     *
     * If the system does not support posix_getgid() the current group will be root (0).
     */
    public static function get_current_group(): int
    {
        return function_exists('posix_getgid') ? posix_getgid() : self::GROUP_ROOT;
    }
    /**
     * use visitor to inspect a content structure
     *
     * If the given content is null it will fall back to use the current root
     * directory of the stream wrapper.
     *
     * Returns given visitor for method chaining comfort.
     *
     * @see     https://github.com/mikey179/vfsStream/issues/10
     *
     * @param vfsStreamVisitor      $visitor the visitor who inspects
     * @param vfsStreamContent|null $content directory structure to inspect
     *
     * @throws InvalidArgumentException
     *
     * @since   0.10.0
     */
    public static function inspect(Vfs_Stream_Visitor $visitor, ?Vfs_Stream_Content $content = null): Vfs_Stream_Visitor
    {
        if ($content !== null) {
            return $visitor->visit($content);
        }
        $root = Vfs_Stream_Wrapper::get_root();
        if ($root === null) {
            throw new InvalidArgumentException('No content given and no root directory set.');
        }
        return $visitor->visit_directory($root);
    }
    /**
     * sets quota to given amount of bytes
     *
     * @since  1.1.0
     */
    public static function set_quota(int $bytes): void
    {
        Vfs_Stream_Wrapper::set_quota(new Quota($bytes));
    }
    /**
     * checks if vfsStream lists dotfiles in directory listings
     *
     * @since   1.3.0
     */
    public static function use_dotfiles(): bool
    {
        return self::$dot_files;
    }
    /**
     * disable dotfiles in directory listings
     *
     * @since  1.3.0
     */
    public static function disable_dotfiles(): void
    {
        self::$dot_files = false;
    }
    /**
     * enable dotfiles in directory listings
     *
     * @since  1.3.0
     */
    public static function enable_dotfiles(): void
    {
        self::$dot_files = true;
    }
}
class_alias('bovigo\vfs\vfsStream', 'org\bovigo\vfs\vfsStream');