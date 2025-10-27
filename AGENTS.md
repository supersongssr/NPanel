# NPanel Development Guidelines

## Build/Lint/Test Commands

### Testing
- Run all tests: `./vendor/bin/phpunit`
- Run single test: `./vendor/bin/phpunit tests/Feature/ExampleTest.php`
- Run specific test method: `./vendor/bin/phpunit --filter testBasicTest`

### Frontend Assets
- Development build: `npm run dev`
- Watch for changes: `npm run watch`
- Production build: `npm run prod`

### Laravel Commands
- Generate IDE helpers: `php artisan ide-helper:generate`
- Clear cache: `php artisan cache:clear`
- Migrate database: `php artisan migrate`

## Code Style Guidelines

### PHP/Laravel Conventions
- Use PSR-4 autoloading with `App\` namespace
- Controllers in `app/Http/Controllers/` with descriptive names
- Models in `app/Http/Models/` (non-standard location)
- Use Laravel's built-in validation and request handling
- Follow Laravel naming conventions: snake_case for variables, camelCase for methods

### Import Organization
- Group imports: Laravel framework first, then third-party, then app-specific
- Use fully qualified class names where appropriate
- Avoid unused imports

### Error Handling
- Use Laravel's validation system for form validation
- Implement proper exception handling in controllers
- Log errors using `Log::` facade
- Return proper HTTP status codes and error messages

### Security
- Always hash passwords using `Hash::make()`
- Validate all user input
- Use CSRF protection
- Sanitize user-generated content
- Never commit sensitive data to repository


