<?php

declare(strict_types=1);

namespace ItPack\CommitIt\Domain\Value;

/**
 * Classifications for detected secrets.
 *
 * @api
 */
enum SecretType: string
{
    case GenericApiKey = 'generic_api_key';
    case AwsAccessKey = 'aws_access_key';
    case AwsSecretKey = 'aws_secret_key';
    case GitHubToken = 'github_token';
    case PrivateKey = 'private_key';
    case BearerToken = 'bearer_token';
    case BasicAuth = 'basic_auth';
    case ConnectionString = 'connection_string';
    case GenericPassword = 'generic_password';
    case SlackToken = 'slack_token';
}
