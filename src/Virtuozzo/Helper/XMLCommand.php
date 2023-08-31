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

    /**
     * @return DOMElement
     */
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

    /**
     * @param DOMElement|array $body
     * @return DOMElement
     */
    protected function createConfigElement($body = null): DOMElement
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
    public function makeLogin(string $userName, string $password): string
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


    /*
       <?xml version="1.0" encoding="utf-8"?>
       <packet version="4.5.0" id="2">
           <target>vzpenvm</target>
           <data>
               <vzpenvm>
                   <create>
                        <name>1d59c6a2-bb4d-e34c-8fcc-2f8d74e12a97</name>
                        <os_template>
                            <name>redhat-as3-minimal</name>
                        </os_template>
                        <memory_size>512</memory_size>
                        <video_memory_size>3</video_memory_size>
                        <cpu_count>1</cpu_count>
                        <home_path>/var/parallels/MyVM.pvm/config.pvs</home_path>
                   </create>
               </vzpenvm>
           </data>
       </packet>
   */
    public function makeCreateServer(
        string $label,
        string $location,
        string $image,
        int $videoMemorySize,
        int $cpuCount,
        int $memorySize
    ): string
    {
        if(is_numeric($image)) {
            $template = $this->createElement('os_template', $image);
        } else {
            $template = $this->createElement('os_template');
            $template->appendChild($this->createElement('name', $image));
        }

        $name = $this->createElement('name', $label);
        $size = $this->createElement('memory_size', $memorySize);
        $videoSize = $this->createElement('video_memory_size', $videoMemorySize);
        $cpu = $this->createElement('cpu_count', $cpuCount);
        $homepath = $this->createElement('home_path', $location);

        $config = $this->createConfigElement([
            $name,
            $template,
            $size,
            $videoSize,
            $cpu,
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
    public function makeServerInfo(string $severId): string
    {
        $body = $this->makeCommandElements('get_info', [
            $this->createElement('eid', $severId),
            $this->createConfigElement(),
        ]);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <set_user_password>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                        <name>root</name>
                        <password>bXlwYXNz</password>
                    </set_user_password>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeSetRootPassword(string $serverId, string $password): string
    {
        $body = $this->makeCommandElements('set_user_password', [
            $this->createElement('eid', $serverId),
            $this->createElement('name', 'root'),
            $this->createElement('password', $password),
        ]);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <set>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                        <config>
                            <memory_size>512</memory_size>
                            <video_memory_size>3</video_memory_size>
                            <cpu_count>1</cpu_count>
                        </config>
                    </set>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeSetServerConfig(
        string $serverId,
        int $videoMemorySize,
        int $cpuCount,
        int $memorySize
    ): string
    {
        $body = $this->makeCommandElements('set', [
            $this->createElement('eid', $serverId),
            $this->createConfigElement([
                $this->createElement('memory_size', $memorySize),
                 $this->createElement('video_memory_size', $videoMemorySize),
                $this->createElement('cpu_count', $cpuCount),
            ])
        ]);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <restart>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                    </restart>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeRestartServer(string $serverId): string
    {
        $body = $this->makeCommandElements('restart',
            $this->createElement('eid', $serverId),
        );

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <stop>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                        <force/>
                    </stop>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeStopServer(string $serverId): string
    {
        $body = $this->makeCommandElements('stop', [
            $this->createElement('eid', $serverId),
            $this->createElement('force'),
        ]);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <start>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                    </start>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeStartServer(string $serverId): string
    {
        $body = $this->makeCommandElements('start',
            $this->createElement('eid', $serverId)
        );

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <destroy>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                    </destroy>
                </vzpenvm>
            </data>
        </packet>
    */
    public function makeDestroyServer(string $serverId): string
    {
        $body = $this->makeCommandElements('destroy',
            $this->createElement('eid', $serverId)
        );

        return $this->build($body);
    }
}
