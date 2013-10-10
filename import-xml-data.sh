#!/bin/bash

curl http://localhost:8080/solr/biblio/update --data-binary "@$1" -H 'Content-type:application/xml'
curl http://localhost:8080/solr/biblio/update?softCommit=true
