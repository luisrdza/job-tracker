<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ContractTest extends TestCase
{
    protected ?\CurlHandle $ch = null;

    public function setUp(): void
    {
        $this->ch = curl_init('http://web/api/v1/health');
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }

    #[PHPUnit\Framework\Attributes\CoversNothing]
    public function testHealthEndpointWorking(): void
    {

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
}