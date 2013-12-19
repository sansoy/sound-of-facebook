<?php
class MyFacebookSong {

    public $midi;                  // Midi object
    public $user_data;             // Facebook User data pulled from API
    public $bpm;                   // Beats Per Minute
    public $bar_repetitions;       // Number of times to repeat Bar
    public $pattern_max_length;    // Time in Seconds
    public $songLength;            // Length of Song

    //---------------------------------------------------------------
    // CONSTRUCTOR
    //---------------------------------------------------------------
    function __construct() {

    }

    function Percussion($midi,$user_friends,$pattern_max_length,$bar_repetitions,$bpm){

        $numb_of_friends = count($user_friends["friends"]["data"]);

        $smallestID = $user_friends["friends"]["data"][0]["id"];
        $largestID = $user_friends["friends"]["data"][$numb_of_friends - 1]["id"];

        //CREATE AN ASSOCIATIVE ARRAY OF Facebook IDS  mapped to Facebook Names
        $friendsArray = array();
        for($i=0;$i<$numb_of_friends;$i++){
            $friendsArray[$user_friends["friends"]["data"][$i]["id"]] = $user_friends["friends"]["data"][$i]["name"];
        }

        //SORT Alphabetically
        asort($friendsArray);

        //SET pointer to first key and grab its ID
        reset($friendsArray);
        $firstSortedKEY = key($friendsArray);

        //SET pointer to last key and grab its ID
        end($friendsArray);
        $lastSortedKEY = key($friendsArray);

        //CREATE a new array containing the firstSortedKey, firstUnsortedKey, lastUnsortedKey, lastSortedKey
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

        //VARIABLE BEATS PER MINUTE ALGORITHM  NOTE:  need to revisit as BPM slows down too much for low number of friends
        //$beat_per_minute_ratio = 1.435;  // This value is based on the ratio of sabri's FriendsList/BeatsPerMinute - 689/480 = 1
        //$bpm = $numb_of_friends/$beat_per_minute_ratio;


        $normalized = $pattern_max_length / $numb_of_friends;

        //FACEBOOK user ids vary up to 9 digits so used Natural Log to determine Velocity/Sound Level for each note.
        $velocityNorm = 127 / log($largestID);

        //OPEN MIDI Object
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

        $this->songLength = $offTime;
    }


    /*
     * PUBLIC FEED API
     * https://developers.facebook.com/docs/public_feed/
     */
    function FacebookFeedTrack($midi,$user_feed){

        require('alchemyapi.php');
        $alchemyapi = new AlchemyAPI();
        $numb_of_feeds = count($user_feed["data"]);



        $trackNotes = array();

        //Convert Sentiment Score to Midi Note
        // A = Midi Note between 21 * 108
        // B = Sentiment Score between -.5 & .5
        // C = (.5 - -.5) / (108 - 21) = 1/87
        // A = (B + .5)/C + 21
        $lowScore = -.5;
        $highScore = .5;
        $lowestMidiNote = 21;
        $highestMidiNote = 108;
        $ratioScoreMidiNotes = ($highScore - $lowScore) / ( $highestMidiNote - $lowestMidiNote );


        for($i=0;$i<$numb_of_feeds;$i++){
            if(isset($user_feed["data"][$i]["message"])){
                $message = $user_feed["data"][$i]["message"];
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

        $timeBetweenNotes = round( ($this->songLength/2) / $numb_of_track_notes );

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

    function SaveMID($midi,$file){
        $midi->saveMidFile($file, 0666);
        $midi->importMid($file);
    }

}

?>