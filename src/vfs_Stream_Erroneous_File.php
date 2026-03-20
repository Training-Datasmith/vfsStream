<?php

declare (strict_types=1);
/**
 * This file is part of vfsStream.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace bovigo\vfs;

use const E_USER_WARNING;
use function trigger_error;
/**
 * File to trigger errors on specific actions.
 *
 * Allows for throwing an error during fopen, fwrite, etc.
 *
 * @api
 */
class Vfs_Stream_Erroneous_File extends Vfs_Stream_File
{
    /** @var string[] */
    private $error_messages;
    /**
     * @param string[] $errorMessages Formatted as [action => message], e.g. ['open' => 'error message']
     * @param int|null $permissions   optional
     */
    public function __construct(string $name, array $error_messages, ?int $permissions = null)
    {
        parent::__construct($name, $permissions);
        $this->error_messages = $error_messages;
    }
    public function open(): void
    {
        if (isset($this->error_messages['open'])) {
            trigger_error($this->error_messages['open'], E_USER_WARNING);
            return;
        }
        parent::open();
    }
    public function open_for_append(): void
    {
        if (isset($this->error_messages['open'])) {
            trigger_error($this->error_messages['open'], E_USER_WARNING);
            return;
        }
        parent::open_for_append();
    }
    public function open_with_truncate(): void
    {
        if (isset($this->error_messages['open'])) {
            trigger_error($this->error_messages['open'], E_USER_WARNING);
            return;
        }
        parent::open_with_truncate();
    }
    public function read(int $count): string
    {
        if (isset($this->error_messages['read'])) {
            trigger_error($this->error_messages['read'], E_USER_WARNING);
            return '';
        }
        return parent::read($count);
    }
    public function read_until_end(): string
    {
        if (isset($this->error_messages['read'])) {
            trigger_error($this->error_messages['read'], E_USER_WARNING);
            return '';
        }
        return parent::read_until_end();
    }
    public function write(string $data): int
    {
        if (isset($this->error_messages['write'])) {
            trigger_error($this->error_messages['write'], E_USER_WARNING);
            return 0;
        }
        return parent::write($data);
    }
    public function truncate(int $size): bool
    {
        if (isset($this->error_messages['truncate'])) {
            trigger_error($this->error_messages['truncate'], E_USER_WARNING);
            return false;
        }
        return parent::truncate($size);
    }
    public function eof(): bool
    {
        if (isset($this->error_messages['eof'])) {
            trigger_error($this->error_messages['eof'], E_USER_WARNING);
            // True on error.
            // See: https://www.php.net/manual/en/function.feof.php#refsect1-function.feof-returnvalues
            return true;
        }
        return parent::eof();
    }
    public function get_bytes_read(): int
    {
        if (isset($this->error_messages['tell'])) {
            trigger_error($this->error_messages['tell'], E_USER_WARNING);
            return 0;
        }
        return parent::get_bytes_read();
    }
    public function seek(int $offset, int $whence): bool
    {
        if (isset($this->error_messages['seek'])) {
            trigger_error($this->error_messages['seek'], E_USER_WARNING);
            return false;
        }
        return parent::seek($offset, $whence);
    }
    public function size(): int
    {
        if (isset($this->error_messages['stat'])) {
            trigger_error($this->error_messages['stat'], E_USER_WARNING);
            return -1;
        }
        return parent::size();
    }
    /**
     * {@inheritDoc}
     */
    public function lock($resource, int $operation): bool
    {
        if (isset($this->error_messages['lock'])) {
            trigger_error($this->error_messages['lock'], E_USER_WARNING);
            return false;
        }
        return parent::lock($resource, $operation);
    }
    public function filemtime(): int
    {
        if (isset($this->error_messages['stat'])) {
            trigger_error($this->error_messages['stat'], E_USER_WARNING);
            return -1;
        }
        return parent::filemtime();
    }
    public function fileatime(): int
    {
        if (isset($this->error_messages['stat'])) {
            trigger_error($this->error_messages['stat'], E_USER_WARNING);
            return -1;
        }
        return parent::fileatime();
    }
    public function filectime(): int
    {
        if (isset($this->error_messages['stat'])) {
            trigger_error($this->error_messages['stat'], E_USER_WARNING);
            return -1;
        }
        return parent::filectime();
    }
}