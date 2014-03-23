<?php

require 'PebbleBitmap.php';


try {
    $converter = new PebbleBitmap("/path/to/image.png");
    //save image in a location
    if ($converter->convertToPbi("/path/to/image.png.pbi")) {
        echo "Successful";
    } else {
        echo "Failed";
    }

    //get raw contents and download
    header('Content-Disposition: attachment; filename="image.png.pbi"');
    header("Content-Type: application/octet-stream");
    echo $converter->convertToPbi();
} catch (Exception $ex) {
    echo "Error occired: " . $ex->getMessage();
}
