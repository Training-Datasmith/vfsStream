<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use const SEEK_SET;
/**
 * Decorator for vfsStreamFile to allow multiple instances of a file to be open.
 *
 * It works by tracking and restoring the position in the file for each specific
 * instance created, even though the underlying file is shared.
 *
 * @internal
 */
final class Opened_File
{
    /** @var vfsStreamFile */
    private $base;
    /** @var int */
    private $position = 0;
    public function __construct(Vfs_Stream_File $base)
    {
        $this->base = $base;
    }
    public function get_base_file(): Vfs_Stream_File
    {
        return $this->base;
    }
    /**
     * simply open the file
     */
    public function open(): void
    {
        $this->base->open();
    }
    /**
     * open file and set pointer to end of file
     */
    public function open_for_append(): void
    {
        $this->base->open_for_append();
        $this->save_position();
    }
    /**
     * open file and truncate content
     */
    public function open_with_truncate(): void
    {
        $this->base->open_with_truncate();
        $this->save_position();
    }
    /**
     * reads the given amount of bytes from content
     */
    public function read(int $count): string
    {
        $this->restore_position();
        $data = $this->base->read($count);
        $this->save_position();
        return $data;
    }
    /**
     * returns the content until its end from current offset
     */
    public function read_until_end(): string
    {
        $this->restore_position();
        $data = $this->base->read_until_end();
        $this->save_position();
        return $data;
    }
    /**
     * writes an amount of data
     *
     * @return  int number of bytes written
     */
    public function write(string $data): int
    {
        $this->restore_position();
        $bytes = $this->base->write($data);
        $this->save_position();
        return $bytes;
    }
    /**
     * Truncates a file to a given length
     *
     * @param int $size length to truncate file to
     */
    public function truncate(int $size): bool
    {
        $this->restore_position();
        return $this->base->truncate($size);
    }
    /**
     * checks whether pointer is at end of file
     */
    public function eof(): bool
    {
        $this->restore_position();
        return $this->base->eof();
    }
    /**
     * returns the current position within the file
     */
    public function get_bytes_read(): int
    {
        $this->restore_position();
        $this->position = $this->base->get_bytes_read();
        return $this->position;
    }
    /**
     * seeks to the given offset
     */
    public function seek(int $offset, int $whence): bool
    {
        if ($whence !== SEEK_SET) {
            $this->restore_position();
        }
        $success = $this->base->seek($offset, $whence);
        $this->save_position();
        return $success;
    }
    /**
     * returns size of content
     */
    public function size(): int
    {
        return $this->base->size();
    }
    /**
     * locks file
     *
     * @param resource|vfsStreamWrapper $resource
     */
    public function lock($resource, int $operation): bool
    {
        return $this->base->lock($resource, $operation);
    }
    /**
     * returns the type of the container
     */
    public function get_type(): int
    {
        return $this->base->get_type();
    }
    /**
     * returns the last modification time of the stream content
     */
    public function filemtime(): int
    {
        return $this->base->filemtime();
    }
    /**
     * returns the last access time of the stream content
     */
    public function fileatime(): int
    {
        return $this->base->fileatime();
    }
    /**
     * returns the last attribute modification time of the stream content
     */
    public function filectime(): int
    {
        return $this->base->filectime();
    }
    /**
     * returns permissions
     */
    public function get_permissions(): int
    {
        return $this->base->get_permissions();
    }
    /**
     * checks whether content is readable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_readable(int $user, int $group): bool
    {
        return $this->base->is_readable($user, $group);
    }
    /**
     * checks whether content is writable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_writable(int $user, int $group): bool
    {
        return $this->base->is_writable($user, $group);
    }
    /**
     * checks whether content is executable
     *
     * @param   int $user  id of user to check for
     * @param   int $group id of group to check for
     */
    public function is_executable(int $user, int $group): bool
    {
        return $this->base->is_executable($user, $group);
    }
    /**
     * returns owner of file
     */
    public function get_user(): int
    {
        return $this->base->get_user();
    }
    /**
     * returns owner group of file
     */
    public function get_group(): int
    {
        return $this->base->get_group();
    }
    private function restore_position(): void
    {
        $this->base->get_content_object()->seek($this->position, SEEK_SET);
    }
    private function save_position(): void
    {
        $this->position = $this->base->get_content_object()->bytes_read();
    }
}