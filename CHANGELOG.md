<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: CC0-1.0
-->
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## 2.4.0 - 2025-08-29

### Added
- search providers marked as external sources 

### Changed
- npm packages updated 
- max NC version bumped to 32

## 2.3.0 - 2025-02-13

### Added

- Helper text to instance URL in Personal settings
- Second Dashboard widget to show already read notifications
- Option to manually trigger custom protocol handler registration if prompt is not shown on page load

### Fixed

- Fixed incorrect encrypted token handling (regression)


## 2.1.0 - 2024-09-16

Maintenance update

### Changed

- Drop support for NC 26, 27
- Update pkgs

## 2.0.7 – 2024-04-24

Maintenance update

### Changed

- Update npm packages (security)
- Localization (l10n) updates

## 2.0.6 - 2024-03-28

### Changed

- Support Nextcloud 29
- Update npm pkgs
- Use dashboard widget component from nextcloud/vue

## 2.0.5 - 2023-12-11

Maintenance update

### Added

- Added Nextcloud 28 support

### Changed

- Updated npm packages
- Updated l10n (localization)

## 2.0.4 - 2023-05-31

### Fixed

- Add missing composer install in krankerl config

## 2.0.3 - 2023-05-31

### Fixed

- vendor dir was excluded from the release archive

## 2.0.2 - 2023-05-17

Maintenance update

### Added

- Added Nextcloud 27 support

## 2.0.1 – 2023-02-22
### Changed
- implement reference provider to search for topics and posts
- NC 26 compat
- use @nextcloud/vue 7.6.1

## 1.0.4 – 2022-08-25
### Added
- optional navigation link

### Changed
- use node 16, bump js libs, adjust to new eslint config
- use material icons
- improve frontend style

## 1.0.2 – 2021-11-12
### Changed
- bump max NC version to 24
- improve release action
- clarify package dependencies

## 1.0.1 – 2021-06-28
### Changed
- stop polling widget content when document is hidden
- bump js libs
- get rid of all deprecated stuff
- bump min NC version to 22
- cleanup backend code

## 1.0.0 – 2021-03-19
### Changed
- bump js libs

## 0.0.9 – 2021-02-16
### Changed
- app certificate

## 0.0.8 – 2021-02-12
### Changed
- bump js libs
- bump max NC version

### Fixed
- import nc dialogs style

## 0.0.7 – 2021-01-21
### Fixed
- avoid using invalid Discourse URL

## 0.0.6 – 2021-01-01
### Changed
- bump js libs

### Fixed
- browser detection

## 0.0.5 – 2020-10-22
### Added
- automatic releases

### Changed
- use Webpack 5 and style lint

## 0.0.4 – 2020-10-12
### Changed
- various small improvements in backend

### Fixed
- use browser's real cryto tool to make a nonce

## 0.0.3 – 2020-10-02
### Added
- handle more notification types
- unified search providers for topics and posts
- lots of translations

### Changed
- improve setting hints
- improve code quality
- bump libs

## 0.0.2 – 2020-09-21
### Changed
* improve authentication design
* improve widget empty content

## 0.0.1 – 2020-09-02
### Added
* the app
