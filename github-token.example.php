<?php
/**
 * Example: copy this file to github-token.php and set your token.
 * The plugin uses it for GitHub API authentication so update checks
 * don't hit rate limits (403). Not committed to git.
 *
 * Create a token: GitHub → Settings → Developer settings →
 * Personal access tokens → Fine-grained or Classic, scope: read access to metadata.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'OV25_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
