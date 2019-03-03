#!/bin/bash

siege -f bench_urls.txt -c10 -i -t1
