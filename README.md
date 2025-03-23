# Quicksilver Scrubber

![Quicksilver Scrubber v1.x](https://img.shields.io/badge/Quicksilver_Scrubber-v1.x-green.svg)  ![Terminus v3.x Compatible](https://img.shields.io/badge/terminus-v3.x-green.svg)

This Quicksilver project is used in conjunction with the Scrubber tool to help scrub the database of PII or sensitive data. The goal of this is

## Requirements

- Configured Scrubber Data Configuration File
- Configured CircleCI Job

### Installation

This project is designed to be included from a site's `composer.json` file, and placed in its appropriate installation directory by [Composer Installers](https://github.com/composer/installers).

In order for this to work, you should have the following in your composer.json file:

```json
{
  "require": {
    "composer/installers": "^1.0.20"
  },
  "extra": {
    "installer-paths": {
      "web/private/scripts/quicksilver": ["type:quicksilver-script"]
    }
  }
}
```

The project can be included by using the command:

`composer require kanopi/quicksilver-scrubber:^1`

### Setting Secrets

The project uses [Terminus Secrets Manager Plugin](https://github.com/pantheon-systems/terminus-secrets-manager-plugin) to operate. The following secrets will need to be set in order for you
to move forward with the scripts. These are what is read within the scrubber file and used.

```bash
terminus secret:set site-id scrubber_processor 'circleci'
terminus secret:set site-id token 'XXXXX'
terminus secret:set site-id repo_source 'github'
terminus secret:set site-id repo_owner 'XXXXX'
terminus secret:set site-id repo_name 'XXXX'
terminus secret:set site-id primary_branch 'XXXX'
```

### Example `pantheon.yml`

Here's an example of what your `pantheon.yml` would look like if this were the only Quicksilver operation you wanted to use.

```yaml
api_version: 1

workflows:
  database_clone:
    after:
      - type: webphp
        description: Trigger Scrubber
        script: private/scripts/quicksilver/quicksilver-scurbber/scrubber.php
```
