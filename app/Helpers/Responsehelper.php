<?php

function success($data,$message){
    return response()->json(
        [
            'result' => 1,
            'message' =>$message,
            'data' => $data
        ]
        );
}

function fail($data,$message){
    return response()->json(
        [
            'result' => 0,
            'message' =>$message,
            'data' => $data
        ]
        );
}


?>