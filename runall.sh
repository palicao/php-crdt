#!/bin/bash

echo "Killing previous versions..."

pkill -f "php public/index.php" sed

echo "Starting GCounter Demo..."

white=$(tput setaf 7)
green=$(tput setaf 2)
magenta=$(tput setaf 5)
default=$(tput sgr0)

php public/index.php -l 8080,8081,8082 -t 8080 2>&1 | sed "s/.*/$white&$default/" &
php public/index.php -l 8080,8081,8082 -t 8081 2>&1 | sed "s/.*/$green&$default/" &
php public/index.php -l 8080,8081,8082 -t 8082 2>&1 | sed "s/.*/$magenta&$default/" &
