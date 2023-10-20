<?php

namespace Upmind\ProvisionProviders\Servers\Virtuozzo\Helper;

use DOMDocument;
use DOMElement;
use Exception;
use Upmind\ProvisionBase\Exception\ProvisionFunctionError;

class XMLCommand
{
    public const XML_VERSION = '1.0';

    public const XML_ENCODING = 'UTF-8';
    protected string $apiVersion;

    protected DOMDocument $domDocument;

    protected string $interface;

    protected array $realms = [
        'system' => '00000000-0000-0000-0000-000000000000',
    ];

    protected array $rootElementAttrs = [
        'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
        'xmlns:ns2' => 'http://www.swsoft.com/webservices/vzl/4.0.0/types',
        'xmlns:ns3' => 'http://www.swsoft.com/webservices/vzp/4.0.0/vzptypes',
        'xmlns:ns4' => 'http://www.swsoft.com/webservices/vza/4.0.0/vzatypes'
    ];

    public function __construct(
        string $version = '7.0.0',
        string $encoding = self::XML_ENCODING,
        string $interface = 'vzpenvm'
    ) {
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

            return $this->toString(true);
        } catch (Exception $e) {
            throw ProvisionFunctionError::create('Build request failed', $e);
        }
    }

    /**
     * @param bool $formatOutput
     *
     * @return string
     */
    public function toString(bool $formatOutput = false): string
    {
        $this->getDomDocument()->formatOutput = $formatOutput;
        if (false !== $command = $this->domDocument->saveXML()) {
            return $command;
        }

        throw ProvisionFunctionError::create("Can't convert XML to string");
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
            throw ProvisionFunctionError::create("Can't build XML command", $e);
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
    protected function setCommandElements(string $command, $body): DOMElement
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
            throw ProvisionFunctionError::create("Can't build XML command", $e);
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
            throw ProvisionFunctionError::create("Can't build XML command", $e);
        }
    }

    /**
     * @param string $image
     * @param string $platform
     * @return DOMElement
     */
    public function createOSElement(string $image, string $platform): DOMElement
    {
        $template = $this->createElement('os');
        $template->setAttribute('xsi:type', 'ns2:osType');

        $template->appendChild($this->createElement('name', $image));
        $template->appendChild($this->createElement('platform', $platform));

        return $template;
    }

    /**
     * @param int $diskSize
     * @return DOMElement
     */
    public function createHardDiskDeviceElement(int $diskSize): DOMElement
    {
        $device = $this->createElement('device');
        $device->setAttribute('xsi:type', 'ns3:vm_hard_disk_device');

        $device->appendChild($this->createElement('boot_sequence_index', 0));
        $device->appendChild($this->createElement('is_bootable'));
        $device->appendChild($this->createElement('enabled', 1));
        $device->appendChild($this->createElement('connected', 1));
        $device->appendChild($this->createElement('emulation_type', 1));
        $device->appendChild($this->createElement('disk_type', 1));
        $device->appendChild($this->createElement('size', $diskSize));

        return $device;
    }

    /**
     * @return DOMElement
     */
    public function createNetworkDeviceElement(?string $ip = '0.0.0.0'): DOMElement
    {
        $device = $this->createElement('device');
        $device->setAttribute('xsi:type', 'ns3:vm_network_device');

        $device->appendChild($this->createElement('enabled', 1));
        $device->appendChild($this->createElement('connected', 1));
        $device->appendChild($this->createElement('emulation_type', 1));
        $device->appendChild($this->createElement('default_gateway'));
        $device->appendChild($this->createElement('virtual_network_id'));
        $ipAddress = $this->createElement('ip_address');
        $ipAddress->appendChild($this->createElement('ip', $ip));
        $device->appendChild($ipAddress);

        return $device;
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
    public function login(string $userName, string $password): string
    {
        $this->setInterface('system');

        $body = $this->setCommandElements('login', [
            $this->createElement('name', $userName),
            $this->createElement('realm', $this->realms['system']),
            $this->createElement('password', $password)
        ]);

        $login = $body->getElementsByTagName('login')[0];
        $login->setAttribute('xsi:type', 'ns2:auth_nameType');

        return $this->build($body, false);
    }

    /*
       <?xml version="1.0" encoding="utf-8"?>
       <packet version="4.5.0" id="2">
           <target>vzpenvm</target>
           <data>
               <vzpenvm>
                   <create>
                        <name>1d59c6a2-bb4d-e34c-8fcc-2f8d74e12a97</name>
                        <os>
                            <name>Ubuntu Linux</name>
                            <platform>Linux</platform>
                        </os>
                        <memory_size>512</memory_size>
                        <cpu_count>1</cpu_count>
                        <home_path>/var/parallels/MyVM.pvm/config.pvs</home_path>
                   </create>
               </vzpenvm>
           </data>
       </packet>
   */
    public function createServer(
        string $label,
        string $location,
        string $image,
        string $platform,
        int    $memorySize,
        int    $cpuCount,
        int    $diskSize
    ): string {
        $template = $this->createOSElement($image, $platform);

        $deviceList = $this->createElement('device_list');
        $deviceList->appendChild($this->createHardDiskDeviceElement($diskSize));
        $deviceList->appendChild($this->createNetworkDeviceElement());

        $name = $this->createElement('name', $label);
        $size = $this->createElement('memory_size', $memorySize);
        $cpu = $this->createElement('cpu_count', $cpuCount);
        $homepath = $this->createElement('home_path', $location);

        $config = $this->createConfigElement([
            $name,
            $template,
            $size,
            $deviceList,
            $cpu,
            $homepath,
        ]);

        $body = $this->setCommandElements('create', $config);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0" id="2">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <install_tools>
                        <eid>a5961178-14d2-40cc-b1e7-41b562a2f4c6</eid>
                    </install_tools>
                </vzpenvm>
            </data>
        </packet>
    */
    public function installGuestTools(string $serverId): string
    {
        $body = $this->setCommandElements('install_tools', [
            $this->createElement('eid', $serverId),
        ]);

        return $this->build($body);
    }

    /*
        <packet version="4.0.0" id="2">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <get_console_info>
                        <eid>a5961178-14d2-40cc-b1e7-41b562a2f4c6</eid>
                    </get_console_info>
                </vzpenvm>
            </data>
        </packet>
    */
    public function getConsoleInfo(string $serverId): string
    {
        $body = $this->setCommandElements('get_console_info', [
            $this->createElement('eid', $serverId),
        ]);

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
    public function serverInfo(string $severId): string
    {
        $body = $this->setCommandElements('get_info', [
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
                        <user>root</user>
                        <password>bXlwYXNz</password>
                    </set_user_password>
                </vzpenvm>
            </data>
        </packet>
    */
    public function setRootPassword(string $serverId, string $password): string
    {
        $body = $this->setCommandElements('set_user_password', [
            $this->createElement('eid', $serverId),
            $this->createElement('user', 'root'),
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
                            <cpu_count>1</cpu_count>
                            <device_list>
                                <device xsi:type="ns3:vm_hard_disk_device">
                                    <size>10<size>
                                </device>
                            </device_list>
                        </config>
                    </set>
                </vzpenvm>
            </data>
        </packet>
    */
    public function setServerConfig(
        string $serverId,
        int    $memorySize,
        int    $cpuCount,
        string $sysName,
        int    $diskSize,
        string $ip
    ): string {
        $deviceList = $this->createElement('device_list');

        $device = $this->createHardDiskDeviceElement($diskSize);
        $networkDevice = $this->createNetworkDeviceElement($ip);

        $device->appendChild($this->createElement('sys_name', $sysName));
        $device->appendChild($this->createElement('recreate'));
        $device->appendChild($this->createElement('is_boot_in_use'));
        $device->appendChild($this->createElement('resize_fs'));

        $deviceList->appendChild($device);
        $deviceList->appendChild($networkDevice);
        $body = $this->setCommandElements('set', [
            $this->createElement('eid', $serverId),

            $this->createConfigElement([
                $this->createElement('memory_size', $memorySize),
                $this->createElement('cpu_count', $cpuCount),
                $deviceList,
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
    public function restartServer(string $serverId): string
    {
        $body = $this->setCommandElements(
            'restart',
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
    public function stopServer(string $serverId): string
    {
        $body = $this->setCommandElements('stop', [
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
    public function startServer(string $serverId): string
    {
        $body = $this->setCommandElements(
            'start',
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
    public function destroyServer(string $serverId): string
    {
        $body = $this->setCommandElements(
            'destroy',
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
                    <set>
                        <eid>6dbd99dc-f212-45de-a5f4-ddb78a2b5280</eid>
                        <config>
                            <os xsi:type="ns2:osType">
                                <name>Ubuntu Linux</name>
                                <platform>Linux</platform>
                            </os>
                        </config>
                    </set>
                </vzpenvm>
            </data>
        </packet>
    */
    public function setServerImage(string $serverId, string $image, string $platform): string
    {
        $body = $this->setCommandElements('set', [
            $this->createElement('eid', $serverId),
            $this->createConfigElement([
                $this->createOSElement($image, $platform)
            ]),
        ]);

        return $this->build($body);
    }

    /*
        <?xml version="1.0" encoding="utf-8"?>
        <packet version="4.0.0">
            <target>vzpenvm</target>
            <data>
                <vzpenvm>
                    <get_vt_settings/>
                </vzpenvm>
            </data>
        </packet>
    */
    public function getVTSettings(): string
    {
        $body = $this->setCommandElements('get_vt_settings', [
        ]);

        return $this->build($body);
    }
}
