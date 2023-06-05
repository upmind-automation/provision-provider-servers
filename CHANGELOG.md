# Changelog

All notable changes to the package will be documented in this file.

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
