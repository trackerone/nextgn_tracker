@extends('errors.layout')

@section('title', __('Service unavailable'))
@section('status', '503')
@section('message', __('The service is temporarily unavailable. Please check back soon.'))
