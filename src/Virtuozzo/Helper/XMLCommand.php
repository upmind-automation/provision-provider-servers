<?php

namespace Upmind\ProvisionProviders\Servers\Virtuozzo\Helper;

use DOMDocument;
use DOMElement;
use Exception;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;

class XMLCommand
{
    public const XML_VERSION = '1.0';

    public const XML_ENCODING = 'utf-8';
    protected string $apiVersion;

    protected DOMDocument $domDocument;

    protected string $interface;

    protected array $realms = [
        'system' => '00000000-0000-0000-0000-000000000000',
    ];

    protected array $rootElementAttrs = [
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        'xmlns:ns2' => 'http://www.swsoft.com/webservices/vzl/4.0.0/types',
    ];

    public function __construct(
        string $version = '4.0.0',
        string $encoding = self::XML_ENCODING,
        string $interface = 'vzpenvm'
    )
    {
        $this->domDocument = new DOMDocument(self::XML_VERSION, $encoding);

        $this->apiVersion = $version;

        $this->setInterface($interface);
    }

    public function setInterface(string $type)
    {
        $this->interface = $type;
    }

    /**
     * @param DOMElement $body
     * @param bool $target
     * @return string
     * @throws ProvisionFunctionError
     */
    public function build(DOMElement $body, bool $target = true): string
    {
        try {
            $rootWrapper = $this->makeRootWrapper();
            $this->getDomDocument()->appendChild($rootWrapper);

            if ($target) {
                $rootWrapper->appendChild($this->createElement('target', $this->interface));
            }

            $rootWrapper->appendChild($body);

            return $this->toString();
        } catch (Exception $e) {
            throw ProvisionFunctionError::create($e->getMessage());
        }
    }

    /**
     * @param bool $formatOutput
     *
     * @return string
     */
    public function toString(bool $formatOutput = false): string
    {
        $this->domDocument->formatOutput = $formatOutput;
        if (false !== $command = $this->domDocument->saveXML()) {
            return $command;
        }

        throw ProvisionFunctionError::create("Can't build XML command");
    }

    /**
     * @return DOMDocument
     */
    protected function getDomDocument(): DOMDocument
    {
        return $this->domDocument;
    }

    protected function makeRootWrapper(): DOMElement
    {
        $rootElement = $this->createElement('packet');

        foreach ($this->rootElementAttrs as $attr => $value) {
            $rootElement->setAttribute($attr, $value);
        }

        $rootElement->setAttribute('version', $this->apiVersion);

        return $rootElement;
    }

    /**
     * Creates new DOM element with the escaped value
     *
     * @param string $name
     * @param string|null $value
     *
     * @return DOMElement
     */
    public function createElement(string $name, ?string $value = null): DOMElement
    {
        try {
            return $this->getDomDocument()->createElement(
                $name,
                !is_null($value) ? htmlentities($value, ENT_XML1) : null
            );
        } catch (Exception $e) {
            throw ProvisionFunctionError::create("Can't build XML command");
        }
    }

    /**
     * @param DOMElement|array $body
     * @param DOMElement $base
     * @return void
     */
    public function setChildElements($body, DOMElement $base): void
    {
        if (is_array($body)) {
            foreach ($body as $element) {
                $base->appendChild($element);
            }
        } else {
            $base->appendChild($body);
        }
    }

    /**
     * @param string $command
     * @param DOMElement|array $body
     * @return DOMElement
     */
    protected function makeCommandElements(string $command, $body): DOMElement
    {
        try {
            $data = $this->createElement('data');
            $interface = $this->createElement($this->interface);
            $command = $this->createElement($command);

            $this->setChildElements($body, $command);

            $interface->appendChild($command);
            $data->appendChild($interface);

            return $data;
        } catch (Exception $e) {
            throw ProvisionFunctionError::create("Can't build XML command");
        }
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xmlns:ns2="http://www.swsoft.com/webservices/vzl/4.0.0/types" version="4.0.0"
        id="2">
            <data>
                <system>
                    <login xsi:type="ns2:auth_nameType">
                        <name>QWRtaW5pc3RyYXRvcg==</name>
                        <realm>00000000-0000-0000-0000-000000000000</realm>
                        <password>MXEydzNl</password>
                    </login>
                </system>
            </data>
        </packet>
     */
    public function makeLoginBody(string $userName, string $password): string
    {
        $this->setInterface('system');

        $body = $this->makeCommandElements('login', [
            $this->createElement('name', $userName),
            $this->createElement('realm', $this->realms['system']),
            $this->createElement('password', $password)
        ]);

        $login = $body->getElementsByTagName('login')[0];
        $login->setAttribute('xsi:type', 'ns2:auth_nameType');

        return $this->build($body,false);
    }

    public function makeCreateServerBody(
        string $label,
        string $location,
        string $image,
        string $size
    ): string
    {
        $name = $this->createElement('name', $label);
        $hostname = $this->createElement('hostname', $label);
        $template = $this->createElement('os_template');
        $template->appendChild($this->createElement('name', $image));
        $size = $this->createElement('memory_size', $size);
        $homepath = $this->createElement('home_path', $location);

        $config = $this->createConfigElement([
            $name,
            $hostname,
            $template,
            $size,
            $homepath,
        ]);

        $body = $this->makeCommandElements('create', $config);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.5.0" id="2">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <get_info>
                        <eid>a5961178-14d2-40cc-b1e7-41b562a2f4c6</eid>
                        <config/>
                    </get_info>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeServerInfoBody(string $severId): string
    {
        $body = $this->makeCommandElements('get_info', [
            $this->createElement('eid', $severId),
            $this->createConfigElement(),
        ]);

        return $this->build($body);
    }

    /**
     * @param DOMElement|array $body
     * @return DOMElement
     */
    public function createConfigElement($body = null): DOMElement
    {
        try {
            $config = $this->createElement('config');
            if (!is_null($body)) {
                $this->setChildElements($body, $config);
            }

            return $config;

        } catch (Exception $e) {
            throw ProvisionFunctionError::create("Can't build XML command");
        }
    }

    public function makeSetRootPasswordBody(string $serverId, string $password): string
    {
        $body = $this->makeCommandElements('set_user_password', [
            $this->createElement('eid', $serverId),
            $this->createElement('name', 'root'),
            $this->createElement('password', $password),
        ]);

        return $this->build($body);
    }

    public function makeServerConfigBody(string $serverId, string $size): string
    {
        $body = $this->makeCommandElements('set', [
            $this->createElement('eid', $serverId),
            $this->createConfigElement([
                $this->createElement('memory_size', $size)
            ])
        ]);

        return $this->build($body);
    }

    public function makeRestartServerBody(string $serverId): string
    {
        $body = $this->makeCommandElements('restart',
            $this->createElement('eid', $serverId),
        );

        return $this->build($body);
    }

    public function makeStopServerBody(string $serverId): string
    {
        $body = $this->makeCommandElements('stop', [
            $this->createElement('eid', $serverId),
            $this->createElement('force'),
        ]);

        return $this->build($body);
    }

    public function makeStartServerBody(string $serverId): string
    {
        $body = $this->makeCommandElements('start',
            $this->createElement('eid', $serverId)
        );

        return $this->build($body);
    }

    public function makeDestroyServerBody(string $serverId): string
    {
        $body = $this->makeCommandElements('destroy',
            $this->createElement('eid', $serverId)
        );

        return $this->build($body);
    }
}
