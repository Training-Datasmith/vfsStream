# Architecture: vfsStream

## Purpose

A PHP stream wrapper library that provides a virtual (in-memory) filesystem for unit testing. Code under test that uses `file_get_contents()`, `fopen()`, `is_file()`, etc. operates against an in-memory structure, eliminating real disk I/O and temporary file management in tests.

## Directory Structure

```
src/
  vfs_Stream.php                   - Factory and static helper: setUp(), newDirectory(), newFile(), etc.
  vfs_Stream_Wrapper.php           - PHP stream wrapper implementation registered as the "vfs://" protocol
  vfs_Stream_File.php              - In-memory file node
  vfs_Stream_Directory.php         - In-memory directory node
  vfs_Stream_Block.php             - Block device simulation
  vfs_Stream_Content.php           - Interface for vfs content nodes
  vfs_Stream_Container.php         - Interface for container nodes (directories)
  vfs_Stream_Abstract_Content.php  - Shared permissions/ownership logic for all content nodes
  Dot_Directory.php                - Represents . and .. entries
  Opened_File.php                  - Tracks open file handles (position, mode, locks)
  Quota.php                        - Simulated disk quota enforcement
  content/
    File_Content.php               - Interface for file data strategies
    String_Based_File_Content.php  - Stores file content as a PHP string (in memory)
    Large_File_Content.php         - Simulates large files without storing all bytes
    Seekable_File_Content.php      - Seekable stream interface for file content
  visitor/
    vfs_Stream_Visitor.php         - Visitor pattern interface for the vfs tree
    vfs_Stream_Abstract_Visitor.php - Base visitor with default no-op methods
    vfs_Stream_Print_Visitor.php   - Prints the vfs tree structure to output
    vfs_Stream_Structure_Visitor.php - Exports the vfs tree as a PHP array
examples/                          - Runnable examples showing usage with and without vfsStream
tests/                             - PHPUnit tests
```

## Key Design Decisions

- **PHP stream wrapper protocol**: The entire library works by registering `vfs://` as a PHP stream wrapper. User code calls standard PHP file functions — no mocking of individual functions is needed.
- **Composite content nodes**: Files and directories are modelled as a composite tree (Composite pattern). Content type is decoupled from the node via `File_Content` strategies.
- **Visitor pattern**: The vfs tree can be traversed and inspected without modifying node classes, via `vfs_Stream_Visitor` implementations.
- **Quota simulation**: `Quota` allows tests to simulate disk-full conditions.
- **File locking**: `vfs_Stream_Wrapper` honours `flock()` calls against in-memory files.

## Extension Points

- Implement `File_Content` to simulate custom file content (e.g., streaming content from a network source).
- Implement `vfs_Stream_Visitor` to add custom tree inspection or transformation.

## Dependency Flow

```
PHP file functions (fopen, file_get_contents, mkdir, ...)
  └─> vfs_Stream_Wrapper (registered as "vfs://" protocol)
        └─> vfs_Stream_Directory / vfs_Stream_File (tree nodes)
              └─> String_Based_File_Content / Large_File_Content
```
