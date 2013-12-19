<?php

//INITIALIZE VARIABLES
$error = "";
$user_friends = "";
$user_feed = "";
$flag = 0;

$facebook_users = [
    "sabri"  => "862925396",
    "trevor" => "1052795152",
    "alyssa"  => "789005723",
    "brian"  => "1045215327",
    "julian" => "698982717"
];

$fb_user = $facebook_users['alyssa'];

//MESSAGE FB USERS WHO DO NOT HAVE ACCESS TO APP SANDBOX MODE
if(isset($_GET["error_code"])){
   echo $_GET["error_message"];
   exit;
}

require './facebook-php-sdk/src/facebook.php';
require('./classes/midi.class.php');
require('./classes/myfacebooksong.class.php');


/*
 * Instantiate Facebook Object
 * using credentials for mysong app
 * https://developers.facebook.com/x/apps/644202548964466/dashboard/
 */
$facebook = new Facebook(array(
       'appId'  => '644202548964466',
       'secret' => 'c89eda7e1fa734586db44b2ece54ded5'
));

$user = $facebook->getUser();

if ($user) {

    $logoutUrl = $facebook->getLogoutUrl();
    try {
       // $user_profile = $facebook->api('/me');
       // $user_data = $facebook->api('/me?fields=friends,feed'); //Full Field List @ https://developers.facebook.com/tools/explorer
        $user_profile = $facebook->api("/$fb_user");
        //$user_data = $facebook->api("/$fb_user?fields=$fields"); //Full Field List @ https://developers.facebook.com/tools/explorer

        $user_friends = $facebook->api("/$fb_user?fields=friends");
        $user_feed = $facebook->api("/$fb_user/feed?limit=100");

    } catch (FacebookApiException $e) {
        error_log($e);
        $user = null;
    }

} else {

    $loginUrl = $facebook->getLoginUrl();
    echo '<script>top.location="' . $loginUrl . '";</script>';
    exit();

}


/*
 * Instantiate Midi Object
 */

// SET Midi Options
$timeline = 0; // 0 = Absolute Time , 1 = Incremental Time
$bar_repetitions = 4;
$bpm = 480;
$pattern_max_length = 1800;
$save_dir = 'tmp/';
srand((double)microtime()*1000000);
$file = $save_dir.rand().'.mid';

$midi = new Midi();
$myfacebooksong = new MyFacebookSong();

if($user_friends != ""){
    // Percussion Track
    $myfacebooksong->Percussion($midi,$user_friends,$pattern_max_length,$bar_repetitions,$bpm);
    $flag = 1;
}

if($user_feed != ""){
    // Facebook Feed Track
    $myfacebooksong->FacebookFeedTrack($midi,$user_feed);
    $flag = 1;
}

// Save & Close Midi File
if($flag == 1){
    $myfacebooksong->SaveMID($midi,$file);
}


?>

<!DOCTYPE HTML>
<html>

<div><h2>MY FACEBOOK SONG</h2></div>

<?php
    if($user_profile != ""){
?>

        <div>
            <p><?=$user_profile['name']?></p>
        </div>

        <?php if(isset($midi)){ ?>
            <pre><?=$midi->getTxt($timeline)?></pre>
            <div>
                <?php
                    //SET Browser MP3 Player Options
                    $visible = true;
                    $autostart = true;
                    $loop = false;
                    $player = 'ogg_html5';  //Using HTML5 default player

                    $midi->playMidFile($file,$visible,$autostart,$loop,$player);
                ?>
            </div>

            <div>
                <input type="button" name="download" value="Download MIDI file (*.mid)" onclick="self.location.href='download.php?f=<?=urlencode($file)?>'" />
            </div>

        <?php } else {

               $error = "There seems to be a problem. Please try again";
    }

}

?>
<div><?=$error?></div>

</html>