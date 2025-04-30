<?php
echo "# Starting Quicksilver Scrubber" . PHP_EOL;

// Don't run the scrubber on Test/Live
if (
    defined('PANTHEON_ENVIRONMENT') && 
    (PANTHEON_ENVIRONMENT === 'live' || PANTHEON_ENVIRONMENT === 'test')
) {
  echo "We dont scrub on live or test environments.";
  return;
}

// Set your environment variables or replace with actual values
if ( !function_exists('pantheon_get_secret') ) {
    echo 'Function to get Pantheon Secrets not available.';
    return;
}

$repo_owner = pantheon_get_secret('repo_owner');
$repo_name = pantheon_get_secret('repo_name');
$primary_branch = pantheon_get_secret('primary_branch');
$pantheon_site = $_ENV['PANTHEON_SITE_NAME'];
$pantheon_env = $_ENV['PANTHEON_ENVIRONMENT'];
$processor = pantheon_get_secret('scrubber_processor');
$repo_source = pantheon_get_secret('repo_source');

echo "Starting scrubber for {$pantheon_site} on {$pantheon_env}." . PHP_EOL .PHP_EOL;
echo "Processor: {$processor}" . PHP_EOL;
echo "Primary branch: {$primary_branch}" . PHP_EOL;
echo "Repo owner: {$repo_owner}" . PHP_EOL;
echo "Repo source: {$repo_source}" . PHP_EOL;
echo "Repo name: {$repo_name}" . PHP_EOL;

if (
    $processor === '' ||
    $repo_owner === '' ||
    $repo_name === '' ||
    $primary_branch === '' ||
    $pantheon_site === '' ||
    $pantheon_env === ''
) {
    echo "Required variables are not set.";
    return;
}

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
];
switch ($processor) {
    case 'circleci':
        $token = pantheon_get_secret('token');

        if ($token === '' || $repo_source === '') {
            echo "CircleCI Token not provided";
            return;
        }

        // Construct the project slug and data payload
        $project_slug = "{$repo_source}/{$repo_owner}/{$repo_name}";
        $data = [
            "branch" => $primary_branch,
            "parameters" => [
                "after_db_clone" => true,
                "site_name" => $pantheon_site,
                "site_env" => $pantheon_env,
            ]
        ];

        // Encode the data as JSON
        $json_data = json_encode($data);
        echo "CircleCI Payload: {$json_data}" . PHP_EOL;
        $url = "https://circleci.com/api/v2/project/{$project_slug}/pipeline";
        echo "CircleCI URL: {$url}" . PHP_EOL;
        $headers[] = "Circle-Token: {$token}";
        break;
    default:
        echo "Processor not supported";
        return;
}


// Initialize the cURL session
$ch = curl_init();

// Set cURL options similar to -fsSLv in the Bash command
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);

// Optional: follow redirects and enable verbose output for debugging
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_VERBOSE, true);

// Execute the request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
  echo 'cURL error: ' . curl_error($ch);
}

// Close the cURL session
curl_close($ch);

// Create a link to the workflow.
$response_data = json_decode($response, true);
$job_number = $response_data['number'];
$workflow_id = $response_data['id'];
$workflow_url = "https://app.circleci.com/pipelines/github/kanopi/mises-aerc/{$job_number}/workflows/{$workflow_id}";

echo "Check workflow status: {$workflow_url}" . PHP_EOL;

echo "Scrubber trigger complete." . PHP_EOL;