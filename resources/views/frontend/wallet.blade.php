@extends('frontend.layouts.app')
@section('title','Wallet')
@section('content')
<div class="wallet">
    
        <div class="card my-card">
            <div class="card-body">
                <div class="mb-3">
                    <span>Balance</span>
                    <h4>{{number_format($user->wallet ? $user->wallet->amount : 0,2)}} <span>MMK</span></h4>
                </div>
                <div class="mb-3">
                    <span>Account Number</span>
                    <h5>{{$user->wallet ? $user->wallet->account_number : '-'}}</h5>
                </div>
                <div>
                    <p>{{$user->name}}</p>
                </div>
            </div>
        </div>
 
</div>
@endsection
