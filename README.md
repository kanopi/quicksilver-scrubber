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
terminus secret:set site-id scrubber_processor 'circleci' --type=env --scope=web
terminus secret:set site-id token 'XXXXX' --type=env --scope=web
terminus secret:set site-id repo_source 'github' --type=env --scope=web
terminus secret:set site-id repo_owner 'XXXXX' --type=env --scope=web
terminus secret:set site-id repo_name 'XXXX' --type=env --scope=web
terminus secret:set site-id primary_branch 'XXXX' --type=env --scope=web
```

### Example `pantheon.yml`

Here's an example of what your `pantheon.yml` would look like if this were the only Quicksilver operation you wanted to use.

```yaml
api_version: 1

workflows:
  clone_database:
    after:
      - type: webphp
        description: Trigger Scrubber
        script: private/scripts/quicksilver/scrubber.php
```

### Example `scrubber.yml`

Look at [scubber.yml](examples/scrubber.yml) for the basic config that will work with CircleCI.

Place the `scrubber.yml` file at the root of your repo.

One of the key things around the example file is that it has the environment values set to access the Pantheon database that are set in CircleCI.

Also make sure that you take the time to make the scrubber project specific by scrubbing the specific tables for your project.  For notes on how to set that refer to the documentation.
https://github.com/kanopi/scrubber/blob/main/docs/datatables.md

### Example CircleCI config

Here are the updates you'd need to make to your CircleCI config.yml

Add the orbs

```yaml
orbs:
  ci-tools: kanopi/ci-tools@2  
  scrubber: kanopi/scrubber@1
```

Add the parameters

```yaml
after_db_clone: &after_db_clone << pipeline.parameters.after_db_clone >>
parameters:
  after_db_clone:
    type: boolean
    default: false
  target_url:
    type: string
    default: ''
  site_name:
    type: string
    default: ''
  site_env:
    type: string
    default: ''
```
Add the scrub data workflow.
```yaml
workflows: 
  scrub-data:
    when: *after_db_clone
    jobs:
      - scrubber/scrub:
          config: scrubber.yml
          context: kanopi-code
          pre-steps:
              - run:
                  name: Setup quicksilver environment variables
                  command: |
                    echo "export SITE_URL=<< pipeline.parameters.target_url >>" >> "$BASH_ENV"
                    echo "export SITE_NAME=<< pipeline.parameters.site_name >>" >> "$BASH_ENV"
                    echo "export SITE_ENV=<< pipeline.parameters.site_env >>" >> "$BASH_ENV"
              - ci-tools/install-terminus
              - run:
                  name: Setup Pantheon database variables
                  command: |
                    terminus auth:login --machine-token="$TERMINUS_TOKEN"
                    # Capture the JSON
                    TERMINUS_DB_JSON=$(terminus connection:info \
                      --format=json \
                      --fields=mysql_username,mysql_host,mysql_password,mysql_port,mysql_database \
                      -- "$SITE_NAME.$SITE_ENV")

                    # Parse into shell vars
                    MYSQL_USER=$(echo "$TERMINUS_DB_JSON" | jq -r '.mysql_username')
                    MYSQL_HOST=$(echo "$TERMINUS_DB_JSON" | jq -r '.mysql_host')
                    MYSQL_PASSWORD=$(echo "$TERMINUS_DB_JSON" | jq -r '.mysql_password')
                    MYSQL_PORT=$(echo "$TERMINUS_DB_JSON" | jq -r '.mysql_port')
                    MYSQL_DATABASE=$(echo "$TERMINUS_DB_JSON" | jq -r '.mysql_database')

                    # Append to $BASH_ENV with proper quoting
                    echo "export MYSQL_USER=\"$MYSQL_USER\"" >> "$BASH_ENV"
                    echo "export MYSQL_HOST=\"$MYSQL_HOST\""       >> "$BASH_ENV"
                    echo "export MYSQL_PASSWORD=\"$MYSQL_PASSWORD\"" >> "$BASH_ENV"
                    echo "export MYSQL_PORT=\"$MYSQL_PORT\""       >> "$BASH_ENV"
                    echo "export MYSQL_DATABASE=\"$MYSQL_DATABASE\"" >> "$BASH_ENV"

```

If you have other workflows, they may be triggered by the API made by the hook.
To make sure they are not triggered you can do something like this
```
  build-test:
    when:
      not:
        or:
          - equal: [ scheduled_pipeline, << pipeline.trigger_source >> ]
          - << pipeline.parameters.after_db_clone >>
```
or
```
  PHPcs:
    when:
      and:
        - not: << pipeline.parameters.after_db_clone >>
```