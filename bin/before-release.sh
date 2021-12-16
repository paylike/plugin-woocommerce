#!/bin/bash

# Exit if any command fails.
set -e
# regenerate classmap for development use
composer dump-autoload --no-dev
