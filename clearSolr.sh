#!/bin/bash

curl -v http://localhost:8080/solr/biblio/update?stream.body=%3Cdelete%3E%3Cquery%3E*:*%3C/query%3E%3C/delete%3E&commit=true
