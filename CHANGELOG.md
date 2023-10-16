# Changelog

All notable changes to the package will be documented in this file.

## [v3.0.3](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v3.0.3) - 2023-10-16

- Update SolusVM ApiClient return response_body if responseData is empty
- Toggle SolusVM virtualization_type parameter to lowercase

## [v3.0.2](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v3.0.2) - 2023-10-12

- Fix Linode error handling sprintf() error

## [v3.0.1](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v3.0.1) - 2023-10-12

- Update Linode, SolusVM nd Virtualizor to require size parameter

## [v3.0.0](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v3.0.0) - 2023-10-12

- Update CreateParams, ResizeParams and ServerInfoResult add `disk_mb`, `memory_mb` + `cpu_cores` values as an alternative to `size`
- Implement Virtuozzo provider

## [v2.3.3](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.3.3) - 2023-06-28

- Fix Virtualizor reinstall() for VPSes not hosted on the default server/node

## [v2.3.2](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.3.2) - 2023-06-15

- Fix SolusVM ApiClient listTemplates() error for 'invalid' virtualization types
  - E.g., VMs that return a virtualization type `"xenhvm"` but this endpoint expects `"xen hvm"`

## [v2.3.1](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.3.1) - 2023-06-09

- Fix Virtualizor create() for location_type=geographic configurations

## [v2.3](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.3) - 2023-06-09

- Increase Virtualizor API request timeout to 60
- Add Server Group location type to Virtualizor provision configurations

## [v2.2](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.2) - 2023-06-09

- Add `ignore_ssl_errors` boolean to Virtualizor configuration
- Update Virtualizor ApiClient::changeVirtualServerRootPass() loosen response check
- Tweak Virtualizor ApiClient connect + request timeouts and add error handling

## [v2.1.4](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.1.4) - 2023-06-07

- Set SolusVM API client connect_timeout to 5 seconds
- Set SolusVM API client timeout to 10 seconds

## [v2.1.3](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.1.3) - 2023-06-05

- Fix SolusVM getServerInfoResult() location for suspended instances

## [v2.1.2](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.1.2) - 2023-06-05

- Fix empty location error in SolusVM getServerInfoResult()

## [v2.1.0](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.1.0) - 2023-06-02

- Add `node` to ServerInfoResult
- Make `ip_address` nullable in ServerInfoResult
- Update SolusVM ServerInfoResult, set `location` according to node location and
  set `node` as the node name
- Implement Virtualizor provider

## [v2.0.1](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.0.1) - 2023-05-18

- Update SolusVM getServerInfoResult() fix undefined index errors for suspended
  servers

## [v2.0](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v2.0) - 2023-04-26

- Add getSshConnectionCommand() function
- Add `virtualization_type`, `customer_identifier`, `email` parameters to CreateParams
  and return values to ServerInfoResult
- Loosen ServerInfoResult `hostname` return value validation to allow non-domain
  hostnames
- Add SolusVM provider

## [v1.0.2](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v1.0.2) - 2023-03-01

- Fix Linode findImage() and findType() id regexes

## [v1.0.1](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v1.0.1) - 2023-01-13

- Reduce min upmind/provision-provider-base version constraint to 3.4

## [v1.0](https://github.com/upmind-automation/provision-provider-servers/releases/tag/v1.0) - 2023-01-13

Initial public release
