<?php
// YOUR NAME AND EMAIL GO HERE
// Marc Tanne marc@marctanne.com
function parse_request($request, $secret)
{
    // separate out the hashed signature from the encoded payload data
    $splitStrings = explode('.', $request);
    // decode the signature hash in preparation for comparison
    $decodedSignatureHash = base64_decode($splitStrings[0]);
    // decode the payload partially leaving it in JSON string format in preparation for re-hashing
    $decodedPayloadJSON = base64_decode($splitStrings[1]);
    // build the new signature hash from the $secret input and the JSON payload
    $rebuiltSignatureHash = hash_hmac('sha256', $decodedPayloadJSON, $secret);
    // compare the hashed values to ensure the data is identical before continuing
    if ($decodedSignatureHash === $rebuiltSignatureHash) {
    	// after validation, decode the json string into its object format
    	$payloadObject = json_decode($decodedPayloadJSON);
    	// structure object into array for comparison tests after the function returns back to outer scope (i.e. the thing that called this function)
    	return array(
    		's'=>$payloadObject->s, 
    		'b'=>$payloadObject->b, 
    		'i'=>$payloadObject->i, 
    		'f'=>$payloadObject->f
    	);
    // if something does not match up, return false and return back to outer scope here with a bool value of false
    } else {
    	return false;
    }
}

function dates_with_at_least_n_scores($pdo, $n)
{
    // store sql statement in variable to be used in query the :n represents $n as a binded parameter to the statement
    $sql = "SELECT * FROM scores WHERE score = :n ORDER BY date DESC";
    // use pdo since we are given a pdo connection object and use the prepare() function to sanitize the data
    $query = $pdo->prepare($sql);
    // bind $n argument input to :n in sql statement
    $query->bindParam(':n', $n);
    // run the query and store the returning object in the $query variable
    $query->execute();
    // check to make sure we got something back
    if ($query) {
        // initialize the array that will store the return dates as strings
        $returnArray = [];
        // loop through each object inside $query and assign it to $object we are using FETCH_LAZY for better performance
        while($object = $query->fetch(PDO::FETCH_LAZY)) {
            // Take the value of the date property in each object and insert it into the array that will return back out from the function
            array_push($returnArray, $object->date);
        }
    }
    // return the array of results back to the outer scope
    return $returnArray;
}

function users_with_top_score_on_date($pdo, $date)
{
    // store sql statement in variable to be used in query the :date represents $date as a binded parameter to the statement
    $sql = "SELECT user_id, score FROM scores WHERE date = :date ORDER BY score DESC";
    // use pdo since we are given a pdo connection object and use the prepare() function to sanitize the data
    $query = $pdo->prepare($sql);
    // bind $date argument input to :date in sql statement
    $query->bindParam(':date', $date);
    // run the query and store the returning object in the $query variable
    $query->execute();
    // check to make sure we got something back
    if ($query) {
        // initialize the array that will store the return dates as strings
        $returnArray = [];
        // initialize high score tracking variable
        $highScore = 0;
        // loop through each object inside $query and assign it to $object we are using FETCH_LAZY for better performance
        while($object = $query->fetch(PDO::FETCH_LAZY)) {
            // check if the score is greater than or equal to the stored highScore
            if ($object->score >= $highScore) {
                // set highScore variable to new high score
                $highScore = $object->score;
                // Take the value of the user_id property and insert it into the array that will return back out from the function
                array_push($returnArray, $object->user_id);
            }
        }
    }
    // return the array of results back to the outer scope
    return $returnArray;
}

function dates_when_user_was_in_top_n($pdo, $user_id, $n)
{
    // we want to return the date(s) when the user was in the top $n scores in each date
    // we first need to get the data
    // store sql statement in variable to be used in query
    $sql = "SELECT * FROM scores ORDER BY date DESC";
    // use pdo since we are given a pdo connection object and use the prepare() function to sanitize the data
    $query = $pdo->prepare($sql);
    // run the query and store the returning object in the $query variable
    $query->execute();
    // check to make sure we got something back
    if ($query) {
    // then we need to re-structure the outputted data by date as a nested array in preparation for sorting
        // initialize curent date variable to hold persistant date through iteration
        $currentDate = null;
        // loop through each object inside $query and assign it to $object. we are using FETCH_LAZY for better performance
        $scoresByDate = array();
        // loop through the data objects
        while($object = $query->fetch(PDO::FETCH_LAZY)) {
            // build a nested array sorted by date, then user_id, with an end value of score
            $scoresByDate[$object->date][$object->user_id] = $object->score;
        }
    // then we take the top $n scores in each date
        // initialize the array that will store the return dates as strings
        $returnArray = [];
        foreach ($scoresByDate as $date => $user) {
            // sort out the top $n scores of each date
            arsort($scoresByDate[$date]);
    // keep only the top $n scores accounting for duplicate scores
            // initialize array to track total occurrances of items in an array
            $unique = [0 => 0, 1 => 0, 2 => 0, 3 => 0];
            // intialize array to track duplicate occurrances of items in array
            $numberOfDuplicates = 0;
            // loop through the structured array and check if the high score is tied, then check if one of those tied scores contains our user
            foreach($scoresByDate[$date] as $user => $value){
                // cast value from string to int
                $value = (int)$value;
                // increment the number with a key of $value then check if that number is greater then 1, if it is we have a duplicate (mimicking a data type of set here for this)
                if(++$unique[$value] > 1){
                    // check if the duplicate contains the desired user
                    if($user == $user_id){
                        // increment the variable
                        $numberOfDuplicates++;
                    }
                }
            }
            // here we add the number of tied scores that our desired user is a part of to the number of top places we are cutting off from
            $cutoff = $numberOfDuplicates + $n;
            // slice out the top $cutoff scores with their user_id's attached. This will be used to add the corresponding dates to the output array
            $slicedArray = array_slice($scoresByDate[$date], 0, $cutoff, true);
            // check if we have only one element in our cutoff array
            if (count($slicedArray) == 1) {
                // if we do, grab just the key value for the comparison
                if ($user_id == key($slicedArray)) {
                    // add date to output array
                    array_push($returnArray, $date);
                }
            // check if we have more then one element in our cutoff array
            } else if (count($slicedArray) > 1) {
                // check the user_id's of the top $n scores, if it is the user_id we want, add it to the returnArray
                if (array_key_exists($user_id, $slicedArray)) {
                    // add date to output array
                    array_push($returnArray, $date);
                }
            }
        }
    }
    // return the array of results back to the outer scope
    return $returnArray;
}
