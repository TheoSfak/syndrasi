<?php
/**
 * SynDrasi - Self-update (GitHub Releases) settings.
 *
 * Developer-managed: set the repository here. The admin Updates tab only shows
 * Check / Backup / Update buttons — it never asks for or edits these values.
 * Values can be overridden with environment variables.
 */
return [
    'owner' => env('GITHUB_OWNER', 'TheoSfak'),
    'repo'  => env('GITHUB_REPO',  'syndrasi'),
    'token' => env('GITHUB_TOKEN', ''), // only needed for a private repo
];
