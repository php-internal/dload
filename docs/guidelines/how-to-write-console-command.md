# Console Commands Development Guidelines

This document provides guidelines for creating new console commands for the DLoad application.

## Command Structure

All commands must follow this structure:

- Extend the `Base` class
- Use the `#[AsCommand]` attribute
- Implement the required methods
- Use proper type declarations and project value objects

## Implementation Steps

1. Create a new PHP file in the `src/Command` directory
2. Define a class that extends `Base`
3. Add the `#[AsCommand]` attribute with name and description
4. Configure command arguments and options
5. Implement the `execute()` method

## Required Methods

### configure()

Override this method to define command arguments and options:

```php
public function configure(): void
{
    parent::configure();
    $this->addArgument('name', InputArgument::REQUIRED, 'Description');
    $this->addOption('option', null, InputOption::VALUE_OPTIONAL, 'Description', 'default');
}
```

### execute()

Implement your command logic in this method:

```php
protected function execute(
    InputInterface $input,
    OutputInterface $output,
): int {
    // Always call parent execute first to initialize services
    parent::execute($input, $output);
    
    // Access container services
    $service = $this->container->get(ServiceClass::class);
    
    // Command logic here
    $this->logger->info('Command executed');
    
    return Command::SUCCESS;
}
```

## Available Services

These services are accessible through the container:

- `Logger` - Use `$this->logger` for logging
- `InputInterface` - Command input
- `OutputInterface` - Command output
- `StyleInterface` - Symfony console styling
- Project-specific services (see modules API documentation)

## Type System and Value Objects

### Use Project Value Objects

**File Paths**: Always use `Path` value object instead of raw strings:

```php
use Internal\DLoad\Module\Common\FileSystem\Path;

// ✅ Preferred
private function getConfigPath(InputInterface $input): Path
{
    $configOption = $input->getOption('config');
    return Path::create($configOption ?? './default.xml');
}

// ❌ Avoid
private function getConfigPath(InputInterface $input): string
{
    return $input->getOption('config') ?? './default.xml';
}
```

**File Operations**: Use `Path` methods for file system operations:

```php
// ✅ Preferred - Using Path object methods
if (!$configPath->exists()) {
    return false;
}

// ❌ Avoid - Raw file system functions
if (!\is_file($configPath)) {
    return false;
}
```

**Type Annotations**: Use precise type annotations in PHPDoc:

```php
/**
 * @param Path $configPath Target configuration path
 * @param list<DownloadConfig> $actions Download actions to include
 */
private function generateFile(Path $configPath, array $actions): void
{
    // Implementation
}
```

## Interactive Command Best Practices

### Input Validation and Interaction Detection

**Use Symfony's Standard Methods**:

```php
// ✅ Preferred - Standard Symfony approach
if ($input->isInteractive()) {
    $this->collectUserInput($input, $output, $style);
}

// ❌ Avoid - Manual option checking
if (!$input->getOption('no-interaction')) {
    $this->collectUserInput($input, $output, $style);
}
```

### File Handling Patterns

**Combine Related Conditions**:

```php
// ✅ Preferred - Combined logical conditions
if (!$configPath->exists() || $input->getOption('overwrite')) {
    return false; // Can proceed
}

// ❌ Avoid - Separate checks
if (!$configPath->exists()) {
    return false;
}
if ($input->getOption('overwrite')) {
    return false;
}
```

**Proper Confirmation Handling**:

```php
private function shouldAbortOperation(
    InputInterface $input,
    StyleInterface $style,
    Path $targetPath,
): bool {
    if (!$targetPath->exists() || $input->getOption('overwrite')) {
        return false;
    }

    if (!$input->isInteractive()) {
        $style->error("Target already exists: {$targetPath}");
        $style->text('Use --overwrite to replace it.');
        return true;
    }

    $question = new ConfirmationQuestion(
        "Target exists at {$targetPath}. Overwrite? [y/N] ",
        false,
    );

    return !$this->getHelper('question')->ask($input, $style, $question);
}
```

## Best Practices

1. **Type Safety**: Use project value objects (`Path`, etc.) instead of primitives
2. **Input Validation**: Check arguments and options before proceeding
3. **Proper Return Codes**: Return appropriate status codes:
    - `Command::SUCCESS` (0) - Command completed successfully
    - `Command::FAILURE` (1) - Command failed
    - `Command::INVALID` (2) - Invalid input provided

4. **Error Handling**: Use try/catch blocks and provide helpful error messages
5. **Progress Feedback**: For long-running commands, show progress information
6. **Interactive Commands**: Use `$input->isInteractive()` for interaction detection
7. **File Operations**: Always use `Path` value object for file system operations
8. **Confirmation Dialogs**: Provide clear user choices with fallback for non-interactive mode

## Code Organization Patterns

### Method Extraction for Complex Logic

Break down complex operations into focused methods:

```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    parent::execute($input, $output);
    
    $style = $this->container->get(StyleInterface::class);
    $targetPath = $this->getTargetPath($input);
    
    if ($this->shouldAbortOperation($input, $style, $targetPath)) {
        return Command::FAILURE;
    }
    
    $data = $input->isInteractive() 
        ? $this->collectInteractiveData($input, $output, $style)
        : $this->getDefaultData();
    
    $this->generateOutput($targetPath, $data);
    
    $style->success("Operation completed: {$targetPath}");
    return Command::SUCCESS;
}
```

### Consistent Error Handling

```php
try {
    $this->performOperation();
    return Command::SUCCESS;
} catch (ValidationException $e) {
    $this->logger->error("Validation failed: {$e->getMessage()}");
    $style->error($e->getMessage());
    return Command::INVALID;
} catch (\Exception $e) {
    $this->logger->error("Operation failed: {$e->getMessage()}");
    $style->error("An error occurred: {$e->getMessage()}");
    return Command::FAILURE;
}
```

## Example Command

```php
<?php

declare(strict_types=1);

namespace Internal\DLoad\Command;

use Internal\DLoad\Module\Common\FileSystem\Path;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\StyleInterface;

#[AsCommand(
    name: 'example',
    description: 'Example command demonstrating best practices',
)]
final class ExampleCommand extends Base
{
    public function configure(): void
    {
        parent::configure();
        $this->addArgument('name', InputArgument::REQUIRED, 'Name argument');
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', './output.txt');
        $this->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite existing output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        
        /** @var StyleInterface $style */
        $style = $this->container->get(StyleInterface::class);
        
        try {
            $name = $input->getArgument('name');
            $outputPath = $this->getOutputPath($input);
            
            if ($this->shouldAbortDueToExistingFile($input, $style, $outputPath)) {
                return Command::FAILURE;
            }
            
            $content = $input->isInteractive()
                ? $this->collectInteractiveContent($input, $output, $style, $name)
                : $this->generateDefaultContent($name);
            
            $this->writeContentToFile($outputPath, $content);
            
            $style->success("Content written to: {$outputPath}");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->logger->error("Command failed: {$e->getMessage()}");
            $style->error("An error occurred: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Gets the output file path as a Path object.
     */
    private function getOutputPath(InputInterface $input): Path
    {
        /** @var string $outputOption */
        $outputOption = $input->getOption('output');
        return Path::create($outputOption);
    }

    /**
     * Checks if operation should be aborted due to existing file.
     */
    private function shouldAbortDueToExistingFile(
        InputInterface $input,
        StyleInterface $style,
        Path $outputPath,
    ): bool {
        if (!$outputPath->exists() || $input->getOption('overwrite')) {
            return false;
        }

        if (!$input->isInteractive()) {
            $style->error("Output file already exists: {$outputPath}");
            $style->text('Use --overwrite to replace it.');
            return true;
        }

        $question = new ConfirmationQuestion(
            "Output file exists at {$outputPath}. Overwrite? [y/N] ",
            false,
        );

        return !$this->getHelper('question')->ask($input, $style, $question);
    }

    /**
     * Collects content through interactive prompts.
     */
    private function collectInteractiveContent(
        InputInterface $input,
        OutputInterface $output,
        StyleInterface $style,
        string $name,
    ): string {
        $style->section('Interactive Content Generation');
        // Interactive logic here
        return "Interactive content for {$name}";
    }

    /**
     * Generates default content for non-interactive mode.
     */
    private function generateDefaultContent(string $name): string
    {
        return "Default content for {$name}";
    }

    /**
     * Writes content to the specified file path.
     */
    private function writeContentToFile(Path $outputPath, string $content): void
    {
        \file_put_contents((string) $outputPath, $content);
    }
}
```

## Additional Resources

- Review existing commands for a complete example of these patterns
- Consult the modules API documentation for available services
- Follow the project's PHP best practices guidelines
