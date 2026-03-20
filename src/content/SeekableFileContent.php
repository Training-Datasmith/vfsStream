<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs\content;

use function class_alias;
use const SEEK_CUR;
use const SEEK_END;
use const SEEK_SET;
use function strlen;
use function substr;
/**
 * Default implementation for file contents based on simple strings.
 *
 * @since  1.3.0
 */
abstract class Seekable_File_Content implements File_Content
{
    /**
     * current position within content
     *
     * @var  int
     */
    private $offset = 0;
    /**
     * reads the given amount of bytes from content
     */
    public function read(int $count): string
    {
        $data = $this->do_read($this->offset, $count);
        $this->offset += $count;
        return $data;
    }
    /**
     * actual reading of given byte count starting at given offset
     */
    abstract protected function do_read(int $offset, int $count): string;
    /**
     * seeks to the given offset
     */
    public function seek(int $offset, int $whence): bool
    {
        $new_offset = $this->offset;
        switch ($whence) {
            case SEEK_CUR:
                $new_offset += $offset;
                break;
            case SEEK_END:
                $new_offset = $this->size() + $offset;
                break;
            case SEEK_SET:
                $new_offset = $offset;
                break;
            default:
                return false;
        }
        if ($new_offset < 0) {
            return false;
        }
        $this->offset = $new_offset;
        return true;
    }
    /**
     * checks whether pointer is at end of file
     */
    public function eof(): bool
    {
        return $this->size() <= $this->offset;
    }
    /**
     * writes an amount of data
     *
     * @return  int     amount of written bytes
     */
    public function write(string $data): int
    {
        $data_length = strlen($data);
        $this->do_write($data, $this->offset, $data_length);
        $this->offset += $data_length;
        return $data_length;
    }
    /**
     * actual writing of data with specified length at given offset
     */
    abstract protected function do_write(string $data, int $offset, int $length): void;
    /**
     * for backwards compatibility with vfsStreamFile::bytesRead()
     *
     * @internal
     */
    public function bytes_read(): int
    {
        return $this->offset;
    }
    /**
     * for backwards compatibility with vfsStreamFile::readUntilEnd()
     *
     * @internal
     */
    public function read_until_end(): string
    {
        /** @var string|false $data */
        $data = substr($this->content(), $this->offset);
        return $data === false ? '' : $data;
    }
}
class_alias('bovigo\vfs\content\SeekableFileContent', 'org\bovigo\vfs\content\SeekableFileContent');