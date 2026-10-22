@extends('errors::minimal')

@section('title', __('http.server_error'))
@section('code', $exception->getStatusCode())
@section('message', \Illuminate\Support\Facades\Lang::has('http.'.$exception->getStatusCode())
    ? __('http.'.$exception->getStatusCode()) : __('http.server_error'))
