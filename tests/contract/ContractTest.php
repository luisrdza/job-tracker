<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

#[PHPUnit\Framework\Attributes\CoversNothing]
class ContractTest extends TestCase
{
    protected ?\CurlHandle $ch = null;
    protected string $baseUrl = 'http://web/api/v1';

    public function setUp(): void
    {
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }

    private function getHeaders(array $overrides = []): array
    {
        $writeTokenPlainText = getenv('TOKEN_WRITE') ?: $_ENV['TOKEN_WRITE'] ?? 'fallback_local';

        $defaultHeaders = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $writeTokenPlainText,
            'Idempotency-Key' => $this->generateUuid()
        ];

        $mergedHeaders = array_merge($defaultHeaders, $overrides);

        $curlHeaders = [];
        foreach ($mergedHeaders as $key => $value) {
            if ($value !== null) {
                $curlHeaders[] = "$key: $value";
            }
        }

        return $curlHeaders;
    }

    public static function invalidLimitValuesProvider(): iterable
    {
        return [
            'limit equals to 0' => [0],
            'limit equals to negative value' => [-50],
            'limit equals to string value' => ['abc'],
            'limit greater than 50' => [51],
            'limit equals to empty string' => [''],
            'limit equals to null' => [null],
        ];
    }

    public static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versión 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function testHealthEndpointWorking(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/health');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $response = curl_exec($this->ch);
        $this->assertNotFalse($response, 'cURL request failed: ' . curl_error($this->ch));
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
        $data = json_decode($response, true);
        $requiredKeys = ['status', 'timestamp', 'version', 'database'];

        $this->assertSame(200, $httpCode);
        $this->assertStringContainsString('application/json', $contentType);
        $this->assertIsArray($data);
        $this->assertCount(count($requiredKeys), $data);
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $data);
            $this->assertIsString($data[$key]);
        }
    }

    public function testPostJobsValidationPassed(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => 5000
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(201, $httpCode);

        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $expectedKeys = ['id', 'title', 'company', 'status', 'salary_max', 'created_at', 'idempotency_key'];
        $actualKeys = array_keys($data);
        sort($expectedKeys);
        sort($actualKeys);
        $this->assertSame($expectedKeys, $actualKeys);

        $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['created_at']);
        $this->assertNotFalse($dateTime);
    }

    public function testPostJobsValidationPassedWithNullSalary(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(201, $httpCode);

        $data = json_decode($response, true);
        $this->assertIsArray($data);

        $this->assertNull($data['salary_max']);
    }

    public function testPostJobsValidationFailedEmptyAuthHeader(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $headers = $this->getHeaders(['Authorization' => 'Bearer ']);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(401, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(401, $data['code']);
        $this->assertSame('Invalid token.', $data['message']);
    }

    public function testPostJobsValidationFailedMissingAuthHeader(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $headers = $this->getHeaders(['Authorization' => null]);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(401, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(401, $data['code']);
        $this->assertSame('Invalid token.', $data['message']);
    }

    public function testPostJobsValidationFailedInvalidAuthHeader(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $headers = $this->getHeaders(['Authorization' => 'Bearer test_1234']);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(401, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(401, $data['code']);
        $this->assertSame('Invalid token.', $data['message']);
    }

    public function testPostJobsValidationFailedReadOnlyTokenAuthHeader(): void
    {
        $readTokenPlainText = getenv('TOKEN_READ') ?: $_ENV['TOKEN_READ'] ?? 'fallback_local';
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $headers = $this->getHeaders(['Authorization' => 'Bearer ' . $readTokenPlainText]);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(403, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(403, $data['code']);
        $this->assertSame('User do not have permission.', $data['message']);
    }

    public function testPostJobsValidationDuplicateIdempotencyKey(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $idempotencyKey = $this->generateUuid();
        $headers = $this->getHeaders(['Idempotency-Key' => $idempotencyKey]);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(201, $httpCode);

        $data = json_decode($response, true);
        $this->assertIsArray($data);

        $secondResponse = curl_exec($this->ch);
        $decodedData = json_decode($secondResponse, true);
        $this->assertSame(409, curl_getinfo($this->ch, CURLINFO_HTTP_CODE));
        $this->assertSame(409, $decodedData['code']);
        $this->assertSame('This idempotency key has already been used.', $decodedData['message']);
    }

    public function testPostJobsValidationFailedMissingIdempotencyKey(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $headers = $this->getHeaders(['Idempotency-Key' => null]);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedInvalidIdempotencyKey(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        $headers = $this->getHeaders(['Idempotency-Key' => 'abc-no-es-uuid']);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => null
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedWithStringSalary(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => "5000"
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedWhenMissingSalaryMaxKey(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $uniqueTitle = 'Test Title ' . uniqid();

        $payload = json_encode([
            'title' => $uniqueTitle,
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedWithEmptyTitle(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $payload = json_encode([
            'title' => ' ',
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => 5000
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedWithInvalidStatus(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $payload = json_encode([
            'title' => 'PHP Dev',
            'company' => 'JobTracker Company',
            'status' => 'invalid',
            'salary_max' => 5000
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedWithInvalidSalaryDataType(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $payload = json_encode([
            'title' => 'PHP Dev',
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => 'five thousand'
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid input data.', $data['message']);
    }

    public function testPostJobsValidationFailedWithMalformedJson(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $payload = "";

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Malformed JSON input.', $data['message']);
    }

    public function testPostJobsValidationFailedWithHTMLTagsInTitle(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $payload = json_encode([
            'title' => '<script>alert(1)</script>',
            'company' => 'JobTracker Company',
            'status' => 'DRAFT',
            'salary_max' => 5000
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('HTML tags are not allowed.', $data['message']);
    }

    public function testPostJobsValidationFailedWithHTMLTagsInCompany(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $payload = json_encode([
            'title' => 'Job Title',
            'company' => '<script>alert(1)</script>',
            'status' => 'DRAFT',
            'salary_max' => 5000
        ]);

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('HTML tags are not allowed.', $data['message']);
    }

    public function testGetAllJobs(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);
        $requiredKeys = ['id', 'title', 'company', 'status', 'salary_max', 'created_at', 'idempotency_key'];

        $this->assertSame(200, $httpCode);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $originalList = array_column($data['data'], 'created_at');
        $orderedList = $originalList;
        rsort($orderedList);

        $this->assertSame($originalList, $orderedList);

        foreach ($data['data'] as $job) {
            $this->assertCount(count($requiredKeys), $job);
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $job);
            }
        }
    }

    public function testGetAllJobsWithValidStatusAndLimit(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?status=DRAFT&limit=10');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);
        $requiredKeys = ['id', 'title', 'company', 'status', 'salary_max', 'created_at', 'idempotency_key'];

        $this->assertSame(200, $httpCode);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $originalList = array_column($data['data'], 'created_at');
        $orderedList = $originalList;
        rsort($orderedList);

        $this->assertSame($originalList, $orderedList);

        foreach ($data['data'] as $job) {
            $this->assertCount(count($requiredKeys), $job);
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $job);
            }
        }
    }

    public function testGetAllJobsWithInvalidStatus(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?status=INVALID');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid status value.', $data['message']);
    }

    #[DataProvider('invalidLimitValuesProvider')]
    public function testGetAllJobsWithInvalidLimitValue(string|int|null $limit): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?limit=' . $limit);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Limit must be a numeric value between 1 and 50.', $data['message']);
    }

    public function testCorruptedCursorReturns422(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?cursor=cursor_corrupto_123');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);

        $this->assertSame(422, $httpCode);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Invalid cursor value.', $data['message']);
    }

    public function testConsecutivePagesDoNotRepeatRows(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?limit=2');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');
        $firstResponse = json_decode(curl_exec($this->ch), true);

        $this->assertSame(200, curl_getinfo($this->ch, CURLINFO_HTTP_CODE));
        $this->assertNotEmpty($firstResponse['data']);
        $this->assertNotNull($firstResponse['next_cursor']);

        $cursor = urlencode($firstResponse['next_cursor']);
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?limit=2&cursor=' . $cursor);
        $secondResponse = json_decode(curl_exec($this->ch), true);

        $this->assertSame(200, curl_getinfo($this->ch, CURLINFO_HTTP_CODE));

        $firstIds = array_column($firstResponse['data'], 'id');
        $secondIds = array_column($secondResponse['data'], 'id');
        $repeatedIds = array_intersect($firstIds, $secondIds);

        $this->assertEmpty($repeatedIds, 'Consecutive pages do not repeat records.');
    }

    public function testLastPageReturnsNullCursor(): void
    {
        $cursor = null;
        $maxIterations = 50;
        $iterations = 0;

        do {
            $url = $this->baseUrl . '/jobs?limit=50';
            if ($cursor !== null) {
                $url .= '&cursor=' . urlencode($cursor);
            }

            curl_setopt($this->ch, CURLOPT_URL, $url);
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

            $response = json_decode(curl_exec($this->ch), true);
            $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

            $this->assertSame(200, $httpCode);
            $cursor = $response['next_cursor'];
            $iterations++;
        } while ($cursor !== null && $iterations < $maxIterations);

        $this->assertNull($cursor, 'Last page should return next_cursor equals to null.');
        $this->assertNotEmpty($response['data']);
    }

    public function testLimitOneReturnsSingleRecordAndNextCursor(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs?limit=1');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $data = json_decode($response, true);

        $this->assertSame(200, $httpCode);
        $this->assertCount(1, $data['data']);
        $this->assertNotNull($data['next_cursor']);
        $this->assertIsString($data['next_cursor']);
    }
}