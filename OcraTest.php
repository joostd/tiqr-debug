<?php
require('vendor/autoload.php'); 
require_once __DIR__ .  '/vendor/tiqr/tiqr-server-libphp/library/tiqr/Tiqr/OATH/OCRA.php';
    
/*
    static function generateOCRA($ocraSuite,
                                 $key,
                                 $counter,
                                 $question,
                                 $password,
                                 $sessionInformation,
                                 $timeStamp)
*/


function decimalToHex($decimalChallenge) {
        return dechex($decimalChallenge);
    }    

$result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QN08", 
                                     "3132333435363738393031323334353637383930", 
                                     "",
                                     decimalToHex("00000000"), 
                                     "", 
                                     "", 
                                     "");
                                     
echo $result, "\n";
        #$this->assertEquals("237653", $result);
        
$result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QN08", 
                                     "3132333435363738393031323334353637383930", 
                                     "", 
                                     decimalToHex("77777777"), 
                                     "", 
                                     "", 
                                     "");
echo $result, "\n";

        #$this->assertEquals("224598", $result);        

                #"ocraSuite": "OCRA-1:HOTP-SHA1-6:QH10-S",
$result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QN08-S", 
                                     "3132333435363738393031323334353637383930", 
                                     "", 
                                     decimalToHex("77777777"), 
                                     "", 
                                     "ABCDEFABCDEF", 
                                     "");

        #$this->assertEquals("675831", $result);        
echo $result, "\n";




$result = OCRA::generateOCRA("OCRA-1:HOTP-SHA1-6:QH10-S", 
                                     "3132333435363738393031323334353637383930313233343536373839303132", 
                                     "", 
                                     "8ab9d15047", 
                                     "", 
                                     "f2fadeb54690d0d71924236f87e090bb", 
                                     "");
echo $result, "\n";
