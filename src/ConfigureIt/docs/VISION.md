sion
ConfigureIt

      +-------------------+
      | Configuration API |
      +-------------------+
                |
        Resolution Pipeline
                |
+------------+-------------+
|            |             |
Sources      Processors     Outputs
|            |             |
Files        Validation      Array
ENV          Defaults        DTO
CLI          Merge           Object
HTTP         Expansion       Container
Database     Casting
Secrets       ...

The user never asks:

"Load YAML."

Instead they ask:

$config = $configure
->load()
->fromFile('config/app.yaml')
->fromEnv()
->fromProgrammatic($defaults)
->resolve();

The format becomes an implementation detail.

Overall Architecture
ItPack
└── ConfigureIt
├── Core
├── Contracts
├── Ports
├── Adapters
├── Pipeline
├── Schema
├── Resolver
├── Sources
├── Loaders
├── Processors
├── Cache
├── Validation
├── Discovery
└── Bridge
Core Concepts

Instead of "Configuration"

I'd introduce several domain concepts.

ConfigurationSource

ConfigurationDocument

ConfigurationLoader

ConfigurationReader

ConfigurationSchema

ConfigurationResolver

ConfigurationPipeline

ConfigurationSnapshot

ConfigurationRepository

ConfigurationContext

Those names scale much better.

Ports
Source Port
ConfigurationSource
interface ConfigurationSource
{
public function load(): ConfigurationDocument;
}
Loader Port

Responsible for parsing.

interface Loader
{
public function supports(Resource $resource): bool;

    public function load(Resource $resource): ConfigurationDocument;
}

Examples

YamlLoader

TomlLoader

JsonLoader

IniLoader

NeonLoader

FrontMatterLoader

PhpLoader

XmlLoader
Reader Port

Responsible for non-file sources.

EnvironmentReader

CliReader

SecretReader

DatabaseReader

HttpReader

ProgrammaticReader
Adapters

Exactly where third-party packages belong.

Adapter/
AuraDiAdapter

DotenvAdapter

NetteSchemaAdapter

NeonAdapter

TomlAdapter

YamlAdapter

FrontMatterAdapter

TableSchemaAdapter

No business logic should know those libraries exist.

Supported Formats
Native

JSON

PHP

INI

ENV

XML

YAML

TOML

NEON

Markdown Front Matter

CSV

TSV

TableSchema

Later

JSON5

HOCON

Cue

Dhall

Properties

Source Types

Instead of thinking in file formats...

Think in configuration sources.

File

Directory

Environment

HTTP

S3

Vault

Secrets Manager

Programmatic

CLI

STDIN

Database

Redis

Consul

Etcd

ZooKeeper

Every source becomes just another adapter.

Resolution Pipeline

This is where ConfigureIt becomes interesting.

Load

↓

Parse

↓

Normalize

↓

Validate

↓

Merge

↓

Resolve references

↓

Expand variables

↓

Type conversion

↓

Apply defaults

↓

Freeze

↓

Cache

Each step is replaceable.

Merge Strategies

One of the biggest missing pieces in PHP config libraries.

Replace

Append

Deep Merge

Recursive Merge

Unique Merge

Override

Priority Merge

First Wins

Last Wins

Example

base.yaml

↓

local.yaml

↓

.env

↓

CLI

↓

Runtime
Schema Layer

This is where Nette Schema shines.

Instead of

array

everything becomes

ConfigurationSchema
Database

Cache

Mail

Logger

Security

Schemas become reusable.

DatabaseSchema

RedisSchema

FilesystemSchema

RabbitMqSchema
Variable Resolution

Support

${HOME}

${APP_ENV}

${database.host}

${vault.secret}

${env(PATH)}

${file(...)}

${baseDir}

Pipeline

ResolveVariableProcessor

ResolveReferenceProcessor

ResolveExpressionProcessor
Processors
VariableExpansionProcessor

PlaceholderProcessor

IncludeProcessor

ImportProcessor

MergeProcessor

ValidationProcessor

CastingProcessor

InterpolationProcessor

FreezeProcessor

CachingProcessor

Everything composable.

Discovery
config/

config/packages/

config/packages/dev/

config/packages/prod/

config/local/


Symfony-style discovery.

Or

app.neon

↓

services.neon

↓

database.neon

↓

cache.neon

↓

mail.neon
Environment Profiles
development

testing

staging

production

demo

ci

Resolution

base

↓

production

↓

.env

↓

CLI

↓

runtime
Configuration Context

Useful for monorepos.

Project

Module

Package

Plugin

Tenant

Workspace

Feature

Every context has independent configuration.

Container Integration

Aura.Di

should become

ContainerBridge
Configuration

↓

Resolver

↓

Container Builder

↓

DI Container

Not tightly coupled.

Extension System
ConfigurationExtension
interface ConfigurationExtension
{
public function register(ConfigurationBuilder $builder): void;
}

Packages can extend ConfigureIt.

Caching
Filesystem

APCu

Redis

Memory
Events
ConfigurationLoaded

ConfigurationValidated

ConfigurationMerged

ConfigurationResolved

ConfigurationFrozen
Example
$config = ConfigureIt::builder()

    ->addSource(new FileSource('config'))

    ->addSource(new EnvSource())

    ->addSource(new CliSource())

    ->schema(new AppSchema())

    ->processor(new VariableExpansionProcessor())

    ->processor(new MergeProcessor())

    ->processor(new ValidationProcessor())

    ->build();
Package Layout
it-pack/configure-it
src/

Contracts/

Core/

Builder/

Pipeline/

Resolver/

Schema/

Loader/

Source/

Processor/

Adapter/

Bridge/

Cache/

Discovery/

Resource/

Context/

Exception/

Event/

Support/
Optional Satellite Packages

Following the modular philosophy of the It_Pack family, I'd keep the core lean and split integrations into install-on-demand packages:

Package	Purpose
it-pack/configure-it	Core contracts, pipeline, resolver, builder
it-pack/configure-it-env	.env and environment variable adapters (phpdotenv)
it-pack/configure-it-yaml	YAML loader (yaml_parse)
it-pack/configure-it-toml	TOML adapter (php-internal/toml)
it-pack/configure-it-neon	NEON loader
it-pack/configure-it-frontmatter	Markdown front matter support
it-pack/configure-it-schema	Nette Schema integration
it-pack/configure-it-di	Aura.Di bridge
it-pack/configure-it-tableschema	Frictionless Table Schema support
it-pack/configure-it-vault	Secret provider adapters (Vault, cloud secret stores)
it-pack/configure-it-symfony	Optional Symfony integration
it-pack/configure-it-laravel	Optional Laravel integration (if desired)

This keeps the dependency graph small while allowing consumers to assemble exactly the capabilities they need.

A distinguishing idea: treat configuration as a typed knowledge graph

One capability that would set ConfigureIt apart from existing PHP configuration libraries is to model configuration as a graph rather than nested arrays.

Instead of:

array

internally, represent:

ConfigurationGraph

├── Nodes
├── Properties
├── References
├── Sources
├── Metadata
└── Provenance

Each value would carry metadata such as:

originating source (file, environment, CLI, secret manager)
original format (YAML, TOML, NEON, etc.)
line and column (when available)
schema definition
whether it came from a default or an override
merge history

This unlocks powerful capabilities almost "for free":

explain why a configuration value has its current value
visualize override chains
detect conflicting configuration sources
generate configuration documentation automatically
export to multiple formats
perform semantic diffs between environments
provide IDE-friendly diagnostics and tooling

That graph-oriented approach also aligns naturally with the broader ontology- and semantic-driven architecture you've been building across the It_Pack ecosystem, making ConfigureIt more than a parser: it becomes a reusable configuration platform that other packages can build upon.