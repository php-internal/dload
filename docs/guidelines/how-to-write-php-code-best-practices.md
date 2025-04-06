# PHP Best Practices for LLM Code Generation

## Overview

This document outlines key practices for generating high-quality PHP code using LLMs, ensuring code remains maintainable, efficient, and follows modern PHP standards.

## Core Principles

### 1. Modern PHP Features (PHP 8.1+)

- Use constructor property promotion
- Leverage named arguments
- Implement match expressions instead of switch statements
- Use union types and nullable types
- Apply return type declarations consistently
- Use null coalescing operators (`??`, `??=`) instead of ternary checks
  ```php
  // Preferred
  $username = $request->username ?? 'guest';

  // Avoid
  $username = isset($request->username) ? $request->username : 'guest';
  ```
- Use throw expressions with null coalescing for concise error handling
  ```php
  // Preferred
  $user = $orm->get('user') ?? throw new NotFoundException();

  // Avoid
  $user = $orm->get('user');
  if ($user === null) {
      throw new NotFoundException();
  }
  ```
- Prefer attributes over PHPDoc annotations whenever possible
  ```php
  // Preferred - Using attributes
  #[AsController]
  #[Route('/api/users')]
  class UserController
  {
      #[Route('/get/{id}', methods: ['GET'])]
      public function getUser(#[FromRoute] int $id): User
      {
          // ...
      }
  }

  // Avoid - Using annotations
  /**
   * @Controller
   * @Route("/api/users")
   */
  class UserController
  {
      /**
       * @Route("/get/{id}", methods={"GET"})
       */
      public function getUser(int $id): User
      {
          // ...
      }
  }
  ```

### 2. Code Structure

- Follow PER-2 coding standards which extends PSR-12
- Maintain single responsibility principle
- Keep methods focused and concise (under 20 lines when possible)
- Favor composition over inheritance
- Use strict typing (`declare(strict_types=1);`)
- Declare classes as `final` by default, only omit when inheritance is actually needed
  ```php
  // Preferred - Final by default
  final class UserRepository 
  {
      // ...
  }

  // Only when needed for inheritance
  abstract class AbstractRepository
  {
      // ...
  }
  ```
- Prefer early returns to reduce nesting and improve readability
- Merge similar conditionals with the same action
  ```php
  // Preferred: Merged conditionals
  public function processUser(?User $user): bool
  {
      if ($user === null or !$user->isActive()) {
          return false;
      }

      // Process the valid user
      return true;
  }

  // Avoid: Repetitive conditionals
  public function processUser(?User $user): bool
  {
      if ($user === null) {
          return false;
      }

      if (!$user->isActive()) {
          return false;
      }

      // Process the valid user
      return true;
  }
  ```
- Use logical operators for compact conditional execution
  ```php
  // Preferred
  $condition and doSomething();

  // Avoid
  if ($condition) {
      doSomething();
  }

  // Preferred - using 'or' instead of 'not' with 'and'
  $skipAction or doAction();

  // Avoid
  !$skipAction and doAction();
  ```
- Prefer ternary operator for simple conditional assignments and returns
  ```php
  // Preferred
  return $condition
      ? $foo
      : $bar;

  // Avoid
  if ($condition) {
      return $foo;
  }

  return $bar;
  ```

### 3. Enumerations (PHP 8.1+)

- Use enums instead of class constants for representing a fixed set of related values
- Use CamelCase for enum case names as per PER-2 standard
  ```php
  // Preferred - Using an enum with CamelCase cases
  enum Status
  {
      case Pending;
      case Processing;
      case Completed;
      case Failed;
  }

  // Avoid - Using class constants
  class Status
  {
      public const PENDING = 'pending';
      public const PROCESSING = 'processing';
      public const COMPLETED = 'completed';
      public const FAILED = 'failed';
  }
  ```

- Use backed enums when you need primitive values (strings/integers) for cases
  ```php
  enum Status: string
  {
      case Pending = 'pending';
      case Processing = 'processing';
      case Completed = 'completed';
      case Failed = 'failed';
  }

  // Usage with database or API
  $status = Status::Completed;
  $database->updateStatus($id, $status->value); // 'completed'
  ```

- Add methods to enums to encapsulate related behavior
  ```php
  enum PaymentStatus: string
  {
      case Pending = 'pending';
      case Paid = 'paid';
      case Refunded = 'refunded';
      case Failed = 'failed';

      public function isSuccessful(): bool
      {
          return $this === self::Paid || $this === self::Refunded;
      }

      public function canBeRefunded(): bool
      {
          return $this === self::Paid;
      }

      public function getLabel(): string
      {
          return match($this) {
              self::Pending => 'Awaiting Payment',
              self::Paid => 'Payment Received',
              self::Refunded => 'Payment Refunded',
              self::Failed => 'Payment Failed',
          };
      }
  }

  // Usage
  $status = PaymentStatus::Paid;
  if ($status->canBeRefunded()) {
      // Process refund
  }
  ```

- Implement interfaces with enums to enforce contracts
  ```php
  interface ColorInterface
  {
      public function getRgb(): string;
  }

  enum Color implements ColorInterface
  {
      case Red;
      case Green;
      case Blue;

      public function getRgb(): string
      {
          return match($this) {
              self::Red => '#FF0000',
              self::Green => '#00FF00',
              self::Blue => '#0000FF',
          };
      }
  }
  ```

- Use static methods for converting from and to enum cases
  ```php
  enum Status: string
  {
      case Pending = 'pending';
      case Processing = 'processing';
      case Completed = 'completed';
      case Failed = 'failed';

      public static function fromDatabase(?string $value): ?self
      {
          if ($value === null) {
              return null;
          }

          return self::tryFrom($value) ?? throw new \InvalidArgumentException("Invalid status: {$value}");
      }
  }
  ```

- Use enums in type declarations
  ```php
  function processOrder(Order $order, Status $status): void
  {
      match($status) {
          Status::Pending => $this->queueOrder($order),
          Status::Processing => $this->notifyProcessing($order),
          Status::Completed => $this->markComplete($order),
          Status::Failed => $this->handleFailure($order),
      };
  }
  ```

### 4. Immutability and Value Objects

- Prefer immutable objects and value objects where appropriate
- Use readonly properties for immutable class properties
  ```php
  final class UserId
  {
      public function __construct(
          public readonly string $value,
      ) {}
  }
  ```
- Use the `with` prefix for methods that return new instances with modified values
  ```php
  final class User
  {
      public function __construct(
          public readonly string $name,
          public readonly string $email,
          public readonly \DateTimeImmutable $createdAt,
      ) {
      }

      // Returns new instance with modified name
      public function withName(string $name): self
      {
          return new self(
              $name,
              $this->email,
              $this->createdAt,
          );
      }

      // Returns new instance with modified email
      public function withEmail(string $email): self
      {
          return new self(
              $this->name,
              $email,
              $this->createdAt,
          );
      }
  }
  
  // Usage
  $user = new User('John', 'john@example.com', new \DateTimeImmutable());
  $updatedUser = $user->withName('Jane')->withEmail('jane@example.com');
  ```

### 5. Dependency Injection and IoC

- Favor constructor injection for dependencies
  ```php
  final class UserService
  {
      public function __construct(
          private readonly UserRepositoryInterface $userRepository,
          private readonly LoggerInterface $logger,
      ) {}
  }
  ```
- Define interfaces for services to allow for different implementations
- Avoid service locators and static method calls for dependencies
- Use dependency injection containers for wiring services together
- Keep classes focused on their responsibility; don't inject unnecessary dependencies

### 6. Type System and Generics

- Use extended type annotations in PHPDoc for more precise type definitions
  ```php
  /**
   * @param non-empty-string $id The user ID
   * @param list<Role> $roles List of user roles
   * @return array<string, mixed>
   */
  public function getUserData(string $id, array $roles): array
  {
      // ...
  }
  ```
- Leverage generics in collections and repositories
  ```php
  /**
   * @template T of object
   */
  interface RepositoryInterface
  {
      /**
       * @param class-string<T> $className
       * @param non-empty-string $id
       * @return T|null
       */
      public function find(string $className, string $id): ?object;

      /**
       * @param T $entity
       * @return void
       */
      public function save(object $entity): void;
  }
  ```
- Use precise numeric range types when applicable
  ```php
  /**
   * @param int<0, max> $limit
   * @param int<0, max> $offset
   * @return list<User>
   */
  public function getUsers(int $limit, int $offset): array
  {
      // ...
  }
  ```

### 7. Error Handling

- Use exceptions for error conditions
- Prefer typed exceptions for specific error categories
- Avoid suppressing errors with `@`
- Include meaningful error messages

### 8. Array and Collection Handling

- Avoid using `empty()` function; use explicit comparisons instead
  - Use `$array === []` instead of `empty($array)` or `count($array) === 0`
  - Use `$string === ''` instead of `empty($string)`
- Prefer array functions like `array_filter()`, `array_map()`, and `array_reduce()`

### 9. Comparison and Null Checks

- Use `$value === null` instead of `is_null($value)` for null checks
- Use strict equality (`===`) instead of loose equality (`==`)
- Use `isset()` only for checking if variables or properties are defined, not for null comparison
  ```php
  // Correct use of isset()
  if (isset($data['key'])) {  // Checks if array key exists and not null
      // Use $data['key']
  }

  // Incorrect use of isset()
  if (isset($definedVariable)) {  // Don't use isset() when variable is definitely defined
      // Instead use $definedVariable !== null
  }
  ```
- Use null coalescing operator for default values
  ```php
  // Preferred
  $config = $options['config'] ?? [];

  // Avoid
  $config = isset($options['config']) ? $options['config'] : [];
  ```

### 10. Security Considerations

- Sanitize all user inputs
- Parameterize database queries
- Avoid using `eval()` or other dynamic code execution
- Implement proper authentication and authorization checks

## Implementation Examples

[To be extended with code examples]
