# Self updater
Automatically update your PrestaShop module by using GitHub's API

## Features
### Mission
Provide a framework that allows a module to self-update with GitHub's API

### Current features
- Find latest release
- Update to latest release
    - Includes automatic updates
- Set GitHub authentication for higher rate limit  

### Roadmap
The issue page will give you a good overview of the current roadmap and priorities:
https://github.com/firstred/modselfupdate/issues

## Installation
### Source
- Clone the source code: `git clone https://github.com/firstred/modselfupdate modselfupdate`
- Change directory: `cd modselfupdate`
- Install composer requirements: `composer install`
- Install node modules: `npm install`
- Grunt the module file: `grunt`

### Module installation
- Upload the module through FTP or your Back Office
- Install the module
- Check if there are any errors and correct them if necessary
- Profit!

## Compatibility
This module has been tested with this version:  
- `1.6.1.6`

## Usage
Tag your releases on GitHub with the version number (e.g. `1.2.0`). This framework will find the latest release and compare it with the currently installed version of the module.  
The first uploaded release zip will be downloaded and used to update the module.

## Requirements
- PHP > 5.3.3

## License
Do What The Fuck You Want To Public License 2.0