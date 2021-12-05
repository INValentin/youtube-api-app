<?php
session_start();

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new Exception(sprintf('Please run "composer require google/apiclient:~2.0" in "%s"', __DIR__));
}
require_once __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName('valTube');
$client->setScopes([
    'https://www.googleapis.com/auth/youtube.readonly',
]);

// TODO: For this request to work, you must replace
//       "YOUR_CLIENT_SECRET_FILE.json" with a pointer to your
//       client_secret.json file. For more information, see
//       https://cloud.google.com/iam/docs/creating-managing-service-account-keys
$client->setAuthConfig('client_secret.json');
$client->setAccessType('offline');

// Request authorization from the user.
$authUrl = $client->createAuthUrl();


pageHeader();

if (!isset($_SESSION['token']) && !isset($_GET['code'])) {
    echo "<p><a href='$authUrl'>Login</a></p>";
    exit();
}

if (isset($_GET['code']) && !isset($_SESSION['token'])) {
    $authCode = $_GET['code'];

    // Exchange authorization code for an access token.
    $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
    $client->setAccessToken($accessToken);
    $_SESSION['token'] = [
        'access' => $client->getAccessToken(),
        'refresh' => $client->getRefreshToken()
    ];
}

if ($client->isAccessTokenExpired()) {
    $refreshToken = $_SESSION['token']['refresh'];

    $accessToken =  $client->fetchAccessTokenWithRefreshToken($refreshToken);
    $client->setAccessToken($accessToken);
} elseif (!$client->isAccessTokenExpired()) {
    $accessToken = $_SESSION['token']['access'];
    $client->setAccessToken($accessToken);
}

// Define service object for making API requests.
$service = new Google\Service\YouTube($client);

$channelParams = [
    'forUsername' => (!empty($_REQUEST['channel']) ? $_REQUEST['channel'] : 'googledevelopers')
];

$channelRes = $service->channels->listChannels('snippet,contentDetails,statistics', $channelParams);

if (!count($channelRes->getItems())) {
    echo "<h5 class='notfound'>Can't find the channel.</h5>";

    exit();
}

$channel = $channelRes->getItems()[0];

if ($channel) {
    echo "<h2>{$channel->getSnippet()->getTitle()}</h2>";
    echo "<p>{$channel->getSnippet()->getDescription()}</p>";
}


$videoParams = [
    'playlistId' => $channel->getContentDetails()->getRelatedPlaylists()->getUploads(),
    'maxResults' => intval(!empty($_REQUEST['maxResults']) ? $_REQUEST['maxResults'] : 10) ?: 10
];


$videos = $service->playlistItems->listPlaylistItems('snippet', $videoParams);

echo "<h4 class='videoHeader'>Latest Videos: </h4>";
echo "<div class='videos'>";

foreach ($videos->getItems() as $video) {
    $videoId = $video->getSnippet()->getResourceId()->getVideoId();
    echo <<<STR
        <div class="video">
            <iframe width="280" height="157.5" src="https://www.youtube.com/embed/$videoId" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
    STR;
}

echo "</div>";


function showForm()
{
?>
    <form action="" method="GET">
        <h2>Find a channel</h2>
        <label for="channel">Channel Name</label>
        <input value="<?php echo !empty($_REQUEST['channel']) ? $_REQUEST['channel'] : 'googledevelopers' ?>" type="text" id="channel" name="channel" placeholder="Enter channel name!">
        <label for="maxRes">Max Videos</label>
        <input value="<?php echo !empty($_REQUEST['maxResults']) ? $_REQUEST['maxResults'] : 10 ?>" type="number" name="maxResults">
        <button type="submit">Get videos</button>
    </form>

<?php
}



pageFooter();

function pageHeader()
{
    echo <<<STR
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="/style.css">
        <title>VTube</title>
    </head>
    <body>
    <div class="container">
    
    STR;

    showForm();
}


function pageFooter()
{
    echo <<<STR
    </div>
        </body>
        </html>
    STR;
}
