<?php

use Hashids\Hashids;

function idtohash($id){
    $hashids = new Hashids('magicpay123!@#',8);
    return $hashids->encode($id);
    $numbers = $hashids->decode($id);
}

function hash2id($hash){
    $hashids = new Hashids('magicpay123!@#',8);
    
    return $hashids->decode($hash)[0];

}



?>