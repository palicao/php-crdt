# CRDT G-Counter

This is the companion code to the presentation "[CRDT G-Counter... or how to build a small distributed system with PHP](This is the companion code to the presentation "[CRDT G-Counter... or how to build a small distributed system with PHP](https://docs.google.com/presentation/d/1UNJDm9x2ROfCxpGIxdhTHOOYEqIqa3pObouijbasSiM/edit?usp=sharing)"

## Running the project
Clone the project, run `composer install`, then `./runall.sh` which will start 3 servers (on port 8080, 8081, 80802).

In order to benchmark the system, make sure that you have [siege](https://github.com/JoeDog/siege) installed on your system, then run `./bench.sh`
