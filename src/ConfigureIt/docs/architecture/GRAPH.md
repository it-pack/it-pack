GRAPH.md

This is the document that differentiates ConfigureIt from every other configuration library.

Most libraries stop here:

Array

ConfigureIt eventually evolves toward

Configuration Graph

Contents

1. Motivation

2. Node Model

3. Edge Model

4. Metadata

5. Provenance

6. Diagnostics

7. Explainability

8. Serialization

9. Future RDF Support

Node example

database.host

Value

localhost

Metadata

source

config/database.yaml

adapter

YamlLoader

priority

20

validated

true

schema

DatabaseSchema

origin

Environment Override

Graph edges

database

├── host

├── port

├── username

└── password

Later:

database.password

↓

SecretReference

↓

Vault

↓

AWS Secret

↓

Resolved Value