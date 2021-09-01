@extends('frontend.layouts.app')
@section('title', 'Receive QR')
@section('content')
    <div class="receive_qr">

        <div class="card">

            <div class="card-body">
                <p class="text-center mb-0">QR Scan to pay me</p>
                <div class="text-center">
                    <img src="data:image/png;base64, {!! base64_encode(
                    QrCode::format('png')->size(240)->generate($user->phone),
                    ) !!} ">

                </div>
                
                <p class="text-center mb-1"><strong>{{$user->name}}</strong></p>
                <p class="text-center mb-1">{{$user->phone}}</p>


            </div>
        </div>


    </div>
@endsection
