<?php

require('../vendor/autoload.php');

// Log errors (but don't display any publicly)
error_reporting(-1);
ini_set('display_errors', 0);

// Load environment variables from .env if present
if (file_exists('../.env')) {
    $dotenv = new Dotenv\Dotenv('../');
    $dotenv->load();
}

// If there is not an incoming submission, output a messages to let us know that our service online
if (empty($_POST)) {
    echo 'Service online.';
    die();
}

// We'll set up a Slack API client with our settings from our environment
$client = new Maknz\Slack\Client(getenv('SLACK_WEBHOOK_URL'), [
    'username' => 'Website Form Submission',
    'icon' => ':memo:',
    'channel' => getenv('SLACK_CHANNEL')
]);

// For the submitted_at field we are going to create a DateTime object so we can adjust and format it
$submittedOn = new DateTime($_POST['metadata']['submitted_at']);

// We are going to change the timezone to Pacific (US)
$timezone = new DateTimeZone('America/Los_Angeles');
$submittedOn->setTimezone($timezone);

// Then we'll format the date
$submittedOn = $submittedOn->format('l F j Y g:i:s A I');

// We'll create a "metadata" attachment which will have the fields that describe our form submission
$metadataAttachment = [
    'color' => '#dddddd',
    'fields' => [
        [
            'title' => 'Submitted on',
            'value' => $submittedOn,
            'short' => false,
        ],
        [
            'title' => 'Submitted from URL',
            'value' => $_POST['metadata']['submitted_from_url'],
            'short' => false,
        ]
    ]
];

// We'll also create an attachment for the actual user-submitted fields. We'll fill in the content dynamically below
$dataAttachment = [
    'color' => '#1F75FE',
    'fields' => []
];

// Here, we'll attach the actual user-submitted content
if (isset($_POST['data']) && is_array($_POST['data']) && count($_POST['data']) > 0) {
    foreach ($_POST['data'] as $key => $value) {
        $dataAttachment['fields'][] = [
            'title' => $key,
            'value' => $value,
            'short' => false,
        ];
    }
}

// Lastly, before sending the message, we'll create actual messages text to go before the attachments.
$messageText = 'A new form submission was received on ' . $_POST['metadata']['submitted_from_domain'] . '.';

// Send the message
$client
    ->attach($metadataAttachment)
    ->attach($dataAttachment)
    ->send($messageText);

error_log('Message sent at ' . date('Y-m-d H:i:s') . ' (UTC)');
