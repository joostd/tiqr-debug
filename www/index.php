<?php

include('../options.php');

session_start();

$tiqr = new Tiqr_Service($options);
$sid = session_id();
$userdata = $tiqr->getAuthenticatedUser($sid);

if( isset($_GET['register']) ) {
    // TODO
    $status = $tiqr->getEnrollmentStatus($sid);
    switch( $status ) {
        case Tiqr_Service::ENROLLMENT_STATUS_IDLE:
            error_log("[$sid] status is $status (idle)");
//            echo "<script>alert('timeout');</script>";
            // starting a new enrollment session
            $uid = base_convert(time(), 10, 36); // use timestamp as userid
            $displayName = "debug";
            error_log("[$sid] uid is $uid and displayName is $displayName");
            $key = $tiqr->startEnrollmentSession($uid, $displayName, $sid);
            error_log("[$sid] started enrollment session key $key");
            $metadataURL = base() . "/tiqr?key=$key";
            error_log("[$sid] generating QR code for metadata URL $metadataURL");
            $url = $tiqr->generateEnrollString($metadataURL);
            error_log("$url\n", 3, "/tmp/tiqr.log");
            break;
        case Tiqr_Service::ENROLLMENT_STATUS_INITIALIZED:
            error_log("[$sid] status is $status (initialized)");
            echo "<script>alert('scan the QR first, retrying');</script>";
            session_regenerate_id();
            break;
        case Tiqr_Service::ENROLLMENT_STATUS_RETRIEVED:
            error_log("[$sid] status is $status (retrieved)");
            // hide qr
            break;
        case Tiqr_Service::ENROLLMENT_STATUS_PROCESSED:
            error_log("[$sid] status is $status (processed)");
            break;
        case Tiqr_Service::ENROLLMENT_STATUS_FINALIZED:
            error_log("[$sid] status is $status (finalized)");
            $tiqr->resetEnrollmentSession($sid);
            error_log("[$sid] reset enrollment");
            break;
        default:
            error_log("[$sid] unknown status: $status");
    }

} else if( isset($_GET['login']) ) {

    if (!is_null($userdata)) {
        error_log("'$userdata' already logged in");
        header("Location: /");
        exit();
    }
    error_log("*** new login session with id=$sid");

    $sessionKey = $tiqr->startAuthenticationSession($userdata, $sid); // prepares the tiqr library for authentication
    error_log("[$sid] session key=$sessionKey");
    $url = $tiqr->generateAuthURL($sessionKey);
    error_log("$url\n", 3, "/tmp/tiqr.log");

} else if( isset($_GET['push']) ) {

    if (is_null($userdata)) {
        echo "cannot send push notification - log in first";
        exit();
    }

    // re-authenticate
    error_log("re-authentication [$userdata]");
    $tiqr->logout($sid);
    error_log("*** new login session with id=$sid");
    $sessionKey = $tiqr->startAuthenticationSession($userdata, $sid); // prepares the tiqr library for authentication
    error_log("[$sid] session key=$sessionKey");

    $userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);
    $notificationType = $userStorage->getNotificationType($userdata);
    $notificationAddress = $userStorage->getNotificationAddress($userdata);
    error_log("type [$notificationType], address [$notificationAddress]");
    $translatedAddress = $tiqr->translateNotificationAddress($notificationType, $notificationAddress);
    error_log("translated address [$translatedAddress]");
    if ($translatedAddress) {
            if ($tiqr->sendAuthNotification($sessionKey, $notificationType, $translatedAddress)) {
                error_log("sent notification of type [$notificationType] to [$translatedAddress]");
            } else {
                error_log("failed sending notification of type [$notificationType] to [$translatedAddress]");
            }
    } else {
            error_log("Could not translate address [$notificationAddress] for notification of type [$notificationType]");
    }
    header("Location: /");

} else if( isset($_GET['logout']) ) {
    $tiqr->logout($sid);
    if( isset($userdata) ) error_log("logging out '$userdata'");
    $userdata = $tiqr->getAuthenticatedUser($sid);
}

echo "<html>";
if( is_null($userdata) )
    echo "<a href='?login'>login</a> | <a href='?register'>register</a>";
else {
    echo "<a href='?logout'>logout</a> | <a href='?push'>push</a>";
    echo "<p>Hello $userdata.</p>\n";
}

if( isset($url) ) { // register or login
    echo "<div><img src='https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$url'></div><code>$url</code>";
}
echo "</html>";