<?php

/**
 * handle requests from tiqr client
 */

include('../../options.php');

function metadata($key)
{
    global $options;
    $tiqr = new Tiqr_Service($options);
    // exchange the key submitted by the phone for a new, unique enrollment secret
    $enrollmentSecret = $tiqr->getEnrollmentSecret($key);
    error_log("enrolment secret for key $key is $enrollmentSecret");
    // $enrollmentSecret is a one time password that the phone is going to use later to post the shared secret of the user account on the phone.
    $enrollmentUrl     = base() . "/tiqr?otp=$enrollmentSecret";
    $authenticationUrl = base() . "/tiqr";
    //Note that for security reasons you can only ever call getEnrollmentMetadata once in an enrollment session, the data is destroyed after your first call.
    $metadata = $tiqr->getEnrollmentMetadata($key, $authenticationUrl, $enrollmentUrl);
    return $metadata;
}

function login( $sessionKey, $userId, $response, $notificationType, $notificationAddress)
{
    global $options;
    $userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);
    $userSecret = $userStorage->getSecret($userId);
    $tiqr = new Tiqr_Service($options);
    $result = $tiqr->authenticate($userId,$userSecret,$sessionKey,$response);
    //Note that actually blocking the user and keeping track of login attempts is a responsibility of your application,
    switch( $result ) {
        case Tiqr_Service::AUTH_RESULT_AUTHENTICATED:
            //echo 'AUTHENTICATED';
            if( isset($notificationType) ) {
                $userStorage->setNotificationType($userId, $notificationType);
                if( isset($notificationAddress) ) {
                    $userStorage->setNotificationAddress($userId, $notificationAddress);
                }
            }
            return "OK";
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_CHALLENGE:
            return 'INVALID_CHALLENGE';
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_REQUEST:
            return 'INVALID_REQUEST';
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_RESPONSE:
            return 'INVALID_RESPONSE';
//        echo “INVALID_RESPONSE:3”;  // 3 attempts left
//        echo “INVALID_RESPONSE:0”;  // blocked
            break;
        case Tiqr_Service::AUTH_RESULT_INVALID_USERID:
            return 'INVALID_USERID';
            break;
    }
}

function register( $enrollmentSecret, $secret, $notificationType, $notificationAddress )
{
    global $options;
    $tiqr = new Tiqr_Service($options);
    // note: userid is never sent together with the secret! userid is retrieved from session
    $userid = $tiqr->validateEnrollmentSecret($enrollmentSecret); // or false if invalid
    error_log("storing new entry $userid:$secret");
    $userStorage = Tiqr_UserStorage::getStorage($options['userstorage']['type'], $options['userstorage']);
    $userStorage->createUser($userid,""); // TODO displayName
    $userStorage->setSecret($userid, $secret);
    $userStorage->setNotificationType($userid, $notificationType);
    $userStorage->setNotificationAddress($userid, $notificationAddress);
    $tiqr->finalizeEnrollment($enrollmentSecret);
    return "OK";
//    return '{"responseCode": 1}';

}

error_log(print_r($_SERVER, true));

switch( $_SERVER['REQUEST_METHOD'] ) {
    case "GET":
        // metadata request
        //error_log("received GET request\n" . print_r($_GET,true));
        // retrieve the temporary reference to the user identity
        $key = $_GET['key'];
        error_log("received metadata request (key=$key)");
        $metadata = metadata($key);
        if( $metadata == false)
            error_log("ERROR: empty metadata - metadata was either lost or destroyed after retrieval");
        else
            error_log("sending metadata:\n" . print_r($metadata,true));
        Header("Content-Type: application/json");
        echo json_encode($metadata);
        break;
    case "POST":
        error_log("X-TIQR-Protocol-Version header is " . $_SERVER['HTTP_X_TIQR_PROTOCOL_VERSION']);
        error_log("received POST request\n" . print_r($_POST,true));
        $operation = $_POST['operation'];
        $version = array_key_exists('HTTP_X_TIQR_PROTOCOL_VERSION', $_SERVER) ? $_SERVER['HTTP_X_TIQR_PROTOCOL_VERSION'] : "1";
        error_log("tiqr client version is " . $version);
        $notificationType = $_POST['notificationType'];
        $notificationAddress = $_POST['notificationAddress'];
//        $language = $_POST['language'];

        switch( $operation ) {
            case "register":
                $enrollmentSecret = $_GET['otp']; // enrollmentsecret relayed by tiqr app
                error_log("enrollmentSecret is $enrollmentSecret");
                $secret = $_POST['secret'];
                $result = register( $enrollmentSecret, $secret, $notificationType, $notificationAddress );
                echo $result;
                break;
            case "login":
                $sessionKey = $_POST['sessionKey'];
                $userId = $_POST['userId'];
                $response = $_POST['response'];
                $notificationType = $_POST['notificationType'];
                $notificationAddress = $_POST['notificationAddress'];
                error_log("received authentication response ($response) from user $userId for session $sessionKey");
                $result = login( $sessionKey, $userId, $response, $notificationType, $notificationAddress );
                error_log("response $result");
                echo $result;
                break;
            default:
                error_log("ERROR: unknown operation (operation) in POST request");
                break;
        }
        break;
}
