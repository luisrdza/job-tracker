<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Domain\JobPosting;

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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

    public function testPostJobsValidationDuplicateIdempotencyKey(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: abc-no-es-uuid'
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

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
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Idempotency-Key: ' . $this->generateUuid()
        ]);

        $payload = "";

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Malformed JSON input.', $data['message']);
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

        $originalList = array_column($data, 'created_at');
        $orderedList = $originalList;
        rsort($orderedList);

        $this->assertSame($originalList, $orderedList);

        foreach ($data as $job) {
            $this->assertCount(count($requiredKeys), $job);
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $job);
            }
        }
    }
}