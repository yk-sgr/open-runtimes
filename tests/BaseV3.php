<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

abstract class BaseV3 extends TestCase
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    private function execute($body = '', $url = '/', $method = 'POST', $headers = [], $port = 3000) {
        $ch = \curl_init();

        $headers = \array_merge([
            'content-type' => 'text/plain',
            'x-open-runtimes-secret' => \getenv('OPEN_RUNTIMES_SECRET')
        ], $headers);
        $headersParsed = [];

        foreach ($headers as $header => $value) {
            $headersParsed[] = $header . ': ' . $value;
        }

        $responseHeaders = [];
        $optArray = array(
            CURLOPT_URL => 'http://localhost:' . $port . $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;
        
                $key = strtolower(trim($header[0]));
                $responseHeaders[$key] = trim($header[1]);

                if(\in_array($key, ['x-open-runtimes-logs', 'x-open-runtimes-errors'])) {
                    $responseHeaders[$key] = \urldecode($responseHeaders[$key]);
                }
        
                return $len;
            },
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => \is_array($body) ? \json_encode($body, JSON_FORCE_OBJECT) : $body,
            CURLOPT_HEADEROPT => \CURLHEADER_UNIFIED,
            CURLOPT_HTTPHEADER => $headersParsed
        );
        
        \curl_setopt_array($ch, $optArray);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, \CURLINFO_HTTP_CODE);

        \curl_close($ch);

        return [
            'code' => $code,
            'body' => $body,
            'headers' => $responseHeaders
        ];
    }

    public function testPlaintextResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'plaintextResponse']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('Hello World 👋', $response['body']);
    }

    public function testJsonResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'jsonResponse']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('application/json', $response['headers']['content-type']);

        $body = \json_decode($response['body'], true);

        self::assertEquals(true, $body['json']);
        self::assertEquals('Developers are awesome.', $body['message']);
    }

    public function testRedirectResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'redirectResponse']);
        self::assertEquals(301, $response['code']);
        self::assertEmpty($response['body']);
        self::assertEquals('https://github.com/', $response['headers']['location']);
    }

    public function testEmptyResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'emptyResponse']);
        self::assertEquals(204, $response['code']);
        self::assertEmpty($response['body']);
    }

    public function testNoResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'noResponse']);
        self::assertEquals(500, $response['code']);
        self::assertEmpty($response['body']);
        self::assertStringContainsString('Return statement missing. return context.res.empty() if no response is expected.', $response['headers']['x-open-runtimes-errors']);
    }

    public function testDoubleResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'doubleResponse']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('This should be returned.', $response['body']);
    }

    public function testHeadersResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'headersResponse', 'x-open-runtimes-custom-in-header' => 'notMissing']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('OK', $response['body']);
        self::assertEquals('first-value', $response['headers']['first-header']);
        self::assertEquals('missing', $response['headers']['second-header']);
        self::assertArrayNotHasKey('x-open-runtimes-custom-out-header', $response['headers']);
    }

    public function testStatusResponse(): void
    {
        $response = $this->execute(headers: ['x-action' => 'statusResponse']);
        self::assertEquals(404, $response['code']);
        self::assertEquals('FAIL', $response['body']);
    }

    public function testException(): void
    {
        $response = $this->execute(headers: ['x-action' => 'nonExistingAction']);
        self::assertEquals(500, $response['code']);
        self::assertEmpty($response['body']);
        self::assertEmpty($response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString('Unkonwn action', $response['headers']['x-open-runtimes-errors']);
        self::assertStringContainsString(\getenv('OPEN_RUNTIMES_ENTRYPOINT'), $response['headers']['x-open-runtimes-errors']);
    }

    public function testWrongSecret(): void
    {
        $response = $this->execute(headers: ['x-open-runtimes-secret' => 'wrongSecret']);
        self::assertEquals(500, $response['code']);
        self::assertEquals('Unauthorized. Provide correct "x-open-runtimes-secret" header.', $response['body']);
    }


    public function testEmptySecret(): void
    {
        $response = $this->execute(headers: ['x-action' => 'plaintextResponse', 'x-open-runtimes-secret' => '']);
        self::assertEquals(500, $response['code']);
        self::assertEquals('Unauthorized. Provide correct "x-open-runtimes-secret" header.', $response['body']);

        $response = $this->execute(headers: ['x-action' => 'plaintextResponse', 'x-open-runtimes-secret' => ''], port: 3001);
        self::assertEquals(200, $response['code']);
        self::assertEquals('Hello World 👋', $response['body']);
    }

    public function testRequestMethod(): void
    {
        $response = $this->execute(method: 'GET', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('GET', $response['body']);

        $response = $this->execute(method: 'POST', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('POST', $response['body']);

        $response = $this->execute(method: 'PUT', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('PUT', $response['body']);

        $response = $this->execute(method: 'DELETE', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('DELETE', $response['body']);

        $response = $this->execute(method: 'OPTIONS', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('OPTIONS', $response['body']);

        $response = $this->execute(method: 'TRACE', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('TRACE', $response['body']);

        $response = $this->execute(method: 'PATCH', headers: ['x-action' => 'requestMethod']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('PATCH', $response['body']);
    }

    public function testRequestUrl(): void
    {
        $response = $this->execute(url: '/', headers: ['x-action' => 'requestUrl']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('/', $response['body']);

        $response = $this->execute(url: '/some/path', headers: ['x-action' => 'requestUrl']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('/some/path', $response['body']);

        $response = $this->execute(url: '/path?key=value', headers: ['x-action' => 'requestUrl']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('/path?key=value', $response['body']);
    }

    public function testRequestHeaders(): void
    {
        $response = $this->execute(headers: ['x-action' => 'requestHeaders', 'x-first-header' => 'first-value', 'x-open-runtimes-custom-header' => 'should-be-hidden']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('application/json', $response['headers']['content-type']);

        $body = \json_decode($response['body'], true);

        self::assertEquals('requestHeaders', $body['x-action']);
        self::assertEquals('first-value', $body['x-first-header']);
        self::assertArrayNotHasKey('x-open-runtimes-custom-header', $body);
    }

    public function testRequestBodyPlaintext(): void
    {
        $response = $this->execute(body: 'Hello 👋', headers: ['x-action' => 'requestBodyPlaintext']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('Hello 👋', $response['body']);

        $response = $this->execute(body: '', headers: ['x-action' => 'requestBodyPlaintext']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('', $response['body']);

        $response = $this->execute(headers: ['x-action' => 'requestBodyPlaintext']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('', $response['body']);
    }

    public function testRequestBodyJson(): void
    {
        $response = $this->execute(body: '{"key1":"OK","key2":"👋","key3":"value3"}', headers: ['x-action' => 'requestBodyJson', 'content-type' => 'application/json']);
        self::assertEquals(200, $response['code']);

        $body = \json_decode($response['body'], true);

        self::assertEquals('OK', $body['key1']);
        self::assertEquals('👋', $body['key2']);
        self::assertEquals('{"key1":"OK","key2":"👋","key3":"value3"}', $body['raw']);

        $response = $this->execute(body: '{"data":"OK"}', headers: ['x-action' => 'requestBodyJson', 'content-type' => 'text/plain']);
        self::assertEquals(200, $response['code']);

        $body = \json_decode($response['body'], true);

        self::assertEquals('Missing key', $body['key1']);
        self::assertEquals('Missing key', $body['key2']);
        self::assertEquals('{"data":"OK"}', $body['raw']);

        $response = $this->execute(body: '', headers: ['x-action' => 'requestBodyJson', 'content-type' => 'application/json']);
        self::assertEquals(200, $response['code']);

        $body = \json_decode($response['body'], true);

        self::assertEquals('Missing key', $body['key1']);
        self::assertEquals('Missing key', $body['key2']);
        self::assertEquals('', $body['raw']);
    }

    public function testEnvVars(): void
    {
        $response = $this->execute(headers: ['x-action' => 'envVars']);
        self::assertEquals(200, $response['code']);
        self::assertEquals('application/json', $response['headers']['content-type']);

        $body = \json_decode($response['body'], true);

        self::assertEquals('customValue', $body['var']);
        self::assertNull($body['emptyVar']);
    }

    public function testLogs(): void
    {
        $response = $this->execute(headers: ['x-action' => 'logs' ]);
        self::assertEquals(200, $response['code']);
        self::assertEmpty($response['body']);
        self::assertStringContainsString('Debug log', $response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString(42, $response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString(4.2, $response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString('true', \strtolower($response['headers']['x-open-runtimes-logs'])); // strlower allows True in Python
        self::assertStringContainsString('Error log', $response['headers']['x-open-runtimes-errors']);
        self::assertStringNotContainsString('Native log', $response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString('Unsupported log noticed. Use context.log() or context.error() for logging.', $response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString('{"objectKey":"objectValue"}', $response['headers']['x-open-runtimes-logs']);
        self::assertStringContainsString('["arrayValue"]', $response['headers']['x-open-runtimes-logs']);
    }

    public function testLibrary(): void
    {
        $response = $this->execute(headers: ['x-action' => 'library'], body: '5');
        self::assertEquals(200, $response['code']);

        $body = \json_decode($response['body'], true);

        self::assertEquals('1', $body['todo']['userId']);
        self::assertEquals('5', $body['todo']['id']);
        self::assertEquals('laboriosam mollitia et enim quasi adipisci quia provident illum', $body['todo']['title']);
        self::assertEquals(false, $body['todo']['completed']);
    }
}