#!/bin/sh
php-scoper add-prefix -f
sed -i "s|includes|..\\\/includes|g" build/composer.json
composer dump-autoload --working-dir build --classmap-authoritative
find ./build/vendor -type d -name bin -prune -exec rm -rf {} \;
# find ./build/vendor -type f ! -name "*.php" | xargs rm
find ./build/vendor -type f \( -name "*.json" -o -name "*.xml" \) | xargs rm
