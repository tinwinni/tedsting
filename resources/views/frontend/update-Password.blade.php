@extends('frontend.layouts.app')
@section('title', 'Update Password')
@section('content')


    <div class="update-password">


        <div class="card mb-3">
            <div class="card-body">
                <div class="text-center">

                    <img src="{{ asset('img/update-password.png') }}" alt="">
                </div>

                <form action="{{ route('update-password.store') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label for="">Old Password</label>
                        <input type="password" name="old_password" class="form-control" value="{{ old('old_password') }}">
                        @error('old_password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="form-group mt-3">
                        <label for="">New Password</label>
                        <input type="password" name="new_password" class="form-control" value="{{ old('new_password') }}">
                        @error('new_password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>


                    <div class="d-grid gap-2 mt-4">

                        <button type="submit" class="btn btn-theme btn-block">Update Password</button>

                    </div>
                </form>



            </div>
        </div>



    </div>

@endsection

@section('scripts')
    <script>
        $(document).ready(function() {

        });
    </script>
@endsection
