#!/usr/bin/env bash

ROOTDIR=`pwd`

for D in `ls`
    do cd $D
    npm install
    npm run gulp compile
    cd $ROOTDIR
done
