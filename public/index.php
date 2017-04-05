<?php


$allowedDomains = array('www.zesty.io', 'blog.zesty.io');

$referer = $_SERVER['HTTP_REFERER'];

$domain = parse_url($referer); //If yes, parse referrer

if(!in_array( $domain['host'], $allowedDomains)) {
    echo "you are not allowed to post at this page";
    die(); //Stop running the script
}


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
$_POST['metadata']['icon'] = isset($_POST['metadata']['icon']) ? $_POST['metadata']['icon'] : 'moneybag';

// We'll set up a Slack API client with our settings from our environment
$client = new Maknz\Slack\Client(getenv('SLACK_WEBHOOK_URL'), [
    'username' => 'Lead Details',
    'icon' => ':'..':',
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
    'color' => '#000000',
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
$messageText = 'A new lead opportunity from ' . $_POST['metadata']['submitted_from_domain'] . ' has appeared!';

// Send the message
$client
    ->attach($metadataAttachment)
    ->attach($dataAttachment)
    ->send($messageText);

error_log('Message sent at ' . date('Y-m-d H:i:s') . ' (UTC)');
