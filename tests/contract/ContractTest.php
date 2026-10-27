<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

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
        $expectedKeys = ['id', 'title', 'company', 'status', 'salary_max', 'created_at'];
        $actualKeys = array_keys($data);
        sort($expectedKeys);
        sort($actualKeys);
        $this->assertSame($expectedKeys, $actualKeys);

        $dateTime = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['created_at']);
        $this->assertNotFalse($dateTime);
    }

    public function testPostJobsValidationFailedWithEmptyTitle(): void
    {
        curl_setopt($this->ch, CURLOPT_URL, $this->baseUrl . '/jobs');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'POST');

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

        $payload = "";

        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($this->ch);
        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $this->assertSame(422, $httpCode);

        $data = json_decode($response, true);
        $this->assertSame(422, $data['code']);
        $this->assertSame('Malformed JSON input.', $data['message']);
    }
}