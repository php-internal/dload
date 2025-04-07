# PHP Unit Testing Guidelines

This document outlines the standards and best practices for writing unit tests for the project.

## Test Structure

- Unit tests should be in `tests/Unit`, integration tests in `tests/Integration`, acceptance tests in `tests/Acceptance`, architecture tests in `tests/Arch`, etc.
- Tests should be organized to mirror the project structure.
- Each concrete PHP class should have a corresponding test class
- Place tests in the appropriate namespace matching the source code structure
- Use `final` keyword for test classes

```
Source: src/ExternalContext/LocalExternalContextSource.php
Test: tests/Unit/ExternalContext/LocalExternalContextSourceTest.php
```

## What to Test

- **Test Concrete Implementations, Not Interfaces**: Interfaces define contracts but don't contain actual logic to test. Only test concrete implementations of interfaces.
- **Focus on Behavior**: Test the behavior of classes rather than their internal implementation details.
- **Test Public API**: Focus on testing the public methods and functionality that clients of the class will use.
- **Test Edge Cases**: Include tests for boundary conditions, invalid inputs, and error scenarios.

## Module Testing

Modules located in `src/Module` are treated as independent units with their own test structure:

- Each module should have its own test directory with the following structure:
  ```
  tests/Unit/Module/{ModuleName}/
  ├── Stub/ (Contains stubs for the module's dependencies)
  └── Internal/ (Tests for module's internal implementations)
  ```

- Internal implementations of a module are located in the `Internal` folder of each module
- Tests for public classes of the module should be placed directly in the module's test directory corresponding to the source code structure
- Each module's tests should be structured as independent areas with their own stubs

## Arrange-Act-Assert (AAA) Pattern

All tests should follow the AAA pattern:

1. **Arrange**: Set up the test environment and prepare inputs
2. **Act**: Execute the code being tested
3. **Assert**: Verify the results are as expected

Use comments to separate these sections for clarity:

```php
public function testFetchContextReturnsDecodedJson(): void
{
    // Arrange
    $filePath = '/path/to/context.json';
    $fileContent = '{"key":"value"}';
    $this->fileSystem->method('exists')->willReturn(true);
    $this->fileSystem->method('readFile')->willReturn($fileContent);

    // Act
    $result = $this->contextSource->fetchContext($filePath);

    // Assert
    self::assertSame(['key' => 'value'], $result);
}
```

## Naming Conventions

- Test classes should be named with the pattern `{ClassUnderTest}Test`
- Test methods should follow the pattern `test{MethodName}{Scenario}`
- Use descriptive method names that explain what is being tested

```php
#[CoversClass(LocalExternalContextSource::class)]
final class LocalExternalContextSourceTest extends TestCase
{
    public function testFetchContextReturnsValidData(): void
    {
        // Test implementation
    }

    public function testFetchContextThrowsExceptionWhenFileNotFound(): void
    {
        // Test implementation
    }
}
```

## Test Implementation

- Use strict typing: `declare(strict_types=1);`
- Use namespaces consistent with the project structure
- Extend `PHPUnit\Framework\TestCase`
- Use assertion methods with descriptive error messages
- Test one behavior per test method
- Use data providers with PHP 8.1+ attributes and generators

```php
#[DataProvider('provideValidFilePaths')]
public function testFetchContextWithVariousPaths(string $path, array $expectedData): void
{
    // Arrange
    $this->fileSystem->method('exists')->willReturn(true);
    $this->fileSystem->method('readFile')->willReturn(\json_encode($expectedData));

    // Act
    $result = $this->contextSource->fetchContext($path);

    // Assert
    self::assertSame($expectedData, $result);
}

public static function provideValidFilePaths(): Generator
{
    yield 'relative path' => ['relative/path.json', ['expected' => 'data']];
    yield 'absolute path' => ['/absolute/path.json', ['expected' => 'data']];
}
```

## Test Isolation

- Each test should be independent of others
- Use setUp() and tearDown() methods for common test preparation and cleanup
- Use test doubles (mocks, stubs) for isolating the code under test from dependencies
- Reset global/static state between tests
- For module tests, use the dedicated Stub directory to store all stub implementations

```php
protected function setUp(): void
{
    // Arrange (common setup)
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->contextSource = new LocalExternalContextSource($this->fileSystem);
}
```

### Module-Specific Test Isolation

When testing modules from `src/Module`:

- Module tests should use stubs from their dedicated `Stub` directory
- Tests should only rely on the public interfaces of the module, not internal implementations
- Internal tests can have additional stubs specific to internal components
- Cross-module dependencies should be stubbed if possible (for interfaces), treating each module as an independent unit

```php
// Example of module test setup with stubs
namespace Tests\Unit\Module\Payment;

use Tests\Unit\Module\Payment\Stub\PaymentGatewayStub;
use Tests\Unit\Module\Payment\Stub\LoggerStub;

final class PaymentProcessorTest extends TestCase
{
    private PaymentGatewayStub $paymentGateway;
    private LoggerStub $logger;
    
    protected function setUp(): void
    {
        $this->paymentGateway = new PaymentGatewayStub();
        $this->logger = new LoggerStub();
        $this->processor = new PaymentProcessor($this->paymentGateway, $this->logger);
    }
}
```

## Assertions

- Use specific assertion methods instead of generic ones
- Provide meaningful failure messages in assertions
- Test both positive and negative scenarios
- Assert state changes and side effects, not just return values

```php
// Good
self::assertSame('expected', $actual, 'Context data should match the expected format');

// Instead of
self::assertTrue($expected === $actual);
```

## Error Handling Tests

When testing exceptions, the AAA pattern is slightly modified:

```php
public function testFetchContextThrowsExceptionWhenFileNotFound(): void
{
    // Arrange
    $filePath = '/non-existent/path.json';
    $this->fileSystem->method('exists')->willReturn(false);

    // Assert (before Act for exceptions)
    $this->expectException(ContextSourceException::class);
    $this->expectExceptionMessage('Cannot read context from file');

    // Act
    $this->contextSource->fetchContext($filePath);
}
```

## Mock Objects

- Only mock direct dependencies of the class under test
- Mock only what is necessary for the test
- **DO NOT MOCK enums or final classes** - this is strictly prohibited
- Prefer typed mock method returns
- Verify critical interactions with mocks

```php
// Arrange
$this->fileSystem->expects(self::once())
    ->method('readFile')
    ->with('/path/to/file.json')
    ->willReturn('{"key": "value"}');
```

### Dealing with Final Classes and Enums

- **Enumerations must not be mocked and need to be used as is** - always use real enum instances in tests
- **Final classes should not be mocked** - use real instances or alternative approaches

When a class under test depends on a final class:

1. **Use real instances** when possible - this is the preferred approach
2. **Create test doubles** by implementing the same interface if the final class implements an interface
3. **Use wrapper/adapter pattern** to create a non-final class that delegates to the final class if necessary
4. **Refactor dependencies** to use interfaces where appropriate for improved testability

```php
// Instead of mocking a final class:
final class FileReader
{
    public function readFile(string $path): string {...}
}

// Create an interface:
interface FileReaderInterface
{
    public function readFile(string $path): string;
}

// Create a test implementation:
class TestFileReader implements FileReaderInterface
{
    public function readFile(string $path): string
    {
        return '{"test":"data"}';
    }
}

// Use in tests:
$fileReader = new TestFileReader();
$myService = new MyService($fileReader);
```

For enums, always use the real enum values directly:

```php
// When testing with an enum dependency:
enum Status: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

// In your test - ALWAYS use the real enum instance:
public function testProcessWithPendingStatus(): void
{
    // Arrange - use the real enum value
    $status = Status::PENDING;
    $processor = new StatusProcessor();
    
    // Act
    $result = $processor->process($status);
    
    // Assert
    self::assertTrue($result);
}
```

## Additional Modern PHPUnit Features

### PHP 8.1+ Attributes

Replace annotations with attributes throughout your tests:

- `#[CoversClass(ClassUnderTest::class)]` - Specify which class is being tested
- `#[CoversMethod('methodName')]` - Specify which method is being tested
- `#[DataProvider('provideTestData')]` - Link to data provider method
- `#[Group('slow')]` - Categorize tests
- `#[TestDox('Class should handle errors gracefully')]` - Better test documentation

### Using depends with Attributes

```php
public function testBasicFunctionality(): SomeClass
{
    // Arrange
    $object = new SomeClass();

    // Act & Assert
    self::assertInstanceOf(SomeClass::class, $object);
    return $object;
}

#[Depends('testBasicFunctionality')]
public function testAdvancedFeature(SomeClass $object): void
{
    // Arrange is handled by the dependency

    // Act
    $result = $object->advancedMethod();

    // Assert
    self::assertTrue($result);
}
```

### Test Extension with Traits

Use traits to share test functionality:

```php
trait CreatesTempFilesTrait
{
    private string $tempFilePath;

    protected function createTempFile(string $content): string
    {
        // Arrange test environment
        $this->tempFilePath = sys_get_temp_dir() . '/' . uniqid('test_', true);
        file_put_contents($this->tempFilePath, $content);
        return $this->tempFilePath;
    }

    protected function tearDown(): void
    {
        // Clean up test environment
        if (isset($this->tempFilePath) && file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
        parent::tearDown();
    }
}

final class MyTest extends TestCase
{
    use CreatesTempFilesTrait;

    public function testFileProcessing(): void
    {
        // Arrange
        $path = $this->createTempFile('{"data":"value"}');

        // Act
        $result = $this->processor->process($path);

        // Assert
        self::assertNotEmpty($result);
    }
}
```

## Example Test Class

```php
<?php

declare(strict_types=1);

namespace Internal\DLoad\Tests\Unit\ExternalContext;

use Generator;
use Internal\DLoad\ExternalContext\ContextSourceException;
use Internal\DLoad\ExternalContext\LocalExternalContextSource;
use Internal\DLoad\FileSystem\FileSystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalExternalContextSource::class)]
final class LocalExternalContextSourceTest extends TestCase
{
    private FileSystem $fileSystem;
    private LocalExternalContextSource $contextSource;

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->fileSystem = $this->createMock(FileSystem::class);
        $this->contextSource = new LocalExternalContextSource($this->fileSystem);
    }

    public function testFetchContextReturnsDecodedJsonWhenFileExists(): void
    {
        // Arrange
        $filePath = '/path/to/context.json';
        $fileContent = '{"key":"value","nested":{"data":true}}';
        $expectedData = ['key' => 'value', 'nested' => ['data' => true]];

        $this->fileSystem->method('exists')->with($filePath)->willReturn(true);
        $this->fileSystem->method('readFile')->with($filePath)->willReturn($fileContent);

        // Act
        $result = $this->contextSource->fetchContext($filePath);

        // Assert
        self::assertSame($expectedData, $result);
    }

    #[DataProvider('provideInvalidPaths')]
    public function testFetchContextThrowsExceptionForInvalidPaths(
        string $path,
        string $expectedExceptionMessage
    ): void {
        // Arrange
        $this->fileSystem->method('exists')->willReturn(false);

        // Assert (before Act for exceptions)
        $this->expectException(ContextSourceException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        // Act
        $this->contextSource->fetchContext($filePath);
    }

    public static function provideInvalidPaths(): Generator
    {
        yield 'empty path' => ['', 'Cannot read context from empty path'];
        yield 'non-existent file' => ['/missing.json', 'File not found: /missing.json'];
    }
}
```
