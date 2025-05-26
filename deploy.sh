#!/bin/bash
rsync -av --exclude='.git' --exclude='deploy.sh' ./ ../cookies/

