# Testing APIs

The CI4 API Starter includes advanced testing tools designed to handle the complexities of RESTful API testing, specifically state isolation and authentication.

## The `ApiTestCase` Class

When writing feature tests (integration tests that hit your endpoints), you should extend `Tests\Support\ApiTestCase` instead of the base CI4 class.

### Why `ApiTestCase`?

In standard CodeIgniter 4 feature tests, making multiple requests in a single test method can lead to "pollution". For example, `$_FILES`, `$_POST`, and shared services (like the Request object) might persist between calls.

`ApiTestCase` automatically:
1. Resets PHP globals (`$_POST`, `$_GET`, `$_FILES`, etc.) before each request.
2. Resets the shared `request` service.
3. Provides helper methods for JSON responses.

---

## Example Test

```php
namespace Tests\Feature;

use Tests\Support\ApiTestCase;

class FileUploadTest extends ApiTestCase
{
    public function testUserCanUploadFileInTwoWays()
    {
        // 1. First call: Multipart
        $result1 = $this->withHeaders(['Authorization' => 'Bearer ...'])
                         ->post('/api/v1/files', ['file' => ...]);
        $result1->assertStatus(201);

        // 2. State is automatically reset here!

        // 3. Second call: Base64
        $result2 = $this->withHeaders(['Authorization' => 'Bearer ...'])
                         ->post('/api/v1/files', ['file' => 'data:image/png;base64,...']);
        $result2->assertStatus(201);
    }
}
```

## Useful Helpers

### `getResponseJson($result)`
Converts the result of a feature call into an associative array for easy inspection.

```php
$result = $this->get('/api/v1/users');
$data = $this->getResponseJson($result);

$this->assertEquals('user@example.com', $data['data'][0]['email']);
```

### Database Refresh
By default, `ApiTestCase` uses the `DatabaseTestTrait` with `$refresh = true`, ensuring every test starts with a clean, migrated database.
