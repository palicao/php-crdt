#!/bin/bash

echo "Killing previous versions..."

pkill -f "php public/index.php" sed

echo "Starting GCounter Demo..."

yellow=$(tput setaf 3)
green=$(tput setaf 2)
magenta=$(tput setaf 5)
default=$(tput sgr0)

php public/index.php -l 9090,9091,9092 -t 9090 2>&1 | sed "s/.*/$yellow&$default/" &
php public/index.php -l 9090,9091,9092 -t 9091 2>&1 | sed "s/.*/$green&$default/" &
php public/index.php -l 9090,9091,9092 -t 9092 2>&1 | sed "s/.*/$magenta&$default/" &
