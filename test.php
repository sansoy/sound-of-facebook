<?php

    require './facebook-php-sdk/src/facebook.php';
	require('./classes/midi.class.php');

    //GRAB FACEBOOK DATA

    $facebook = new Facebook(array(
     //   'appId'  => '644202548964466',
     //   'secret' => 'c89eda7e1fa734586db44b2ece54ded5'
        'appId'  => '365142410261822',
        'secret' => 'bebed2e3623099efb2c620e5b6431058'
    ));

    $user = $facebook->getUser();

    if ($user) {
        $logoutUrl = $facebook->getLogoutUrl();
    } else {
        $loginUrl = $facebook->getLoginUrl();
        echo '<script>top.location="' . $loginUrl . '";</script>';
        exit();
    }

    if ($user) {
        try {
            // Proceed knowing you have a logged in user who's authenticated.

            //$user_data = $facebook->api('/me?fields=friends,likes,feed,music');

            // SABRI
           //$user_profile = $facebook->api('/me');
           //$user_data = $facebook->api('/me?fields=friends,feed');
            //$user_data = $facebook->api('/me?fields=feed.fields(message)');

            // DEUTSCH LA
            //$user_profile = $facebook->api('/100003568580126');
            //$user_data = $facebook->api('/100003568580126?fields=friends');

            // TREVOR
            $user_profile = $facebook->api('/1052795152');
            $user_data = $facebook->api('/1052795152?fields=friends');

            //BRIAN
            //$user_profile = $facebook->api('/1045215327');
            //$user_data = $facebook->api('/1045215327?fields=friends');
            //$user_data = $facebook->api('/me?fields=picture');
            //var_dump($user_profile);
           // echo "<br />";
            //var_dump($user_data["picture"]['data']['url']);

           // $image = "https://fbcdn-profile-a.akamaihd.net/hprofile-ak-prn2/1117430_862925396_213490467_n.jpg";
           // $exif = exif_read_data($image,0,true);

           // var_dump($exif);

            /*
            echo " $image :<br />\n";
            foreach ($exif as $key => $section) {
                foreach ($section as $name => $val) {
                    echo "$key.$name: $val<br />\n";
                }
            }
*/
           // exit;


        } catch (FacebookApiException $e) {
            error_log($e);
            $user = null;
        }
    }

    if(isset($user_data)){

        //Create Midi Object
        $midi = new Midi();


        //Player Variables
        $player = 'ogg_html5';
        $autostart = true;
        $loop = false;
        $visible = true;
        $tt = 0; // 0 = Absolute , 1 = Delta
        $bar_repetitions = 4;
        $bpm = 480;

        $save_dir = 'tmp/';
        srand((double)microtime()*1000000);
        //$file = $save_dir.rand().'.mid';
        $file = $save_dir .'test.mid';



        // First Track
        $percussionLength = percussion($midi,$user_data,$bar_repetitions,$bpm);

        // Second Track
        //facebookFeedTrack($midi,$user_data,$percussionLength);

        saveMID($midi,$file);

    } else {
        echo "OOPS!";
    }

    function percussion($midi,$user_data,$bar_repetitions,$bpm){
        $numb_of_friends = count($user_data["friends"]["data"]);
        $smallestID = $user_data["friends"]["data"][0]["id"];
        $largestID = $user_data["friends"]["data"][$numb_of_friends - 1]["id"];

        $friendsArray = array();
        for($i=0;$i<$numb_of_friends;$i++){
            $friendsArray[$user_data["friends"]["data"][$i]["id"]] = $user_data["friends"]["data"][$i]["name"];
        }

        asort($friendsArray);
        reset($friendsArray);
        $firstSortedKEY = key($friendsArray);

        end($friendsArray);
        $lastSortedKEY = key($friendsArray);

        $i=0;
        $beatsArray = array();
        $beatsArray[0] = $firstSortedKEY;
        foreach ($friendsArray as $key => $value) {
            if($key == $smallestID || $key == $largestID){
                $beatsArray[$i] = $key;
            }
            $i++;
        }
        $beatsArray[$i] = $lastSortedKEY;

        //$beat_per_minute_ratio = 1.435;  //number of sabris friendslist (689) / example 480bpm
        //$bpm = $numb_of_friends/$beat_per_minute_ratio;


        $pattern_max_length = 1800;
        $normalized = $pattern_max_length / $numb_of_friends;
        $velocityNorm = 127 / log($largestID);

        //INSTANTIATE MIDI
        $midi->open(240);
        $midi->setBpm($bpm);

        $tn = $midi->newTrack() - 1;
        $inst = 60;  //HIGH BONGO
        $ch = 10;  //PERCUSSION CHANNEL 10
        $currentTime = 0;

        $midi->addMsg($tn, "0 PrCh ch=$ch p=1");

        for($i=0;$i<$bar_repetitions;$i++){
            foreach ($beatsArray as $key => $value) {
                $onTime = ($key * $normalized) + $currentTime;
                $offTime = $onTime + 120;
                $velo = log($value) * $velocityNorm;
                $midi->addMsg($tn, "$onTime On ch=$ch n=$inst v=$velo");
                $midi->addMsg($tn, "$offTime Off ch=$ch n=$inst v=$velo");
            }
            $currentTime = $offTime + 480;
        }
        $midi->addMsg($tn, "$offTime Meta TrkEnd");

        return $offTime;
    }

    /*
     * PUBLIC FEED API
     * https://developers.facebook.com/docs/public_feed/
     *
     * This feed is restricted to a limited set of publishers.  Currently, cant apply to use this API
     */
    function facebookFeedTrack($midi,$user_data,$percussionLength){

        require('alchemyapi.php');
        $alchemyapi = new AlchemyAPI();
        $numb_of_feeds = count($user_data["feed"]["data"]);
        $trackNotes = array();

        //Convert score to Midi Note
        // A = midi note between 21 * 108
        // B = Sentiment Score between -.5 & .5
        // C = (.5 - -.5) /(108 - 21) = 87
        // A = (B + .5)/C + 21
        $lowScore = -.5;
        $highScore = .5;
        $lowestMidiNote = 21;
        $highestMidiNote = 108;
        $ratioScoreMidiNotes = ($highScore - $lowScore) / ( $highestMidiNote - $lowestMidiNote );


        for($i=0;$i<$numb_of_feeds;$i++){
            if(isset($user_data["feed"]["data"][$i]["message"])){
                $message = $user_data["feed"]["data"][$i]["message"];
                $response = $alchemyapi->sentiment('text',$message, null);
                if (array_key_exists('score', $response['docSentiment'])) {
                    $sentimentscore = $response['docSentiment']['score'];
                    $midiNote = round(($sentimentscore + $highScore) / $ratioScoreMidiNotes +  $lowestMidiNote );
                    array_push($trackNotes,$midiNote);
                } else {
                    $sentimentscore = 0;
                    $midiNote = round ( ($sentimentscore + $highScore) / $ratioScoreMidiNotes +  $lowestMidiNote );
                    array_push($trackNotes, $midiNote);
                }
            }
        }

        $numb_of_track_notes = count($trackNotes);


        $tn = $midi->newTrack()-1;
        $inst = 79;  //Tubular Bells = 14
        $ch = 5;  // Note: CHANNEL 10 reserved only for Percussion Instruments
        $velo = 127;

        $timeBetweenNotes = round( ($percussionLength/2) / $numb_of_track_notes );

        $currentTime = $timeBetweenNotes;

        $midi->addMsg($tn, "0 PrCh ch=$ch p=$inst");


                for($j=0;$j<$numb_of_track_notes;$j++){
                    $note = $trackNotes[$j];
                    $onTime = $currentTime;
                    $offTime = $onTime + $timeBetweenNotes;

                    $midi->addMsg($tn, "$onTime On ch=$ch n=$note v=$velo");
                    $midi->addMsg($tn, "$offTime Off ch=$ch n=$note v=$velo");

                    $currentTime = $currentTime + 2 * $timeBetweenNotes;
                }


        $midi->addMsg($tn, "$offTime Meta TrkEnd");

    }

    function saveMID($midi,$file){
        $midi->saveMidFile($file, 0666);
        $midi->importMid($file);
    }
?>


<html>
<div><h2></h2><?=$user_profile['name']?>'s Song</h2></div>
<?php if(isset($midi)){ ?>
<pre>
<?=$midi->getTxt($tt)?>
</pre>

<div>
   <?=$midi->playMidFile($file,$visible,$autostart,$loop,$player)?>
</div>

<div>
    DOWNLOAD<br />

<input type="button" name="download" value="Save as SMF (*.mid)" onclick="self.location.href='download.php?f=<?=urlencode($file)?>'" />
</div>
<?php } else { ?>
<div>There seems to be a problem</div>
<?php } ?>
</html>