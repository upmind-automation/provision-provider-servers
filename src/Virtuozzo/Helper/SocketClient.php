<?php

declare(strict_types=1);

namespace Upmind\ProvisionProviders\Servers\Virtuozzo\Helper;

use SimpleXMLElement;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;
use Illuminate\Support\Str;

class SocketClient
{
    /** @var resource */
    protected $client;

    protected string $host;

    protected int $port;

    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    protected function makeAddress(): string
    {
        return sprintf("tcp://%s:%s", $this->host, $this->port);
    }

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

        $this->client = $client;
    }


    /**
     * @param int $length
     *
     * @return SimpleXMLElement
     * @throws ProvisionFunctionError
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

    public function checkResponseErrors(string $resultXml): SimpleXMLElement
    {
        try {
            $resultXml = $this->cleanUpNamespaces($resultXml);
            $resultXml = new SimpleXMLElement($resultXml);

        } catch (\Exception $e) {
            throw ProvisionFunctionError::create("Can't parse response", $e);
        }

        $type = $resultXml->origin;
        $env = $resultXml->data->{$type};

        if ($env->error) {
            throw ProvisionFunctionError::create((string)$env->error->message)
                ->withData([
                    'response' => $resultXml,
                ]);
        }

        if (!$env->children()) {
            throw ProvisionFunctionError::create('Empty provider api response');
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

        return $result;
    }


    public function checkAuth(string $response): void
    {
        try {
            $xml = new SimpleXMLElement($response);
        } catch (\Exception $e) {
            throw ProvisionFunctionError::create("Can't parse response", $e);
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
     * @param string $content
     *
     * @return int
     * @throws ProvisionFunctionError
     */
    public function write(string $content): int
    {
        if (false === $written = fwrite($this->client, $content . "\0")) {
            throw ProvisionFunctionError::create("Error occurred on writing to the socket");
        }

        return $written;
    }

    /**
     * @return void
     * @throws \ProvisionFunctionError
     */
    public function close(): void
    {
        if (!is_null($this->client)) {
            if (false === fclose($this->client)) {
                throw ProvisionFunctionError::create("Can't close the socket");
            };
        }
    }
}
