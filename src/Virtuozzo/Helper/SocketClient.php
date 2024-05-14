<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtuozzo\Helper;

use SimpleXMLElement;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

class SocketClient
{
    /** @var resource|null */
    protected $client;

    protected string $host;

    protected int $port;

    /** @var LoggerInterface|null */
    protected $logger;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Set a PSR-3 logger.
     */
    public function setPsrLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    protected function writeLog(string $text, string $action)
    {
        if (isset($this->logger)) {
            $this->logger->debug(sprintf("Virtuozzo [%s]:\n %s", $action, $this->formatLog($text)));
        }
    }

    protected function formatLog(string $xml): string
    {
        $xml = trim($xml);

        $messages = explode("\0", $xml);
        if (count($messages) > 1) {
            return implode("\0", array_map([$this, 'formatLog'], $messages));
        }

        try {
            $dom = new \DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXml($xml);

            return $dom->saveXML() ?: $xml;
        } catch (Throwable $e) {
            // ignore error and return original xml
            return $xml;
        }
    }

    protected function makeAddress(): string
    {
        return sprintf("tcp://%s:%s", $this->host, $this->port);
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function connect(int $flags, int $timeout = 1800): void
    {
        if (
            false === $client = stream_socket_client(
                $this->makeAddress(),
                $errorCode,
                $errorMessage,
                $timeout,
                $flags,
                stream_context_create()
            )
        ) {
            throw ProvisionFunctionError::create("Can't connect to socket: $errorCode, $errorMessage");
        }

        if (!is_resource($client) || $errorCode !== 0) {
            throw ProvisionFunctionError::create("Can't connect to socket: $errorCode, $errorMessage");
        }

        $this->writeLog("Stream opened to " . $this->host . " port " . $this->port, "Connection made");

        $this->client = $client;
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function request(string $xml): SimpleXMLElement
    {
        $resultXml = '';
        $written = $this->write($xml);

        if ($written > 0) {
            //skip login response
            $this->read();

            $auth = $this->read();

            $this->checkAuth($auth);

            $resultXml = $this->read();
        }

        return $this->checkResponseErrors($resultXml);
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function checkResponseErrors(string $response): SimpleXMLElement
    {
        try {
            $resultXml = $this->cleanUpNamespaces($response);
            $resultXml = new SimpleXMLElement($resultXml);
        } catch (\Exception $e) {
            throw ProvisionFunctionError::create("Can't parse response", $e)
                ->withData([
                    'response' => $response,
                ]);
        }

        $type = $resultXml->origin;
        $env = $resultXml->data->{$type};

        if ($env->error) {
            throw ProvisionFunctionError::create((string)$env->error->message)
                ->withData([
                    'response' => $response,
                ]);
        }

        if (!$env->children()) {
            throw ProvisionFunctionError::create('Empty provider api response')
                ->withData([
                    'response' => $response,
                ]);
        }

        return $resultXml;
    }

    public function read(): string
    {
        $result = '';

        do {
            $row = fread($this->client, 1);
            $result .= $row;
        } while ($row != "\0");

        $this->writeLog($result, "READ");

        return $result;
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function checkAuth(string $response): void
    {
        try {
            $xml = new SimpleXMLElement($response);
        } catch (\Exception $e) {
            throw ProvisionFunctionError::create("Can't parse response", $e)
                ->withData([
                    'response' => $response,
                ]);
        }

        if ($error = $this->parseXmlError($xml->xpath('//ns1:message'))) {
            throw ProvisionFunctionError::create($error)
                ->withData([
                    'response' => $xml,
                ]);
        }
    }

    private function parseXmlError(array $errors): ?string
    {
        $result = [];

        foreach ($errors as $error) {
            if (Str::contains($error, 'System errors')) {
                $result[] = $error;
            }
        }

        if ($result) {
            return sprintf("Provider API Error: %s", implode(', ', $result));
        }

        return null;
    }

    public function cleanUpNamespaces($xml_root)
    {
        $xml_root = str_replace("xsi:type", "xsitype", $xml_root);

        $record_element = new SimpleXMLElement($xml_root);
        foreach ($record_element->getDocNamespaces() as $name => $ns) {
            if ($name != "") {
                $xml_root = str_replace($name . ":", "", $xml_root);
            }
        }

        $record_element = new SimpleXMLElement($xml_root);
        foreach ($record_element->children() as $field) {
            $field_element = new SimpleXMLElement($field->asXML());

            foreach ($field_element->getDocNamespaces() as $name2 => $ns2) {
                if ($name2 != "") {
                    $xml_root = str_replace($name2 . ":", "", $xml_root);
                }
            }
        }

        return $xml_root;
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function write(string $content): int
    {
        $this->writeLog($content, "WRITE");

        if (false === $written = fwrite($this->client, $content . "\0")) {
            throw ProvisionFunctionError::create("Error occurred on writing to the socket");
        }

        return $written;
    }

    /**
     * @throws \Upmind\ProvisionBase\Exception\ProvisionFunctionError
     */
    public function close(): void
    {
        if (!is_null($this->client)) {
            if (false === fclose($this->client)) {
                throw ProvisionFunctionError::create("Can't close the socket");
            }

            $this->writeLog("Disconnected", "DISCONNECT");
        }
    }
}
